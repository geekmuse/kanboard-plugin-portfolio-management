<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Core\Base;
use Throwable;

class PortfolioViewController extends Base
{
    public function show()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if (! is_array($portfolio)) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/show', [
            'title' => t('Portfolio Dashboard'),
            'portfolio' => $portfolio,
            'overview' => $this->getOverview($portfolioId, $portfolio),
        ]));
    }

    public function tasks()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if (! is_array($portfolio)) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        $filters = $this->buildTaskFilters();

        $tasks = [];
        if (is_object($this->portfolioTaskModel) && method_exists($this->portfolioTaskModel, 'getTasks')) {
            $resolvedTasks = $this->portfolioTaskModel->getTasks($portfolioId, $filters);
            if (is_array($resolvedTasks)) {
                $tasks = $resolvedTasks;
            }
        }

        $counts = [
            'total' => 0,
            'active' => 0,
            'closed' => 0,
            'blocked' => 0,
        ];

        if (is_object($this->portfolioTaskModel) && method_exists($this->portfolioTaskModel, 'getCounts')) {
            $resolvedCounts = $this->portfolioTaskModel->getCounts($portfolioId);
            if (is_array($resolvedCounts)) {
                $counts = array_merge($counts, $resolvedCounts);
            }
        }

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/tasks', [
            'title' => t('Portfolio Tasks'),
            'portfolio' => $portfolio,
            'tasks' => $tasks,
            'counts' => $counts,
            'projects' => $this->getPortfolioProjects($portfolioId),
            'milestones' => $this->getPortfolioMilestones($portfolioId),
            'filters' => $this->buildTaskFilterValues($filters),
            'previous_offset' => $this->getPreviousOffset($filters),
            'next_offset' => $this->getNextOffset($filters, $tasks),
            'pagination_query' => $this->buildPaginationQuery($filters),
        ]));
    }

    public function board()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if (! is_array($portfolio)) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        $activeTasks = $this->getPortfolioTasks($portfolioId, [
            'status_id' => 1,
            'sort' => 'project',
            'direction' => 'ASC',
            'limit' => 500,
            'offset' => 0,
        ]);

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/board', [
            'title' => t('Portfolio Board'),
            'portfolio' => $portfolio,
            'counts' => $this->getPortfolioTaskCounts($portfolioId),
            'board_columns' => $this->buildBoardColumns($activeTasks),
        ]));
    }

    public function timeline()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if (! is_array($portfolio)) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        $timelineData = $this->buildTimelineData(
            $this->getPortfolioTasks($portfolioId, [
                'status_id' => 1,
                'sort' => 'date_due',
                'direction' => 'ASC',
                'limit' => 500,
                'offset' => 0,
            ]),
            $this->getPortfolioMilestones($portfolioId)
        );

        $timelineJson = json_encode($timelineData['items'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (! is_string($timelineJson)) {
            $timelineJson = '[]';
        }

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/timeline', [
            'title' => t('Portfolio Timeline'),
            'portfolio' => $portfolio,
            'timeline_data' => $timelineData,
            'timeline_json' => $timelineJson,
        ]));
    }

    /**
     * @param array<string, mixed> $portfolio
     *
     * @return array<string, mixed>
     */
    private function getOverview(int $portfolioId, array $portfolio): array
    {
        $overview = [
            'portfolio' => $portfolio,
            'project_count' => 0,
            'projects' => [],
            'task_counts' => [
                'total' => 0,
                'active' => 0,
                'closed' => 0,
                'blocked' => 0,
            ],
            'milestones' => [],
            'at_risk_milestones' => 0,
            'overdue_milestones' => 0,
            'critical_path_length' => 0,
        ];

        if (! is_object($this->portfolioTaskModel) || ! method_exists($this->portfolioTaskModel, 'getOverview')) {
            return $overview;
        }

        $resolvedOverview = $this->portfolioTaskModel->getOverview($portfolioId);
        if (! is_array($resolvedOverview)) {
            return $overview;
        }

        $overview = array_replace_recursive($overview, $resolvedOverview);
        if (! is_array($overview['portfolio'])) {
            $overview['portfolio'] = $portfolio;
        }

        return $overview;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPortfolioTasks(int $portfolioId, array $filters): array
    {
        if (! is_object($this->portfolioTaskModel) || ! method_exists($this->portfolioTaskModel, 'getTasks')) {
            return [];
        }

        $tasks = $this->portfolioTaskModel->getTasks($portfolioId, $filters);

        return is_array($tasks) ? $tasks : [];
    }

    /**
     * @return array<string, int>
     */
    private function getPortfolioTaskCounts(int $portfolioId): array
    {
        $counts = [
            'total' => 0,
            'active' => 0,
            'closed' => 0,
            'blocked' => 0,
        ];

        if (! is_object($this->portfolioTaskModel) || ! method_exists($this->portfolioTaskModel, 'getCounts')) {
            return $counts;
        }

        $resolvedCounts = $this->portfolioTaskModel->getCounts($portfolioId);
        if (! is_array($resolvedCounts)) {
            return $counts;
        }

        return array_merge($counts, $resolvedCounts);
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBoardColumns(array $tasks): array
    {
        $columnMap = [];

        foreach ($tasks as $task) {
            $columnId = (int) ($task['column_id'] ?? 0);
            $columnTitle = trim((string) ($task['column_title'] ?? ''));
            if ($columnTitle === '') {
                $columnTitle = t('Unassigned');
            }

            $columnKey = $columnId > 0 ? 'id-' . $columnId : 'title-' . $columnTitle;
            if (! array_key_exists($columnKey, $columnMap)) {
                $columnMap[$columnKey] = [
                    'id' => $columnId,
                    'title' => $columnTitle,
                    'total' => 0,
                    'blocked' => 0,
                    'tasks' => [],
                ];
            }

            ++$columnMap[$columnKey]['total'];

            if ((bool) ($task['is_blocked'] ?? false)) {
                ++$columnMap[$columnKey]['blocked'];
            }

            $columnMap[$columnKey]['tasks'][] = $task;
        }

        $columns = array_values($columnMap);

        usort(
            $columns,
            static fn (array $left, array $right): int => strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''))
        );

        return $columns;
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @param array<int, array<string, mixed>> $milestones
     *
     * @return array<string, mixed>
     */
    private function buildTimelineData(array $tasks, array $milestones): array
    {
        $items = [];

        foreach ($milestones as $milestone) {
            $targetDate = (int) ($milestone['target_date'] ?? 0);
            if ($targetDate <= 0) {
                continue;
            }

            $items[] = [
                'id' => (int) ($milestone['id'] ?? 0),
                'type' => 'milestone',
                'type_label' => t('Milestone'),
                'name' => (string) ($milestone['name'] ?? ''),
                'project_name' => '',
                'date' => $targetDate,
                'date_label' => date('Y-m-d', $targetDate),
                'status' => t('Milestone'),
            ];
        }

        foreach ($tasks as $task) {
            $dueDate = (int) ($task['date_due'] ?? 0);
            if ($dueDate <= 0) {
                continue;
            }

            $taskStatus = (int) ($task['is_active'] ?? 1) === 1 ? t('Active') : t('Closed');
            $items[] = [
                'id' => (int) ($task['id'] ?? 0),
                'type' => 'task',
                'type_label' => t('Task'),
                'name' => (string) ($task['title'] ?? ''),
                'project_name' => (string) ($task['project_name'] ?? ''),
                'date' => $dueDate,
                'date_label' => date('Y-m-d', $dueDate),
                'status' => $taskStatus,
            ];
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $comparison = ((int) ($left['date'] ?? 0)) <=> ((int) ($right['date'] ?? 0));
                if ($comparison !== 0) {
                    return $comparison;
                }

                return strcmp((string) ($left['type'] ?? ''), (string) ($right['type'] ?? ''));
            }
        );

        $timelineMinDate = 0;
        $timelineMaxDate = 0;

        if ($items !== []) {
            $timelineMinDate = (int) ($items[0]['date'] ?? 0);
            $timelineMaxDate = (int) ($items[count($items) - 1]['date'] ?? 0);
        }

        return [
            'has_items' => $items !== [],
            'items' => $items,
            'min_date' => $timelineMinDate,
            'max_date' => $timelineMaxDate,
            'min_date_label' => $timelineMinDate > 0 ? date('Y-m-d', $timelineMinDate) : '',
            'max_date_label' => $timelineMaxDate > 0 ? date('Y-m-d', $timelineMaxDate) : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTaskFilters(): array
    {
        $filters = [];

        $statusId = $this->resolveStatusFilter();
        if ($statusId !== null) {
            $filters['status_id'] = $statusId;
        }

        foreach (['assignee_id', 'project_id', 'milestone_id'] as $filterName) {
            $value = $this->resolvePositiveIntegerFilter($filterName);
            if ($value !== null) {
                $filters[$filterName] = $value;
            }
        }

        $hasDependencies = $this->resolveOptionalBoolean($this->request->getValue('has_dependencies', null));
        if ($hasDependencies !== null) {
            $filters['has_dependencies'] = $hasDependencies;
        }

        $sort = strtolower(trim((string) $this->request->getValue('sort', 'priority')));
        $filters['sort'] = in_array($sort, ['priority', 'date_due', 'project', 'date_creation'], true)
            ? $sort
            : 'priority';

        $direction = strtoupper(trim((string) $this->request->getValue('direction', 'DESC')));
        $filters['direction'] = in_array($direction, ['ASC', 'DESC'], true)
            ? $direction
            : 'DESC';

        $defaultLimit = $this->getDefaultTaskLimit();
        $limit = (int) $this->request->getValue('limit', $defaultLimit);
        if ($limit <= 0) {
            $limit = $defaultLimit;
        }
        $filters['limit'] = min($limit, 500);

        $offset = (int) $this->request->getValue('offset', 0);
        $filters['offset'] = max(0, $offset);

        return $filters;
    }

    private function resolveStatusFilter(): ?int
    {
        $value = $this->request->getValue('status_id', null);
        if ($value === null || $value === '') {
            return null;
        }

        $statusId = (int) $value;
        if (! in_array($statusId, [0, 1], true)) {
            return null;
        }

        return $statusId;
    }

    private function resolvePositiveIntegerFilter(string $name): ?int
    {
        $value = $this->request->getValue($name, null);
        if ($value === null || $value === '') {
            return null;
        }

        $resolvedValue = (int) $value;

        return $resolvedValue > 0 ? $resolvedValue : null;
    }

    private function resolveOptionalBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }

            if ($value === 0) {
                return false;
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = strtolower(trim($value));

        if (in_array($normalizedValue, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($normalizedValue, ['0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }

    private function getDefaultTaskLimit(): int
    {
        $defaultLimit = $this->getConfigValueAsInt('portfolio_tasks_per_page', 50);

        if ($defaultLimit <= 0) {
            return 50;
        }

        return min($defaultLimit, 500);
    }

    private function getConfigValueAsInt(string $key, int $default): int
    {
        if (! is_object($this->configModel) || ! method_exists($this->configModel, 'get')) {
            return $default;
        }

        try {
            return (int) $this->configModel->get($key, $default);
        } catch (Throwable $exception) {
            return $default;
        }
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, string>
     */
    private function buildTaskFilterValues(array $filters): array
    {
        return [
            'status_id' => array_key_exists('status_id', $filters) ? (string) ((int) $filters['status_id']) : '',
            'assignee_id' => array_key_exists('assignee_id', $filters) ? (string) ((int) $filters['assignee_id']) : '',
            'project_id' => array_key_exists('project_id', $filters) ? (string) ((int) $filters['project_id']) : '',
            'milestone_id' => array_key_exists('milestone_id', $filters) ? (string) ((int) $filters['milestone_id']) : '',
            'has_dependencies' => array_key_exists('has_dependencies', $filters)
                ? ((bool) $filters['has_dependencies'] ? '1' : '0')
                : '',
            'sort' => (string) ($filters['sort'] ?? 'priority'),
            'direction' => (string) ($filters['direction'] ?? 'DESC'),
            'limit' => (string) ((int) ($filters['limit'] ?? 50)),
            'offset' => (string) ((int) ($filters['offset'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getPreviousOffset(array $filters): ?int
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = (int) ($filters['limit'] ?? 50);

        if ($offset <= 0) {
            return null;
        }

        return max(0, $offset - max(1, $limit));
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $tasks
     */
    private function getNextOffset(array $filters, array $tasks): ?int
    {
        $offset = (int) ($filters['offset'] ?? 0);
        $limit = max(1, (int) ($filters['limit'] ?? 50));

        if (count($tasks) < $limit) {
            return null;
        }

        return $offset + $limit;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, int|string>
     */
    private function buildPaginationQuery(array $filters): array
    {
        $query = [
            'sort' => (string) ($filters['sort'] ?? 'priority'),
            'direction' => (string) ($filters['direction'] ?? 'DESC'),
            'limit' => (int) ($filters['limit'] ?? 50),
        ];

        foreach (['status_id', 'assignee_id', 'project_id', 'milestone_id'] as $filterName) {
            if (array_key_exists($filterName, $filters)) {
                $query[$filterName] = (int) $filters[$filterName];
            }
        }

        if (array_key_exists('has_dependencies', $filters)) {
            $query['has_dependencies'] = (bool) $filters['has_dependencies'] ? '1' : '0';
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPortfolioProjects(int $portfolioId): array
    {
        if (! is_object($this->portfolioProjectModel) || ! method_exists($this->portfolioProjectModel, 'getProjects')) {
            return [];
        }

        $projects = $this->portfolioProjectModel->getProjects($portfolioId);

        return is_array($projects) ? $projects : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPortfolioMilestones(int $portfolioId): array
    {
        if (! is_object($this->milestoneModel) || ! method_exists($this->milestoneModel, 'getByPortfolioId')) {
            return [];
        }

        $milestones = $this->milestoneModel->getByPortfolioId($portfolioId);

        return is_array($milestones) ? $milestones : [];
    }
}
