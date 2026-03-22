<?php

declare(strict_types=1);

namespace Kanboard\Core\Plugin;

if (! class_exists(__NAMESPACE__ . '\\Base')) {
    class Base
    {
        /** @var array<string, mixed> */
        public array $container = [];

        /** @var mixed */
        public $api;

        /** @var mixed */
        public $apiAccessMap;

        /** @var mixed */
        public $route;

        /** @var mixed */
        public $applicationAccessMap;

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

namespace Kanboard\Core\Security;

if (! class_exists(__NAMESPACE__ . '\\Role')) {
    class Role
    {
        public const APP_USER = 'app-user';

        public const APP_MANAGER = 'app-manager';
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Plugin;

use Kanboard\Core\Security\Role;
use Kanboard\Plugin\Portfolio\Plugin;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Plugin.php';

final class FakeProcedureHandler
{
    /**
     * @var array<string, callable>
     */
    private array $callbacks = [];

    public function withCallback(string $name, callable $callback): self
    {
        $this->callbacks[$name] = $callback;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getMethodNames(): array
    {
        return array_keys($this->callbacks);
    }

    public function getCallback(string $name): callable
    {
        return $this->callbacks[$name];
    }
}

final class FakeApi
{
    public function __construct(private FakeProcedureHandler $procedureHandler)
    {
    }

    public function getProcedureHandler(): FakeProcedureHandler
    {
        return $this->procedureHandler;
    }
}

final class FakeApiAccessMap
{
    /**
     * @var array<int, array{procedure: string, methods: array<int, string>, role: mixed}>
     */
    private array $entries = [];

    /**
     * @param array<int, string> $methods
     *
     * @param mixed $role
     */
    public function add(string $procedure, array $methods, $role): void
    {
        $this->entries[] = [
            'procedure' => $procedure,
            'methods' => $methods,
            'role' => $role,
        ];
    }

    /**
     * @return array<int, array{procedure: string, methods: array<int, string>, role: mixed}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

final class FakeRoute
{
    /**
     * @var array<int, array{path: string, controller: string, action: string, plugin: string}>
     */
    private array $routes = [];

    public function addRoute(string $path, string $controller, string $action, string $plugin): void
    {
        $this->routes[] = [
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
            'plugin' => $plugin,
        ];
    }

    /**
     * @return array<int, array{path: string, controller: string, action: string, plugin: string}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

final class FakeApplicationAccessMap
{
    /**
     * @var array<int, array{controller: string, methods: mixed, role: mixed}>
     */
    private array $entries = [];

    /** @param mixed $methods */
    public function add(string $controller, $methods, $role): void
    {
        $this->entries[] = [
            'controller' => $controller,
            'methods' => $methods,
            'role' => $role,
        ];
    }

    /**
     * @return array<int, array{controller: string, methods: mixed, role: mixed}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

final class FakePortfolioModel
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $createCalls = [];

    /**
     * @var array<int, int>
     */
    public array $getByIdCalls = [];

    /**
     * @var array<int, array{0: int, 1: array<string, mixed>}>
     */
    public array $updateCalls = [];

    /** @param array<string, mixed> $values */
    public function create(array $values): int
    {
        $this->createCalls[] = $values;

        return 101;
    }

    /** @return array<string, mixed> */
    public function getById(int $id): array
    {
        $this->getByIdCalls[] = $id;

        return ['id' => $id, 'name' => 'Portfolio'];
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): bool
    {
        $this->updateCalls[] = [$id, $values];

        return true;
    }
}

final class ApiRegistrationTest extends TestCase
{
    private Plugin $plugin;

    private FakeProcedureHandler $procedureHandler;

    private FakeApiAccessMap $apiAccessMap;

    private FakeRoute $route;

    private FakeApplicationAccessMap $applicationAccessMap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureHandler = new FakeProcedureHandler();
        $this->apiAccessMap = new FakeApiAccessMap();
        $this->route = new FakeRoute();
        $this->applicationAccessMap = new FakeApplicationAccessMap();

        $this->plugin = new Plugin();
        $this->plugin->container = [];
        $this->plugin->api = new FakeApi($this->procedureHandler);
        $this->plugin->apiAccessMap = $this->apiAccessMap;
        $this->plugin->route = $this->route;
        $this->plugin->applicationAccessMap = $this->applicationAccessMap;

        $this->plugin->initialize();
    }

    public function testRegistersAllTwentyEightJsonRpcMethods(): void
    {
        $expected = [
            'createPortfolio',
            'getPortfolio',
            'getPortfolioByName',
            'getAllPortfolios',
            'updatePortfolio',
            'removePortfolio',
            'addProjectToPortfolio',
            'removeProjectFromPortfolio',
            'getPortfolioProjects',
            'getProjectPortfolios',
            'createMilestone',
            'getMilestone',
            'getPortfolioMilestones',
            'updateMilestone',
            'removeMilestone',
            'addTaskToMilestone',
            'removeTaskFromMilestone',
            'getMilestoneTasks',
            'getTaskMilestones',
            'getMilestoneProgress',
            'getPortfolioDependencies',
            'getBlockedTasks',
            'getBlockingTasks',
            'getPortfolioCriticalPath',
            'getPortfolioDependencyGraph',
            'getPortfolioTasks',
            'getPortfolioTaskCount',
            'getPortfolioOverview',
        ];

        $this->assertSame($expected, $this->procedureHandler->getMethodNames());
    }

    public function testRegistersPortfolioCrudRoutes(): void
    {
        $this->assertSame(
            [
                [
                    'path' => '/portfolios',
                    'controller' => 'PortfolioListController',
                    'action' => 'index',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/create',
                    'controller' => 'PortfolioModificationController',
                    'action' => 'create',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id',
                    'controller' => 'PortfolioViewController',
                    'action' => 'show',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/tasks',
                    'controller' => 'PortfolioViewController',
                    'action' => 'tasks',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/board',
                    'controller' => 'PortfolioViewController',
                    'action' => 'board',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/timeline',
                    'controller' => 'PortfolioViewController',
                    'action' => 'timeline',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/milestones',
                    'controller' => 'MilestoneController',
                    'action' => 'index',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/settings',
                    'controller' => 'PortfolioModificationController',
                    'action' => 'settings',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/edit',
                    'controller' => 'PortfolioModificationController',
                    'action' => 'edit',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/:portfolio_id/remove',
                    'controller' => 'PortfolioModificationController',
                    'action' => 'remove',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/milestone/create/:portfolio_id',
                    'controller' => 'MilestoneController',
                    'action' => 'create',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/milestone/:milestone_id',
                    'controller' => 'MilestoneController',
                    'action' => 'show',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/milestone/:milestone_id/edit',
                    'controller' => 'MilestoneController',
                    'action' => 'edit',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/milestone/:milestone_id/remove',
                    'controller' => 'MilestoneController',
                    'action' => 'remove',
                    'plugin' => 'Portfolio',
                ],
            ],
            array_slice($this->route->getRoutes(), 0, 14)
        );
    }

    public function testPortfolioAndMilestoneRoutesApplyExpectedRoleRules(): void
    {
        $this->assertSame(
            [
                [
                    'controller' => 'PortfolioListController',
                    'methods' => '*',
                    'role' => Role::APP_USER,
                ],
                [
                    'controller' => 'PortfolioViewController',
                    'methods' => '*',
                    'role' => Role::APP_USER,
                ],
                [
                    'controller' => 'MilestoneController',
                    'methods' => ['index', 'show'],
                    'role' => Role::APP_USER,
                ],
                [
                    'controller' => 'MilestoneController',
                    'methods' => ['create', 'save', 'edit', 'update', 'remove', 'delete', 'addTask', 'removeTask'],
                    'role' => Role::APP_MANAGER,
                ],
                [
                    'controller' => 'DependencyController',
                    'methods' => '*',
                    'role' => Role::APP_USER,
                ],
                [
                    'controller' => 'PortfolioModificationController',
                    'methods' => '*',
                    'role' => Role::APP_MANAGER,
                ],
            ],
            array_slice($this->applicationAccessMap->getEntries(), 0, 6)
        );
    }

    public function testRegistersDependencyControllerRoutes(): void
    {
        $allRoutes = $this->route->getRoutes();
        $routesByPath = [];
        foreach ($allRoutes as $route) {
            $routesByPath[$route['path']] = $route;
        }

        $this->assertArrayHasKey('/portfolio/:portfolio_id/dependencies', $routesByPath);
        $this->assertSame('DependencyController', $routesByPath['/portfolio/:portfolio_id/dependencies']['controller']);
        $this->assertSame('graph', $routesByPath['/portfolio/:portfolio_id/dependencies']['action']);

        $this->assertArrayHasKey('/portfolio/:portfolio_id/dependencies/data', $routesByPath);
        $this->assertSame('DependencyController', $routesByPath['/portfolio/:portfolio_id/dependencies/data']['controller']);
        $this->assertSame('graphData', $routesByPath['/portfolio/:portfolio_id/dependencies/data']['action']);

        $this->assertArrayHasKey('/portfolio/:portfolio_id/dependencies/blocked', $routesByPath);
        $this->assertSame('DependencyController', $routesByPath['/portfolio/:portfolio_id/dependencies/blocked']['controller']);
        $this->assertSame('blocked', $routesByPath['/portfolio/:portfolio_id/dependencies/blocked']['action']);

        $this->assertArrayHasKey('/portfolio/:portfolio_id/dependencies/critical-path', $routesByPath);
        $this->assertSame('DependencyController', $routesByPath['/portfolio/:portfolio_id/dependencies/critical-path']['controller']);
        $this->assertSame('criticalPath', $routesByPath['/portfolio/:portfolio_id/dependencies/critical-path']['action']);
    }

    public function testDependencyControllerIsAccessibleByAppUser(): void
    {
        $entries = $this->applicationAccessMap->getEntries();
        $dependencyEntry = null;
        foreach ($entries as $entry) {
            if ($entry['controller'] === 'DependencyController') {
                $dependencyEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($dependencyEntry, 'DependencyController must be registered in the application access map');
        $this->assertSame('*', $dependencyEntry['methods']);
        $this->assertSame(Role::APP_USER, $dependencyEntry['role']);
    }

    public function testWriteApiMethodsAreProtectedByManagerRole(): void
    {
        $this->assertSame(
            [
                [
                    'procedure' => 'PortfolioProcedure',
                    'methods' => ['createPortfolio', 'updatePortfolio', 'removePortfolio'],
                    'role' => Role::APP_MANAGER,
                ],
                [
                    'procedure' => 'PortfolioProcedure',
                    'methods' => ['addProjectToPortfolio', 'removeProjectFromPortfolio'],
                    'role' => Role::APP_MANAGER,
                ],
                [
                    'procedure' => 'MilestoneProcedure',
                    'methods' => ['createMilestone', 'updateMilestone', 'removeMilestone'],
                    'role' => Role::APP_MANAGER,
                ],
                [
                    'procedure' => 'MilestoneProcedure',
                    'methods' => ['addTaskToMilestone', 'removeTaskFromMilestone'],
                    'role' => Role::APP_MANAGER,
                ],
            ],
            $this->apiAccessMap->getEntries()
        );
    }

    public function testRepresentativeReadAndWriteCallbacksDelegateToModels(): void
    {
        $portfolioModel = new FakePortfolioModel();
        $this->plugin->container['portfolioModel'] = $portfolioModel;

        $createPortfolio = $this->procedureHandler->getCallback('createPortfolio');
        $result = $createPortfolio('Q2 Launch', 'Cross-project launch work', 7);

        $this->assertSame(101, $result);
        $this->assertSame(
            [[
                'name' => 'Q2 Launch',
                'description' => 'Cross-project launch work',
                'owner_id' => 7,
            ]],
            $portfolioModel->createCalls
        );

        $getPortfolio = $this->procedureHandler->getCallback('getPortfolio');
        $portfolio = $getPortfolio(42);

        $this->assertSame(['id' => 42, 'name' => 'Portfolio'], $portfolio);
        $this->assertSame([42], $portfolioModel->getByIdCalls);

        $updatePortfolio = $this->procedureHandler->getCallback('updatePortfolio');
        $updatePortfolio(42, null, 'Updated description', null, 0);

        $this->assertSame(
            [[
                42,
                [
                    'description' => 'Updated description',
                    'is_active' => 0,
                ],
            ]],
            $portfolioModel->updateCalls
        );
    }
}
