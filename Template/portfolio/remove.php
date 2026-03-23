<div class="page-header">
    <h2 class="portfolio-remove-title"><?= $this->text->e($title ?? t('Remove Portfolio')) ?></h2>
</div>

<p class="portfolio-remove-message">
    <?= $this->text->e(t('Do you really want to remove this portfolio? All milestones will also be removed.')) ?>
</p>

<p class="portfolio-remove-target">
    <strong><?= $this->text->e(t('Portfolio Name')) ?>:</strong>
    <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
</p>

<form method="post" action="<?= $this->url->href('PortfolioModificationController', 'delete', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-remove-form">
    <?= $this->form->csrf() ?>

    <button type="submit" class="btn btn-red"><?= $this->text->e(t('Remove Portfolio')) ?></button>
    <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn">
        <?= $this->text->e(t('Cancel')) ?>
    </a>
</form>
