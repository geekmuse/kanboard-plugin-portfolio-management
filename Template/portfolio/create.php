<section class="sidebar-container">
    <?php $sidebar_active = 'create'; $portfolio = null; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
        <div class="page-header">
            <h2><?= $this->text->e($title ?? t('Create Portfolio')) ?></h2>
        </div>

        <?php if (! empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $message): ?>
                    <p><?= $this->text->e((string) $message) ?></p>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <form method="post" action="<?= $this->url->href('PortfolioModificationController', 'save', ['plugin' => 'Portfolio']) ?>">
            <?= $this->form->csrf() ?>
            <?php require __DIR__ . '/_form.php' ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Save')) ?></button>
                <?= $this->text->e(t('or')) ?>
                <a href="<?= $this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']) ?>">
                    <?= $this->text->e(t('Cancel')) ?>
                </a>
            </div>
        </form>
    </div>
</section>
