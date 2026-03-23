<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use ArrayAccess;
use Kanboard\Core\Base;
use Throwable;

class PortfolioTaskModel extends Base
{
    private const PORTFOLIO_TABLE = 'portfolios';

    private const MEMBERSHIP_TABLE = 'portfolio_has_projects';

    private const MILESTONE_TABLE = 'milestones';

    private const MILESTONE_TASK_TABLE = 'milestone_has_tasks';

    private const BLOCKS_LABEL = 'blocks';

    private const BLOCKED_BY_LABEL = 'is blocked by';

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTasks(int $portfolioId, array $filters = []): array
    {
        $rows = $this->buildFilteredTaskRows($portfolioId, $filters);
        if ($rows === []) {
            return [];
        }

        $sort = $this->normalizeSort((string) ($filters['sort'] ?? 'priority'));
        $direction = $this->normalizeDirection((string) ($filters['direction'] ?? 'DESC'));

        $this->sortTaskRows($rows, $sort, $direction);

        $limit = $this->normalizeLimit($filters['limit'] ?? null);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        return array_values(array_slice($rows, $offset, $limit));
    }

    public function getCounts(int $portfolioId, ?int $statusId = null): array
    {
        $filters = [];
        if ($statusId !== null) {
            $filters['status_id'] = $statusId;
        }

        $rows = $this->buildFilteredTaskRows($portfolioId, $filters);

        $counts = [
            'total' => count($rows),
            'active' => 0,
            'closed' => 0,
            'blocked' => 0,
        ];

        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 1) === 1) {
                ++$counts['active'];
            } else {
                ++$counts['closed'];
            }

            if ((bool) ($row['is_blocked'] ?? false)) {
                ++$counts['blocked'];
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(int $portfolioId): array
    {
        $portfolio = $this->db->table(self::PORTFOLIO_TABLE)
            ->eq('id', $portfolioId)
            ->findOne();

        if (! is_array($portfolio)) {
            return [
                'portfolio' => null,
                'project_count' => 0,
                'projects' => [],
                'task_counts' => $this->getCounts($portfolioId),
                'milestones' => [],
                'at_risk_milestones' => 0,
                'overdue_milestones' => 0,
                'critical_path_length' => 0,
            ];
        }

        $projects = $this->getOverviewProjects($portfolioId);

        [$milestones, $atRiskMilestones, $overdueMilestones] = $this->getOverviewMilestones($portfolioId);

        $criticalPathLength = 0;
        $dependencyModel = $this->resolveContainerService('dependencyModel');
        if (is_object($dependencyModel) && method_exists($dependencyModel, 'getCriticalPath')) {
            try {
                $criticalPath = $dependencyModel->getCriticalPath($portfolioId);
                if (is_array($criticalPath)) {
                    $criticalPathLength = count($criticalPath);
                }
            } catch (Throwable $exception) {
                $criticalPathLength = 0;
            }
        }

        return [
            'portfolio' => $portfolio,
            'project_count' => count($projects),
            'projects' => $projects,
            'task_counts' => $this->getCounts($portfolioId),
            'milestones' => $milestones,
            'at_risk_milestones' => $atRiskMilestones,
            'overdue_milestones' => $overdueMilestones,
            'critical_path_length' => $criticalPathLength,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFilteredTaskRows(int $portfolioId, array $filters): array
    {
        $projectIds = $this->getPortfolioProjectIds($portfolioId);
        if ($projectIds === []) {
            return [];
        }

        $projectScope = [];
        foreach ($projectIds as $projectId) {
            $projectScope[$projectId] = true;
        }

        $statusFilter = null;
        if (array_key_exists('status_id', $filters) && $filters['status_id'] !== null) {
            $statusFilter = (int) $filters['status_id'];
            if (! in_array($statusFilter, [0, 1], true)) {
                return [];
            }
        }

        $assigneeFilterEnabled = array_key_exists('assignee_id', $filters) && $filters['assignee_id'] !== null;
        $assigneeFilter = (int) ($filters['assignee_id'] ?? 0);

        $projectFilter = null;
        if (array_key_exists('project_id', $filters) && $filters['project_id'] !== null) {
            $projectFilter = (int) $filters['project_id'];
            if (! array_key_exists($projectFilter, $projectScope)) {
                return [];
            }
        }

        $milestoneTaskFilter = $this->resolveMilestoneTaskFilter($portfolioId, $filters['milestone_id'] ?? null);
        if ($milestoneTaskFilter !== null && $milestoneTaskFilter === []) {
            return [];
        }

        $hasDependenciesFilter = $this->resolveBooleanFilter($filters['has_dependencies'] ?? null) === true;

        try {
            $taskRows = $this->db->table('tasks')->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($taskRows) || $taskRows === []) {
            return [];
        }

        $taskMap = [];
        foreach ($taskRows as $taskRow) {
            if (! is_array($taskRow)) {
                continue;
            }

            $taskId = (int) ($taskRow['id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $taskMap[$taskId] = $taskRow;
        }

        $projectMap = $this->buildTableLookup('projects');
        $columnMap = $this->buildTableLookup('columns');
        $userMap = $this->buildTableLookup('users');
        $dependencyStats = $this->buildTaskDependencyStats($taskMap, $projectScope);

        $rows = [];

        foreach ($taskMap as $taskId => $task) {
            $projectId = (int) ($task['project_id'] ?? 0);
            if (! array_key_exists($projectId, $projectScope)) {
                continue;
            }

            if ($statusFilter !== null && (int) ($task['is_active'] ?? 1) !== $statusFilter) {
                continue;
            }

            if ($assigneeFilterEnabled && (int) ($task['owner_id'] ?? 0) !== $assigneeFilter) {
                continue;
            }

            if ($projectFilter !== null && $projectId !== $projectFilter) {
                continue;
            }

            if ($milestoneTaskFilter !== null && ! array_key_exists($taskId, $milestoneTaskFilter)) {
                continue;
            }

            $stats = $dependencyStats[$taskId] ?? [
                'blocked_by_count' => 0,
                'has_cross_project_dependency' => false,
            ];

            if ($hasDependenciesFilter && ! (bool) ($stats['has_cross_project_dependency'] ?? false)) {
                continue;
            }

            $ownerId = (int) ($task['owner_id'] ?? 0);
            $columnId = (int) ($task['column_id'] ?? 0);
            $blockedByCount = (int) ($stats['blocked_by_count'] ?? 0);

            $project = $projectMap[$projectId] ?? [];
            $column = $columnMap[$columnId] ?? [];
            $user = $userMap[$ownerId] ?? [];

            $rows[] = [
                'id' => $task['id'] ?? 0,
                'title' => (string) ($task['title'] ?? ''),
                'project_id' => $task['project_id'] ?? 0,
                'project_name' => (string) ($project['name'] ?? ''),
                'column_id' => $task['column_id'] ?? 0,
                'column_title' => (string) ($column['title'] ?? ''),
                'owner_id' => $task['owner_id'] ?? 0,
                'assignee_username' => (string) ($user['username'] ?? ''),
                'assignee_name' => (string) ($user['name'] ?? ''),
                'is_active' => $task['is_active'] ?? 1,
                'date_due' => $task['date_due'] ?? 0,
                'date_creation' => $task['date_creation'] ?? 0,
                'priority' => $task['priority'] ?? 0,
                'score' => $task['score'] ?? 0,
                'color_id' => (string) ($task['color_id'] ?? ''),
                'category_id' => $task['category_id'] ?? 0,
                'swimlane_id' => $task['swimlane_id'] ?? 0,
                'is_blocked' => $blockedByCount > 0,
                'blocked_by_count' => $blockedByCount,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function getPortfolioProjectIds(int $portfolioId): array
    {
        try {
            $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
                ->eq('portfolio_id', $portfolioId)
                ->asc('position')
                ->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($memberships)) {
            return [];
        }

        $projectIds = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $projectId = (int) ($membership['project_id'] ?? 0);
            if ($projectId > 0) {
                $projectIds[] = $projectId;
            }
        }

        return array_values(array_unique($projectIds));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTableLookup(string $table): array
    {
        try {
            $rows = $this->db->table($table)->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $lookup = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $lookup[$id] = $row;
            }
        }

        return $lookup;
    }

    /**
     * @param array<int, array<string, mixed>> $taskMap
     * @param array<int, bool> $projectScope
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTaskDependencyStats(array $taskMap, array $projectScope): array
    {
        $stats = [];

        foreach ($taskMap as $taskId => $task) {
            $taskProjectId = (int) ($task['project_id'] ?? 0);
            if (! array_key_exists($taskProjectId, $projectScope)) {
                continue;
            }

            $stats[$taskId] = [
                'blocked_by_count' => 0,
                'has_cross_project_dependency' => false,
            ];
        }

        if ($stats === []) {
            return [];
        }

        $linkDefinitions = $this->getDependencyLinkDefinitions();
        if ($linkDefinitions === []) {
            return $stats;
        }

        try {
            $taskLinks = $this->db->table('task_has_links')->findAll();
        } catch (Throwable $exception) {
            return $stats;
        }

        if (! is_array($taskLinks) || $taskLinks === []) {
            return $stats;
        }

        $seenBlockedByPairs = [];

        foreach ($taskLinks as $taskLink) {
            if (! is_array($taskLink)) {
                continue;
            }

            $linkId = (int) ($taskLink['link_id'] ?? 0);
            if ($linkId <= 0 || ! array_key_exists($linkId, $linkDefinitions)) {
                continue;
            }

            $taskId = (int) ($taskLink['task_id'] ?? 0);
            $oppositeTaskId = (int) ($taskLink['opposite_task_id'] ?? 0);

            if (! array_key_exists($taskId, $taskMap) || ! array_key_exists($oppositeTaskId, $taskMap)) {
                continue;
            }

            $task = $taskMap[$taskId];
            $oppositeTask = $taskMap[$oppositeTaskId];
            $taskProjectId = (int) ($task['project_id'] ?? 0);
            $oppositeProjectId = (int) ($oppositeTask['project_id'] ?? 0);

            if (! array_key_exists($taskProjectId, $projectScope) || ! array_key_exists($oppositeProjectId, $projectScope)) {
                continue;
            }

            $taskBlocksOpposite = (bool) ($linkDefinitions[$linkId]['task_blocks_opposite'] ?? true);
            $blockingTaskId = $taskBlocksOpposite ? $taskId : $oppositeTaskId;
            $blockedTaskId = $taskBlocksOpposite ? $oppositeTaskId : $taskId;

            if (! array_key_exists($blockingTaskId, $stats) || ! array_key_exists($blockedTaskId, $stats)) {
                continue;
            }

            $blockingTask = $taskMap[$blockingTaskId];
            $blockedTask = $taskMap[$blockedTaskId];

            $isCrossProject = (int) ($blockingTask['project_id'] ?? 0) !== (int) ($blockedTask['project_id'] ?? 0);
            if ($isCrossProject) {
                $stats[$blockingTaskId]['has_cross_project_dependency'] = true;
                $stats[$blockedTaskId]['has_cross_project_dependency'] = true;
            }

            $blockingTaskIsActive = (int) ($blockingTask['is_active'] ?? 1) === 1;
            $blockedTaskIsActive = (int) ($blockedTask['is_active'] ?? 1) === 1;
            $isResolved = ! $blockingTaskIsActive || ! $blockedTaskIsActive;

            if ($isResolved) {
                continue;
            }

            $pairKey = $blockedTaskId . ':' . $blockingTaskId;
            if (array_key_exists($pairKey, $seenBlockedByPairs)) {
                continue;
            }

            ++$stats[$blockedTaskId]['blocked_by_count'];
            $seenBlockedByPairs[$pairKey] = true;
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDependencyLinkDefinitions(): array
    {
        try {
            $links = $this->db->table('links')->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($links)) {
            return [];
        }

        $definitions = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $linkId = (int) ($link['id'] ?? 0);
            if ($linkId <= 0) {
                continue;
            }

            $label = $this->normalizeLinkLabel($link['label'] ?? '');
            $oppositeLabel = $this->normalizeLinkLabel($link['opposite_label'] ?? '');

            if (! $this->isDependencyLinkLabelSet($label, $oppositeLabel)) {
                continue;
            }

            $taskBlocksOpposite = $this->resolveTaskBlocksOpposite($label, $oppositeLabel);
            if ($taskBlocksOpposite === null) {
                continue;
            }

            $definitions[$linkId] = [
                'task_blocks_opposite' => $taskBlocksOpposite,
            ];
        }

        return $definitions;
    }

    private function normalizeLinkLabel(mixed $label): string
    {
        return strtolower(trim((string) $label));
    }

    private function isDependencyLinkLabelSet(string $label, string $oppositeLabel): bool
    {
        return in_array(self::BLOCKS_LABEL, [$label, $oppositeLabel], true)
            || in_array(self::BLOCKED_BY_LABEL, [$label, $oppositeLabel], true);
    }

    private function resolveTaskBlocksOpposite(string $label, string $oppositeLabel): ?bool
    {
        if ($label === self::BLOCKS_LABEL) {
            return true;
        }

        if ($label === self::BLOCKED_BY_LABEL) {
            return false;
        }

        if ($oppositeLabel === self::BLOCKED_BY_LABEL) {
            return true;
        }

        if ($oppositeLabel === self::BLOCKS_LABEL) {
            return false;
        }

        return null;
    }

    /**
     * @param mixed $milestoneId
     *
     * @return array<int, bool>|null
     */
    private function resolveMilestoneTaskFilter(int $portfolioId, $milestoneId): ?array
    {
        if ($milestoneId === null || $milestoneId === '') {
            return null;
        }

        $resolvedMilestoneId = (int) $milestoneId;
        if ($resolvedMilestoneId <= 0) {
            return [];
        }

        $milestone = $this->db->table(self::MILESTONE_TABLE)
            ->eq('id', $resolvedMilestoneId)
            ->findOne();

        if (! is_array($milestone) || (int) ($milestone['portfolio_id'] ?? 0) !== $portfolioId) {
            return [];
        }

        $memberships = $this->db->table(self::MILESTONE_TASK_TABLE)
            ->eq('milestone_id', $resolvedMilestoneId)
            ->findAll();

        if (! is_array($memberships) || $memberships === []) {
            return [];
        }

        $taskFilter = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $taskId = (int) ($membership['task_id'] ?? 0);
            if ($taskId > 0) {
                $taskFilter[$taskId] = true;
            }
        }

        return $taskFilter;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function sortTaskRows(array &$rows, string $sort, string $direction): void
    {
        usort(
            $rows,
            static function (array $left, array $right) use ($sort, $direction): int {
                $comparison = 0;

                if ($sort === 'project') {
                    $comparison = strcmp(
                        strtolower((string) ($left['project_name'] ?? '')),
                        strtolower((string) ($right['project_name'] ?? ''))
                    );
                } else {
                    $comparison = ((int) ($left[$sort] ?? 0)) <=> ((int) ($right[$sort] ?? 0));
                }

                if ($comparison === 0) {
                    $comparison = ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
                }

                if ($direction === 'DESC') {
                    return -1 * $comparison;
                }

                return $comparison;
            }
        );
    }

    private function normalizeSort(string $sort): string
    {
        $normalizedSort = strtolower(trim($sort));
        $allowed = ['priority', 'date_due', 'project', 'date_creation'];

        return in_array($normalizedSort, $allowed, true) ? $normalizedSort : 'priority';
    }

    private function normalizeDirection(string $direction): string
    {
        $normalizedDirection = strtoupper(trim($direction));

        return in_array($normalizedDirection, ['ASC', 'DESC'], true) ? $normalizedDirection : 'DESC';
    }

    private function resolveBooleanFilter(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 0) {
                return false;
            }

            if ($value === 1) {
                return true;
            }

            return null;
        }

        if (is_string($value)) {
            $normalizedValue = strtolower(trim($value));
            if ($normalizedValue === '') {
                return null;
            }

            if (in_array($normalizedValue, ['1', 'true', 'yes'], true)) {
                return true;
            }

            if (in_array($normalizedValue, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    private function normalizeLimit(mixed $limit): int
    {
        $resolvedLimit = (int) $limit;

        if ($resolvedLimit <= 0) {
            $resolvedLimit = $this->getConfigValueAsInt('portfolio_tasks_per_page', 50);
        }

        if ($resolvedLimit <= 0) {
            $resolvedLimit = 50;
        }

        return min($resolvedLimit, 500);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getOverviewProjects(int $portfolioId): array
    {
        try {
            $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
                ->eq('portfolio_id', $portfolioId)
                ->asc('position')
                ->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($memberships) || $memberships === []) {
            return [];
        }

        $projectMap = $this->buildTableLookup('projects');
        $projects = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $projectId = (int) ($membership['project_id'] ?? 0);
            if ($projectId <= 0 || ! array_key_exists($projectId, $projectMap)) {
                continue;
            }

            $project = $projectMap[$projectId];
            $projects[] = [
                'id' => $project['id'] ?? $projectId,
                'name' => (string) ($project['name'] ?? ''),
                'is_active' => $project['is_active'] ?? 1,
                'position' => $membership['position'] ?? 0,
                'added_at' => $membership['added_at'] ?? 0,
            ];
        }

        return $projects;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int}
     */
    private function getOverviewMilestones(int $portfolioId): array
    {
        try {
            $milestones = $this->db->table(self::MILESTONE_TABLE)
                ->eq('portfolio_id', $portfolioId)
                ->asc('target_date')
                ->findAll();
        } catch (Throwable $exception) {
            return [[], 0, 0];
        }

        if (! is_array($milestones) || $milestones === []) {
            return [[], 0, 0];
        }

        $summary = [];
        $atRiskCount = 0;
        $overdueCount = 0;

        foreach ($milestones as $milestone) {
            if (! is_array($milestone)) {
                continue;
            }

            $milestoneSummary = $this->calculateMilestoneHealth($milestone);
            $summary[] = $milestoneSummary;

            if ((bool) ($milestoneSummary['is_at_risk'] ?? false)) {
                ++$atRiskCount;
            }

            if ((bool) ($milestoneSummary['is_overdue'] ?? false)) {
                ++$overdueCount;
            }
        }

        return [$summary, $atRiskCount, $overdueCount];
    }

    /**
     * @param array<string, mixed> $milestone
     *
     * @return array<string, mixed>
     */
    private function calculateMilestoneHealth(array $milestone): array
    {
        $milestoneId = (int) ($milestone['id'] ?? 0);
        if ($milestoneId <= 0) {
            return [
                'id' => 0,
                'name' => '',
                'target_date' => 0,
                'percent' => 0.0,
                'is_at_risk' => false,
                'is_overdue' => false,
            ];
        }

        $memberships = $this->db->table(self::MILESTONE_TASK_TABLE)
            ->eq('milestone_id', $milestoneId)
            ->findAll();

        if (! is_array($memberships)) {
            $memberships = [];
        }

        $total = 0;
        $completed = 0;

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $taskId = (int) ($membership['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            ++$total;

            $task = $this->db->table('tasks')->eq('id', $taskId)->findOne();
            if (is_array($task) && (int) ($task['is_active'] ?? 1) === 0) {
                ++$completed;
            }
        }

        $percent = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
        $targetDate = (int) ($milestone['target_date'] ?? 0);
        $now = time();

        $atRiskDays = max(0, $this->getConfigValueAsInt('portfolio_milestone_at_risk_days', 7));
        $atRiskThreshold = $this->getConfigValueAsInt('portfolio_milestone_at_risk_threshold', 80);

        $isOverdue = $targetDate > 0 && $targetDate < $now && $percent < 100;
        $isAtRisk = ! $isOverdue
            && $targetDate > 0
            && $targetDate < ($now + ($atRiskDays * 86400))
            && $percent < $atRiskThreshold;

        return [
            'id' => $milestone['id'] ?? 0,
            'name' => (string) ($milestone['name'] ?? ''),
            'target_date' => $milestone['target_date'] ?? 0,
            'percent' => $percent,
            'is_at_risk' => $isAtRisk,
            'is_overdue' => $isOverdue,
        ];
    }

    private function getConfigValueAsInt(string $key, int $default): int
    {
        $configModel = $this->resolveContainerService('configModel');
        if (! is_object($configModel) || ! method_exists($configModel, 'get')) {
            return $default;
        }

        try {
            return (int) $configModel->get($key, $default);
        } catch (Throwable $exception) {
            return $default;
        }
    }

    private function resolveContainerService(string $serviceKey): mixed
    {
        if (is_array($this->container) && array_key_exists($serviceKey, $this->container)) {
            return $this->container[$serviceKey];
        }

        if ($this->container instanceof ArrayAccess && isset($this->container[$serviceKey])) {
            return $this->container[$serviceKey];
        }

        return null;
    }
}
