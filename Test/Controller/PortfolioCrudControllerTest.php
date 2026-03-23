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
                $expected = (string) ($this->container['csrf_token'] ?? 'csrf-token');
                $actual = (string) $this->request->getValue('csrf_token', '');

                if ($actual !== $expected) {
                    throw new \RuntimeException('Invalid CSRF token');
                }
            }
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Controller\PortfolioListController;
    use Kanboard\Plugin\Portfolio\Controller\PortfolioModificationController;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Controller/PortfolioListController.php';
    require_once __DIR__ . '/../../Controller/PortfolioModificationController.php';
    require_once __DIR__ . '/FakeLayoutHelper.php';

    final class PortfolioCrudFakeRequest
    {
        /**
         * @param array<string, mixed> $values
         * @param array<string, mixed> $integerParams
         */
        public function __construct(private array $values = [], private array $integerParams = [])
        {
        }

        public function getIntegerParam(string $name, int $default = 0): int
        {
            if (array_key_exists($name, $this->integerParams)) {
                return (int) $this->integerParams[$name];
            }

            if (array_key_exists($name, $this->values)) {
                return (int) $this->values[$name];
            }

            return $default;
        }

        /** @return mixed */
        public function getValue(string $name, $default = null)
        {
            return $this->values[$name] ?? $default;
        }

        /**
         * @return array<string, mixed>
         */
        public function getValues(): array
        {
            return $this->values;
        }
    }

    final class PortfolioCrudFakeResponse
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

    final class PortfolioCrudFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class PortfolioCrudFakeFormHelper
    {
        public function __construct(private string $token)
        {
        }

        public function csrf(): string
        {
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->token, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    final class PortfolioCrudFakeUrlHelper
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

    final class PortfolioCrudFakeTemplate
    {
        public string $lastTemplate = '';

        /**
         * @var array<string, mixed>
         */
        public array $lastParams = [];

        public PortfolioCrudFakeTextHelper $text;

        public PortfolioCrudFakeUrlHelper $url;

        public PortfolioCrudFakeFormHelper $form;

        public function __construct(string $csrfToken)
        {
            $this->text = new PortfolioCrudFakeTextHelper();
            $this->url = new PortfolioCrudFakeUrlHelper();
            $this->form = new PortfolioCrudFakeFormHelper($csrfToken);
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

    final class PortfolioCrudFakeFlash
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

    final class PortfolioCrudFakeModel
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        private array $portfolios = [];

        /** @var array<int, array<string, mixed>> */
        public array $createCalls = [];

        /** @var array<int, array{id: int, values: array<string, mixed>}> */
        public array $updateCalls = [];

        /** @var array<int, int> */
        public array $removeCalls = [];

        public bool $createShouldFail = false;

        public bool $updateShouldFail = false;

        public bool $removeShouldFail = false;

        /**
         * @param array<int, array<string, mixed>> $seedPortfolios
         */
        public function __construct(array $seedPortfolios = [])
        {
            foreach ($seedPortfolios as $portfolio) {
                $id = (int) ($portfolio['id'] ?? 0);
                if ($id > 0) {
                    $this->portfolios[$id] = $portfolio;
                }
            }
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getAll(): array
        {
            return array_values($this->portfolios);
        }

        /**
         * @param array<string, mixed> $values
         *
         * @return int|false
         */
        public function create(array $values)
        {
            $this->createCalls[] = $values;

            if ($this->createShouldFail) {
                return false;
            }

            $nextId = $this->portfolios === [] ? 1 : (max(array_keys($this->portfolios)) + 1);
            $this->portfolios[$nextId] = [
                'id' => $nextId,
                'name' => (string) ($values['name'] ?? ''),
                'description' => (string) ($values['description'] ?? ''),
                'owner_id' => (int) ($values['owner_id'] ?? 0),
                'is_active' => (int) ($values['is_active'] ?? 1),
            ];

            return $nextId;
        }

        /**
         * @return array<string, mixed>|null
         */
        public function getById(int $portfolioId): ?array
        {
            return $this->portfolios[$portfolioId] ?? null;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function update(int $portfolioId, array $values): bool
        {
            $this->updateCalls[] = [
                'id' => $portfolioId,
                'values' => $values,
            ];

            if ($this->updateShouldFail || ! array_key_exists($portfolioId, $this->portfolios)) {
                return false;
            }

            $this->portfolios[$portfolioId] = array_merge($this->portfolios[$portfolioId], $values);

            return true;
        }

        public function remove(int $portfolioId): bool
        {
            $this->removeCalls[] = $portfolioId;

            if ($this->removeShouldFail || ! array_key_exists($portfolioId, $this->portfolios)) {
                return false;
            }

            unset($this->portfolios[$portfolioId]);

            return true;
        }
    }

    final class PortfolioCrudFakeProjectModel
    {
        /** @param array<int, array<string, mixed>> $projects */
        public function __construct(private array $projects = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getAll(): array
        {
            return $this->projects;
        }
    }

    final class PortfolioCrudFakePortfolioProjectModel
    {
        /**
         * @var array<int, array<int, array<string, mixed>>>
         */
        private array $portfolioProjects = [];

        /** @var array<int, array{portfolio_id: int, project_id: int, position: int}> */
        public array $addCalls = [];

        /** @var array<int, array{portfolio_id: int, project_id: int}> */
        public array $removeCalls = [];

        public bool $addShouldFail = false;

        public bool $removeShouldFail = false;

        /**
         * @param array<int, array<int, array<string, mixed>>> $portfolioProjects
         */
        public function __construct(array $portfolioProjects = [])
        {
            $this->portfolioProjects = $portfolioProjects;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getProjects(int $portfolioId): array
        {
            return $this->portfolioProjects[$portfolioId] ?? [];
        }

        public function add(int $portfolioId, int $projectId, int $position = 0): bool
        {
            $this->addCalls[] = [
                'portfolio_id' => $portfolioId,
                'project_id' => $projectId,
                'position' => $position,
            ];

            return ! $this->addShouldFail;
        }

        public function remove(int $portfolioId, int $projectId): bool
        {
            $this->removeCalls[] = [
                'portfolio_id' => $portfolioId,
                'project_id' => $projectId,
            ];

            return ! $this->removeShouldFail;
        }
    }

    final class PortfolioCrudControllerTest extends TestCase
    {
        private const CSRF_TOKEN = 'csrf-token';

        public function testPortfolioListPageRendersEscapedRows(): void
        {
            $model = new PortfolioCrudFakeModel([
                ['id' => 1, 'name' => 'Roadmap', 'description' => 'Cross-team milestones', 'is_active' => 1],
                ['id' => 2, 'name' => '<script>alert(1)</script>', 'description' => 'Unsafe', 'is_active' => 0],
            ]);

            $services = $this->buildServices($model);
            $controller = new PortfolioListController($services);

            $html = $controller->index();

            $this->assertSame('Portfolio:portfolio/index', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Roadmap', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        }

        public function testCreatePortfolioFormDisplay(): void
        {
            $services = $this->buildServices(new PortfolioCrudFakeModel());
            $controller = new PortfolioModificationController($services);

            $html = $controller->create();

            $this->assertSame('Portfolio:portfolio/create', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Create Portfolio', $html);
            $this->assertStringContainsString('name="csrf_token"', $html);
        }

        public function testCreatePortfolioSubmitRedirectsToPortfolioPageOnSuccess(): void
        {
            $request = new PortfolioCrudFakeRequest([
                'csrf_token' => self::CSRF_TOKEN,
                'name' => 'Q2 Launch',
                'description' => 'Cross-project release',
                'owner_id' => 3,
                'is_active' => 1,
            ]);
            $model = new PortfolioCrudFakeModel();
            $services = $this->buildServices($model, $request);
            $controller = new PortfolioModificationController($services);

            $controller->save();

            $this->assertSame([
                'Portfolio created successfully.',
            ], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=PortfolioViewController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=show', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('portfolio_id=1', (string) $services['response']->redirectUrl);
            $this->assertCount(1, $model->createCalls);
        }

        public function testUpdatePortfolioRedirectsToPortfolioPageOnSuccess(): void
        {
            $request = new PortfolioCrudFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'name' => 'Updated Name',
                    'description' => 'Updated Description',
                    'owner_id' => 7,
                    'is_active' => 1,
                ],
                ['portfolio_id' => 9]
            );
            $model = new PortfolioCrudFakeModel([
                ['id' => 9, 'name' => 'Old Name', 'description' => '', 'owner_id' => 1, 'is_active' => 1],
            ]);
            $services = $this->buildServices($model, $request);
            $controller = new PortfolioModificationController($services);

            $controller->update();

            $this->assertSame([
                'Portfolio updated successfully.',
            ], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=PortfolioViewController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('portfolio_id=9', (string) $services['response']->redirectUrl);
        }

        public function testUpdateFailureShowsTranslatedErrorMessage(): void
        {
            $request = new PortfolioCrudFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'name' => 'Duplicate Name',
                    'description' => 'Description',
                    'owner_id' => 7,
                    'is_active' => 1,
                ],
                ['portfolio_id' => 4]
            );
            $model = new PortfolioCrudFakeModel([
                ['id' => 4, 'name' => 'Existing Name', 'description' => '', 'owner_id' => 2, 'is_active' => 1],
            ]);
            $model->updateShouldFail = true;
            $services = $this->buildServices($model, $request);
            $controller = new PortfolioModificationController($services);

            $html = $controller->update();

            $this->assertSame([
                'Unable to update portfolio.',
            ], $services['flash']->failureMessages);
            $this->assertSame('Portfolio:portfolio/edit', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Unable to update portfolio.', $html);
        }

        public function testRemoveConfirmationFlowRendersPortfolioAndCsrfForm(): void
        {
            $request = new PortfolioCrudFakeRequest([], ['portfolio_id' => 12]);
            $model = new PortfolioCrudFakeModel([
                ['id' => 12, 'name' => 'Release Governance', 'description' => 'description', 'owner_id' => 1, 'is_active' => 1],
            ]);
            $services = $this->buildServices($model, $request);
            $controller = new PortfolioModificationController($services);

            $html = $controller->remove();

            $this->assertSame('Portfolio:portfolio/remove', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Release Governance', $html);
            $this->assertStringContainsString('Do you really want to remove this portfolio?', $html);
            $this->assertStringContainsString('name="csrf_token"', $html);
        }

        public function testSettingsPageShowsCurrentMembershipAndAvailableProjects(): void
        {
            $request = new PortfolioCrudFakeRequest([], ['portfolio_id' => 3]);
            $model = new PortfolioCrudFakeModel([
                ['id' => 3, 'name' => 'Launch Portfolio', 'description' => '', 'owner_id' => 1, 'is_active' => 1],
            ]);
            $projectModel = new PortfolioCrudFakeProjectModel([
                ['id' => 10, 'name' => 'Backend Revamp', 'identifier' => 'backend'],
                ['id' => 11, 'name' => '<script>alert(1)</script>', 'identifier' => 'unsafe'],
                ['id' => 12, 'name' => 'Mobile Rollout', 'identifier' => 'mobile'],
            ]);
            $portfolioProjectModel = new PortfolioCrudFakePortfolioProjectModel([
                3 => [
                    ['id' => 11, 'name' => '<script>alert(1)</script>', 'identifier' => 'unsafe', 'position' => 2],
                ],
            ]);
            $services = $this->buildServices($model, $request, $projectModel, $portfolioProjectModel);
            $controller = new PortfolioModificationController($services);

            $html = $controller->settings();

            $this->assertSame('Portfolio:portfolio/settings', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Settings', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('Mobile Rollout', $html);
            $this->assertStringNotContainsString('<option value="11">', $html);
            $this->assertStringContainsString('name="csrf_token"', $html);
        }

        public function testAddProjectSettingsActionRedirectsWithSuccessMessage(): void
        {
            $request = new PortfolioCrudFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'project_id' => 42,
                    'position' => 7,
                ],
                ['portfolio_id' => 5]
            );
            $model = new PortfolioCrudFakeModel([
                ['id' => 5, 'name' => 'Operations', 'description' => '', 'owner_id' => 1, 'is_active' => 1],
            ]);
            $portfolioProjectModel = new PortfolioCrudFakePortfolioProjectModel();
            $services = $this->buildServices(
                $model,
                $request,
                new PortfolioCrudFakeProjectModel(),
                $portfolioProjectModel
            );
            $controller = new PortfolioModificationController($services);

            $controller->addProject();

            $this->assertSame([
                [
                    'portfolio_id' => 5,
                    'project_id' => 42,
                    'position' => 7,
                ],
            ], $portfolioProjectModel->addCalls);
            $this->assertSame(['Project added to portfolio.'], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=PortfolioModificationController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=settings', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('portfolio_id=5', (string) $services['response']->redirectUrl);
        }

        public function testRemoveProjectSettingsActionRedirectsWithSuccessMessage(): void
        {
            $request = new PortfolioCrudFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'project_id' => 9,
                ],
                ['portfolio_id' => 5]
            );
            $model = new PortfolioCrudFakeModel([
                ['id' => 5, 'name' => 'Operations', 'description' => '', 'owner_id' => 1, 'is_active' => 1],
            ]);
            $portfolioProjectModel = new PortfolioCrudFakePortfolioProjectModel();
            $services = $this->buildServices(
                $model,
                $request,
                new PortfolioCrudFakeProjectModel(),
                $portfolioProjectModel
            );
            $controller = new PortfolioModificationController($services);

            $controller->removeProject();

            $this->assertSame([
                [
                    'portfolio_id' => 5,
                    'project_id' => 9,
                ],
            ], $portfolioProjectModel->removeCalls);
            $this->assertSame(['Project removed from portfolio.'], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=PortfolioModificationController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=settings', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('portfolio_id=5', (string) $services['response']->redirectUrl);
        }

        public function testAddProjectSettingsActionRejectsInvalidCsrfToken(): void
        {
            $request = new PortfolioCrudFakeRequest(
                [
                    'csrf_token' => 'invalid-token',
                    'project_id' => 42,
                    'position' => 1,
                ],
                ['portfolio_id' => 5]
            );
            $services = $this->buildServices(new PortfolioCrudFakeModel(), $request);
            $controller = new PortfolioModificationController($services);

            $this->expectException(\RuntimeException::class);
            $controller->addProject();
        }

        /**
         * @return array<string, mixed>
         */
        private function buildServices(
            PortfolioCrudFakeModel $model,
            ?PortfolioCrudFakeRequest $request = null,
            ?PortfolioCrudFakeProjectModel $projectModel = null,
            ?PortfolioCrudFakePortfolioProjectModel $portfolioProjectModel = null
        ): array {
            $response = new PortfolioCrudFakeResponse();
            $template = new PortfolioCrudFakeTemplate(self::CSRF_TOKEN);
            $flash = new PortfolioCrudFakeFlash();
            $url = new PortfolioCrudFakeUrlHelper();

            return [
                'request' => $request ?? new PortfolioCrudFakeRequest(),
                'response' => $response,
                'template' => $template,
                'helper' => new FakeHelper(),
                'flash' => $flash,
                'url' => $url,
                'portfolioModel' => $model,
                'projectModel' => $projectModel ?? new PortfolioCrudFakeProjectModel(),
                'portfolioProjectModel' => $portfolioProjectModel ?? new PortfolioCrudFakePortfolioProjectModel(),
                'csrf_token' => self::CSRF_TOKEN,
            ];
        }
    }
}
