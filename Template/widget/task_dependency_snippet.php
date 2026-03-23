<?php
/**
 * Task detail sidebar — cross-project dependency note.
 *
 * Reuses the same PortfolioHelper cache already warmed by board_blocked_indicator
 * (or warms it lazily on first call for this project).
 *
 * @var array<string, mixed> $task
 */
$taskId    = (int) ($task['id'] ?? 0);
$projectId = (int) ($task['project_id'] ?? 0);
$isBlocked = ($taskId > 0 && $projectId > 0)
    ? $this->portfolioHelper->isTaskBlocked($taskId, $projectId)
    : false;

$portfolios = ($projectId > 0)
    ? $this->portfolioHelper->getPortfoliosForProject($projectId)
    : [];
?>
<?php if ($isBlocked): ?>
<div class="portfolio-widget-task-dependency">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Portfolio Dependencies')) ?></strong>
    <p class="portfolio-widget-dependency-blocked">
        <?= $this->text->e(t('This task is blocked by a cross-project dependency.')) ?>
    </p>
    <?php if (! empty($portfolios)): ?>
    <ul class="portfolio-widget-list">
        <?php foreach ($portfolios as $pf): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('DependencyController', 'blocked', ['portfolio_id' => (int) ($pf['id'] ?? 0)], 'Portfolio') ?>">
                <?= $this->text->e((string) ($pf['name'] ?? '')) ?>
                &mdash; <?= $this->text->e(t('Blocked Tasks')) ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>
<?php endif ?>
