<?php

declare(strict_types=1);

namespace {
    if (! function_exists('t')) {
        function t(string $message): string
        {
            return $message;
        }
    }
}

namespace Kanboard\Core {
    if (! class_exists(__NAMESPACE__ . '\\Base')) {
        class Base
        {
            /** @var array<string, mixed> */
            protected array $container;

            /**
             * @param array<string, mixed> $container
             */
            public function __construct(array $container = [])
            {
                $this->container = $container;
            }

            /** @return mixed */
            public function __get(string $name)
            {
                if (! array_key_exists($name, $this->container)) {
                    return null;
                }

                $service = $this->container[$name];

                if ($service instanceof \Closure) {
                    $service = $service($this->container);
                    $this->container[$name] = $service;
                }

                return $service;
            }

            protected function checkCSRFParam(): void
            {
                $request = $this->__get('request');
                $session = $this->__get('session');

                $submittedToken = is_object($request) && method_exists($request, 'getValue')
                    ? (string) $request->getValue('csrf_token', '')
                    : '';

                $expectedToken = is_object($session) && method_exists($session, 'getCsrfToken')
                    ? (string) $session->getCsrfToken()
                    : '';

                if ($submittedToken === '' || $submittedToken !== $expectedToken) {
                    throw new \RuntimeException('Invalid CSRF token');
                }
            }
        }
    }
}

