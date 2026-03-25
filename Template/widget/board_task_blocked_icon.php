<?php
/**
 * Board card icons — blocked status icon.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 * Reuses the PortfolioHelper per-project lazy cache populated by the
 * board:task:footer hook — no additional DB queries per card.
 *
 * @var bool $isBlocked
 */
?>
<?php if (! empty($isBlocked)): ?>
<span
    class="portfolio-board-blocked-icon portfolio-task-blocked"
    title="<?= $this->text->e(t('Blocked by cross-project dependency')) ?>"
>&#x1F534;</span>
<?php endif ?>
