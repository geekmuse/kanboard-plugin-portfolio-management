<section class="sidebar-container">
    <?php $sidebar_active = 'dashboard'; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
        <div class="page-header">
            <h2><?= $this->text->e($title ?? t('Portfolio Dashboard')) ?>: <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></h2>
        </div>

        <div class="listing">
            <h3><?= $this->text->e(t('Overview Metrics')) ?></h3>
            <div class="portfolio-metrics-grid">
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Projects')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['project_count'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Total Tasks')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['task_counts']['total'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Active Tasks')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['task_counts']['active'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Closed Tasks')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['task_counts']['closed'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Blocked Tasks')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['task_counts']['blocked'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('At-Risk Milestones')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['at_risk_milestones'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Overdue Milestones')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['overdue_milestones'] ?? 0))) ?></strong>
                </div>
                <div class="portfolio-metric-card">
                    <span class="portfolio-metric-label"><?= $this->text->e(t('Critical Path Length')) ?></span>
                    <strong class="portfolio-metric-value"><?= $this->text->e((string) ((int) ($overview['critical_path_length'] ?? 0))) ?></strong>
                </div>
            </div>
        </div>

        <div class="listing">
            <h3><?= $this->text->e(t('Project Summary')) ?></h3>
            <?php if (empty($overview['projects'])): ?>
                <p class="alert"><?= $this->text->e(t('No projects assigned to this portfolio.')) ?></p>
            <?php else: ?>
                <table class="table-striped table-scrolling">
                    <thead>
                    <tr>
                        <th><?= $this->text->e(t('Project')) ?></th>
                        <th><?= $this->text->e(t('Status')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($overview['projects'] as $project): ?>
                        <tr>
                            <td><?= $this->text->e((string) ($project['name'] ?? '')) ?></td>
                            <td><?= $this->text->e(((int) ($project['is_active'] ?? 1) === 1) ? t('Active') : t('Inactive')) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>

        <div class="listing">
            <h3><?= $this->text->e(t('Milestone Health')) ?></h3>
            <?php if (empty($overview['milestones'])): ?>
                <p class="alert"><?= $this->text->e(t('No milestones found.')) ?></p>
            <?php else: ?>
                <?php
                $milestonesList = is_array($overview['milestones']) ? $overview['milestones'] : [];
                $hasScoreData = false;
                $hasTimeData = false;
                foreach ($milestonesList as $ms) {
                    if ((int) ($ms['score_total'] ?? 0) > 0) {
                        $hasScoreData = true;
                    }
                    if ((int) ($ms['time_total'] ?? 0) > 0) {
                        $hasTimeData = true;
                    }
                }
                ?>
                <table class="table-striped table-scrolling">
                    <thead>
                    <tr>
                        <th><?= $this->text->e(t('Milestone')) ?></th>
                        <th><?= $this->text->e(t('Target Date')) ?></th>
                        <th><?= $this->text->e(t('Progress')) ?></th>
                        <?php if ($hasScoreData): ?>
                            <th><?= $this->text->e(t('Score')) ?></th>
                        <?php endif ?>
                        <?php if ($hasTimeData): ?>
                            <th><?= $this->text->e(t('Est. Hours')) ?></th>
                        <?php endif ?>
                        <th><?= $this->text->e(t('Health')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($milestonesList as $milestone): ?>
                        <?php
                        $targetDate = (int) ($milestone['target_date'] ?? 0);
                        $healthLabel = t('On Track');
                        if ((bool) ($milestone['is_overdue'] ?? false)) {
                            $healthLabel = t('Overdue');
                        } elseif ((bool) ($milestone['is_at_risk'] ?? false)) {
                            $healthLabel = t('At Risk');
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $this->url->href('MilestoneController', 'show', ['milestone_id' => (int) ($milestone['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                                    <?= $this->text->e((string) ($milestone['name'] ?? '')) ?>
                                </a>
                            </td>
                            <td><?= $this->text->e($targetDate > 0 ? date('Y-m-d', $targetDate) : t('No target date')) ?></td>
                            <td><?= $this->text->e((string) ((float) ($milestone['percent'] ?? 0))) ?>%</td>
                            <?php if ($hasScoreData): ?>
                                <td>
                                    <?= $this->text->e((string) ((int) ($milestone['score_completed'] ?? 0))) ?>
                                    /
                                    <?= $this->text->e((string) ((int) ($milestone['score_total'] ?? 0))) ?>
                                </td>
                            <?php endif ?>
                            <?php if ($hasTimeData): ?>
                                <td>
                                    <?= $this->text->e((string) ((int) ($milestone['time_completed'] ?? 0))) ?>
                                    /
                                    <?= $this->text->e((string) ((int) ($milestone['time_total'] ?? 0))) ?>
                                </td>
                            <?php endif ?>
                            <td><?= $this->text->e($healthLabel) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
        <div class="listing">
            <h3><?= $this->text->e(t('Recent Activity')) ?></h3>
            <?php $activityList = is_array($activities ?? null) ? ($activities ?? []) : []; ?>
            <?php if ($activityList === []): ?>
                <p class="alert portfolio-activity-empty"><?= $this->text->e(t('No recent activity.')) ?></p>
            <?php else: ?>
                <table class="table-striped table-scrolling portfolio-activity-table">
                    <thead>
                    <tr>
                        <th><?= $this->text->e(t('Date')) ?></th>
                        <th><?= $this->text->e(t('Event')) ?></th>
                        <th><?= $this->text->e(t('Task')) ?></th>
                        <th><?= $this->text->e(t('Project')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activityList as $activity): ?>
                        <?php
                        $actDateCreation = (int) ($activity['date_creation'] ?? 0);
                        $actTaskId = (int) ($activity['task_id'] ?? 0);
                        $actProjectId = (int) ($activity['project_id'] ?? 0);
                        ?>
                        <tr class="portfolio-activity-row">
                            <td class="portfolio-activity-date">
                                <?= $this->text->e($actDateCreation > 0 ? date('Y-m-d H:i', $actDateCreation) : '') ?>
                            </td>
                            <td class="portfolio-activity-event">
                                <?= $this->text->e((string) ($activity['event_name'] ?? '')) ?>
                            </td>
                            <td class="portfolio-activity-task">
                                <?php if ($actTaskId > 0 && $actProjectId > 0): ?>
                                    <a href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $actTaskId, 'project_id' => $actProjectId]) ?>">
                                        <?= $this->text->e(t('Task') . ' #' . (string) $actTaskId) ?>
                                    </a>
                                <?php elseif ($actTaskId > 0): ?>
                                    <?= $this->text->e(t('Task') . ' #' . (string) $actTaskId) ?>
                                <?php endif ?>
                            </td>
                            <td class="portfolio-activity-project">
                                <?= $this->text->e((string) ($activity['project_name'] ?? '')) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    </div>
</section>
