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
        $this->route->addRoute('/portfolio/:portfolio_id/roadmap', 'PortfolioViewController', 'roadmap', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/gantt', 'PortfolioViewController', 'gantt', 'Portfolio');
        $this->route->addRoute('/portfolio/:portfolio_id/workload', 'PortfolioViewController', 'workload', 'Portfolio');
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

        // Task detail — portfolio context banner (top of page, above task body).
        // Shows "This task is in Portfolio: {name}" with links to portfolio dashboards.
        // Renders nothing when the task's project belongs to no portfolios.
        $this->template->hook->attachCallable(
            'template:task:show:top',
            'Portfolio:widget/task_context_banner',
            function (array $params) {
                $task      = $params['task'] ?? $params;
                $projectId = (int) ($task['project_id'] ?? 0);
                return [
                    'portfolios' => $projectId > 0
                        ? $this->container['portfolioProjectModel']->getPortfolios($projectId)
                        : [],
                ];
            }
        );

        // Project header — portfolio membership badge(s) with links to dashboards.
        // Renders nothing when the project belongs to no portfolios.
        $this->template->hook->attachCallable(
            'template:project:header:after',
            'Portfolio:widget/project_header_badge',
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

        // Board card icons — blocked icon alongside existing footer indicator.
        // Reuses the PortfolioHelper per-project lazy cache (same instance as the
        // board:task:footer hook above), so no additional DB queries are made.
        $this->template->hook->attachCallable(
            'template:board:task:icons',
            'Portfolio:widget/board_task_blocked_icon',
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

        // Task creation form — optional milestone assignment dropdown.
        // When the task's project belongs to one or more portfolios, renders a
        // dropdown listing all active milestones across those portfolios.
        // Milestones are pre-fetched once per portfolio (no N+1): one call to
        // getPortfolios() then one getByPortfolioId() per portfolio found.
        // When the project belongs to no portfolios, returns an empty list so
        // the template renders nothing.
        $this->template->hook->attachCallable(
            'template:task:form:first-column',
            'Portfolio:widget/task_form_milestone_dropdown',
            function (array $params) {
                $projectId = (int) ($params['project_id'] ?? ($params['task']['project_id'] ?? 0));
                if ($projectId <= 0) {
                    return ['milestones' => []];
                }

                $portfolios = $this->container['portfolioProjectModel']->getPortfolios($projectId);
                if (empty($portfolios)) {
                    return ['milestones' => []];
                }

                $milestones = [];
                foreach ($portfolios as $portfolio) {
                    $portfolioId = (int) ($portfolio['id'] ?? 0);
                    if ($portfolioId > 0) {
                        foreach ($this->container['milestoneModel']->getByPortfolioId($portfolioId) as $milestone) {
                            $milestones[] = $milestone;
                        }
                    }
                }

                return ['milestones' => $milestones];
            }
        );

        // Header dropdown — quick links to portfolio list and create form
        $this->template->hook->attach('template:header:dropdown:menu', 'Portfolio:widget/header_dropdown');

        // Config sidebar — portfolio management link
        $this->template->hook->attach('template:config:sidebar', 'Portfolio:widget/config_sidebar');

        // Task search filter: portfolio:<id|name>
        $this->registerTaskSearchFilter();

        // Task/dependency lifecycle events
        //
        // IMPORTANT: We use $this->dispatcher->addListener() instead of
        // $this->on() because Kanboard\Core\Plugin\Base::on() wraps the
        // callback and passes the DI $container — NOT the event object.
        // We need the actual TaskEvent (which implements ArrayAccess) to
        // read task_id and other event data.
        //
        // We capture $plugin (not $this->container) because:
        // - Pimple Container is an object (passed by reference, auto-resolves closures)
        // - The test stub uses __get() for lazy service resolution
        // - Both work correctly when we access $plugin->dependencyModel
        $plugin = $this;

        $this->dispatcher->addListener('task.close', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $plugin->dependencyModel->onTaskClosed($taskId);
        });

        $this->dispatcher->addListener('task.open', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $plugin->dependencyModel->onTaskOpened($taskId);
        });

        $this->dispatcher->addListener('task_internal_link.create_update', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $plugin->dependencyModel->onLinkChanged($taskId);
        });

        $this->dispatcher->addListener('task_internal_link.delete', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            $plugin->dependencyModel->onLinkChanged($taskId);
        });

        // task.create — assign the newly created task to a milestone if the
        // user selected one from the task creation form dropdown. The event
        // data includes all form values submitted to TaskCreationModel::create()
        // (including the milestone_id field injected by the hook above).
        // milestoneTaskModel->add() validates project-in-portfolio membership
        // so no additional guard is needed here.
        $this->dispatcher->addListener('task.create', function ($event) use ($plugin) {
            $taskId      = (int) ($event['task_id'] ?? 0);
            $milestoneId = (int) ($event['milestone_id'] ?? 0);
            if ($taskId > 0 && $milestoneId > 0) {
                $plugin->milestoneTaskModel->add($milestoneId, $taskId);
            }
        });

        // task.move.column — auto-complete milestones when all tasks are done.
        //
        // Fires when a task changes columns. If the destination column matches a
        // done pattern (done, completed, closed, finished, deployed, released) AND
        // all tasks in any of the task's milestones have is_active=0, the milestone
        // status is set to 0 (completed) via milestoneModel->update().
        //
        // The column title is read from the event data (TaskEvent includes the full
        // task row after the move, which typically includes column_title from the
        // tasks query join). A DB fallback via column_id is provided for robustness.
        //
        // Guard order: task_id → config enabled → done column → milestone lookup
        $this->dispatcher->addListener('task.move.column', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            if ($taskId <= 0) {
                return;
            }

            // Check portfolio_auto_complete_milestones config (default 1 = enabled).
            // Use the magic __get() accessor so the service is resolved lazily from
            // the container at call time — consistent with other listener patterns.
            /** @var mixed $configModel */
            $configModel = $plugin->configModel;
            if (is_object($configModel) && method_exists($configModel, 'get')) {
                if ((int) $configModel->get('portfolio_auto_complete_milestones', 1) !== 1) {
                    return;
                }
            }

            // Resolve destination column title.
            // TaskEvent data for task.move.column typically includes the full task
            // row (column_title from joins). Fall back to a DB lookup via column_id
            // if column_title is absent (defensive; handles stripped event payloads).
            $columnTitle = strtolower(trim((string) ($event['column_title'] ?? '')));
            if ($columnTitle === '') {
                $columnId = (int) ($event['column_id'] ?? 0);
                if ($columnId > 0) {
                    try {
                        /** @var mixed $db */
                        $db = $plugin->db;
                        if (is_object($db) && method_exists($db, 'table')) {
                            $col = $db->table('columns')->eq('id', $columnId)->findOne();
                            if (is_array($col)) {
                                $columnTitle = strtolower(trim((string) ($col['title'] ?? '')));
                            }
                        }
                    } catch (\Throwable $e) {
                        // DB unavailable — skip
                    }
                }

                if ($columnTitle === '') {
                    return;
                }
            }

            // Same done-pattern list as PortfolioViewController::resolveCanonicalLane()
            $donePatterns = ['done', 'completed', 'closed', 'finished', 'deployed', 'released'];
            $isDoneColumn = false;
            foreach ($donePatterns as $donePattern) {
                if (str_contains($columnTitle, $donePattern)) {
                    $isDoneColumn = true;
                    break;
                }
            }

            if (! $isDoneColumn) {
                return;
            }

            /** @var mixed $milestoneTaskModel */
            $milestoneTaskModel = $plugin->milestoneTaskModel;
            /** @var mixed $milestoneModel */
            $milestoneModel = $plugin->milestoneModel;

            if (! is_object($milestoneTaskModel) || ! method_exists($milestoneTaskModel, 'getMilestones')) {
                return;
            }

            if (! is_object($milestoneModel) || ! method_exists($milestoneModel, 'update')) {
                return;
            }

            $milestones = $milestoneTaskModel->getMilestones($taskId);
            if (empty($milestones)) {
                return;
            }

            foreach ($milestones as $milestone) {
                $milestoneId = (int) ($milestone['id'] ?? 0);
                if ($milestoneId <= 0) {
                    continue;
                }

                // Skip milestones that are already completed (status = 0)
                if ((int) ($milestone['status'] ?? 1) === 0) {
                    continue;
                }

                if (! method_exists($milestoneTaskModel, 'getTasks')) {
                    continue;
                }

                $tasks = $milestoneTaskModel->getTasks($milestoneId);
                if (empty($tasks)) {
                    continue;
                }

                $allDone = true;
                foreach ($tasks as $task) {
                    if ((int) ($task['is_active'] ?? 1) !== 0) {
                        $allDone = false;
                        break;
                    }
                }

                if ($allDone) {
                    $milestoneModel->update($milestoneId, ['status' => 0]);
                }
            }
        });

        // task.update — hook point for critical-path cache invalidation.
        // Fires when task fields are modified. If date_due or priority changes
        // on a task in a portfolio milestone, the critical-path may need
        // recomputation. The stub method returns early for now; the event
        // wiring is in place so future functionality can be added without
        // touching Plugin.php again.
        // Guard: task_id absent → no-op.
        $this->dispatcher->addListener('task.update', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            if ($taskId <= 0) {
                return;
            }

            $plugin->dependencyModel->onTaskUpdated($taskId);
        });

        // task.assignee_change — hook point for real-time workload recalculation.
        // Fires when a task is reassigned. The stub method returns early for now;
        // the event wiring is in place so future workload-update logic can be
        // added without touching Plugin.php again.
        // Guard: task_id absent → no-op.
        $this->dispatcher->addListener('task.assignee_change', function ($event) use ($plugin) {
            $taskId = (int) ($event['task_id'] ?? 0);
            if ($taskId <= 0) {
                return;
            }

            $plugin->portfolioTaskModel->onAssigneeChanged($taskId);
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
            ->withCallback('getMilestoneProgress', function ($milestone_id, $weight_by = 'count') {
                return $this->milestoneModel->getProgress((int) $milestone_id, (string) $weight_by);
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
            })
            ->withCallback('getPortfolioStatusReport', function ($portfolio_id, $period_days = 7) {
                return $this->portfolioTaskModel->getStatusReport((int) $portfolio_id, (int) $period_days);
            })
            ->withCallback('getPortfolioActivity', function ($portfolio_id, $limit = 25, $offset = 0) {
                return $this->portfolioTaskModel->getActivity((int) $portfolio_id, (int) $limit, (int) $offset);
            })
            ->withCallback('getPortfolioWorkload', function ($portfolio_id) {
                return $this->portfolioTaskModel->getWorkload((int) $portfolio_id);
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
        return 'Portfolio';
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
        return '1.22.1';
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
