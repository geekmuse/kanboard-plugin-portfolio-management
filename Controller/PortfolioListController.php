<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Controller\Base;

class PortfolioListController extends Base
{
    public function index()
    {
        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/index', [
            'title' => t('Portfolios'),
            'portfolios' => $this->portfolioModel->getAll(),
        ]));
    }
}
