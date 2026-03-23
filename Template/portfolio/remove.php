<section class="sidebar-container">
    <?php $sidebar_active = 'remove'; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
        <div class="page-header">
            <h2><?= $this->text->e($title ?? t('Remove Portfolio')) ?></h2>
        </div>

        <div class="confirm">
            <p class="alert alert-info">
                <?= $this->text->e(t('Do you really want to remove this portfolio? All milestones will also be removed.')) ?>
            </p>
            <p><strong><?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></strong></p>

            <div class="form-actions">
                <form method="post" action="<?= $this->url->href('PortfolioModificationController', 'delete', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                    <?= $this->form->csrf() ?>
                    <button type="submit" class="btn btn-red"><?= $this->text->e(t('Yes')) ?></button>
                    <?= $this->text->e(t('or')) ?>
                    <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                        <?= $this->text->e(t('Cancel')) ?>
                    </a>
                </form>
            </div>
        </div>
    </div>
</section>
