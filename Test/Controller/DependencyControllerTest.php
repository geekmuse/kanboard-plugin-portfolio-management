<?php

declare(strict_types=1);

namespace {
    if (! function_exists('t')) {
        function t(string $message): string
        {
            return $message;
        }
    }

    if (! function_exists('htmlspecialchars')) {
        function htmlspecialchars(string $string, int $flags = ENT_QUOTES, string $encoding = 'UTF-8'): string
        {
            return \htmlspecialchars($string, $flags, $encoding);
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

namespace Kanboard\Controller {
    if (! class_exists(__NAMESPACE__ . '\\Base')) {
        class Base extends \Kanboard\Core\Base
        {
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Controller\DependencyController;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Controller/DependencyController.php';
    require_once __DIR__ . '/FakeLayoutHelper.php';

    final class DependencyControllerFakeRequest
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

    final class DependencyControllerFakeResponse
    {
        public ?string $htmlContent = null;

        public ?string $redirectUrl = null;

        /** @var array<string, mixed>|null */
        public ?array $jsonPayload = null;

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

        /** @param array<string, mixed> $data */
        public function json(array $data): string
        {
            $this->jsonPayload = $data;
            $encoded = (string) json_encode($data);
            $this->htmlContent = $encoded;

            return $encoded;
        }
    }

    final class DependencyControllerFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class DependencyControllerFakeUrlHelper
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

    final class DependencyControllerFakeTemplate
    {
        public string $lastTemplate = '';

        /**
         * @var array<string, mixed>
         */
        public array $lastParams = [];

        public DependencyControllerFakeTextHelper $text;

        public DependencyControllerFakeUrlHelper $url;

        public function __construct()
        {
            $this->text = new DependencyControllerFakeTextHelper();
            $this->url = new DependencyControllerFakeUrlHelper();
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

    final class DependencyControllerFakeFlash
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

    final class DependencyControllerFakePortfolioModel
    {
        /**
         * @param array<int, array<string, mixed>> $portfolios
         */
        public function __construct(private array $portfolios = [])
        {
        }

        /** @return array<string, mixed>|null */
        public function getById(int $portfolioId): ?array
        {
            foreach ($this->portfolios as $portfolio) {
                if ((int) ($portfolio['id'] ?? 0) === $portfolioId) {
                    return $portfolio;
                }
            }

            return null;
        }
    }

    final class DependencyControllerFakeDependencyModel
    {
        /** @var array<int, array<string, mixed>> */
        public array $getGraphCalls = [];

        /** @var array<int, int> */
        public array $getBlockedTasksCalls = [];

        /** @var array<int, int> */
        public array $getCriticalPathCalls = [];

        /**
         * @param array<string, mixed> $graphData
         * @param array<int, array<string, mixed>> $blockedTasks
         * @param array<int, array<string, mixed>> $criticalPath
         */
        public function __construct(
            private array $graphData = ['nodes' => [], 'edges' => [], 'critical_path' => []],
            private array $blockedTasks = [],
            private array $criticalPath = []
        ) {
        }

        /**
         * @return array<string, mixed>
         */
        public function getGraph(int $portfolioId, bool $crossProjectOnly = true): array
        {
            $this->getGraphCalls[] = ['portfolio_id' => $portfolioId, 'cross_project_only' => $crossProjectOnly];

            return $this->graphData;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getBlockedTasks(int $portfolioId): array
        {
            $this->getBlockedTasksCalls[] = $portfolioId;

            return $this->blockedTasks;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getCriticalPath(int $portfolioId): array
        {
            $this->getCriticalPathCalls[] = $portfolioId;

            return $this->criticalPath;
        }
    }

    final class DependencyControllerTest extends TestCase
    {
        public function testGraphPageRendersWithGraphDataEmbeddedInContainer(): void
        {
            $graphData = [
                'nodes' => [
                    ['id' => 10, 'title' => 'Task Alpha', 'project_name' => 'Project A', 'is_active' => 1],
                    ['id' => 20, 'title' => 'Task Beta <script>xss</script>', 'project_name' => 'Project B', 'is_active' => 1],
                ],
                'edges' => [
                    ['source' => 10, 'target' => 20, 'label' => 'blocks', 'is_resolved' => false],
                ],
                'critical_path' => [10, 20],
            ];

            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 7, 'cross_project_only' => 1]);
            $dependencyModel = new DependencyControllerFakeDependencyModel($graphData);
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([
                    ['id' => 7, 'name' => 'Q3 Portfolio'],
                ]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->graph();

            $this->assertSame('Portfolio:dependency/graph', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Dependency Graph', $html);
            $this->assertStringContainsString('Q3 Portfolio', $html);
            $this->assertStringContainsString('data-graph=', $html);
            $this->assertStringContainsString('portfolio-dependency-graph', $html);
            // The XSS title must be entity-encoded in the data-graph attribute
            $this->assertStringNotContainsString('<script>xss</script>', $html);
            $this->assertSame([['portfolio_id' => 7, 'cross_project_only' => true]], $dependencyModel->getGraphCalls);
        }

        public function testGraphPageShowsEmptyStateWhenNoDependencies(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 7, 'cross_project_only' => 1]);
            $dependencyModel = new DependencyControllerFakeDependencyModel();
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([
                    ['id' => 7, 'name' => 'Empty Portfolio'],
                ]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->graph();

            $this->assertStringContainsString('No dependencies found for this portfolio.', $html);
            $this->assertStringNotContainsString('data-graph=', $html);
        }

        public function testGraphPageRedirectsWhenPortfolioNotFound(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 999]);
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([]),
                new DependencyControllerFakeDependencyModel(),
                $request
            );

            $controller = new DependencyController($services);
            $controller->graph();

            $this->assertSame(['Portfolio not found.'], $services['flash']->failureMessages);
            $this->assertStringContainsString('controller=PortfolioListController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=index', (string) $services['response']->redirectUrl);
        }

        public function testGraphDataEndpointReturnsJsonPayload(): void
        {
            $graphData = [
                'nodes' => [
                    ['id' => 1, 'title' => 'Task One', 'project_name' => 'Proj A', 'is_active' => 1],
                ],
                'edges' => [],
                'critical_path' => [1],
            ];

            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 5, 'cross_project_only' => 1]);
            $dependencyModel = new DependencyControllerFakeDependencyModel($graphData);
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([
                    ['id' => 5, 'name' => 'Test Portfolio'],
                ]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $controller->graphData();

            $this->assertNotNull($services['response']->jsonPayload);
            $payload = $services['response']->jsonPayload;
            $this->assertArrayHasKey('nodes', $payload);
            $this->assertArrayHasKey('edges', $payload);
            $this->assertArrayHasKey('critical_path', $payload);
            $this->assertSame(1, count($payload['nodes']));
            $this->assertSame([1], $payload['critical_path']);
            $this->assertSame([['portfolio_id' => 5, 'cross_project_only' => true]], $dependencyModel->getGraphCalls);
        }

        public function testGraphDataEndpointRespectsNoCrossProjectFilter(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 5, 'cross_project_only' => 0]);
            $dependencyModel = new DependencyControllerFakeDependencyModel();
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([['id' => 5, 'name' => 'Portfolio']]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $controller->graphData();

            $this->assertSame([['portfolio_id' => 5, 'cross_project_only' => false]], $dependencyModel->getGraphCalls);
        }

        public function testBlockedTasksPageRendersEscapedBlockerList(): void
        {
            $blockedTasks = [
                [
                    'id' => 55,
                    'title' => 'Blocked Task <b>inject</b>',
                    'project_name' => 'Project Alpha',
                    'is_active' => 1,
                    'blockers' => [
                        [
                            'task_id' => 30,
                            'task_title' => 'Upstream Task',
                            'project_name' => 'Project Beta',
                            'is_active' => 1,
                        ],
                    ],
                ],
            ];

            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 3]);
            $dependencyModel = new DependencyControllerFakeDependencyModel(
                ['nodes' => [], 'edges' => [], 'critical_path' => []],
                $blockedTasks
            );
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([
                    ['id' => 3, 'name' => 'Q4 Plan'],
                ]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->blocked();

            $this->assertSame('Portfolio:dependency/blocked', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Blocked Tasks', $html);
            $this->assertStringContainsString('Q4 Plan', $html);
            $this->assertStringContainsString('&lt;b&gt;inject&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('<b>inject</b>', $html);
            $this->assertStringContainsString('Upstream Task', $html);
            $this->assertStringContainsString('Project Beta', $html);
            $this->assertSame([3], $dependencyModel->getBlockedTasksCalls);
        }

        public function testBlockedTasksPageShowsEmptyStateWhenNoBlockedTasks(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 3]);
            $dependencyModel = new DependencyControllerFakeDependencyModel();
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([['id' => 3, 'name' => 'Q4 Plan']]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->blocked();

            $this->assertStringContainsString('No blocked tasks found for this portfolio.', $html);
        }

        public function testBlockedTasksPageRedirectsWhenPortfolioNotFound(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 999]);
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([]),
                new DependencyControllerFakeDependencyModel(),
                $request
            );

            $controller = new DependencyController($services);
            $controller->blocked();

            $this->assertSame(['Portfolio not found.'], $services['flash']->failureMessages);
            $this->assertStringContainsString('controller=PortfolioListController', (string) $services['response']->redirectUrl);
        }

        public function testCriticalPathPageRendersCriticalPathChain(): void
        {
            $criticalPath = [
                [
                    'id' => 10,
                    'title' => 'First <em>task</em>',
                    'project_name' => 'Backend',
                    'assignee' => 'alice',
                    'is_active' => 1,
                    'chain_position' => 1,
                    'downstream_count' => 2,
                    'priority' => 3,
                    'color_id' => 'red',
                ],
                [
                    'id' => 20,
                    'title' => 'Second Task',
                    'project_name' => 'Frontend',
                    'assignee' => 'bob',
                    'is_active' => 1,
                    'chain_position' => 2,
                    'downstream_count' => 0,
                    'priority' => 2,
                    'color_id' => 'blue',
                ],
            ];

            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 4]);
            $dependencyModel = new DependencyControllerFakeDependencyModel(
                ['nodes' => [], 'edges' => [], 'critical_path' => []],
                [],
                $criticalPath
            );
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([
                    ['id' => 4, 'name' => 'Flagship'],
                ]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->criticalPath();

            $this->assertSame('Portfolio:dependency/critical_path', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Critical Path', $html);
            $this->assertStringContainsString('Flagship', $html);
            $this->assertStringContainsString('&lt;em&gt;task&lt;/em&gt;', $html);
            $this->assertStringNotContainsString('<em>task</em>', $html);
            $this->assertStringContainsString('Second Task', $html);
            $this->assertStringContainsString('alice', $html);
            $this->assertStringContainsString('bob', $html);
            $this->assertSame([4], $dependencyModel->getCriticalPathCalls);
        }

        public function testCriticalPathPageShowsEmptyStateWhenNoCriticalPath(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 4]);
            $dependencyModel = new DependencyControllerFakeDependencyModel();
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([['id' => 4, 'name' => 'Flagship']]),
                $dependencyModel,
                $request
            );

            $controller = new DependencyController($services);
            $html = $controller->criticalPath();

            $this->assertStringContainsString('No critical path found', $html);
        }

        public function testCriticalPathPageRedirectsWhenPortfolioNotFound(): void
        {
            $request = new DependencyControllerFakeRequest([], ['portfolio_id' => 0]);
            $services = $this->buildServices(
                new DependencyControllerFakePortfolioModel([]),
                new DependencyControllerFakeDependencyModel(),
                $request
            );

            $controller = new DependencyController($services);
            $controller->criticalPath();

            $this->assertSame(['Portfolio not found.'], $services['flash']->failureMessages);
            $this->assertStringContainsString('controller=PortfolioListController', (string) $services['response']->redirectUrl);
        }

        /**
         * @return array<string, mixed>
         */
        private function buildServices(
            DependencyControllerFakePortfolioModel $portfolioModel,
            DependencyControllerFakeDependencyModel $dependencyModel,
            ?DependencyControllerFakeRequest $request = null
        ): array {
            $template = new DependencyControllerFakeTemplate();

            return [
                'request' => $request ?? new DependencyControllerFakeRequest(),
                'response' => new DependencyControllerFakeResponse(),
                'template' => $template,
                'helper' => new FakeHelper(),
                'flash' => new DependencyControllerFakeFlash(),
                'url' => new DependencyControllerFakeUrlHelper(),
                'text' => $template->text,
                'portfolioModel' => $portfolioModel,
                'dependencyModel' => $dependencyModel,
            ];
        }
    }
}
