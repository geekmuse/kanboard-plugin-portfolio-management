<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio;

if (! function_exists(__NAMESPACE__ . '\\t')) {
    function t(string $message, mixed ...$args): string
    {
        if ($args === []) {
            return $message;
        }

        return vsprintf($message, $args);
    }
}

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

        /** @var array<string, array<int, callable>> */
        public array $registeredEvents = [];

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

        public function on(string $eventName, callable $callback): void
        {
            if (! array_key_exists($eventName, $this->registeredEvents)) {
                $this->registeredEvents[$eventName] = [];
            }

            $this->registeredEvents[$eventName][] = $callback;
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

final class FakeTemplateHook
{
    /**
     * @var array<int, array{hook: string, template: string, callable: bool}>
     */
    private array $attachments = [];

    public function attach(string $hook, string $template): void
    {
        $this->attachments[] = ['hook' => $hook, 'template' => $template, 'callable' => false];
    }

    /** @param callable $callable */
    public function attachCallable(string $hook, string $template, $callable): void
    {
        $this->attachments[] = ['hook' => $hook, 'template' => $template, 'callable' => true];
    }

    /**
     * @return array<int, array{hook: string, template: string, callable: bool}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}

final class FakeTemplateHookRegistry
{
    public FakeTemplateHook $hook;

    public function __construct()
    {
        $this->hook = new FakeTemplateHook();
    }
}

final class FakeHook
{
    /**
     * @var array<int, array{event: string, params: mixed}>
     */
    private array $events = [];

    /** @param mixed $params */
    public function on(string $event, $params): void
    {
        $this->events[] = ['event' => $event, 'params' => $params];
    }

    /**
     * @return array<int, array{event: string, params: mixed}>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}

final class FakeEventManager
{
    /**
     * @var array<int, array{event_name: string, label: string}>
     */
    public array $registered = [];

    public function register(string $eventName, string $label): void
    {
        $this->registered[] = [
            'event_name' => $eventName,
            'label' => $label,
        ];
    }
}

final class FakeActionManager
{
    /**
     * @var array<int, object>
     */
    public array $actions = [];

    public function register(object $action): void
    {
        $this->actions[] = $action;
    }
}

final class FakeUserNotificationTypeModel
{
    /**
     * @var array<int, array{type: string, label: string, template: string}>
     */
    public array $types = [];

    public function setType(string $type, string $label, string $template): void
    {
        $this->types[] = [
            'type' => $type,
            'label' => $label,
            'template' => $template,
        ];
    }
}

final class FakeDependencyModel
{
    /** @var array<int, int> */
    public array $closedCalls = [];

    /** @var array<int, int> */
    public array $openedCalls = [];

    /** @var array<int, int> */
    public array $linkChangedCalls = [];

    public function onTaskClosed(int $taskId): void
    {
        $this->closedCalls[] = $taskId;
    }

    public function onTaskOpened(int $taskId): void
    {
        $this->openedCalls[] = $taskId;
    }

    public function onLinkChanged(int $taskId): void
    {
        $this->linkChangedCalls[] = $taskId;
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

    private FakeTemplateHookRegistry $templateHookRegistry;

    private FakeEventManager $eventManager;

    private FakeActionManager $actionManager;

    private FakeUserNotificationTypeModel $userNotificationTypeModel;

    private FakeHook $hookRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->procedureHandler          = new FakeProcedureHandler();
        $this->apiAccessMap              = new FakeApiAccessMap();
        $this->route                     = new FakeRoute();
        $this->applicationAccessMap      = new FakeApplicationAccessMap();
        $this->templateHookRegistry      = new FakeTemplateHookRegistry();
        $this->eventManager              = new FakeEventManager();
        $this->actionManager             = new FakeActionManager();
        $this->userNotificationTypeModel = new FakeUserNotificationTypeModel();
        $this->hookRegistry              = new FakeHook();

        $this->plugin = new Plugin();
        $this->plugin->container = [
            'template' => $this->templateHookRegistry,
            'eventManager' => $this->eventManager,
            'actionManager' => $this->actionManager,
            'userNotificationTypeModel' => $this->userNotificationTypeModel,
        ];
        $this->plugin->api = new FakeApi($this->procedureHandler);
        $this->plugin->apiAccessMap = $this->apiAccessMap;
        $this->plugin->route = $this->route;
        $this->plugin->applicationAccessMap = $this->applicationAccessMap;
        $this->plugin->hook = $this->hookRegistry;

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
                    'path' => '/portfolio/config',
                    'controller' => 'ConfigController',
                    'action' => 'show',
                    'plugin' => 'Portfolio',
                ],
                [
                    'path' => '/portfolio/config/save',
                    'controller' => 'ConfigController',
                    'action' => 'save',
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
            array_slice($this->route->getRoutes(), 0, 16)
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
                    'controller' => 'ConfigController',
                    'methods' => '*',
                    'role' => Role::APP_MANAGER,
                ],
                [
                    'controller' => 'PortfolioModificationController',
                    'methods' => '*',
                    'role' => Role::APP_MANAGER,
                ],
            ],
            array_slice($this->applicationAccessMap->getEntries(), 0, 7)
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

    public function testRegistersDependencyLifecycleListenersAndDelegatesToDependencyModel(): void
    {
        $dependencyModel = new FakeDependencyModel();
        $this->plugin->container['dependencyModel'] = $dependencyModel;

        $registeredEvents = $this->plugin->registeredEvents;

        $this->assertArrayHasKey('task.close', $registeredEvents);
        $this->assertArrayHasKey('task.open', $registeredEvents);
        $this->assertArrayHasKey('task_internal_link.create_update', $registeredEvents);
        $this->assertArrayHasKey('task_internal_link.delete', $registeredEvents);

        $registeredEvents['task.close'][0](['task_id' => 17]);
        $registeredEvents['task.open'][0](['task_id' => 29]);
        $registeredEvents['task_internal_link.create_update'][0](['task_id' => 41]);
        $registeredEvents['task_internal_link.delete'][0]([]);

        $this->assertSame([17], $dependencyModel->closedCalls);
        $this->assertSame([29], $dependencyModel->openedCalls);
        $this->assertSame([41, 0], $dependencyModel->linkChangedCalls);
    }

    public function testRegistersCustomEventAutomaticActionsAndNotificationType(): void
    {
        $this->assertSame(
            [[
                'event_name' => 'portfolio.dependency.resolved',
                'label' => 'Cross-project dependency resolved',
            ]],
            $this->eventManager->registered
        );

        $registeredActions = array_map(
            static fn (object $action): string => get_class($action),
            $this->actionManager->actions
        );

        $this->assertContains('Kanboard\\Plugin\\Portfolio\\Action\\NotifyDependencyResolved', $registeredActions);
        $this->assertContains('Kanboard\\Plugin\\Portfolio\\Action\\CommentDependencyResolved', $registeredActions);

        $this->assertSame(
            [[
                'type' => 'dependency_resolved',
                'label' => 'Cross-project dependency resolved',
                'template' => 'Portfolio:notification/dependency_resolved',
            ]],
            $this->userNotificationTypeModel->types
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

    public function testRegistersAllRequiredTemplateHooks(): void
    {
        $attachments = $this->templateHookRegistry->hook->getAttachments();
        $hookNames   = array_column($attachments, 'hook');

        // Layout assets are registered via hook->on(), not template->hook->attach()
        // — verified separately in testRegistersAssetHooks().

        // Dashboard widget
        $this->assertContains('template:dashboard:show:before-task-list', $hookNames);

        // Board card blocked indicator
        $this->assertContains('template:board:task:footer', $hookNames);

        // Task detail sidebar (milestone + dependency; hook registered twice)
        $taskDetailHooks = array_values(array_filter(
            $hookNames,
            static fn (string $h): bool => $h === 'template:task:details:second-column'
        ));
        $this->assertCount(2, $taskDetailHooks);

        // Project sidebar
        $this->assertContains('template:project:sidebar', $hookNames);

        // Header dropdown
        $this->assertContains('template:header:dropdown:menu', $hookNames);

        // Config sidebar
        $this->assertContains('template:config:sidebar', $hookNames);
    }

    public function testRegistersAssetHooks(): void
    {
        $events      = $this->hookRegistry->getEvents();
        $eventNames  = array_column($events, 'event');
        $templates   = array_column(array_column($events, 'params'), 'template');

        // CSS asset
        $this->assertContains('template:layout:css', $eventNames);
        $this->assertContains('plugins/Portfolio/Asset/css/portfolio.css', $templates);

        // JS assets
        $jsEvents = array_filter($events, static fn ($e) => $e['event'] === 'template:layout:js');
        $jsTemplates = array_column(array_column(array_values($jsEvents), 'params'), 'template');
        $this->assertContains('plugins/Portfolio/Asset/js/portfolio-graph.js', $jsTemplates);
        $this->assertContains('plugins/Portfolio/Asset/js/portfolio-gantt.js', $jsTemplates);
    }

    public function testTemplateHooksPointToCorrectWidgetTemplates(): void
    {
        $attachments    = $this->templateHookRegistry->hook->getAttachments();
        $templateByHook = [];

        foreach ($attachments as $entry) {
            $templateByHook[$entry['hook']][] = $entry['template'];
        }

        $this->assertSame(
            ['Portfolio:widget/dashboard_portfolios'],
            $templateByHook['template:dashboard:show:before-task-list']
        );
        $this->assertSame(['Portfolio:widget/board_blocked_indicator'], $templateByHook['template:board:task:footer']);
        $this->assertSame(['Portfolio:widget/project_sidebar'], $templateByHook['template:project:sidebar']);
        $this->assertSame(['Portfolio:widget/header_dropdown'], $templateByHook['template:header:dropdown:menu']);
        $this->assertSame(['Portfolio:widget/config_sidebar'], $templateByHook['template:config:sidebar']);

        // Two task-detail hooks for milestone info and dependency snippet
        $this->assertContains('Portfolio:widget/task_milestone_info', $templateByHook['template:task:details:second-column']);
        $this->assertContains('Portfolio:widget/task_dependency_snippet', $templateByHook['template:task:details:second-column']);
    }
}
