<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use Kanboard\Core\Base;

class PortfolioProjectModel extends Base
{
    private const MEMBERSHIP_TABLE = 'portfolio_has_projects';

    /**
     * @return bool
     */
    public function add(int $portfolioId, int $projectId, int $position = 0): bool
    {
        if (! $this->portfolioExists($portfolioId) || ! $this->projectExists($projectId)) {
            return false;
        }

        if ($this->membershipExists($portfolioId, $projectId)) {
            return false;
        }

        $result = $this->db->table(self::MEMBERSHIP_TABLE)->insert([
            'portfolio_id' => $portfolioId,
            'project_id' => $projectId,
            'position' => $position,
            'added_at' => time(),
        ]);

        return (bool) $result;
    }

    public function remove(int $portfolioId, int $projectId): bool
    {
        if (! $this->membershipExists($portfolioId, $projectId)) {
            return false;
        }

        $removed = (bool) $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->eq('project_id', $projectId)
            ->remove();

        if (! $removed) {
            return false;
        }

        $this->removeProjectTasksFromPortfolioMilestones($portfolioId, $projectId);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProjects(int $portfolioId): array
    {
        $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->asc('position')
            ->findAll();

        if (! is_array($memberships)) {
            return [];
        }

        $projects = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $projectId = (int) ($membership['project_id'] ?? 0);
            if ($projectId === 0) {
                continue;
            }

            $project = $this->db->table('projects')->eq('id', $projectId)->findOne();
            if (! is_array($project)) {
                continue;
            }

            $project['position'] = $membership['position'] ?? 0;
            $project['added_at'] = $membership['added_at'] ?? 0;

            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPortfolios(int $projectId): array
    {
        $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('project_id', $projectId)
            ->findAll();

        if (! is_array($memberships)) {
            return [];
        }

        $portfolios = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $portfolioId = (int) ($membership['portfolio_id'] ?? 0);
            if ($portfolioId === 0) {
                continue;
            }

            $portfolio = $this->db->table('portfolios')->eq('id', $portfolioId)->findOne();
            if (! is_array($portfolio)) {
                continue;
            }

            $portfolio['position'] = $membership['position'] ?? 0;
            $portfolio['added_at'] = $membership['added_at'] ?? 0;
            $portfolios[] = $portfolio;
        }

        usort(
            $portfolios,
            static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''))
        );

        return $portfolios;
    }

    /**
     * @return array<int, int>
     */
    public function getProjectIds(int $portfolioId): array
    {
        $memberships = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->asc('position')
            ->findAll();

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

        return $projectIds;
    }

    private function portfolioExists(int $portfolioId): bool
    {
        $portfolio = $this->db->table('portfolios')->eq('id', $portfolioId)->findOne();

        return is_array($portfolio);
    }

    private function projectExists(int $projectId): bool
    {
        $project = $this->db->table('projects')->eq('id', $projectId)->findOne();

        return is_array($project);
    }

    private function membershipExists(int $portfolioId, int $projectId): bool
    {
        $membership = $this->db->table(self::MEMBERSHIP_TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->eq('project_id', $projectId)
            ->findOne();

        return is_array($membership);
    }

    private function removeProjectTasksFromPortfolioMilestones(int $portfolioId, int $projectId): void
    {
        $milestones = $this->db->table('milestones')->eq('portfolio_id', $portfolioId)->findAll();
        if (! is_array($milestones) || $milestones === []) {
            return;
        }

        $tasks = $this->db->table('tasks')->eq('project_id', $projectId)->findAll();
        if (! is_array($tasks) || $tasks === []) {
            return;
        }

        foreach ($milestones as $milestone) {
            if (! is_array($milestone)) {
                continue;
            }

            $milestoneId = (int) ($milestone['id'] ?? 0);
            if ($milestoneId === 0) {
                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $taskId = (int) ($task['id'] ?? 0);
                if ($taskId === 0) {
                    continue;
                }

                $this->db->table('milestone_has_tasks')
                    ->eq('milestone_id', $milestoneId)
                    ->eq('task_id', $taskId)
                    ->remove();
            }
        }
    }
}
