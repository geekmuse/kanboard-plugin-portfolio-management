<li>
    <a href="<?= $this->url->href('PortfolioListController', 'index', [], 'Portfolio') ?>">
        <?= $this->text->e(t('Portfolios')) ?>
    </a>
</li>
<li>
    <a href="<?= $this->url->href('PortfolioModificationController', 'create', [], 'Portfolio') ?>">
        <?= $this->text->e(t('Create Portfolio')) ?>
    </a>
</li>
