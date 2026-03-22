<div class="page-header">
    <h2 class="portfolio-milestone-create-title"><?= $this->text->e($title ?? t('Create Milestone')) ?></h2>
</div>

<p class="portfolio-milestone-create-portfolio-name">
    <strong><?= $this->text->e(t('Portfolio Name')) ?>:</strong>
    <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
</p>

<?php if (! empty($errors)): ?>
    <div class="alert alert-error portfolio-form-errors">
        <?php foreach ($errors as $message): ?>
            <p><?= $this->text->e((string) $message) ?></p>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= $this->url->href('MilestoneController', 'save', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>" class="portfolio-form">
    <?= $this->form->csrf() ?>
    <?php require __DIR__ . '/_form.php' ?>

    <div class="portfolio-form-actions">
        <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Save')) ?></button>
        <a href="<?= $this->url->href('MilestoneController', 'index', ['portfolio_id' => (int) ($portfolio['id'] ?? 0)], 'Portfolio') ?>" class="btn">
            <?= $this->text->e(t('Cancel')) ?>
        </a>
    </div>
</form>
