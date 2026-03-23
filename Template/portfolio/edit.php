<div class="page-header">
    <h2 class="portfolio-edit-title"><?= $this->text->e($title ?? t('Edit Portfolio')) ?></h2>
</div>

<?php if (! empty($errors)): ?>
    <div class="alert alert-error portfolio-form-errors">
        <?php foreach ($errors as $message): ?>
            <p><?= $this->text->e((string) $message) ?></p>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= $this->url->href('PortfolioModificationController', 'update', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-form">
    <?= $this->form->csrf() ?>
    <?php require __DIR__ . '/_form.php' ?>

    <div class="portfolio-form-actions">
        <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Save')) ?></button>
        <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn">
            <?= $this->text->e(t('Cancel')) ?>
        </a>
    </div>
</form>
