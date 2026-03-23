<?php
/**
 * Board card footer — blocked-status badge.
 *
 * Blocked task IDs are fetched once per project per request via
 * PortfolioHelper::isTaskBlocked() which lazily loads and caches the full
 * set of blocked IDs for all portfolios containing the project, preventing
 * an N+1 query pattern during board rendering.
 *
 * @var array<string, mixed> $task
 */
$taskId    = (int) ($task['id'] ?? 0);
$projectId = (int) ($task['project_id'] ?? 0);
$isBlocked = ($taskId > 0 && $projectId > 0)
    ? $this->portfolioHelper->isTaskBlocked($taskId, $projectId)
    : false;
?>
<?php if ($isBlocked): ?>
<span
    class="portfolio-board-blocked-badge portfolio-badge portfolio-task-blocked"
    title="<?= $this->text->e(t('Blocked by cross-project dependency')) ?>"
>
    <?= $this->text->e(t('Blocked')) ?>
</span>
<?php endif ?>
