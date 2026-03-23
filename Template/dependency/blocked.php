<section class="sidebar-container">
    <?php $sidebar_active = "blocked"; require __DIR__ . "/../portfolio/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-blocked-title">
        <?= $this->text->e($title ?? t('Blocked Tasks')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    </div>

<?php $blockedTasks = $blocked_tasks ?? [] ?>

<?php if (empty($blockedTasks)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No blocked tasks found for this portfolio.')) ?></p>
<?php else: ?>
    <p class="portfolio-dependency-count">
        <?= $this->text->e(t('Blocked Tasks')) ?>: <?= $this->text->e((string) count($blockedTasks)) ?>
    </p>

    <div class="portfolio-blocked-task-list">
        <?php foreach ($blockedTasks as $blockedTask): ?>
            <?php $blockers = (array) ($blockedTask['blockers'] ?? []) ?>
            <div class="portfolio-blocked-task-item">
                <div class="portfolio-blocked-task-header">
                    <span class="portfolio-badge portfolio-task-blocked"><?= $this->text->e(t('Blocked')) ?></span>
                    <strong class="portfolio-blocked-task-title">
                        #<?= $this->text->e((string) ((int) ($blockedTask['id'] ?? 0))) ?>
                        <?= $this->text->e((string) ($blockedTask['title'] ?? '')) ?>
                    </strong>
                    <span class="portfolio-blocked-task-project">
                        (<?= $this->text->e((string) ($blockedTask['project_name'] ?? '')) ?>)
                    </span>
                </div>

                <?php if (! empty($blockers)): ?>
                    <div class="portfolio-blocker-list">
                        <span class="portfolio-blocker-label"><?= $this->text->e(t('Blocked by')) ?>:</span>
                        <ul class="portfolio-blocker-items">
                            <?php foreach ($blockers as $blocker): ?>
                                <li class="portfolio-blocker-item">
                                    #<?= $this->text->e((string) ((int) ($blocker['task_id'] ?? 0))) ?>
                                    <?= $this->text->e((string) ($blocker['task_title'] ?? '')) ?>
                                    <span class="portfolio-blocker-project">
                                        (<?= $this->text->e((string) ($blocker['project_name'] ?? '')) ?>)
                                    </span>
                                    <?php if ((int) ($blocker['is_active'] ?? 1) === 0): ?>
                                        <span class="portfolio-badge portfolio-task-closed"><?= $this->text->e(t('Closed')) ?></span>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

    </div>
</section>
