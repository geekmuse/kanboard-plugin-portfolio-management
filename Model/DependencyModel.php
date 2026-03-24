<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use ArrayAccess;
use Kanboard\Core\Base;
use Throwable;

class DependencyModel extends Base
{
    private const BLOCKS_LABEL = 'blocks';

    private const BLOCKED_BY_LABEL = 'is blocked by';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDependencies(int $portfolioId, bool $crossProjectOnly = true): array
    {
        $projectIds = $this->getPortfolioProjectIds($portfolioId);
        if ($projectIds === []) {
            return [];
        }

        $projectScope = [];
        foreach ($projectIds as $projectId) {
            $projectScope[$projectId] = true;
        }

        $linkDefinitions = $this->getDependencyLinkDefinitions();
        if ($linkDefinitions === []) {
            return [];
        }

        try {
            $taskLinks = $this->db->table('task_has_links')->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($taskLinks)) {
            return [];
        }

        $dependencies = [];
        $seenEdges = [];

        foreach ($taskLinks as $taskLink) {
            if (! is_array($taskLink)) {
                continue;
            }

            $linkTypeId = (int) ($taskLink['link_id'] ?? 0);
            if ($linkTypeId <= 0 || ! array_key_exists($linkTypeId, $linkDefinitions)) {
                continue;
            }

            $linkDefinition = $linkDefinitions[$linkTypeId];
            $taskId = (int) ($taskLink['task_id'] ?? 0);
            $oppositeTaskId = (int) ($taskLink['opposite_task_id'] ?? 0);

            if ($taskId <= 0 || $oppositeTaskId <= 0) {
                continue;
            }

            $task = $this->getTask($taskId);
            $oppositeTask = $this->getTask($oppositeTaskId);

            if ($task === null || $oppositeTask === null) {
                continue;
            }

            $taskProjectId = (int) ($task['project_id'] ?? 0);
            $oppositeTaskProjectId = (int) ($oppositeTask['project_id'] ?? 0);

            if (! isset($projectScope[$taskProjectId]) && ! isset($projectScope[$oppositeTaskProjectId])) {
                continue;
            }

            $taskBlocksOpposite = (bool) ($linkDefinition['task_blocks_opposite'] ?? true);
            $blockingTask = $taskBlocksOpposite ? $task : $oppositeTask;
            $blockedTask = $taskBlocksOpposite ? $oppositeTask : $task;

            $isCrossProject = (int) ($blockingTask['project_id'] ?? 0) !== (int) ($blockedTask['project_id'] ?? 0);
            if ($crossProjectOnly && ! $isCrossProject) {
                continue;
            }

            $blockingTaskId = (int) ($blockingTask['id'] ?? 0);
            $blockedTaskId = (int) ($blockedTask['id'] ?? 0);

            // Deduplicate: Kanboard stores bidirectional rows (blocks + is blocked by)
            // for each logical dependency. Only keep one direction per pair.
            $edgeKey = $blockingTaskId . ':' . $blockedTaskId;
            if (isset($seenEdges[$edgeKey])) {
                continue;
            }
            $seenEdges[$edgeKey] = true;

            $blockingTaskIsActive = (int) ($blockingTask['is_active'] ?? 1);
            $blockedTaskIsActive = (int) ($blockedTask['is_active'] ?? 1);

            $dependencies[] = [
                'link_id' => $taskLink['id'] ?? $linkTypeId,
                'link_label' => $this->resolveLinkLabel($linkDefinition, $taskBlocksOpposite),
                'task_id' => $blockingTask['id'] ?? 0,
                'task_title' => (string) ($blockingTask['title'] ?? ''),
                'task_project_id' => $blockingTask['project_id'] ?? 0,
                'task_project_name' => $this->getProjectName((int) ($blockingTask['project_id'] ?? 0)),
                'task_is_active' => $blockingTask['is_active'] ?? 1,
                'opposite_task_id' => $blockedTask['id'] ?? 0,
                'opposite_task_title' => (string) ($blockedTask['title'] ?? ''),
                'opposite_task_project_id' => $blockedTask['project_id'] ?? 0,
                'opposite_task_project_name' => $this->getProjectName((int) ($blockedTask['project_id'] ?? 0)),
                'opposite_task_is_active' => $blockedTask['is_active'] ?? 1,
                'is_cross_project' => $isCrossProject,
                'is_resolved' => $blockingTaskIsActive === 0 || $blockedTaskIsActive === 0,
            ];
        }

        usort(
            $dependencies,
            static function (array $left, array $right): int {
                $leftTaskId = (int) ($left['task_id'] ?? 0);
                $rightTaskId = (int) ($right['task_id'] ?? 0);

                if ($leftTaskId !== $rightTaskId) {
                    return $leftTaskId <=> $rightTaskId;
                }

                $leftOppositeTaskId = (int) ($left['opposite_task_id'] ?? 0);
                $rightOppositeTaskId = (int) ($right['opposite_task_id'] ?? 0);

                if ($leftOppositeTaskId !== $rightOppositeTaskId) {
                    return $leftOppositeTaskId <=> $rightOppositeTaskId;
                }

                return (int) ($left['link_id'] ?? 0) <=> (int) ($right['link_id'] ?? 0);
            }
        );

        return $dependencies;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlockedTasks(int $portfolioId): array
    {
        $projectIds = $this->getPortfolioProjectIds($portfolioId);
        if ($projectIds === []) {
            return [];
        }

        $projectScope = [];
        foreach ($projectIds as $projectId) {
            $projectScope[$projectId] = true;
        }

        $dependencies = $this->getDependencies($portfolioId, false);
        if ($dependencies === []) {
            return [];
        }

        $tasks = [];
        $seenBlockers = [];

        foreach ($dependencies as $dependency) {
            $blockedTaskId = (int) ($dependency['opposite_task_id'] ?? 0);
            $blockedProjectId = (int) ($dependency['opposite_task_project_id'] ?? 0);
            $blockedTaskIsActive = (int) ($dependency['opposite_task_is_active'] ?? 1) === 1;
            $isResolved = (bool) ($dependency['is_resolved'] ?? false);

            if ($blockedTaskId <= 0 || ! isset($projectScope[$blockedProjectId]) || ! $blockedTaskIsActive || $isResolved) {
                continue;
            }

            if (! array_key_exists($blockedTaskId, $tasks)) {
                $tasks[$blockedTaskId] = [
                    'id' => $dependency['opposite_task_id'] ?? 0,
                    'title' => (string) ($dependency['opposite_task_title'] ?? ''),
                    'project_id' => $dependency['opposite_task_project_id'] ?? 0,
                    'project_name' => (string) ($dependency['opposite_task_project_name'] ?? ''),
                    'is_active' => $dependency['opposite_task_is_active'] ?? 1,
                    'blockers' => [],
                ];
                $seenBlockers[$blockedTaskId] = [];
            }

            $blockerTaskId = (int) ($dependency['task_id'] ?? 0);
            if ($blockerTaskId <= 0 || array_key_exists($blockerTaskId, $seenBlockers[$blockedTaskId])) {
                continue;
            }

            $tasks[$blockedTaskId]['blockers'][] = [
                'task_id' => $dependency['task_id'] ?? 0,
                'task_title' => (string) ($dependency['task_title'] ?? ''),
                'project_id' => $dependency['task_project_id'] ?? 0,
                'project_name' => (string) ($dependency['task_project_name'] ?? ''),
                'is_active' => $dependency['task_is_active'] ?? 1,
            ];
            $seenBlockers[$blockedTaskId][$blockerTaskId] = true;
        }

        $result = array_values($tasks);

        usort(
            $result,
            static fn (array $left, array $right): int => (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0)
        );

        foreach ($result as &$task) {
            if (! is_array($task['blockers'] ?? null)) {
                $task['blockers'] = [];
                continue;
            }

            usort(
                $task['blockers'],
                static fn (array $left, array $right): int => (int) ($left['task_id'] ?? 0) <=> (int) ($right['task_id'] ?? 0)
            );
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlockingTasks(int $portfolioId): array
    {
        $projectIds = $this->getPortfolioProjectIds($portfolioId);
        if ($projectIds === []) {
            return [];
        }

        $projectScope = [];
        foreach ($projectIds as $projectId) {
            $projectScope[$projectId] = true;
        }

        $dependencies = $this->getDependencies($portfolioId, false);
        if ($dependencies === []) {
            return [];
        }

        $tasks = [];
        $seenBlocked = [];

        foreach ($dependencies as $dependency) {
            $blockingTaskId = (int) ($dependency['task_id'] ?? 0);
            $blockingProjectId = (int) ($dependency['task_project_id'] ?? 0);
            $blockingTaskIsActive = (int) ($dependency['task_is_active'] ?? 1) === 1;
            $blockedTaskIsActive = (int) ($dependency['opposite_task_is_active'] ?? 1) === 1;
            $isResolved = (bool) ($dependency['is_resolved'] ?? false);

            if (
                $blockingTaskId <= 0
                || ! isset($projectScope[$blockingProjectId])
                || ! $blockingTaskIsActive
                || ! $blockedTaskIsActive
                || $isResolved
            ) {
                continue;
            }

            if (! array_key_exists($blockingTaskId, $tasks)) {
                $tasks[$blockingTaskId] = [
                    'id' => $dependency['task_id'] ?? 0,
                    'title' => (string) ($dependency['task_title'] ?? ''),
                    'project_id' => $dependency['task_project_id'] ?? 0,
                    'project_name' => (string) ($dependency['task_project_name'] ?? ''),
                    'is_active' => $dependency['task_is_active'] ?? 1,
                    'blocking' => [],
                ];
                $seenBlocked[$blockingTaskId] = [];
            }

            $blockedTaskId = (int) ($dependency['opposite_task_id'] ?? 0);
            if ($blockedTaskId <= 0 || array_key_exists($blockedTaskId, $seenBlocked[$blockingTaskId])) {
                continue;
            }

            $tasks[$blockingTaskId]['blocking'][] = [
                'task_id' => $dependency['opposite_task_id'] ?? 0,
                'task_title' => (string) ($dependency['opposite_task_title'] ?? ''),
                'project_id' => $dependency['opposite_task_project_id'] ?? 0,
                'project_name' => (string) ($dependency['opposite_task_project_name'] ?? ''),
                'is_active' => $dependency['opposite_task_is_active'] ?? 1,
            ];
            $seenBlocked[$blockingTaskId][$blockedTaskId] = true;
        }

        $result = array_values($tasks);

        usort(
            $result,
            static fn (array $left, array $right): int => (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0)
        );

        foreach ($result as &$task) {
            if (! is_array($task['blocking'] ?? null)) {
                $task['blocking'] = [];
                continue;
            }

            usort(
                $task['blocking'],
                static fn (array $left, array $right): int => (int) ($left['task_id'] ?? 0) <=> (int) ($right['task_id'] ?? 0)
            );
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCriticalPath(int $portfolioId): array
    {
        return $this->buildCriticalPath($portfolioId, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGraph(int $portfolioId, bool $crossProjectOnly = true): array
    {
        $dependencies = $this->getDependencies($portfolioId, $crossProjectOnly);
        if ($dependencies === []) {
            return [
                'nodes' => [],
                'edges' => [],
                'critical_path' => [],
            ];
        }

        $nodes = [];
        $edges = [];
        $seenEdges = [];

        foreach ($dependencies as $dependency) {
            $sourceTaskId = (int) ($dependency['task_id'] ?? 0);
            $targetTaskId = (int) ($dependency['opposite_task_id'] ?? 0);

            if ($sourceTaskId <= 0 || $targetTaskId <= 0) {
                continue;
            }

            $this->addGraphNode($nodes, $sourceTaskId, [
                'title' => (string) ($dependency['task_title'] ?? ''),
                'project_id' => (int) ($dependency['task_project_id'] ?? 0),
                'project_name' => (string) ($dependency['task_project_name'] ?? ''),
                'is_active' => (int) ($dependency['task_is_active'] ?? 1),
            ]);

            $this->addGraphNode($nodes, $targetTaskId, [
                'title' => (string) ($dependency['opposite_task_title'] ?? ''),
                'project_id' => (int) ($dependency['opposite_task_project_id'] ?? 0),
                'project_name' => (string) ($dependency['opposite_task_project_name'] ?? ''),
                'is_active' => (int) ($dependency['opposite_task_is_active'] ?? 1),
            ]);

            $edgeKey = $sourceTaskId . ':' . $targetTaskId . ':' . strtolower((string) ($dependency['link_label'] ?? self::BLOCKS_LABEL));
            if (array_key_exists($edgeKey, $seenEdges)) {
                continue;
            }

            $edges[] = [
                'source' => $sourceTaskId,
                'target' => $targetTaskId,
                'label' => (string) ($dependency['link_label'] ?? self::BLOCKS_LABEL),
                'is_resolved' => (bool) ($dependency['is_resolved'] ?? false),
            ];
            $seenEdges[$edgeKey] = true;
        }

        $nodeList = array_values($nodes);

        usort(
            $nodeList,
            static fn (array $left, array $right): int => (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0)
        );

        usort(
            $edges,
            static function (array $left, array $right): int {
                $sourceCompare = (int) ($left['source'] ?? 0) <=> (int) ($right['source'] ?? 0);
                if ($sourceCompare !== 0) {
                    return $sourceCompare;
                }

                return (int) ($left['target'] ?? 0) <=> (int) ($right['target'] ?? 0);
            }
        );

        $criticalPath = array_map(
            static fn (array $task): int => (int) ($task['id'] ?? 0),
            $this->buildCriticalPath($portfolioId, $crossProjectOnly)
        );

        return [
            'nodes' => $nodeList,
            'edges' => $edges,
            'critical_path' => $criticalPath,
        ];
    }

    public function onTaskClosed(int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }

        $resolvedTask = $this->getTask($taskId);
        if ($resolvedTask === null) {
            return;
        }

        $resolvedProjectId = (int) ($resolvedTask['project_id'] ?? 0);
        if ($resolvedProjectId <= 0) {
            return;
        }

        $portfolioIds = $this->getPortfolioIdsByProjectId($resolvedProjectId);
        if ($portfolioIds === []) {
            return;
        }

        $unblockedTasks = [];

        foreach ($portfolioIds as $portfolioId) {
            $crossProjectDependencies = $this->getDependencies($portfolioId, true);
            if ($crossProjectDependencies === []) {
                continue;
            }

            $allDependencies = $this->getDependencies($portfolioId, false);

            foreach ($crossProjectDependencies as $dependency) {
                $blockingTaskId = (int) ($dependency['task_id'] ?? 0);
                $blockedTaskId = (int) ($dependency['opposite_task_id'] ?? 0);

                if ($blockingTaskId !== $taskId || $blockedTaskId <= 0) {
                    continue;
                }

                if ((int) ($dependency['opposite_task_is_active'] ?? 1) !== 1) {
                    continue;
                }

                if (! (bool) ($dependency['is_resolved'] ?? false)) {
                    continue;
                }

                if (! $this->isTaskUnblockedAfterResolution($blockedTaskId, $taskId, $allDependencies)) {
                    continue;
                }

                if (array_key_exists($blockedTaskId, $unblockedTasks)) {
                    continue;
                }

                $blockedTask = $this->getTask($blockedTaskId);
                if ($blockedTask === null) {
                    continue;
                }

                $blockedProjectId = (int) ($blockedTask['project_id'] ?? 0);
                $unblockedTasks[$blockedTaskId] = [
                    'task_id' => $blockedTaskId,
                    'task_title' => (string) ($blockedTask['title'] ?? ''),
                    'project_id' => $blockedProjectId,
                    'project_name' => $this->getProjectName($blockedProjectId),
                    'owner_id' => (int) ($blockedTask['owner_id'] ?? 0),
                ];
            }
        }

        if ($unblockedTasks === []) {
            return;
        }

        ksort($unblockedTasks);

        $this->dispatchDependencyResolvedEvent([
            'resolved_task_id' => $taskId,
            'resolved_task_title' => (string) ($resolvedTask['title'] ?? ''),
            'resolved_project_id' => $resolvedProjectId,
            'resolved_project_name' => $this->getProjectName($resolvedProjectId),
            'unblocked_tasks' => array_values($unblockedTasks),
        ]);
    }

    public function onTaskOpened(int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }
    }

    public function onLinkChanged(int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }
    }

    /**
     * @return array<int, int>
     */
    private function getPortfolioProjectIds(int $portfolioId): array
    {
        try {
            $memberships = $this->db->table('portfolio_has_projects')
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
     * @return array<int, int>
     */
    private function getPortfolioIdsByProjectId(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        try {
            $memberships = $this->db->table('portfolio_has_projects')
                ->eq('project_id', $projectId)
                ->asc('portfolio_id')
                ->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($memberships)) {
            return [];
        }

        $portfolioIds = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $portfolioId = (int) ($membership['portfolio_id'] ?? 0);
            if ($portfolioId > 0) {
                $portfolioIds[] = $portfolioId;
            }
        }

        return array_values(array_unique($portfolioIds));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDependencyLinkDefinitions(): array
    {
        $configuredLabels = $this->getConfiguredDependencyLabels();
        if ($configuredLabels === []) {
            return [];
        }

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

            if (! $this->isDependencyLinkLabelSet($label, $oppositeLabel, $configuredLabels)) {
                continue;
            }

            $taskBlocksOpposite = $this->resolveTaskBlocksOpposite($label, $oppositeLabel, $configuredLabels);
            if ($taskBlocksOpposite === null) {
                continue;
            }

            $definitions[$linkId] = [
                'id' => $linkId,
                'label' => (string) ($link['label'] ?? ''),
                'opposite_label' => (string) ($link['opposite_label'] ?? ''),
                'task_blocks_opposite' => $taskBlocksOpposite,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    private function getConfiguredDependencyLabels(): array
    {
        $rawLabels = strtolower(trim($this->getConfigValueAsString('portfolio_dependency_link_types', self::BLOCKS_LABEL)));
        $parts = explode(',', $rawLabels);
        $labels = [];

        foreach ($parts as $part) {
            $label = $this->normalizeLinkLabel($part);
            if ($label === '' || in_array($label, $labels, true)) {
                continue;
            }

            $labels[] = $label;
        }

        if ($labels === []) {
            $labels[] = self::BLOCKS_LABEL;
        }

        if (in_array(self::BLOCKS_LABEL, $labels, true) && ! in_array(self::BLOCKED_BY_LABEL, $labels, true)) {
            $labels[] = self::BLOCKED_BY_LABEL;
        }

        if (in_array(self::BLOCKED_BY_LABEL, $labels, true) && ! in_array(self::BLOCKS_LABEL, $labels, true)) {
            $labels[] = self::BLOCKS_LABEL;
        }

        return $labels;
    }

    private function getConfigValueAsString(string $key, string $default): string
    {
        $configModel = $this->resolveContainerService('configModel');
        if (! is_object($configModel) || ! method_exists($configModel, 'get')) {
            return $default;
        }

        try {
            return (string) $configModel->get($key, $default);
        } catch (Throwable $exception) {
            return $default;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getTask(int $taskId): ?array
    {
        $task = $this->db->table('tasks')->eq('id', $taskId)->findOne();

        return is_array($task) ? $task : null;
    }

    private function getProjectName(int $projectId): string
    {
        if ($projectId <= 0) {
            return '';
        }

        $project = $this->db->table('projects')->eq('id', $projectId)->findOne();

        return is_array($project) ? (string) ($project['name'] ?? '') : '';
    }

    private function getTaskAssigneeUsername(int $ownerId): string
    {
        if ($ownerId <= 0) {
            return '';
        }

        try {
            $user = $this->db->table('users')->eq('id', $ownerId)->findOne();
        } catch (Throwable $exception) {
            return '';
        }

        return is_array($user) ? (string) ($user['username'] ?? '') : '';
    }

    private function normalizeLinkLabel(mixed $label): string
    {
        return strtolower(trim((string) $label));
    }

    /**
     * @param array<int, string> $configuredLabels
     */
    private function isDependencyLinkLabelSet(string $label, string $oppositeLabel, array $configuredLabels): bool
    {
        return ($label !== '' && in_array($label, $configuredLabels, true))
            || ($oppositeLabel !== '' && in_array($oppositeLabel, $configuredLabels, true));
    }

    /**
     * @param array<int, string> $configuredLabels
     */
    private function resolveTaskBlocksOpposite(string $label, string $oppositeLabel, array $configuredLabels): ?bool
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

        $labelIsConfigured = false;
        if ($label !== '') {
            $labelIsConfigured = in_array($label, $configuredLabels, true);
        }

        $oppositeIsConfigured = false;
        if ($oppositeLabel !== '') {
            $oppositeIsConfigured = in_array($oppositeLabel, $configuredLabels, true);
        }

        if ($labelIsConfigured) {
            return true;
        }

        if ($oppositeIsConfigured) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $linkDefinition
     */
    private function resolveLinkLabel(array $linkDefinition, bool $taskBlocksOpposite): string
    {
        $label = $taskBlocksOpposite
            ? (string) ($linkDefinition['label'] ?? '')
            : (string) ($linkDefinition['opposite_label'] ?? '');

        return $label !== '' ? $label : self::BLOCKS_LABEL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCriticalPath(int $portfolioId, bool $crossProjectOnly): array
    {
        $graph = $this->buildActiveDependencyGraph($portfolioId, $crossProjectOnly);
        if ($graph['nodes'] === [] || $graph['edges'] === []) {
            return [];
        }

        $nodeIds = array_keys($graph['nodes']);
        sort($nodeIds);

        [$edges, $topologicalOrder] = $this->breakCyclesAndSort($nodeIds, $graph['edges']);
        if ($topologicalOrder === []) {
            return [];
        }

        $adjacency = [];
        foreach ($nodeIds as $nodeId) {
            $adjacency[$nodeId] = [];
        }

        foreach ($edges as $edge) {
            $source = (int) ($edge['source'] ?? 0);
            $target = (int) ($edge['target'] ?? 0);

            if ($source <= 0 || $target <= 0) {
                continue;
            }

            $adjacency[$source][] = $target;
        }

        foreach ($adjacency as &$targets) {
            sort($targets);
            $targets = array_values(array_unique($targets));
        }
        unset($targets);

        // Time-weighted longest path: use each blocker's due date as the
        // edge weight so the critical path is the chain that delays the
        // final task the most, not just the chain with the most hops.
        $nodeWeight = [];
        foreach ($graph['nodes'] as $nid => $nodeData) {
            $dueDate = (int) ($nodeData['date_due'] ?? 0);
            // Fallback: use 1 if no due date, so hop-count still works
            $nodeWeight[$nid] = $dueDate > 0 ? $dueDate : 1;
        }

        $distance = [];
        $previous = [];

        foreach ($topologicalOrder as $nodeId) {
            if (! array_key_exists($nodeId, $distance)) {
                $distance[$nodeId] = $nodeWeight[$nodeId] ?? 1;
            }

            foreach ($adjacency[$nodeId] as $targetId) {
                $candidateDistance = $distance[$nodeId] + ($nodeWeight[$targetId] ?? 1);
                $existingDistance = $distance[$targetId] ?? ($nodeWeight[$targetId] ?? 1);

                if ($candidateDistance > $existingDistance) {
                    $distance[$targetId] = $candidateDistance;
                    $previous[$targetId] = $nodeId;
                }
            }
        }

        $bestEndNode = 0;
        $bestLength = 0;

        foreach ($topologicalOrder as $nodeId) {
            $nodeDistance = $distance[$nodeId] ?? 0;

            if ($nodeDistance > $bestLength) {
                $bestLength = $nodeDistance;
                $bestEndNode = $nodeId;
            }
        }

        if ($bestEndNode <= 0) {
            return [];
        }

        $pathIds = [];
        $guard = count($topologicalOrder) + 1;
        $cursor = $bestEndNode;

        while ($cursor > 0 && $guard > 0) {
            array_unshift($pathIds, $cursor);

            if (! array_key_exists($cursor, $previous)) {
                break;
            }

            $cursor = (int) $previous[$cursor];
            --$guard;
        }

        $descendantMemo = [];
        $criticalPath = [];

        foreach ($pathIds as $index => $taskId) {
            if (! array_key_exists($taskId, $graph['nodes'])) {
                continue;
            }

            $task = $graph['nodes'][$taskId];
            $descendants = $this->collectDescendants($taskId, $adjacency, $descendantMemo);

            $criticalPath[] = [
                'id' => $taskId,
                'title' => (string) ($task['title'] ?? ''),
                'project_id' => (int) ($task['project_id'] ?? 0),
                'project_name' => (string) ($task['project_name'] ?? ''),
                'is_active' => (int) ($task['is_active'] ?? 1),
                'priority' => (int) ($task['priority'] ?? 0),
                'color_id' => (string) ($task['color_id'] ?? ''),
                'assignee' => (string) ($task['assignee'] ?? ''),
                'chain_position' => $index + 1,
                'downstream_count' => count($descendants),
            ];
        }

        return $criticalPath;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>|array<int, array<string, int>>>
     */
    private function buildActiveDependencyGraph(int $portfolioId, bool $crossProjectOnly): array
    {
        $dependencies = $this->getDependencies($portfolioId, $crossProjectOnly);
        if ($dependencies === []) {
            return [
                'nodes' => [],
                'edges' => [],
            ];
        }

        $nodes = [];
        $edges = [];
        $edgeIndex = [];

        foreach ($dependencies as $sequence => $dependency) {
            if ((bool) ($dependency['is_resolved'] ?? false)) {
                continue;
            }

            $source = (int) ($dependency['task_id'] ?? 0);
            $target = (int) ($dependency['opposite_task_id'] ?? 0);
            $sourceIsActive = (int) ($dependency['task_is_active'] ?? 1) === 1;
            $targetIsActive = (int) ($dependency['opposite_task_is_active'] ?? 1) === 1;

            if ($source <= 0 || $target <= 0 || ! $sourceIsActive || ! $targetIsActive || $source === $target) {
                continue;
            }

            $this->addGraphNode($nodes, $source, [
                'title' => (string) ($dependency['task_title'] ?? ''),
                'project_id' => (int) ($dependency['task_project_id'] ?? 0),
                'project_name' => (string) ($dependency['task_project_name'] ?? ''),
                'is_active' => (int) ($dependency['task_is_active'] ?? 1),
            ]);

            $this->addGraphNode($nodes, $target, [
                'title' => (string) ($dependency['opposite_task_title'] ?? ''),
                'project_id' => (int) ($dependency['opposite_task_project_id'] ?? 0),
                'project_name' => (string) ($dependency['opposite_task_project_name'] ?? ''),
                'is_active' => (int) ($dependency['opposite_task_is_active'] ?? 1),
            ]);

            $key = $source . ':' . $target;
            $candidateEdge = [
                'source' => $source,
                'target' => $target,
                'sequence' => (int) ($dependency['link_id'] ?? $sequence),
            ];

            if (! array_key_exists($key, $edgeIndex)) {
                $edgeIndex[$key] = count($edges);
                $edges[] = $candidateEdge;
                continue;
            }

            $existingEdgeIndex = $edgeIndex[$key];
            $existingSequence = (int) ($edges[$existingEdgeIndex]['sequence'] ?? 0);
            if ($candidateEdge['sequence'] >= $existingSequence) {
                $edges[$existingEdgeIndex] = $candidateEdge;
            }
        }

        if ($edges === []) {
            return [
                'nodes' => [],
                'edges' => [],
            ];
        }

        usort(
            $edges,
            static function (array $left, array $right): int {
                $sourceCompare = (int) ($left['source'] ?? 0) <=> (int) ($right['source'] ?? 0);
                if ($sourceCompare !== 0) {
                    return $sourceCompare;
                }

                return (int) ($left['target'] ?? 0) <=> (int) ($right['target'] ?? 0);
            }
        );

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, mixed> $fallback
     */
    private function addGraphNode(array &$nodes, int $taskId, array $fallback): void
    {
        if ($taskId <= 0) {
            return;
        }

        if (array_key_exists($taskId, $nodes)) {
            return;
        }

        $task = $this->getTask($taskId);
        $taskProjectId = (int) ($task['project_id'] ?? ($fallback['project_id'] ?? 0));
        $ownerId = (int) ($task['owner_id'] ?? 0);

        $nodes[$taskId] = [
            'id' => $taskId,
            'title' => (string) ($task['title'] ?? ($fallback['title'] ?? '')),
            'project_id' => $taskProjectId,
            'project_name' => $taskProjectId > 0
                ? $this->getProjectName($taskProjectId)
                : (string) ($fallback['project_name'] ?? ''),
            'is_active' => (int) ($task['is_active'] ?? ($fallback['is_active'] ?? 1)),
            'priority' => (int) ($task['priority'] ?? 0),
            'color_id' => (string) ($task['color_id'] ?? ''),
            'assignee' => $this->getTaskAssigneeUsername($ownerId),
            'date_due' => (int) ($task['date_due'] ?? 0),
        ];
    }

    /**
     * @param array<int, int> $nodeIds
     * @param array<int, array<string, int>> $edges
     *
     * @return array{0: array<int, array<string, int>>, 1: array<int, int>}
     */
    private function breakCyclesAndSort(array $nodeIds, array $edges): array
    {
        $workingEdges = array_values($edges);
        $topologicalOrder = $this->topologicalSort($nodeIds, $workingEdges);

        while (count($topologicalOrder) < count($nodeIds) && $workingEdges !== []) {
            $sortedNodes = array_flip($topologicalOrder);
            $cycleNodes = [];

            foreach ($nodeIds as $nodeId) {
                if (! array_key_exists($nodeId, $sortedNodes)) {
                    $cycleNodes[$nodeId] = true;
                }
            }

            $removeIndex = null;
            $removeSequence = PHP_INT_MIN;

            foreach ($workingEdges as $index => $edge) {
                $source = (int) ($edge['source'] ?? 0);
                $target = (int) ($edge['target'] ?? 0);

                if (! isset($cycleNodes[$source]) || ! isset($cycleNodes[$target])) {
                    continue;
                }

                $sequence = (int) ($edge['sequence'] ?? 0);
                if ($sequence >= $removeSequence) {
                    $removeSequence = $sequence;
                    $removeIndex = $index;
                }
            }

            if ($removeIndex === null) {
                break;
            }

            unset($workingEdges[$removeIndex]);
            $workingEdges = array_values($workingEdges);
            $topologicalOrder = $this->topologicalSort($nodeIds, $workingEdges);
        }

        if (count($topologicalOrder) < count($nodeIds)) {
            $existing = array_flip($topologicalOrder);
            $missing = [];

            foreach ($nodeIds as $nodeId) {
                if (! array_key_exists($nodeId, $existing)) {
                    $missing[] = $nodeId;
                }
            }

            sort($missing);
            $topologicalOrder = array_merge($topologicalOrder, $missing);
        }

        return [$workingEdges, $topologicalOrder];
    }

    /**
     * @param array<int, int> $nodeIds
     * @param array<int, array<string, int>> $edges
     *
     * @return array<int, int>
     */
    private function topologicalSort(array $nodeIds, array $edges): array
    {
        $indegree = [];
        $adjacency = [];

        foreach ($nodeIds as $nodeId) {
            $indegree[$nodeId] = 0;
            $adjacency[$nodeId] = [];
        }

        foreach ($edges as $edge) {
            $source = (int) ($edge['source'] ?? 0);
            $target = (int) ($edge['target'] ?? 0);

            if (! array_key_exists($source, $indegree) || ! array_key_exists($target, $indegree)) {
                continue;
            }

            $adjacency[$source][] = $target;
            ++$indegree[$target];
        }

        foreach ($adjacency as &$targets) {
            sort($targets);
            $targets = array_values(array_unique($targets));
        }
        unset($targets);

        $queue = [];
        foreach ($indegree as $nodeId => $count) {
            if ($count === 0) {
                $queue[] = $nodeId;
            }
        }

        sort($queue);

        $order = [];

        while ($queue !== []) {
            $nodeId = (int) array_shift($queue);
            $order[] = $nodeId;

            foreach ($adjacency[$nodeId] as $targetId) {
                --$indegree[$targetId];
                if ($indegree[$targetId] === 0) {
                    $queue[] = $targetId;
                }
            }

            sort($queue);
        }

        return $order;
    }

    /**
     * @param array<int, array<int, int>> $adjacency
     * @param array<int, array<int, bool>> $memo
     *
     * @return array<int, bool>
     */
    private function collectDescendants(int $nodeId, array $adjacency, array &$memo): array
    {
        if (array_key_exists($nodeId, $memo)) {
            return $memo[$nodeId];
        }

        $descendants = [];

        foreach ($adjacency[$nodeId] ?? [] as $childId) {
            $descendants[$childId] = true;

            foreach ($this->collectDescendants($childId, $adjacency, $memo) as $grandChildId => $trueValue) {
                $descendants[$grandChildId] = $trueValue;
            }
        }

        $memo[$nodeId] = $descendants;

        return $descendants;
    }

    /**
     * @param array<int, array<string, mixed>> $dependencies
     */
    private function isTaskUnblockedAfterResolution(int $blockedTaskId, int $resolvedTaskId, array $dependencies): bool
    {
        foreach ($dependencies as $dependency) {
            $candidateBlockedTaskId = (int) ($dependency['opposite_task_id'] ?? 0);
            $candidateBlockingTaskId = (int) ($dependency['task_id'] ?? 0);
            $candidateResolved = (bool) ($dependency['is_resolved'] ?? false);
            $blockedTaskIsActive = (int) ($dependency['opposite_task_is_active'] ?? 1) === 1;

            if ($candidateBlockedTaskId !== $blockedTaskId || ! $blockedTaskIsActive) {
                continue;
            }

            if ($candidateBlockingTaskId === $resolvedTaskId) {
                continue;
            }

            if (! $candidateResolved) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $eventPayload
     */
    private function dispatchDependencyResolvedEvent(array $eventPayload): void
    {
        $dispatcher = $this->resolveContainerService('dispatcher');
        if (is_object($dispatcher) && method_exists($dispatcher, 'dispatch')) {
            $dispatcher->dispatch('portfolio.dependency.resolved', $eventPayload);

            return;
        }

        $eventManager = $this->resolveContainerService('eventManager');
        if (is_object($eventManager) && method_exists($eventManager, 'fire')) {
            $eventManager->fire('portfolio.dependency.resolved', $eventPayload);
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
