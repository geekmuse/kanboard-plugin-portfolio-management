<?php
/**
 * Task detail sidebar — portfolio milestone membership.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 * @var array<int, array<string, mixed>> $milestones
 */
?>
<?php if (! empty($milestones)): ?>
<div class="portfolio-widget-task-milestones">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Milestones')) ?></strong>
    <ul class="portfolio-widget-list">
        <?php foreach ($milestones as $ms): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($ms['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e((string) ($ms['name'] ?? '')) ?>
            </a>
            <?php if ((int) ($ms['is_critical'] ?? 0)): ?>
            <span class="portfolio-badge portfolio-badge--critical"><?= $this->text->e(t('Critical')) ?></span>
            <?php endif ?>
        </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>
