<li>
    <a href="<?= $this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']) ?>">
        <?= $this->text->e(t('Portfolios')) ?>
    </a>
</li>
<li>
    <a href="<?= $this->url->href('PortfolioModificationController', 'create', ['plugin' => 'Portfolio']) ?>">
        <?= $this->text->e(t('Create Portfolio')) ?>
    </a>
</li>
