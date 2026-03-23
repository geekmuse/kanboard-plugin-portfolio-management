<?php

namespace Kanboard\Plugin\Portfolio;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;
use Kanboard\Plugin\Portfolio\Action\CommentDependencyResolved;
use Kanboard\Plugin\Portfolio\Action\NotifyDependencyResolved;
use Kanboard\Plugin\Portfolio\Filter\TaskPortfolioFilter;
use Kanboard\Plugin\Portfolio\Notification\DependencyResolvedType;

class Plugin extends Base
{
    public function initialize()
    {
        $this->container['portfolioModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioModel($c);
        };

        $this->container['portfolioProjectModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioProjectModel($c);
        };

        $this->container['milestoneModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\MilestoneModel($c);
        };

        $this->container['milestoneTaskModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\MilestoneTaskModel($c);
        };

        $this->container['dependencyModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\DependencyModel($c);
        };

        $this->container['portfolioTaskModel'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Model\PortfolioTaskModel($c);
        };

        $this->container['portfolioHelper'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Helper\PortfolioHelper($c);
        };

        $this->container['portfolioValidator'] = function ($c) {
            return new \Kanboard\Plugin\Portfolio\Validator\PortfolioValidator($c);
        };

        $this->route->addRoute('/portfolios', 'PortfolioListController', 'index', 'Portfolio');
        $this->route->addRoute('/portfolio/create', 'PortfolioModificationController', 'create', 'Portfolio');
        $this->route->addRoute('/portfolio/config', 'ConfigController', 'show', 'Portfolio');
        $this->route->addRoute('/portfolio/config/save', 'ConfigController', 'save', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id', 'PortfolioViewController', 'show', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/tasks', 'PortfolioViewController', 'tasks', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/board', 'PortfolioViewController', 'board', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/board/move-task', 'PortfolioViewController', 'moveTask', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/timeline', 'PortfolioViewController', 'timeline', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/gantt', 'PortfolioViewController', 'gantt', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/milestones', 'MilestoneController', 'index', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/settings', 'PortfolioModificationController', 'settings', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/edit', 'PortfolioModificationController', 'edit', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/remove', 'PortfolioModificationController', 'remove', 'Portfolio');
        $this->route->addRoute('/milestone/create/:portfolio_id', 'MilestoneController', 'create', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id', 'MilestoneController', 'show', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id/edit', 'MilestoneController', 'edit', 'Portfolio');
        $this->route->addRoute('/milestone/:milestone_id/remove', 'MilestoneController', 'remove', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/dependencies', 'DependencyController', 'graph', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/dependencies/data', 'DependencyController', 'graphData', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/dependencies/blocked', 'DependencyController', 'blocked', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/dependencies/critical-path', 'DependencyController', 'criticalPath', 'Portfolio');

        $this->applicationAccessMap->add('PortfolioListController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('PortfolioViewController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('MilestoneController', ['index', 'show'], Role::APP_USER);
        $this->applicationAccessMap->add(
            'MilestoneController',
            ['create', 'save', 'edit', 'update', 'remove', 'delete', 'addTask', 'removeTask'],
            Role::APP_MANAGER
        );
        $this->applicationAccessMap->add('DependencyController', '*', Role::APP_USER);
        $this->applicationAccessMap->add('ConfigController', '*', Role::APP_MANAGER);
        $this->applicationAccessMap->add('PortfolioModificationController', '*', Role::APP_MANAGER);

        // -----------------------------------------------------------------
        // Template hooks — integrate portfolio data into Kanboard pages
        // -----------------------------------------------------------------

        // CSS/JS assets — use hook->on() with real file paths so Kanboard's
        // AssetHelper can call filemtime() for cache-busting correctly.
        $this->hook->on('template:layout:css', ['template' => 'plugins/Portfolio/Asset/css/portfolio.css']);

        // Dashboard — portfolio links + at-risk milestone summary
        // attachCallable pre-fetches data so templates don't need to access
        // plugin-registered container entries via $this->xxx (unsupported in
        // Kanboard's template context for non-core services).
        $this->template->hook->attachCallable(
            'template:dashboard:show:before-task-list',
            'Portfolio:widget/dashboard_portfolios',
            function () {
                $enabled = (int) $this->container['configModel']->get('portfolio_dashboard_widget_enabled', 1) === 1;
                return [
                    'widgetEnabled'    => $enabled,
                    'portfolios'       => $enabled ? $this->container['portfolioHelper']->getAllPortfolios() : [],
                    'atRiskMilestones' => $enabled ? $this->container['portfolioHelper']->getGlobalAtRiskMilestones() : [],
                ];
            }
        );

        // Board card — blocked indicator (uses per-project lazy cache in PortfolioHelper)
        $this->template->hook->attachCallable(
            'template:board:task:footer',
            'Portfolio:widget/board_blocked_indicator',
            function (array $params) {
                $task      = $params['task'] ?? $params;
                $taskId    = (int) ($task['id'] ?? 0);
                $projectId = (int) ($task['project_id'] ?? 0);
                $enabled   = (int) $this->container['configModel']->get('portfolio_board_show_blockers', 1) === 1;
                return [
                    'isBlocked' => $enabled && $taskId > 0 && $projectId > 0
                        && $this->container['portfolioHelper']->isTaskBlocked($taskId, $projectId),
                ];
            }
        );

        // Task detail sidebar — milestone membership
        $this->template->hook->attachCallable(
            'template:task:details:second-column',
            'Portfolio:widget/task_milestone_info',
            function (array $params) {
                $task    = $params['task'] ?? $params;
                $taskId  = (int) ($task['id'] ?? 0);
                return [
                    'milestones' => $taskId > 0
                        ? $this->container['milestoneTaskModel']->getMilestones($taskId)
                        : [],
                ];
            }
        );

        // Task detail sidebar — cross-project dependency snippet
        $this->template->hook->attachCallable(
            'template:task:details:second-column',
            'Portfolio:widget/task_dependency_snippet',
            function (array $params) {
                $task      = $params['task'] ?? $params;
                $taskId    = (int) ($task['id'] ?? 0);
                $projectId = (int) ($task['project_id'] ?? 0);
                return [
                    'isBlocked'  => $taskId > 0 && $projectId > 0
                        && $this->container['portfolioHelper']->isTaskBlocked($taskId, $projectId),
                    'portfolios' => $projectId > 0
                        ? $this->container['portfolioProjectModel']->getPortfolios($projectId)
                        : [],
                ];
            }
        );

        // Project sidebar — portfolio membership for the project
        $this->template->hook->attachCallable(
            'template:project:sidebar',
            'Portfolio:widget/project_sidebar',
            function (array $params) {
                $project   = $params['project'] ?? $params;
                $projectId = (int) ($project['id'] ?? 0);
                return [
                    'portfolios' => $projectId > 0
                        ? $this->container['portfolioProjectModel']->getPortfolios($projectId)
                        : [],
                ];
            }
        );

        // Header dropdown — quick links to portfolio list and create form
        $this->template->hook->attach('template:header:dropdown:menu', 'Portfolio:widget/header_dropdown');

        // Config sidebar — portfolio management link
        $this->template->hook->attach('template:config:sidebar', 'Portfolio:widget/config_sidebar');

        // Task search filter: portfolio:<id|name>
        $this->registerTaskSearchFilter();

        // Task/dependency lifecycle events
        $this->on('task.close', function ($event) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $this->dependencyModel->onTaskClosed($taskId);
        });

        $this->on('task.open', function ($event) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $this->dependencyModel->onTaskOpened($taskId);
        });

