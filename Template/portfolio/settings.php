<section class="sidebar-container">
    <?php $sidebar_active = 'settings'; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
<div class="page-header">
    <h2 class="portfolio-settings-title"><?= $this->text->e($title ?? t('Portfolio Settings')) ?></h2>
</div>

<p class="portfolio-settings-portfolio-name">
    <strong><?= $this->text->e(t('Portfolio Name')) ?>:</strong>
    <?= $this->text->e((string) ($portfolio['name'] ?? '')) ?>
</p>

<div class="portfolio-settings-section">
    <h3 class="portfolio-settings-section-title"><?= $this->text->e(t('Add Project')) ?></h3>

    <?php if (empty($available_projects)): ?>
        <p class="portfolio-settings-empty-available"><?= $this->text->e(t('No available projects to add.')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= $this->url->href('PortfolioModificationController', 'addProject', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-settings-add-form">
            <?= $this->form->csrf() ?>

            <div class="portfolio-form-row">
                <label for="form-project-id"><?= $this->text->e(t('Project')) ?></label>
                <select id="form-project-id" name="project_id" required>
                    <?php foreach ($available_projects as $project): ?>
                        <option value="<?= $this->text->e((string) ((int) ($project['id'] ?? 0))) ?>">
                            <?= $this->text->e((string) ($project['name'] ?? '')) ?>
                            <?php if ((string) ($project['identifier'] ?? '') !== ''): ?>
                                (<?= $this->text->e((string) ($project['identifier'] ?? '')) ?>)
                            <?php endif ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="portfolio-form-row">
                <label for="form-position"><?= $this->text->e(t('Position')) ?></label>
                <input type="number" id="form-position" name="position" min="0" value="0">
            </div>

            <div class="portfolio-form-actions">
                <button type="submit" class="btn btn-blue"><?= $this->text->e(t('Add Project')) ?></button>
            </div>
        </form>
    <?php endif ?>
</div>

<div class="portfolio-settings-section">
    <h3 class="portfolio-settings-section-title"><?= $this->text->e(t('Portfolio Projects')) ?></h3>

    <?php if (empty($projects)): ?>
        <p class="portfolio-settings-empty-members"><?= $this->text->e(t('No projects assigned to this portfolio.')) ?></p>
    <?php else: ?>
        <table class="table-striped portfolio-settings-project-table">
            <thead>
            <tr>
                <th><?= $this->text->e(t('Project')) ?></th>
                <th><?= $this->text->e(t('Identifier')) ?></th>
                <th><?= $this->text->e(t('Position')) ?></th>
                <th><?= $this->text->e(t('Actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?= $this->text->e((string) ($project['name'] ?? '')) ?></td>
                    <td><?= $this->text->e((string) ($project['identifier'] ?? '')) ?></td>
                    <td><?= $this->text->e((string) ((int) ($project['position'] ?? 0))) ?></td>
                    <td>
                        <form method="post" action="<?= $this->url->href('PortfolioModificationController', 'removeProject', ['portfolio_id' => (int) ($portfolio['id'] ?? 0), 'plugin' => 'Portfolio']) ?>" class="portfolio-settings-remove-form">
                            <?= $this->form->csrf() ?>
                            <input type="hidden" name="project_id" value="<?= $this->text->e((string) ((int) ($project['id'] ?? 0))) ?>">
                            <button type="submit" class="btn btn-red btn-sm"><?= $this->text->e(t('Remove Project')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

    </div>
</section>