namespace Kanboard\Controller {
    if (! class_exists(__NAMESPACE__ . '\\BaseController')) {
        class BaseController extends \Kanboard\Core\Base
        {
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Controller\ConfigController;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Controller/ConfigController.php';
    require_once __DIR__ . '/FakeLayoutHelper.php';

    final class ConfigControllerFakeRequest
    {
        /**
         * @param array<string, mixed> $values
         */
        public function __construct(private array $values = [])
        {
        }

        /** @return mixed */
        public function getValue(string $name, $default = null)
        {
            return $this->values[$name] ?? $default;
        }

        /** @return array<string, mixed> */
        public function getValues(): array
        {
            return $this->values;
        }
    }

    final class ConfigControllerFakeResponse
    {
        public ?string $htmlContent = null;

        public ?string $redirectUrl = null;

        public function html(string $content): string
        {
            $this->htmlContent = $content;

            return $content;
        }

        public function redirect(string $url): string
        {
            $this->redirectUrl = $url;

            return $url;
        }
    }

    final class ConfigControllerFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class ConfigControllerFakeUrlHelper
    {
        /**
         * @param array<string, mixed> $params
         */
        public function href(string $controller, string $action, array $params = [], string $plugin = ''): string
        {
            $query = array_merge([
                'controller' => $controller,
                'action' => $action,
                'plugin' => $plugin,
            ], $params);

            return '/?' . http_build_query($query);
        }
    }

    final class ConfigControllerFakeFormHelper
    {
        public function __construct(private string $token)
        {
        }

        public function csrf(): string
        {
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->token, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    final class ConfigControllerFakeTemplate
    {
        public string $lastTemplate = '';

        /** @var array<string, mixed> */
        public array $lastParams = [];

        public ConfigControllerFakeTextHelper $text;

        public ConfigControllerFakeUrlHelper $url;

        public ConfigControllerFakeFormHelper $form;

        public function __construct(string $csrfToken)
        {
            $this->text = new ConfigControllerFakeTextHelper();
            $this->url = new ConfigControllerFakeUrlHelper();
            $this->form = new ConfigControllerFakeFormHelper($csrfToken);
        }

        /**
         * @param array<string, mixed> $params
         */
        public function render(string $template, array $params = []): string
        {
            $this->lastTemplate = $template;
            $this->lastParams = $params;

            $resolved = str_replace('Portfolio:', '', $template);
            $templateFile = __DIR__ . '/../../Template/' . $resolved . '.php';
            if (! file_exists($templateFile)) {
                return '';
            }

            extract($params, EXTR_SKIP);

            ob_start();
            include $templateFile;

            return (string) ob_get_clean();
        }
    }

    final class ConfigControllerFakeFlash
    {
        /** @var array<int, string> */
        public array $successMessages = [];

        /** @var array<int, string> */
        public array $failureMessages = [];

        public function success(string $message): void
        {
            $this->successMessages[] = $message;
        }

        public function failure(string $message): void
        {
            $this->failureMessages[] = $message;
        }
    }

    final class ConfigControllerFakeSession
    {
        public function __construct(private string $csrfToken)
        {
        }

        public function getCsrfToken(): string
        {
            return $this->csrfToken;
        }
    }

    final class ConfigControllerFakeConfigModel
    {
        /** @var array<string, mixed> */
        private array $values;

        /** @var array<int, array{key: string, value: string}> */
        public array $saveCalls = [];

        /** @param array<string, mixed> $values */
        public function __construct(array $values = [], private bool $failSaves = false)
        {
            $this->values = $values;
        }

        /** @param mixed $default */
        public function get(string $key, $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function save(string $key, string $value): bool
        {
            $this->saveCalls[] = ['key' => $key, 'value' => $value];
            $this->values[$key] = $value;

            return ! $this->failSaves;
        }
    }

    final class ConfigControllerTest extends TestCase
    {
        private const CSRF_TOKEN = 'csrf-token';

        public function testShowRendersSettingsTemplateAndEscapesValues(): void
        {
            $controller = $this->buildController(
                new ConfigControllerFakeRequest(),
                new ConfigControllerFakeConfigModel([
                    'portfolio_dependency_link_types' => 'blocks, <script>alert(1)</script>',
                    'portfolio_tasks_per_page' => 75,
                ])
            );

            $html = $controller->show();

            $layout = $this->getService($controller, 'helper')->layout;
            $this->assertSame('Portfolio:config/settings', $layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Settings', $html);
            $this->assertStringContainsString('blocks, &lt;script&gt;alert(1)&lt;/script&gt;', $html);
            $this->assertStringContainsString('value="75"', $html);
        }

        public function testSaveNormalizesAndPersistsSettings(): void
        {
            $configModel = new ConfigControllerFakeConfigModel();
            $request = new ConfigControllerFakeRequest([
                'csrf_token' => self::CSRF_TOKEN,
                'portfolio_milestone_at_risk_days' => '14',
                'portfolio_milestone_at_risk_threshold' => '92',
                'portfolio_board_show_blockers' => '1',
                'portfolio_dependency_link_types' => 'blocks, relates to, blocks',
                'portfolio_tasks_per_page' => '250',
                'portfolio_milestone_weight_by' => 'score',
            ]);

            $controller = $this->buildController($request, $configModel);

            $redirectUrl = $controller->save();

            $flash = $this->getService($controller, 'flash');
            $response = $this->getService($controller, 'response');

            $saved = [];
            foreach ($configModel->saveCalls as $call) {
                $saved[$call['key']] = $call['value'];
            }

            $this->assertSame('14', $saved['portfolio_milestone_at_risk_days']);
            $this->assertSame('92', $saved['portfolio_milestone_at_risk_threshold']);
            $this->assertSame('1', $saved['portfolio_board_show_blockers']);
            $this->assertSame('0', $saved['portfolio_dashboard_widget_enabled']);
            $this->assertSame('blocks, relates to', $saved['portfolio_dependency_link_types']);
            $this->assertSame('250', $saved['portfolio_tasks_per_page']);
            $this->assertSame('score', $saved['portfolio_milestone_weight_by']);
            $this->assertSame(['Portfolio settings saved successfully.'], $flash->successMessages);
            $this->assertSame($response->redirectUrl, $redirectUrl);
            $this->assertStringContainsString('controller=ConfigController', (string) $redirectUrl);
            $this->assertStringContainsString('action=show', (string) $redirectUrl);
        }

        public function testSaveNormalizesInvalidWeightByToCount(): void
        {
            $configModel = new ConfigControllerFakeConfigModel();
            $request = new ConfigControllerFakeRequest([
                'csrf_token' => self::CSRF_TOKEN,
                'portfolio_milestone_weight_by' => 'invalid_value',
            ]);

            $controller = $this->buildController($request, $configModel);
            $controller->save();

            $saved = [];
            foreach ($configModel->saveCalls as $call) {
                $saved[$call['key']] = $call['value'];
            }

            $this->assertSame('count', $saved['portfolio_milestone_weight_by']);
        }

        public function testSaveShowsFailureWhenConfigSaveFails(): void
        {
            $request = new ConfigControllerFakeRequest([
                'csrf_token' => self::CSRF_TOKEN,
            ]);

            $controller = $this->buildController(
                $request,
                new ConfigControllerFakeConfigModel([], true)
            );

            $controller->save();

            $flash = $this->getService($controller, 'flash');
            $this->assertSame(['Unable to save portfolio settings.'], $flash->failureMessages);
        }

        private function buildController(
            ConfigControllerFakeRequest $request,
            ConfigControllerFakeConfigModel $configModel
        ): ConfigController {
            $services = [
                'request' => $request,
                'response' => new ConfigControllerFakeResponse(),
                'template' => new ConfigControllerFakeTemplate(self::CSRF_TOKEN),
                'helper' => new FakeHelper(),
                'flash' => new ConfigControllerFakeFlash(),
                'url' => new ConfigControllerFakeUrlHelper(),
                'configModel' => $configModel,
                'session' => new ConfigControllerFakeSession(self::CSRF_TOKEN),
            ];

            return new ConfigController($services);
        }

        private function getService(ConfigController $controller, string $name): mixed
        {
            return $controller->{$name};
        }
    }
}
