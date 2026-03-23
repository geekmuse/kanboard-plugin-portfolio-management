<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Helper;

use Kanboard\Core\Base;

/**
 * PortfolioHelper
 *
 * Provides utility methods consumed by widget templates and controllers.
 * Includes lazy-per-project caching for board blocked-status preloading
 * to prevent N+1 queries when rendering Kanboard board cards.
 */
class PortfolioHelper extends Base
{
    /**
     * Per-project cache of blocked task IDs gathered from all portfolios
     * that contain the project.
     *
     * @var array<int, int[]>  project_id → [ task_id, ... ]
     */
    private array $boardBlockedCache = [];

    /**
     * Tracks which projects have already had their blocked status loaded.
     *
     * @var array<int, bool>  project_id → true
     */
    private array $boardBlockedLoaded = [];

    // -----------------------------------------------------------------------
    // Board blocked-status (N+1 prevention)
    // -----------------------------------------------------------------------

    /**
     * Return true when $taskId is blocked (cross-project) within any portfolio
     * that contains $projectId.
     *
     * The first call for a given $projectId queries the dependency model once
     * and stores the results; all subsequent calls for the same project are
     * resolved from the in-memory cache (O(1)).
     */
    public function isTaskBlocked(int $taskId, int $projectId): bool
    {
        $this->ensureBoardBlockedLoaded($projectId);

        return in_array($taskId, $this->boardBlockedCache[$projectId] ?? [], true);
    }

    /**
     * Explicitly warm the blocked-status cache for the given project.
     * Idempotent — safe to call multiple times per request.
     */
    public function preloadBoardBlockedStatus(int $projectId): void
    {
        $this->ensureBoardBlockedLoaded($projectId);
    }

    /**
     * Lazy-load blocked task IDs for all portfolios that contain $projectId.
     */
    private function ensureBoardBlockedLoaded(int $projectId): void
    {
        if (! empty($this->boardBlockedLoaded[$projectId])) {
            return;
        }

        $this->boardBlockedLoaded[$projectId] = true;
        $this->boardBlockedCache[$projectId]  = [];

        if (! is_object($this->portfolioProjectModel) || ! is_object($this->dependencyModel)) {
            return;
        }

        $portfolios = $this->portfolioProjectModel->getPortfolios($projectId);
        if (! is_array($portfolios) || empty($portfolios)) {
            return;
        }

        $blockedIds = [];

        foreach ($portfolios as $portfolio) {
            $portfolioId = (int) ($portfolio['id'] ?? 0);
            if ($portfolioId <= 0) {
                continue;
            }

            $blockedTasks = $this->dependencyModel->getBlockedTasks($portfolioId);
            if (! is_array($blockedTasks)) {
                continue;
            }

            foreach ($blockedTasks as $task) {
                $id = (int) ($task['id'] ?? 0);
                if ($id > 0) {
                    $blockedIds[] = $id;
                }
            }
        }

        $this->boardBlockedCache[$projectId] = array_values(array_unique($blockedIds));
    }

    // -----------------------------------------------------------------------
    // Project → Portfolio lookups
    // -----------------------------------------------------------------------

    /**
     * Return the portfolios that contain the given project.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPortfoliosForProject(int $projectId): array
    {
        if (! is_object($this->portfolioProjectModel) || ! method_exists($this->portfolioProjectModel, 'getPortfolios')) {
            return [];
        }

        $result = $this->portfolioProjectModel->getPortfolios($projectId);

        return is_array($result) ? $result : [];
    }

    // -----------------------------------------------------------------------
    // Global portfolio / milestone helpers (dashboard widget)
    // -----------------------------------------------------------------------

    /**
     * Return all portfolios, ordered by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPortfolios(): array
    {
        if (! is_object($this->portfolioModel) || ! method_exists($this->portfolioModel, 'getAll')) {
            return [];
        }

        $result = $this->portfolioModel->getAll();

        return is_array($result) ? $result : [];
    }

    /**
     * Return milestones that are at-risk or overdue across all portfolios.
     * Each entry is a milestone array augmented with 'progress', 'portfolio_name',
     * and 'portfolio_id' keys.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGlobalAtRiskMilestones(): array
    {
        if (! is_object($this->milestoneModel) || ! is_object($this->portfolioModel)) {
            return [];
        }

        $portfolios = $this->getAllPortfolios();
        $atRisk     = [];

        foreach ($portfolios as $portfolio) {
            $portfolioId = (int) ($portfolio['id'] ?? 0);
            if ($portfolioId <= 0) {
                continue;
            }

            $milestones = $this->milestoneModel->getByPortfolioId($portfolioId);
            if (! is_array($milestones)) {
                continue;
            }

            foreach ($milestones as $milestone) {
                $milestoneId = (int) ($milestone['id'] ?? 0);
                if ($milestoneId <= 0) {
                    continue;
                }

                $progress = $this->milestoneModel->getProgress($milestoneId);
                if (! is_array($progress)) {
                    continue;
                }

                if (($progress['is_at_risk'] ?? false) || ($progress['is_overdue'] ?? false)) {
                    $atRisk[] = array_merge($milestone, [
                        'progress'       => $progress,
                        'portfolio_name' => (string) ($portfolio['name'] ?? ''),
                        'portfolio_id'   => $portfolioId,
                    ]);
                }
            }
        }

        return $atRisk;
    }
}
