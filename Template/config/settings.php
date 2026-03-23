<div class="page-header">
    <h2><?= $this->text->e($title ?? t('Portfolio Settings')) ?></h2>
</div>

<div class="portfolio-config-settings">
    <form method="post" action="<?= $this->url->href('ConfigController', 'save', ['plugin' => 'Portfolio']) ?>" class="portfolio-config-form">
        <?= $this->form->csrf() ?>

        <div class="portfolio-config-field">
            <label for="portfolio_milestone_at_risk_days"><?= $this->text->e(t('Milestone At-Risk Window (days)')) ?></label>
            <input
                type="number"
                min="0"
                max="365"
                id="portfolio_milestone_at_risk_days"
                name="portfolio_milestone_at_risk_days"
                value="<?= $this->text->e((string) ($settings['portfolio_milestone_at_risk_days'] ?? 7)) ?>"
            >
        </div>

        <div class="portfolio-config-field">
            <label for="portfolio_milestone_at_risk_threshold"><?= $this->text->e(t('Milestone At-Risk Threshold (%%)')) ?></label>
            <input
                type="number"
                min="1"
                max="100"
                id="portfolio_milestone_at_risk_threshold"
                name="portfolio_milestone_at_risk_threshold"
                value="<?= $this->text->e((string) ($settings['portfolio_milestone_at_risk_threshold'] ?? 80)) ?>"
            >
        </div>

        <div class="portfolio-config-field portfolio-config-field--checkbox">
            <label>
                <input
                    type="checkbox"
                    name="portfolio_board_show_blockers"
                    value="1"
                    <?= ((int) ($settings['portfolio_board_show_blockers'] ?? 1) === 1) ? 'checked' : '' ?>
                >
                <?= $this->text->e(t('Show board blocked indicators')) ?>
            </label>
        </div>

        <div class="portfolio-config-field portfolio-config-field--checkbox">
            <label>
                <input
                    type="checkbox"
                    name="portfolio_dashboard_widget_enabled"
                    value="1"
                    <?= ((int) ($settings['portfolio_dashboard_widget_enabled'] ?? 1) === 1) ? 'checked' : '' ?>
                >
                <?= $this->text->e(t('Show dashboard portfolio widget')) ?>
            </label>
        </div>

        <div class="portfolio-config-field">
            <label for="portfolio_dependency_link_types"><?= $this->text->e(t('Dependency link labels')) ?></label>
            <input
                type="text"
                id="portfolio_dependency_link_types"
                name="portfolio_dependency_link_types"
                value="<?= $this->text->e((string) ($settings['portfolio_dependency_link_types'] ?? 'blocks')) ?>"
                placeholder="<?= $this->text->e(t('Example: blocks, is blocked by')) ?>"
            >
            <p class="portfolio-config-help">
                <?= $this->text->e(t('Comma-separated task link labels treated as dependencies.')) ?>
            </p>
        </div>

        <div class="portfolio-config-field">
            <label for="portfolio_tasks_per_page"><?= $this->text->e(t('Default portfolio tasks per page')) ?></label>
            <input
                type="number"
                min="1"
                max="500"
                id="portfolio_tasks_per_page"
                name="portfolio_tasks_per_page"
                value="<?= $this->text->e((string) ($settings['portfolio_tasks_per_page'] ?? 50)) ?>"
            >
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Save')) ?></button>
            <?= $this->text->e(t('or')) ?>
            <a href="<?= $this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']) ?>" class="btn"><?= $this->text->e(t('Portfolios')) ?></a>
        </div>
    </form>
</div>