        $this->on('task_internal_link.create_update', function ($event) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $this->dependencyModel->onLinkChanged($taskId);
        });

        $this->on('task_internal_link.delete', function ($event) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $this->dependencyModel->onLinkChanged($taskId);
        });

        $this->eventManager->register(DependencyResolvedType::EVENT_NAME, DependencyResolvedType::getLabel());
        $this->actionManager->register(new NotifyDependencyResolved($this->container));
        $this->actionManager->register(new CommentDependencyResolved($this->container));
        $this->userNotificationTypeModel->setType(
            DependencyResolvedType::getType(),
            DependencyResolvedType::getLabel(),
            DependencyResolvedType::getTemplate()
        );

        $this->api->getProcedureHandler()
            ->withCallback('createPortfolio', function ($name, $description = '', $owner_id = 0) {
                return $this->portfolioModel->create(compact('name', 'description', 'owner_id'));
            })
            ->withCallback('getPortfolio', function ($portfolio_id) {
                return $this->portfolioModel->getById((int) $portfolio_id);
            })
            ->withCallback('getPortfolioByName', function ($name) {
                return $this->portfolioModel->getByName((string) $name);
            })
            ->withCallback('getAllPortfolios', function () {
                return $this->portfolioModel->getAll();
            })
            ->withCallback('updatePortfolio', function ($portfolio_id, $name = null, $description = null, $owner_id = null, $is_active = null) {
                $values = array_filter(
                    compact('name', 'description', 'owner_id', 'is_active'),
                    static fn ($value): bool => $value !== null
                );

                return $this->portfolioModel->update((int) $portfolio_id, $values);
            })
            ->withCallback('removePortfolio', function ($portfolio_id) {
                return $this->portfolioModel->remove((int) $portfolio_id);
            })
            ->withCallback('addProjectToPortfolio', function ($portfolio_id, $project_id, $position = 0) {
                return $this->portfolioProjectModel->add((int) $portfolio_id, (int) $project_id, (int) $position);
            })
            ->withCallback('removeProjectFromPortfolio', function ($portfolio_id, $project_id) {
                return $this->portfolioProjectModel->remove((int) $portfolio_id, (int) $project_id);
            })
            ->withCallback('getPortfolioProjects', function ($portfolio_id) {
                return $this->portfolioProjectModel->getProjects((int) $portfolio_id);
            })
            ->withCallback('getProjectPortfolios', function ($project_id) {
                return $this->portfolioProjectModel->getPortfolios((int) $project_id);
            })
            ->withCallback('createMilestone', function ($portfolio_id, $name, $description = '', $target_date = null, $color_id = 'blue', $owner_id = 0) {
                return $this->milestoneModel->create(compact('portfolio_id', 'name', 'description', 'target_date', 'color_id', 'owner_id'));
            })
            ->withCallback('getMilestone', function ($milestone_id) {
                return $this->milestoneModel->getById((int) $milestone_id);
            })
            ->withCallback('getPortfolioMilestones', function ($portfolio_id) {
                return $this->milestoneModel->getByPortfolioId((int) $portfolio_id);
            })
            ->withCallback('updateMilestone', function ($milestone_id, $name = null, $description = null, $target_date = null, $color_id = null, $owner_id = null, $status = null) {
                return $this->milestoneModel->update(
                    (int) $milestone_id,
                    compact('name', 'description', 'target_date', 'color_id', 'owner_id', 'status')
                );
            })
            ->withCallback('removeMilestone', function ($milestone_id) {
                return $this->milestoneModel->remove((int) $milestone_id);
            })
            ->withCallback('addTaskToMilestone', function ($milestone_id, $task_id, $is_critical = 0, $position = 0) {
                return $this->milestoneTaskModel->add((int) $milestone_id, (int) $task_id, (int) $is_critical, (int) $position);
            })
            ->withCallback('removeTaskFromMilestone', function ($milestone_id, $task_id) {
                return $this->milestoneTaskModel->remove((int) $milestone_id, (int) $task_id);
            })
            ->withCallback('getMilestoneTasks', function ($milestone_id) {
                return $this->milestoneTaskModel->getTasks((int) $milestone_id);
            })
            ->withCallback('getTaskMilestones', function ($task_id) {
                return $this->milestoneTaskModel->getMilestones((int) $task_id);
            })
            ->withCallback('getMilestoneProgress', function ($milestone_id) {
                return $this->milestoneModel->getProgress((int) $milestone_id);
            })
            ->withCallback('getPortfolioDependencies', function ($portfolio_id, $cross_project_only = true) {
                return $this->dependencyModel->getDependencies((int) $portfolio_id, (bool) $cross_project_only);
            })
            ->withCallback('getBlockedTasks', function ($portfolio_id) {
                return $this->dependencyModel->getBlockedTasks((int) $portfolio_id);
            })
            ->withCallback('getBlockingTasks', function ($portfolio_id) {
                return $this->dependencyModel->getBlockingTasks((int) $portfolio_id);
            })
            ->withCallback('getPortfolioCriticalPath', function ($portfolio_id) {
                return $this->dependencyModel->getCriticalPath((int) $portfolio_id);
            })
            ->withCallback('getPortfolioDependencyGraph', function ($portfolio_id, $cross_project_only = true) {
                return $this->dependencyModel->getGraph((int) $portfolio_id, (bool) $cross_project_only);
            })
            ->withCallback('getPortfolioTasks', function (
                $portfolio_id,
                $status_id = null,
                $assignee_id = null,
                $project_id = null,
                $milestone_id = null,
                $has_dependencies = null,
                $sort = 'priority',
                $direction = 'DESC',
                $limit = 50,
                $offset = 0
            ) {
                return $this->portfolioTaskModel->getTasks(
                    (int) $portfolio_id,
                    compact('status_id', 'assignee_id', 'project_id', 'milestone_id', 'has_dependencies', 'sort', 'direction', 'limit', 'offset')
                );
            })
            ->withCallback('getPortfolioTaskCount', function ($portfolio_id, $status_id = null) {
                $resolvedStatusId = $status_id === null ? null : (int) $status_id;

                return $this->portfolioTaskModel->getCounts((int) $portfolio_id, $resolvedStatusId);
            })
            ->withCallback('getPortfolioOverview', function ($portfolio_id) {
                return $this->portfolioTaskModel->getOverview((int) $portfolio_id);
            });

        $this->apiAccessMap->add('PortfolioProcedure', ['createPortfolio', 'updatePortfolio', 'removePortfolio'], Role::APP_MANAGER);
        $this->apiAccessMap->add('PortfolioProcedure', ['addProjectToPortfolio', 'removeProjectFromPortfolio'], Role::APP_MANAGER);
        $this->apiAccessMap->add('MilestoneProcedure', ['createMilestone', 'updateMilestone', 'removeMilestone'], Role::APP_MANAGER);
        $this->apiAccessMap->add('MilestoneProcedure', ['addTaskToMilestone', 'removeTaskFromMilestone'], Role::APP_MANAGER);
    }

    private function registerTaskSearchFilter(): void
    {
        /** @var mixed $container */
        $container = $this->container;

        if (is_object($container) && method_exists($container, 'extend')) {
            $container->extend('taskLexer', static function ($taskLexer, $c) {
                if (is_object($taskLexer) && method_exists($taskLexer, 'withFilter')) {
                    $filter = new TaskPortfolioFilter();
                    $filter->setContainer($c);
                    $taskLexer->withFilter($filter);
                }

                return $taskLexer;
            });
        }
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__ . '/Locale');
    }

    public function getPluginName()
    {
        return '1.17.0';
    }

    public function getPluginDescription()
    {
        return t('Cross-project portfolio management: portfolios, milestones, dependency visualization');
    }

    public function getPluginAuthor()
    {
        return 'Geekmuse';
    }

    public function getPluginVersion()
    {
        return '1.17.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/geekmuse/kanboard-plugin-portfolio-management';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }
}
