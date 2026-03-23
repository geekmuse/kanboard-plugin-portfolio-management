<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Controller\BaseController;

class DependencyController extends BaseController
{
    public function graph()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->getPortfolio($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->redirectToPortfolioList();
        }

        $crossProjectOnly = (bool) $this->request->getIntegerParam('cross_project_only', 1);
        $graph = $this->resolveDependencyGraph($portfolioId, $crossProjectOnly);

        return $this->response->html($this->helper->layout->app('Portfolio:dependency/graph', [
            'title' => t('Dependency Graph'),
            'portfolio' => $portfolio,
            'cross_project_only' => $crossProjectOnly,
            'graph' => $graph,
            'graph_data_url' => $this->helper->url->href(
                'DependencyController',
                'graphData',
                ['portfolio_id' => $portfolioId, 'cross_project_only' => $crossProjectOnly ? 1 : 0, 'plugin' => 'Portfolio']
            ),
        ]));
    }

    public function graphData()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $crossProjectOnly = (bool) $this->request->getIntegerParam('cross_project_only', 1);

        $graph = $this->resolveDependencyGraph($portfolioId, $crossProjectOnly);

        return $this->response->json($graph);
    }

    public function blocked()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->getPortfolio($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->redirectToPortfolioList();
        }

        $blockedTasks = $this->resolveBlockedTasks($portfolioId);

        return $this->response->html($this->helper->layout->app('Portfolio:dependency/blocked', [
            'title' => t('Blocked Tasks'),
            'portfolio' => $portfolio,
            'blocked_tasks' => $blockedTasks,
        ]));
    }

    public function criticalPath()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->getPortfolio($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->redirectToPortfolioList();
        }

        $criticalPath = $this->resolveCriticalPath($portfolioId);

        return $this->response->html($this->helper->layout->app('Portfolio:dependency/critical_path', [
            'title' => t('Critical Path'),
            'portfolio' => $portfolio,
            'critical_path' => $criticalPath,
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPortfolio(int $portfolioId): ?array
    {
        if (! is_object($this->portfolioModel) || ! method_exists($this->portfolioModel, 'getById')) {
            return null;
        }

        $portfolio = $this->portfolioModel->getById($portfolioId);

        return is_array($portfolio) ? $portfolio : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDependencyGraph(int $portfolioId, bool $crossProjectOnly): array
    {
        $empty = ['nodes' => [], 'edges' => [], 'critical_path' => []];

        if (! is_object($this->dependencyModel) || ! method_exists($this->dependencyModel, 'getGraph')) {
            return $empty;
        }

        $graph = $this->dependencyModel->getGraph($portfolioId, $crossProjectOnly);

        return is_array($graph) ? $graph : $empty;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveBlockedTasks(int $portfolioId): array
    {
        if (! is_object($this->dependencyModel) || ! method_exists($this->dependencyModel, 'getBlockedTasks')) {
            return [];
        }

        $tasks = $this->dependencyModel->getBlockedTasks($portfolioId);

        return is_array($tasks) ? $tasks : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCriticalPath(int $portfolioId): array
    {
        if (! is_object($this->dependencyModel) || ! method_exists($this->dependencyModel, 'getCriticalPath')) {
            return [];
        }

        $path = $this->dependencyModel->getCriticalPath($portfolioId);

        return is_array($path) ? $path : [];
    }

    private function redirectToPortfolioList()
    {
        return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
    }
}
