<section class="sidebar-container">
    <?php $sidebar_active = 'list'; $portfolio = null; require __DIR__ . '/_sidebar.php'; ?>

    <div class="sidebar-content">
        <div class="page-header">
            <h2><?= $this->text->e($title ?? t('Portfolios')) ?></h2>
        </div>

        <?php if (empty($portfolios)): ?>
            <p class="alert"><?= $this->text->e(t('No portfolios found.')) ?></p>
        <?php else: ?>
            <table class="table-striped table-scrolling">
                <thead>
                <tr>
                    <th><?= $this->text->e(t('Portfolio Name')) ?></th>
                    <th><?= $this->text->e(t('Portfolio Description')) ?></th>
                    <th><?= $this->text->e(t('Status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($portfolios as $p): ?>
                    <tr>
                        <td>
                            <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => (int) ($p['id'] ?? 0), 'plugin' => 'Portfolio']) ?>">
                                <?= $this->text->e((string) ($p['name'] ?? '')) ?>
                            </a>
                        </td>
                        <td><?= $this->text->e((string) ($p['description'] ?? '')) ?></td>
                        <td><?= $this->text->e(((int) ($p['is_active'] ?? 1) === 1) ? t('Active') : t('Inactive')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</section>
