<?php
/**
 * Task detail sidebar — portfolio milestone membership.
 *
 * @var array<string, mixed> $task
 */
$taskId    = (int) ($task['id'] ?? 0);
$milestones = [];

if ($taskId > 0 && is_object($this->milestoneTaskModel) && method_exists($this->milestoneTaskModel, 'getMilestones')) {
    $result     = $this->milestoneTaskModel->getMilestones($taskId);
    $milestones = is_array($result) ? $result : [];
}
?>
<?php if (! empty($milestones)): ?>
<div class="portfolio-widget-task-milestones">
    <strong class="portfolio-widget-section-label"><?= $this->text->e(t('Milestones')) ?></strong>
    <ul class="portfolio-widget-list">
        <?php foreach ($milestones as $ms): ?>
        <li class="portfolio-widget-list-item">
            <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($ms['id'] ?? 0)], 'Portfolio') ?>">
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
