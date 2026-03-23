<li class="portfolio-config-sidebar-item">
    <a href="<?= $this->url->href('ConfigController', 'show', [], 'Portfolio') ?>">
        <?= $this->text->e(t('Portfolio Settings')) ?>
    </a>
</li>
<li class="portfolio-config-sidebar-item">
    <a href="<?= $this->url->href('PortfolioListController', 'index', [], 'Portfolio') ?>">
        <?= $this->text->e(t('Portfolios')) ?>
    </a>
</li>
