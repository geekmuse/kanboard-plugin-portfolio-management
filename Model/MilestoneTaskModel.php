<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use Kanboard\Core\Base;

class MilestoneTaskModel extends Base
{
    private const MEMBERSHIP_TABLE = 'milestone_has_tasks';

    public function add(int $milestoneId, int $taskId, int $isCritical = 0, int $position = 0): bool
    {
        $milestone = $this->getMilestone($milestoneId);
        $task = $this->getTask($taskId);

        if ($milestone === null || $task === null) {
            return false;
        }

        $portfolioId = (int) ($milestone['portfolio_id'] ?? 0);
        $projectId = (int) ($task['project_id'] ?? 0);

        if ($portfolioId <= 0 || $projectId <= 0) {
            return false;
        }

        if (! $this->projectBelongsToPortfolio($portfolioId, $projectId)) {
            return false;
        }

        if ($this->membershipExists($milestoneId, $taskId)) {
            return false;
        }

        $result = $this->db->table(self::MEMBERSHIP_TABLE)->insert([
            'milestone_id' => $milestoneId,
            'task_id' => $taskId,
            'position' => $position,
            'is_critical' => $isCritical > 0 ? 1 : 0,
            'added_at' => time(),
        ]);

        return (bool) $result;
    }

    public function remove(int $milestoneId, int $taskId): bool
    {
        if (! $this->membershipExists($milestoneId, $taskId)) {
            return false;
        }

        return (bool) $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('milestone_id', $milestoneId)
            ->eq('task_id', $taskId)
            ->remove();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTasks(int $milestoneId): array
    {
        $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('milestone_id', $milestoneId)
            ->asc('position')
            ->findAll();

        if (! is_array($memberships)) {
            return [];
        }

        $tasks = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $taskId = (int) ($membership['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $task = $this->getTask($taskId);
            if ($task === null) {
                continue;
            }

            $project = $this->db->table('projects')->eq('id', (int) ($task['project_id'] ?? 0))->findOne();
            $column = $this->db->table('columns')->eq('id', (int) ($task['column_id'] ?? 0))->findOne();
            $assignee = $this->db->table('users')->eq('id', (int) ($task['owner_id'] ?? 0))->findOne();

            $tasks[] = [
                'id' => $task['id'] ?? 0,
                'title' => $task['title'] ?? '',
                'project_id' => $task['project_id'] ?? 0,
                'project_name' => is_array($project) ? ($project['name'] ?? '') : '',
                'column_id' => $task['column_id'] ?? 0,
                'column_title' => is_array($column) ? ($column['title'] ?? '') : '',
                'owner_id' => $task['owner_id'] ?? 0,
                'assignee_username' => is_array($assignee) ? ($assignee['username'] ?? '') : '',
                'assignee_name' => is_array($assignee) ? ($assignee['name'] ?? '') : '',
                'is_active' => $task['is_active'] ?? 1,
                'date_due' => $task['date_due'] ?? 0,
                'priority' => $task['priority'] ?? 0,
                'color_id' => $task['color_id'] ?? '',
                'position' => $membership['position'] ?? 0,
                'is_critical' => $membership['is_critical'] ?? 0,
                'added_at' => $membership['added_at'] ?? 0,
            ];
        }

        return $tasks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMilestones(int $taskId): array
    {
        $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('task_id', $taskId)
            ->asc('milestone_id')
            ->findAll();

        if (! is_array($memberships)) {
            return [];
        }

        $milestones = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $milestoneId = (int) ($membership['milestone_id'] ?? 0);
            if ($milestoneId <= 0) {
                continue;
            }

            $milestone = $this->getMilestone($milestoneId);
            if ($milestone === null) {
                continue;
            }

            $milestone['position'] = $membership['position'] ?? 0;
            $milestone['is_critical'] = $membership['is_critical'] ?? 0;
            $milestone['added_at'] = $membership['added_at'] ?? 0;

            $milestones[] = $milestone;
        }

        return $milestones;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMilestone(int $milestoneId): ?array
    {
        $milestone = $this->db->table('milestones')->eq('id', $milestoneId)->findOne();

        return is_array($milestone) ? $milestone : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getTask(int $taskId): ?array
    {
        $task = $this->db->table('tasks')->eq('id', $taskId)->findOne();

        return is_array($task) ? $task : null;
    }

    private function projectBelongsToPortfolio(int $portfolioId, int $projectId): bool
    {
        $membership = $this->db->table('portfolio_has_projects')
            ->eq('portfolio_id', $portfolioId)
            ->eq('project_id', $projectId)
            ->findOne();

        return is_array($membership);
    }

    private function membershipExists(int $milestoneId, int $taskId): bool
    {
        $membership = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('milestone_id', $milestoneId)
            ->eq('task_id', $taskId)
            ->findOne();

        return is_array($membership);
    }
}
