<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Core\Base;

class PortfolioListController extends Base
{
    public function index()
    {
        return $this->response->html($this->template->render('portfolio/index', [
            'title' => t('Portfolios'),
            'portfolios' => $this->portfolioModel->getAll(),
        ]));
    }
}
