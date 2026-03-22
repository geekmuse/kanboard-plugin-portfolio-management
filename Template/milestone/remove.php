<div class="page-header">
    <h2 class="portfolio-milestone-remove-title"><?= $this->text->e($title ?? t('Remove Milestone')) ?></h2>
</div>

<p class="portfolio-milestone-remove-message">
    <?= $this->text->e(t('Do you really want to remove this milestone?')) ?>
</p>

<p class="portfolio-milestone-remove-target">
    <strong><?= $this->text->e(t('Milestone Name')) ?>:</strong>
    <?= $this->text->e((string) ($milestone['name'] ?? '')) ?>
</p>

<form method="post" action="<?= $this->url->href('MilestoneController', 'delete', ['milestone_id' => (int) ($milestone['id'] ?? 0)], 'Portfolio') ?>" class="portfolio-milestone-remove-form">
    <?= $this->form->csrf() ?>

    <button type="submit" class="btn btn-red"><?= $this->text->e(t('Remove Milestone')) ?></button>
    <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($milestone['id'] ?? 0)], 'Portfolio') ?>" class="btn">
        <?= $this->text->e(t('Cancel')) ?>
    </a>
</form>
