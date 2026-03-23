<?php
/**
 * Task detail sidebar — cross-project dependency note.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 * @var bool                             $isBlocked
 * @var array<int, array<string, mixed>> $portfolios
 */
?>
<?php if (! empty($isBlocked)): ?>
<div class="portfolio-widget-task-dependency">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Portfolio Dependencies')) ?></strong>
    <p class="portfolio-widget-dependency-blocked">
        <?= $this->text->e(t('This task is blocked by a cross-project dependency.')) ?>
    </p>
    <?php if (! empty($portfolios)): ?>
    <ul class="portfolio-widget-list">
        <?php foreach ($portfolios as $pf): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('DependencyController', 'blocked', ['portfolio_id' => (int) ($pf['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e((string) ($pf['name'] ?? '')) ?>
                &mdash; <?= $this->text->e(t('Blocked Tasks')) ?>
            </a>
        </li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>
<?php endif ?>
