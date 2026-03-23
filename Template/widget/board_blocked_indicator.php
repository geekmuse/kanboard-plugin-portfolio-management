<?php
/**
 * Board card footer — blocked-status badge.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 * @var bool $isBlocked
 */
?>
<?php if (! empty($isBlocked)): ?>
<span
    class="portfolio-board-blocked-badge portfolio-badge portfolio-task-blocked"
    title="<?= $this->text->e(t('Blocked by cross-project dependency')) ?>"
>
    <?= $this->text->e(t('Blocked')) ?>
</span>
<?php endif ?>
