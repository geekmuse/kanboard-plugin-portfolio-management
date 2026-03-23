<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Controller\BaseController;

class PortfolioModificationController extends BaseController
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $errors
     */
    public function create(array $values = [], array $errors = [])
    {
        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/create', [
            'title' => t('Create Portfolio'),
            'values' => $values,
            'errors' => $errors,
        ]));
    }

    public function save()
    {

        $values = $this->getFormValues();
        $portfolioId = $this->portfolioModel->create($values);

        if ($portfolioId !== false) {
            $this->flash->success(t('Portfolio created successfully.'));

            return $this->response->redirect($this->helper->url->href(
                'PortfolioViewController',
                'show',
                ['portfolio_id' => (int) $portfolioId, 'plugin' => 'Portfolio']
            ));
        }

        $this->flash->failure(t('Unable to create portfolio.'));

        return $this->create($values, ['form' => t('Unable to create portfolio.')]);
    }

    public function edit(array $values = [], array $errors = [])
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        if ($values === []) {
            $values = [
                'name' => (string) ($portfolio['name'] ?? ''),
                'description' => (string) ($portfolio['description'] ?? ''),
                'owner_id' => (int) ($portfolio['owner_id'] ?? 0),
                'is_active' => isset($portfolio['is_active']) ? (int) $portfolio['is_active'] : 1,
            ];
        }

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/edit', [
            'title' => t('Edit Portfolio'),
            'portfolio' => $portfolio,
            'values' => $values,
            'errors' => $errors,
        ]));
    }

    public function update()
    {

        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $values = $this->getFormValues();

        if ($this->portfolioModel->update($portfolioId, $values)) {
            $this->flash->success(t('Portfolio updated successfully.'));

            return $this->response->redirect($this->helper->url->href(
                'PortfolioViewController',
                'show',
                ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']
            ));
        }

        $this->flash->failure(t('Unable to update portfolio.'));

        return $this->edit($values, ['form' => t('Unable to update portfolio.')]);
    }

    public function settings()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        $projects = $this->portfolioProjectModel->getProjects($portfolioId);

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/settings', [
            'title' => t('Portfolio Settings'),
            'portfolio' => $portfolio,
            'projects' => $projects,
            'available_projects' => $this->getAvailableProjects($projects),
        ]));
    }

    public function addProject()
    {
        $values = $this->request->getValues();
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $projectId = (int) ($values['project_id'] ?? 0);
        $position = (int) ($values['position'] ?? 0);

        if ($this->portfolioProjectModel->add($portfolioId, $projectId, $position)) {
            $this->flash->success(t('Project added to portfolio.'));
        } else {
            $this->flash->failure(t('Unable to add project to portfolio.'));
        }

        return $this->response->redirect($this->helper->url->href(
                'PortfolioModificationController',
                'settings',
                ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']
            ));
    }

    public function removeProject()
    {
        $values = $this->request->getValues();
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $projectId = (int) ($values['project_id'] ?? 0);

        if ($this->portfolioProjectModel->remove($portfolioId, $projectId)) {
            $this->flash->success(t('Project removed from portfolio.'));
        } else {
            $this->flash->failure(t('Unable to remove project from portfolio.'));
        }

        return $this->response->redirect($this->helper->url->href(
                'PortfolioModificationController',
                'settings',
                ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']
            ));
    }

    public function remove()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->portfolioModel->getById($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        return $this->response->html($this->helper->layout->app('Portfolio:portfolio/remove', [
            'title' => t('Remove Portfolio'),
            'portfolio' => $portfolio,
        ]));
    }

    public function delete()
    {

        $portfolioId = $this->request->getIntegerParam('portfolio_id');

        if ($this->portfolioModel->remove($portfolioId)) {
            $this->flash->success(t('Portfolio removed successfully.'));

            return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
        }

        $this->flash->failure(t('Unable to remove portfolio.'));

        return $this->response->redirect($this->helper->url->href(
                'PortfolioModificationController',
                'remove',
                ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']
            ));
    }

    /**
     * @param array<int, array<string, mixed>> $portfolioProjects
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAvailableProjects(array $portfolioProjects): array
    {
        if (! is_object($this->projectModel) || ! method_exists($this->projectModel, 'getAll')) {
            return [];
        }

        $memberProjectIds = [];
        foreach ($portfolioProjects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId > 0) {
                $memberProjectIds[$projectId] = true;
            }
        }

        $allProjects = $this->projectModel->getAll();
        if (! is_array($allProjects)) {
            return [];
        }

        $availableProjects = [];

        foreach ($allProjects as $project) {
            if (! is_array($project)) {
                continue;
            }

            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId === 0 || array_key_exists($projectId, $memberProjectIds)) {
                continue;
            }

            $availableProjects[] = $project;
        }

        usort(
            $availableProjects,
            static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''))
        );

        return $availableProjects;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormValues(): array
    {
        $values = $this->request->getValues();

        return [
            'name' => (string) ($values['name'] ?? ''),
            'description' => (string) ($values['description'] ?? ''),
            'owner_id' => (int) ($values['owner_id'] ?? 0),
            'is_active' => array_key_exists('is_active', $values) ? (int) $values['is_active'] : 0,
        ];
    }
}
