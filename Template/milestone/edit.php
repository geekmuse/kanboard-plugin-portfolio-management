<section class="sidebar-container">
    <?php $sidebar_active = "milestones"; require __DIR__ . "/../portfolio/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-milestone-edit-title"><?= $this->text->e($title ?? t('Edit Milestone')) ?></h2>
</div>

<p class="portfolio-milestone-edit-portfolio-name">
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

<form method="post" action="<?= $this->url->href('MilestoneController', 'update', ['milestone_id' => (int) ($milestone['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-form">
    <?= $this->form->csrf() ?>
    <?php require __DIR__ . '/_form.php' ?>

    <div class="portfolio-form-actions">
        <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Save')) ?></button>
        <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($milestone['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="btn">
            <?= $this->text->e(t('Cancel')) ?>
        </a>
    </div>
</form>

    </div>
</section>
