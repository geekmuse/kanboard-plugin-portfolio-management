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
            }
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Helper\PortfolioHelper;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Helper/PortfolioHelper.php';

    // -----------------------------------------------------------------------
    // Shared fake helpers for template rendering
    // -----------------------------------------------------------------------

    final class WidgetFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class WidgetFakeUrlHelper
    {
        /**
         * @param array<string, mixed> $params
         */
        public function href(string $controller, string $action, array $params = [], string $plugin = ''): string
        {
            $query = array_merge([
                'controller' => $controller,
                'action'     => $action,
                'plugin'     => $plugin,
            ], $params);

            return '/?' . http_build_query($query);
        }
    }

    // -----------------------------------------------------------------------
    // Fake PortfolioHelper for widget template rendering
    // -----------------------------------------------------------------------

    final class WidgetFakePortfolioHelper
    {
        /**
         * @param int[]                            $blockedTaskIds   task IDs to report as blocked
         * @param array<int, array<string, mixed>> $portfolios       portfolios to return for any project
         * @param array<int, array<string, mixed>> $allPortfolios    portfolio list for dashboard
         * @param array<int, array<string, mixed>> $atRiskMilestones at-risk milestones for dashboard
         */
        public function __construct(
            private array $blockedTaskIds = [],
            private array $portfolios = [],
            private array $allPortfolios = [],
            private array $atRiskMilestones = []
        ) {
        }

        public function isTaskBlocked(int $taskId, int $projectId): bool
        {
            return in_array($taskId, $this->blockedTaskIds, true);
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getPortfoliosForProject(int $projectId): array
        {
            return $this->portfolios;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getAllPortfolios(): array
        {
            return $this->allPortfolios;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getGlobalAtRiskMilestones(): array
        {
            return $this->atRiskMilestones;
        }
    }

    // -----------------------------------------------------------------------
    // Fake MilestoneTaskModel
    // -----------------------------------------------------------------------

    final class WidgetFakeMilestoneTaskModel
    {
        /** @param array<int, array<string, mixed>> $milestones */
        public function __construct(private array $milestones = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getMilestones(int $taskId): array
        {
            return $this->milestones;
        }
    }

    // -----------------------------------------------------------------------
    // Widget rendering context
    //
    // When renderWidget() includes a template file, $this is this object, so
    // $this->portfolioHelper, $this->text->e(), $this->url->href(), etc. all
    // resolve correctly — exactly as they do in a real Kanboard template.
    // -----------------------------------------------------------------------

    final class WidgetFakeContext
    {
        public WidgetFakeTextHelper $text;
        public WidgetFakeUrlHelper $url;

        /** @var array<string, mixed> */
        private array $services = [];

        public function __construct()
        {
            $this->text = new WidgetFakeTextHelper();
            $this->url  = new WidgetFakeUrlHelper();
        }

        public function addService(string $name, mixed $service): void
        {
            $this->services[$name] = $service;
        }

        /** @return mixed */
        public function __get(string $name)
        {
            return $this->services[$name] ?? null;
        }

        /**
         * Render a widget template file.
         * $vars are extracted into the local scope; $this resolves to this
         * WidgetFakeContext object so template service calls work.
         *
         * @param array<string, mixed> $vars
         */
        public function renderWidget(string $templateFile, array $vars = []): string
        {
            extract($vars, EXTR_SKIP);

            ob_start();
            include $templateFile;

            return (string) ob_get_clean();
        }
    }

    // -----------------------------------------------------------------------
    // Fake models for PortfolioHelper unit tests
    // -----------------------------------------------------------------------

    final class WidgetFakePortfolioProjectModel
    {
        public int $getPortfoliosCalls = 0;

        /**
         * @param array<int, array<string, mixed>> $portfolios
         */
        public function __construct(private array $portfolios = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getPortfolios(int $projectId): array
        {
            $this->getPortfoliosCalls++;

            return $this->portfolios;
        }
    }

    final class WidgetFakeDependencyModel
    {
        public int $getBlockedTasksCalls = 0;

        /**
         * @param array<int, array<string, mixed>> $blockedTasks
         */
        public function __construct(private array $blockedTasks = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getBlockedTasks(int $portfolioId): array
        {
            $this->getBlockedTasksCalls++;

            return $this->blockedTasks;
        }
    }

    final class WidgetFakePortfolioModel
    {
        /**
         * @param array<int, array<string, mixed>> $portfolios
         */
        public function __construct(private array $portfolios = [])
        {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getAll(): array
        {
            return $this->portfolios;
        }
    }

    final class WidgetFakeMilestoneModel
    {
        /**
         * @param array<int, array<string, mixed>>             $milestones
         * @param array<int, array<string, mixed>>             $progressMap  milestone_id → progress
         */
        public function __construct(
            private array $milestones = [],
            private array $progressMap = []
        ) {
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getByPortfolioId(int $portfolioId): array
        {
            return $this->milestones;
        }

        /**
         * @return array<string, mixed>
         */
        public function getProgress(int $milestoneId): array
        {
            return $this->progressMap[$milestoneId] ?? [
                'total'          => 0,
                'completed'      => 0,
                'percent'        => 0,
                'blocked_count'  => 0,
                'is_at_risk'     => false,
                'is_overdue'     => false,
                'target_date'    => null,
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Test suite
    // -----------------------------------------------------------------------

    final class WidgetHookTest extends TestCase
    {
        private string $templateDir;

        protected function setUp(): void
        {
            $this->templateDir = __DIR__ . '/../../Template/widget';
        }

        // -------------------------------------------------------------------
        // board_blocked_indicator template
        // -------------------------------------------------------------------

        public function testBoardBlockedIndicatorShowsBadgeForBlockedTask(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                blockedTaskIds: [42]
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/board_blocked_indicator.php',
                ['task' => ['id' => 42, 'project_id' => 1]]
            );

            $this->assertStringContainsString('portfolio-task-blocked', $html);
            $this->assertStringContainsString('Blocked', $html);
            $this->assertStringContainsString('portfolio-board-blocked-badge', $html);
        }

        public function testBoardBlockedIndicatorRendersNothingForUnblockedTask(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                blockedTaskIds: []
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/board_blocked_indicator.php',
                ['task' => ['id' => 99, 'project_id' => 1]]
            );

            $this->assertStringNotContainsString('portfolio-task-blocked', $html);
            $this->assertStringNotContainsString('Blocked', $html);
        }

        public function testBoardBlockedIndicatorEscapesTranslationOutput(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                blockedTaskIds: [5]
            ));

            // Template output should only contain the safe label
            $html = $context->renderWidget(
                $this->templateDir . '/board_blocked_indicator.php',
                ['task' => ['id' => 5, 'project_id' => 2]]
            );

            $this->assertStringContainsString('portfolio-task-blocked', $html);
            $this->assertStringNotContainsString('<script', $html);
        }

        public function testBoardBlockedIndicatorHandlesMissingTaskGracefully(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                blockedTaskIds: [1]
            ));

            // Missing $task should produce no output (task IDs default to 0)
            $html = $context->renderWidget(
                $this->templateDir . '/board_blocked_indicator.php',
                ['task' => []]
            );

            $this->assertStringNotContainsString('portfolio-task-blocked', $html);
        }

        // -------------------------------------------------------------------
        // task_milestone_info template
        // -------------------------------------------------------------------

        public function testTaskMilestoneInfoRendersMilestoneNames(): void
        {
            $milestones = [
                ['id' => 10, 'name' => 'Q3 Milestone', 'is_critical' => 0],
                ['id' => 11, 'name' => 'Launch <b>Day</b>', 'is_critical' => 1],
            ];

            $context = new WidgetFakeContext();
            $context->addService('milestoneTaskModel', new WidgetFakeMilestoneTaskModel($milestones));

            $html = $context->renderWidget(
                $this->templateDir . '/task_milestone_info.php',
                ['task' => ['id' => 7, 'project_id' => 1]]
            );

            $this->assertStringContainsString('Q3 Milestone', $html);
            // XSS in milestone name must be escaped
            $this->assertStringContainsString('&lt;b&gt;Day&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('<b>Day</b>', $html);
            $this->assertStringContainsString('Critical', $html);
            $this->assertStringContainsString('Milestones', $html);
        }

        public function testTaskMilestoneInfoRendersNothingWhenNoMilestones(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('milestoneTaskModel', new WidgetFakeMilestoneTaskModel([]));

            $html = $context->renderWidget(
                $this->templateDir . '/task_milestone_info.php',
                ['task' => ['id' => 7, 'project_id' => 1]]
            );

            $trimmed = trim($html);
            $this->assertSame('', $trimmed);
        }

        public function testTaskMilestoneInfoHandlesNullModelGracefully(): void
        {
            $context = new WidgetFakeContext();
            // No milestoneTaskModel service registered

            $html = $context->renderWidget(
                $this->templateDir . '/task_milestone_info.php',
                ['task' => ['id' => 7, 'project_id' => 1]]
            );

            $trimmed = trim($html);
            $this->assertSame('', $trimmed);
        }

        // -------------------------------------------------------------------
        // dashboard_portfolios template
        // -------------------------------------------------------------------

        public function testDashboardPortfoliosWidgetRendersPortfolioLinks(): void
        {
            $portfolios = [
                ['id' => 1, 'name' => 'Alpha Portfolio'],
                ['id' => 2, 'name' => 'Beta Portfolio <em>x</em>'],
            ];

            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                allPortfolios: $portfolios
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/dashboard_portfolios.php'
            );

            $this->assertStringContainsString('Alpha Portfolio', $html);
            $this->assertStringContainsString('&lt;em&gt;x&lt;/em&gt;', $html);
            $this->assertStringNotContainsString('<em>x</em>', $html);
            $this->assertStringContainsString('PortfolioViewController', $html);
            $this->assertStringContainsString('portfolio_id=1', $html);
            $this->assertStringContainsString('View all portfolios', $html);
            $this->assertStringContainsString('Create Portfolio', $html);
        }

        public function testDashboardPortfoliosWidgetRendersAtRiskMilestones(): void
        {
            $portfolios = [
                ['id' => 1, 'name' => 'My Portfolio'],
            ];
            $atRisk = [
                [
                    'id'             => 5,
                    'name'           => 'At Risk MS',
                    'portfolio_name' => 'My Portfolio',
                    'portfolio_id'   => 1,
                    'progress'       => ['percent' => 40, 'is_at_risk' => true, 'is_overdue' => false],
                ],
            ];

            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                allPortfolios:    $portfolios,
                atRiskMilestones: $atRisk
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/dashboard_portfolios.php'
            );

            $this->assertStringContainsString('At-Risk Milestones', $html);
            $this->assertStringContainsString('At Risk MS', $html);
            $this->assertStringContainsString('My Portfolio', $html);
            $this->assertStringContainsString('40%', $html);
            $this->assertStringContainsString('portfolio-widget-list--at-risk', $html);
        }

        public function testDashboardPortfoliosWidgetRendersNothingWhenNoPortfolios(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper());

            $html = $context->renderWidget(
                $this->templateDir . '/dashboard_portfolios.php'
            );

            $trimmed = trim($html);
            $this->assertSame('', $trimmed);
        }

        // -------------------------------------------------------------------
        // project_sidebar template
        // -------------------------------------------------------------------

        public function testProjectSidebarRendersPortfolioLinks(): void
        {
            $portfolios = [
                ['id' => 3, 'name' => 'Side Portfolio'],
            ];

            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                portfolios: $portfolios
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/project_sidebar.php',
                ['project' => ['id' => 10]]
            );

            $this->assertStringContainsString('Side Portfolio', $html);
            $this->assertStringContainsString('portfolio_id=3', $html);
            $this->assertStringContainsString('PortfolioViewController', $html);
            $this->assertStringContainsString('portfolio-widget-project-sidebar', $html);
        }

        public function testProjectSidebarRendersNothingWhenNotInPortfolio(): void
        {
            $context = new WidgetFakeContext();
            $context->addService('portfolioHelper', new WidgetFakePortfolioHelper(
                portfolios: []
            ));

            $html = $context->renderWidget(
                $this->templateDir . '/project_sidebar.php',
                ['project' => ['id' => 10]]
            );

            $trimmed = trim($html);
            $this->assertSame('', $trimmed);
        }

        // -------------------------------------------------------------------
        // PortfolioHelper unit tests — caching / N+1 prevention
        // -------------------------------------------------------------------

        public function testPortfolioHelperIsTaskBlockedReturnsTrueForBlockedId(): void
        {
            $portfolioProjectModel = new WidgetFakePortfolioProjectModel([
                ['id' => 1, 'name' => 'Portfolio One'],
            ]);
            $dependencyModel = new WidgetFakeDependencyModel([
                ['id' => 42, 'title' => 'Blocked Task'],
            ]);

            $helper = new PortfolioHelper([
                'portfolioProjectModel' => $portfolioProjectModel,
                'dependencyModel'       => $dependencyModel,
            ]);

            $this->assertTrue($helper->isTaskBlocked(42, 10));
            $this->assertFalse($helper->isTaskBlocked(99, 10));
        }

        public function testPortfolioHelperCachePreventsRepeatedQueriesForSameProject(): void
        {
            $portfolioProjectModel = new WidgetFakePortfolioProjectModel([
                ['id' => 1, 'name' => 'Portfolio One'],
            ]);
            $dependencyModel = new WidgetFakeDependencyModel([
                ['id' => 7, 'title' => 'Blocked Task'],
            ]);

            $helper = new PortfolioHelper([
                'portfolioProjectModel' => $portfolioProjectModel,
                'dependencyModel'       => $dependencyModel,
            ]);

            // Three calls for the same project — dependency model should only be queried once
            $helper->isTaskBlocked(7, 5);
            $helper->isTaskBlocked(7, 5);
            $helper->isTaskBlocked(8, 5);

            $this->assertSame(1, $portfolioProjectModel->getPortfoliosCalls);
            $this->assertSame(1, $dependencyModel->getBlockedTasksCalls);
        }

        public function testPortfolioHelperQueriesSeparatelyForDifferentProjects(): void
        {
            $portfolioProjectModel = new WidgetFakePortfolioProjectModel([
                ['id' => 1, 'name' => 'P1'],
            ]);
            $dependencyModel = new WidgetFakeDependencyModel([
                ['id' => 7, 'title' => 'Task 7'],
            ]);

            $helper = new PortfolioHelper([
                'portfolioProjectModel' => $portfolioProjectModel,
                'dependencyModel'       => $dependencyModel,
            ]);

            $helper->isTaskBlocked(7, 10);
            $helper->isTaskBlocked(7, 20); // different project → new load

            $this->assertSame(2, $portfolioProjectModel->getPortfoliosCalls);
            $this->assertSame(2, $dependencyModel->getBlockedTasksCalls);
        }

        public function testPortfolioHelperPreloadIsIdempotent(): void
        {
            $portfolioProjectModel = new WidgetFakePortfolioProjectModel([
                ['id' => 1, 'name' => 'P1'],
            ]);
            $dependencyModel = new WidgetFakeDependencyModel([]);

            $helper = new PortfolioHelper([
                'portfolioProjectModel' => $portfolioProjectModel,
                'dependencyModel'       => $dependencyModel,
            ]);

            $helper->preloadBoardBlockedStatus(3);
            $helper->preloadBoardBlockedStatus(3);
            $helper->preloadBoardBlockedStatus(3);

            $this->assertSame(1, $portfolioProjectModel->getPortfoliosCalls);
        }

        public function testPortfolioHelperReturnsFalseWhenProjectHasNoPortfolios(): void
        {
            $portfolioProjectModel = new WidgetFakePortfolioProjectModel([]);
            $dependencyModel       = new WidgetFakeDependencyModel([
                ['id' => 99, 'title' => 'Should Not Appear'],
            ]);

            $helper = new PortfolioHelper([
                'portfolioProjectModel' => $portfolioProjectModel,
                'dependencyModel'       => $dependencyModel,
            ]);

            $this->assertFalse($helper->isTaskBlocked(99, 5));
            // dependency model should not be queried when project has no portfolios
            $this->assertSame(0, $dependencyModel->getBlockedTasksCalls);
        }

        public function testPortfolioHelperGetAllPortfoliosDelegatesToModel(): void
        {
            $portfolioModel = new WidgetFakePortfolioModel([
                ['id' => 1, 'name' => 'PF1'],
                ['id' => 2, 'name' => 'PF2'],
            ]);

            $helper = new PortfolioHelper([
                'portfolioModel'       => $portfolioModel,
                'portfolioProjectModel' => new WidgetFakePortfolioProjectModel(),
                'dependencyModel'      => new WidgetFakeDependencyModel(),
            ]);

            $result = $helper->getAllPortfolios();

            $this->assertCount(2, $result);
            $this->assertSame('PF1', $result[0]['name']);
        }

        public function testPortfolioHelperGetGlobalAtRiskMilestonesFiltersCorrectly(): void
        {
            $portfolioModel = new WidgetFakePortfolioModel([
                ['id' => 10, 'name' => 'Portfolio X'],
            ]);
            $milestoneModel = new WidgetFakeMilestoneModel(
                milestones: [
                    ['id' => 100, 'name' => 'Risky MS'],
                    ['id' => 101, 'name' => 'Safe MS'],
                ],
                progressMap: [
                    100 => ['percent' => 20, 'is_at_risk' => true,  'is_overdue' => false],
                    101 => ['percent' => 80, 'is_at_risk' => false, 'is_overdue' => false],
                ]
            );

            $helper = new PortfolioHelper([
                'portfolioModel'        => $portfolioModel,
                'milestoneModel'        => $milestoneModel,
                'portfolioProjectModel' => new WidgetFakePortfolioProjectModel(),
                'dependencyModel'       => new WidgetFakeDependencyModel(),
            ]);

            $result = $helper->getGlobalAtRiskMilestones();

            $this->assertCount(1, $result);
            $this->assertSame('Risky MS', $result[0]['name']);
            $this->assertSame('Portfolio X', $result[0]['portfolio_name']);
        }
    }
}
