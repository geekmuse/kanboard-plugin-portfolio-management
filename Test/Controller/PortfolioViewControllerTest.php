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
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Controller\PortfolioViewController;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Controller/PortfolioViewController.php';
    require_once __DIR__ . '/FakeLayoutHelper.php';

    final class PortfolioViewFakeRequest
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
    }

    final class PortfolioViewFakeResponse
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

    final class PortfolioViewFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class PortfolioViewFakeUrlHelper
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

    final class PortfolioViewFakeTemplate
    {
        public string $lastTemplate = '';

        /**
         * @var array<string, mixed>
         */
        public array $lastParams = [];

        public PortfolioViewFakeTextHelper $text;

        public PortfolioViewFakeUrlHelper $url;

        public function __construct()
        {
            $this->text = new PortfolioViewFakeTextHelper();
            $this->url = new PortfolioViewFakeUrlHelper();
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

    final class PortfolioViewFakeFlash
    {
        /** @var array<int, string> */
        public array $failureMessages = [];

        public function failure(string $message): void
        {
            $this->failureMessages[] = $message;
        }
    }

    final class PortfolioViewFakePortfolioModel
    {
        /** @param array<int, array<string, mixed>> $seedPortfolios */
        public function __construct(private array $seedPortfolios = [])
        {
        }

        /** @return array<string, mixed>|null */
        public function getById(int $portfolioId): ?array
        {
            foreach ($this->seedPortfolios as $portfolio) {
                if ((int) ($portfolio['id'] ?? 0) === $portfolioId) {
                    return $portfolio;
                }
            }

            return null;
        }
    }

    final class PortfolioViewFakePortfolioTaskModel
    {
        /** @var array<int, int> */
        public array $overviewCalls = [];

        /** @var array<int, array{portfolio_id: int, filters: array<string, mixed>}> */
        public array $taskCalls = [];

        /** @var array<int, array{portfolio_id: int, status_id: int|null}> */
        public array $countCalls = [];

        /**
         * @param array<string, mixed> $overview
         * @param array<int, array<string, mixed>> $tasks
         * @param array<string, int> $counts
         */
        public function __construct(
            private array $overview = [],
            private array $tasks = [],
            private array $counts = ['total' => 0, 'active' => 0, 'closed' => 0, 'blocked' => 0]
        ) {
        }

        /** @return array<string, mixed> */
        public function getOverview(int $portfolioId): array
        {
            $this->overviewCalls[] = $portfolioId;

            return $this->overview;
        }

        /**
         * @param array<string, mixed> $filters
         *
         * @return array<int, array<string, mixed>>
         */
        public function getTasks(int $portfolioId, array $filters = []): array
        {
            $this->taskCalls[] = [
                'portfolio_id' => $portfolioId,
                'filters' => $filters,
            ];

            return $this->tasks;
        }

        /** @return array<string, int> */
        public function getCounts(int $portfolioId, ?int $statusId = null): array
        {
            $this->countCalls[] = [
                'portfolio_id' => $portfolioId,
                'status_id' => $statusId,
            ];

            return $this->counts;
        }
    }

    final class PortfolioViewFakePortfolioProjectModel
    {
        /**
         * @param array<int, array<int, array<string, mixed>>> $projectsByPortfolio
         */
        public function __construct(private array $projectsByPortfolio = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getProjects(int $portfolioId): array
        {
            return $this->projectsByPortfolio[$portfolioId] ?? [];
        }
    }

    final class PortfolioViewFakeMilestoneModel
    {
        /** @var array<int, int> */
        public array $getByPortfolioIdCalls = [];

        /**
         * @param array<int, array<int, array<string, mixed>>> $milestonesByPortfolio
         */
        public function __construct(private array $milestonesByPortfolio = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getByPortfolioId(int $portfolioId): array
        {
            $this->getByPortfolioIdCalls[] = $portfolioId;

            return $this->milestonesByPortfolio[$portfolioId] ?? [];
        }
    }

    final class PortfolioViewFakeConfigModel
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values = [])
        {
        }

        /** @param mixed $default */
        public function get(string $key, $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
    }

    final class PortfolioViewControllerTest extends TestCase
    {
        public function testPortfolioDashboardRendersOverviewAndEscapesContent(): void
        {
            $request = new PortfolioViewFakeRequest([], ['portfolio_id' => 4]);
            $portfolioModel = new PortfolioViewFakePortfolioModel([
                ['id' => 4, 'name' => '<script>alert(1)</script>', 'description' => 'desc', 'is_active' => 1],
            ]);
            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel([
                'portfolio' => ['id' => 4, 'name' => '<script>alert(1)</script>'],
                'project_count' => 1,
                'projects' => [
                    ['id' => 10, 'name' => '<b>Unsafe Project</b>', 'is_active' => 1, 'position' => 1],
                ],
                'task_counts' => ['total' => 8, 'active' => 5, 'closed' => 3, 'blocked' => 2],
                'milestones' => [
                    ['id' => 90, 'name' => '<img src=x onerror=alert(2)>', 'target_date' => 1717200000, 'percent' => 50.0, 'is_at_risk' => true, 'is_overdue' => false],
                ],
                'at_risk_milestones' => 1,
                'overdue_milestones' => 0,
                'critical_path_length' => 3,
            ]);

            $services = $this->buildServices($portfolioModel, $portfolioTaskModel, $request);
            $controller = new PortfolioViewController($services);

            $html = $controller->show();

            $this->assertSame('Portfolio:portfolio/show', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Dashboard', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $html);
            $this->assertSame([4], $portfolioTaskModel->overviewCalls);
        }

        public function testPortfolioTasksRendersUnifiedTaskListAndDelegatesFilters(): void
        {
            $request = new PortfolioViewFakeRequest([
                'status_id' => '1',
                'assignee_id' => '5',
                'project_id' => '10',
                'milestone_id' => '501',
                'has_dependencies' => '1',
                'sort' => 'date_due',
                'direction' => 'ASC',
                'limit' => '25',
                'offset' => '25',
            ], ['portfolio_id' => 4]);

            $portfolioModel = new PortfolioViewFakePortfolioModel([
                ['id' => 4, 'name' => 'Launch <Q2>', 'description' => 'desc', 'is_active' => 1],
            ]);

            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel(
                [],
                [
                    [
                        'id' => 501,
                        'title' => '<script>alert(5)</script>',
                        'project_name' => 'Website',
                        'column_title' => 'Doing',
                        'assignee_name' => 'Alice',
                        'assignee_username' => 'alice',
                        'is_active' => 1,
                        'priority' => 2,
                        'date_due' => 1717200000,
                        'is_blocked' => true,
                        'blocked_by_count' => 2,
                    ],
                ],
                ['total' => 10, 'active' => 8, 'closed' => 2, 'blocked' => 3]
            );

            $projectModel = new PortfolioViewFakePortfolioProjectModel([
                4 => [
                    ['id' => 10, 'name' => 'Website'],
                ],
            ]);

            $milestoneModel = new PortfolioViewFakeMilestoneModel([
                4 => [
                    ['id' => 501, 'name' => 'Release Candidate'],
                ],
            ]);

            $services = $this->buildServices(
                $portfolioModel,
                $portfolioTaskModel,
                $request,
                $projectModel,
                $milestoneModel
            );

            $controller = new PortfolioViewController($services);
            $html = $controller->tasks();

            $this->assertSame('Portfolio:portfolio/tasks', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Tasks', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(5)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(5)</script>', $html);
            $this->assertStringContainsString('Blocked (2)', $html);

            $this->assertSame([
                [
                    'portfolio_id' => 4,
                    'filters' => [
                        'status_id' => 1,
                        'assignee_id' => 5,
                        'project_id' => 10,
                        'milestone_id' => 501,
                        'has_dependencies' => true,
                        'sort' => 'date_due',
                        'direction' => 'ASC',
                        'limit' => 25,
                        'offset' => 25,
                    ],
                ],
            ], $portfolioTaskModel->taskCalls);

            $this->assertSame([
                [
                    'portfolio_id' => 4,
                    'status_id' => null,
                ],
            ], $portfolioTaskModel->countCalls);
        }

        public function testPortfolioTasksUsesConfiguredDefaultPageSizeWhenLimitIsMissing(): void
        {
            $request = new PortfolioViewFakeRequest([
                'status_id' => '1',
            ], ['portfolio_id' => 4]);

            $portfolioModel = new PortfolioViewFakePortfolioModel([
                ['id' => 4, 'name' => 'Launch', 'description' => 'desc', 'is_active' => 1],
            ]);

            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel();

            $services = $this->buildServices(
                $portfolioModel,
                $portfolioTaskModel,
                $request,
                null,
                null,
                new PortfolioViewFakeConfigModel([
                    'portfolio_tasks_per_page' => 75,
                ])
            );

            $controller = new PortfolioViewController($services);
            $controller->tasks();

            $this->assertSame(75, (int) $portfolioTaskModel->taskCalls[0]['filters']['limit']);
        }

        public function testPortfolioBoardRendersAggregateColumnsWithEscapedTaskContent(): void
        {
            $request = new PortfolioViewFakeRequest([], ['portfolio_id' => 4]);
            $portfolioModel = new PortfolioViewFakePortfolioModel([
                ['id' => 4, 'name' => 'Launch <Q2>', 'description' => 'desc', 'is_active' => 1],
            ]);

            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel(
                [],
                [
                    [
                        'id' => 700,
                        'title' => '<script>alert(7)</script>',
                        'project_name' => 'Website',
                        'column_id' => 9,
                        'column_title' => 'Doing',
                        'assignee_name' => 'Alice',
                        'assignee_username' => 'alice',
                        'date_due' => 1717200000,
                        'is_blocked' => true,
                        'blocked_by_count' => 1,
                    ],
                    [
                        'id' => 701,
                        'title' => 'Finalize copy',
                        'project_name' => 'Docs',
                        'column_id' => 0,
                        'column_title' => '',
                        'assignee_name' => '',
                        'assignee_username' => '',
                        'date_due' => 0,
                        'is_blocked' => false,
                        'blocked_by_count' => 0,
                    ],
                ],
                ['total' => 2, 'active' => 2, 'closed' => 0, 'blocked' => 1]
            );

            $services = $this->buildServices($portfolioModel, $portfolioTaskModel, $request);
            $controller = new PortfolioViewController($services);

            $html = $controller->board();

            $this->assertSame('Portfolio:portfolio/board', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Board', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(7)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(7)</script>', $html);
            $this->assertStringContainsString('Blocked (1)', $html);
            $this->assertStringContainsString('Unassigned', $html);

            $this->assertSame([
                [
                    'portfolio_id' => 4,
                    'filters' => [
                        'status_id' => 1,
                        'sort' => 'project',
                        'direction' => 'ASC',
                        'limit' => 500,
                        'offset' => 0,
                    ],
                ],
            ], $portfolioTaskModel->taskCalls);

            $this->assertSame([
                [
                    'portfolio_id' => 4,
                    'status_id' => null,
                ],
            ], $portfolioTaskModel->countCalls);
        }

        public function testPortfolioTimelineRendersTimelineDataAndReferencesGanttAsset(): void
        {
            $request = new PortfolioViewFakeRequest([], ['portfolio_id' => 4]);
            $portfolioModel = new PortfolioViewFakePortfolioModel([
                ['id' => 4, 'name' => 'Launch <Q2>', 'description' => 'desc', 'is_active' => 1],
            ]);

            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel(
                [],
                [
                    [
                        'id' => 702,
                        'title' => '<script>alert(8)</script>',
                        'project_name' => 'Website',
                        'date_due' => 1719800000,
                        'is_active' => 1,
                    ],
                ]
            );

            $milestoneModel = new PortfolioViewFakeMilestoneModel([
                4 => [
                    [
                        'id' => 99,
                        'name' => '<b>RC</b>',
                        'target_date' => 1719000000,
                    ],
                ],
            ]);

            $services = $this->buildServices(
                $portfolioModel,
                $portfolioTaskModel,
                $request,
                null,
                $milestoneModel
            );

            $controller = new PortfolioViewController($services);
            $html = $controller->timeline();

            $this->assertSame('Portfolio:portfolio/timeline', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Timeline', $html);
            $this->assertStringContainsString('plugins/Portfolio/Asset/js/portfolio-gantt.js', $html);
            $this->assertStringContainsString('portfolio-timeline-chart', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(8)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(8)</script>', $html);

            $this->assertSame([
                [
                    'portfolio_id' => 4,
                    'filters' => [
                        'status_id' => 1,
                        'sort' => 'date_due',
                        'direction' => 'ASC',
                        'limit' => 500,
                        'offset' => 0,
                    ],
                ],
            ], $portfolioTaskModel->taskCalls);

            $this->assertSame([4], $milestoneModel->getByPortfolioIdCalls);
        }

        public function testPortfolioDashboardRedirectsWhenPortfolioDoesNotExist(): void
        {
            $request = new PortfolioViewFakeRequest([], ['portfolio_id' => 99]);
            $portfolioModel = new PortfolioViewFakePortfolioModel();
            $portfolioTaskModel = new PortfolioViewFakePortfolioTaskModel();

            $services = $this->buildServices($portfolioModel, $portfolioTaskModel, $request);
            $controller = new PortfolioViewController($services);

            $controller->show();

            $this->assertSame(['Portfolio not found.'], $services['flash']->failureMessages);
            $this->assertStringContainsString('controller=PortfolioListController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=index', (string) $services['response']->redirectUrl);
        }

        /**
         * @return array<string, mixed>
         */
        private function buildServices(
            PortfolioViewFakePortfolioModel $portfolioModel,
            PortfolioViewFakePortfolioTaskModel $portfolioTaskModel,
            ?PortfolioViewFakeRequest $request = null,
            ?PortfolioViewFakePortfolioProjectModel $portfolioProjectModel = null,
            ?PortfolioViewFakeMilestoneModel $milestoneModel = null,
            ?PortfolioViewFakeConfigModel $configModel = null
        ): array {
            return [
                'request' => $request ?? new PortfolioViewFakeRequest(),
                'response' => new PortfolioViewFakeResponse(),
                'template' => new PortfolioViewFakeTemplate(),
                'helper' => new FakeHelper(),
                'flash' => new PortfolioViewFakeFlash(),
                'url' => new PortfolioViewFakeUrlHelper(),
                'portfolioModel' => $portfolioModel,
                'portfolioTaskModel' => $portfolioTaskModel,
                'portfolioProjectModel' => $portfolioProjectModel ?? new PortfolioViewFakePortfolioProjectModel(),
                'milestoneModel' => $milestoneModel ?? new PortfolioViewFakeMilestoneModel(),
                'configModel' => $configModel ?? new PortfolioViewFakeConfigModel(),
            ];
        }
    }
}
