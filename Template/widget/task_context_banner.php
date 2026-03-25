<?php
/**
 * Task detail — portfolio context banner (rendered at top of task detail page).
 *
 * Variables pre-fetched by Plugin::attachCallable():
 *
 * @var array<int, array<string, mixed>> $portfolios  Portfolios that contain this task's project.
 *                                                    Empty when the project belongs to no portfolios.
 */
?>
<?php if (! empty($portfolios)): ?>
<div class="portfolio-widget-task-banner">
    <strong class="portfolio-widget-banner-label"><?= $this->text->e(t('This task is in Portfolio:')) ?></strong>
    <?php foreach ($portfolios as $key => $portfolio): ?>
    <?php if ($key > 0): ?><span class="portfolio-widget-banner-separator">,</span> <?php endif ?>
    <a
        href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>"
        class="portfolio-widget-banner-link"
    ><?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></a>
    <?php endforeach ?>
</div>
<?php endif ?>
