<section class="sidebar-container">
    <?php $sidebar_active = "critical-path"; require __DIR__ . "/../portfolio/_sidebar.php"; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-critical-path-title">
        <?= $this->text->e($title ?? t('Critical Path')) ?>:
        <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
    </h2>

    </div>

<?php $criticalPath = $critical_path ?? [] ?>

<?php if (empty($criticalPath)): ?>
    <p class="portfolio-empty-state"><?= $this->text->e(t('No critical path found. Add cross-project task dependencies to compute the critical path.')) ?></p>
<?php else: ?>
    <div class="portfolio-critical-path-summary">
        <span class="portfolio-badge">
            <?= $this->text->e(t('Critical Path Length')) ?>: <?= $this->text->e((string) count($criticalPath)) ?>
        </span>
    </div>

    <div class="portfolio-critical-path-flow">
        <?php $lastIndex = count($criticalPath) - 1 ?>
        <?php foreach ($criticalPath as $index => $task): ?>
            <?php
            $chainPosition  = (int) ($task['chain_position'] ?? 0);
            $downstreamCount = (int) ($task['downstream_count'] ?? 0);
            $isActive       = (int) ($task['is_active'] ?? 1);
            $taskId         = (int) ($task['id'] ?? 0);
            $taskTitle      = (string) ($task['title'] ?? '');
            $projectName    = (string) ($task['project_name'] ?? '');
            $assignee       = (string) ($task['assignee'] ?? '');
            $nodeCls        = 'portfolio-critical-path-node' . ($isActive === 0 ? ' portfolio-critical-path-node--closed' : '');
            ?>
            <div class="<?= $this->text->e($nodeCls) ?>">
                <span class="portfolio-critical-path-pos"><?= $this->text->e((string) $chainPosition) ?></span>
                <div class="portfolio-critical-path-node-body">
                    <strong class="portfolio-critical-path-node-title">
                        #<?= $this->text->e((string) $taskId) ?>
                        <?= $this->text->e($taskTitle) ?>
                    </strong>
                    <span class="portfolio-critical-path-node-meta">
                        <?= $this->text->e($projectName) ?>
                        <?php if ($assignee !== ''): ?>
                            · <?= $this->text->e($assignee) ?>
                        <?php endif ?>
                        <?php if ($isActive === 0): ?>
                            · <em><?= $this->text->e(t('Closed')) ?></em>
                        <?php endif ?>
                        <?php if ($downstreamCount > 0): ?>
                            · <?= $this->text->e(t('%d downstream', $downstreamCount)) ?>
                        <?php endif ?>
                    </span>
                </div>
            </div>
            <?php if ($index < $lastIndex): ?>
                <span class="portfolio-critical-path-arrow" aria-hidden="true">→</span>
            <?php endif ?>
        <?php endforeach ?>
    </div>
<?php endif ?>

    </div>
</section>
