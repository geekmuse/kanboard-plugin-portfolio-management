<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Controller\BaseController;

class MilestoneController extends BaseController
{
    public function index()
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->getPortfolio($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->redirectToPortfolioList();
        }

        $milestones = $this->getMilestones($portfolioId);

        return $this->response->html($this->helper->layout->app('Portfolio:milestone/index', [
            'title' => t('Portfolio Milestones'),
            'portfolio' => $portfolio,
            'milestones' => $milestones,
            'progress_map' => $this->buildProgressMap($milestones),
        ]));
    }

    public function show()
    {
        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $milestone = $this->getMilestone($milestoneId);

        if ($milestone === null) {
            $this->flash->failure(t('Milestone not found.'));

            return $this->redirectToPortfolioList();
        }

        $portfolioId = (int) ($milestone['portfolio_id'] ?? 0);

        return $this->response->html($this->helper->layout->app('Portfolio:milestone/show', [
            'title' => t('Milestone Details'),
            'milestone' => $milestone,
            'portfolio' => $this->getPortfolio($portfolioId) ?? [],
            'tasks' => $this->getMilestoneTasks($milestoneId),
            'progress' => $this->getMilestoneProgress($milestoneId),
        ]));
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $errors
     */
    public function create(array $values = [], array $errors = [])
    {
        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $portfolio = $this->getPortfolio($portfolioId);

        if ($portfolio === null) {
            $this->flash->failure(t('Portfolio not found.'));

            return $this->redirectToPortfolioList();
        }

        if ($values === []) {
            $values = [
                'name' => '',
                'description' => '',
                'target_date' => '',
                'color_id' => 'blue',
                'owner_id' => 0,
                'status' => 1,
            ];
        }

        return $this->response->html($this->helper->layout->app('Portfolio:milestone/create', [
            'title' => t('Create Milestone'),
            'portfolio' => $portfolio,
            'values' => $values,
            'errors' => $errors,
        ]));
    }

    public function save()
    {

        $portfolioId = $this->request->getIntegerParam('portfolio_id');
        $values = $this->getFormValues();
        $values['portfolio_id'] = $portfolioId;

        $milestoneId = is_object($this->milestoneModel) && method_exists($this->milestoneModel, 'create')
            ? $this->milestoneModel->create($values)
            : false;

        if ($milestoneId !== false) {
            $this->flash->success(t('Milestone created successfully.'));

            return $this->redirectToMilestone((int) $milestoneId);
        }

        $this->flash->failure(t('Unable to create milestone.'));

        return $this->create($values, ['form' => t('Unable to create milestone.')]);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $errors
     */
    public function edit(array $values = [], array $errors = [])
    {
        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $milestone = $this->getMilestone($milestoneId);

        if ($milestone === null) {
            $this->flash->failure(t('Milestone not found.'));

            return $this->redirectToPortfolioList();
        }

        if ($values === []) {
            $values = [
                'name' => (string) ($milestone['name'] ?? ''),
                'description' => (string) ($milestone['description'] ?? ''),
                'target_date' => $this->formatTargetDate($milestone['target_date'] ?? 0),
                'color_id' => (string) ($milestone['color_id'] ?? 'blue'),
                'owner_id' => (int) ($milestone['owner_id'] ?? 0),
                'status' => (int) ($milestone['status'] ?? 1),
            ];
        }

        return $this->response->html($this->helper->layout->app('Portfolio:milestone/edit', [
            'title' => t('Edit Milestone'),
            'milestone' => $milestone,
            'portfolio' => $this->getPortfolio((int) ($milestone['portfolio_id'] ?? 0)) ?? [],
            'values' => $values,
            'errors' => $errors,
        ]));
    }

    public function update()
    {

        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $values = $this->getFormValues();

        $updated = is_object($this->milestoneModel) && method_exists($this->milestoneModel, 'update')
            ? (bool) $this->milestoneModel->update($milestoneId, $values)
            : false;

        if ($updated) {
            $this->flash->success(t('Milestone updated successfully.'));

            return $this->redirectToMilestone($milestoneId);
        }

        $this->flash->failure(t('Unable to update milestone.'));

        return $this->edit($values, ['form' => t('Unable to update milestone.')]);
    }

    public function remove()
    {
        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $milestone = $this->getMilestone($milestoneId);

        if ($milestone === null) {
            $this->flash->failure(t('Milestone not found.'));

            return $this->redirectToPortfolioList();
        }

        return $this->response->html($this->helper->layout->app('Portfolio:milestone/remove', [
            'title' => t('Remove Milestone'),
            'milestone' => $milestone,
            'portfolio' => $this->getPortfolio((int) ($milestone['portfolio_id'] ?? 0)) ?? [],
        ]));
    }

    public function delete()
    {

        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $milestone = $this->getMilestone($milestoneId);
        $portfolioId = (int) ($milestone['portfolio_id'] ?? 0);

        $removed = is_object($this->milestoneModel) && method_exists($this->milestoneModel, 'remove')
            ? (bool) $this->milestoneModel->remove($milestoneId)
            : false;

        if ($removed) {
            $this->flash->success(t('Milestone removed successfully.'));

            if ($portfolioId > 0) {
                return $this->redirectToPortfolioMilestones($portfolioId);
            }

            return $this->redirectToPortfolioList();
        }

        $this->flash->failure(t('Unable to remove milestone.'));

        return $this->response->redirect($this->helper->url->href(
                'MilestoneController',
                'remove',
                ['milestone_id' => $milestoneId, 'plugin' => 'Portfolio']
            ));
    }

    public function addTask()
    {

        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $taskId = (int) $this->request->getValue('task_id', 0);
        $isCritical = (int) $this->request->getValue('is_critical', 0);
        $position = (int) $this->request->getValue('position', 0);

        $added = is_object($this->milestoneTaskModel) && method_exists($this->milestoneTaskModel, 'add')
            ? (bool) $this->milestoneTaskModel->add($milestoneId, $taskId, $isCritical, $position)
            : false;

        if ($added) {
            $this->flash->success(t('Task added to milestone.'));
        } else {
            $this->flash->failure(t('Unable to add task to milestone.'));
        }

        return $this->redirectToMilestone($milestoneId);
    }

    public function removeTask()
    {

        $milestoneId = $this->request->getIntegerParam('milestone_id');
        $taskId = (int) $this->request->getValue('task_id', 0);

        $removed = is_object($this->milestoneTaskModel) && method_exists($this->milestoneTaskModel, 'remove')
            ? (bool) $this->milestoneTaskModel->remove($milestoneId, $taskId)
            : false;

        if ($removed) {
            $this->flash->success(t('Task removed from milestone.'));
        } else {
            $this->flash->failure(t('Unable to remove task from milestone.'));
        }

        return $this->redirectToMilestone($milestoneId);
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
            'target_date' => (string) ($values['target_date'] ?? ''),
            'color_id' => (string) ($values['color_id'] ?? 'blue'),
            'owner_id' => (int) ($values['owner_id'] ?? 0),
            'status' => (int) ($values['status'] ?? 1),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $milestones
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProgressMap(array $milestones): array
    {
        $progressMap = [];

        foreach ($milestones as $milestone) {
            if (! is_array($milestone)) {
                continue;
            }

            $milestoneId = (int) ($milestone['id'] ?? 0);
            if ($milestoneId <= 0) {
                continue;
            }

            $progressMap[$milestoneId] = $this->getMilestoneProgress($milestoneId);
        }

        return $progressMap;
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
     * @return array<int, array<string, mixed>>
     */
    private function getMilestones(int $portfolioId): array
    {
        if (! is_object($this->milestoneModel) || ! method_exists($this->milestoneModel, 'getByPortfolioId')) {
            return [];
        }

        $milestones = $this->milestoneModel->getByPortfolioId($portfolioId);

        return is_array($milestones) ? $milestones : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMilestone(int $milestoneId): ?array
    {
        if (! is_object($this->milestoneModel) || ! method_exists($this->milestoneModel, 'getById')) {
            return null;
        }

        $milestone = $this->milestoneModel->getById($milestoneId);

        return is_array($milestone) ? $milestone : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMilestoneTasks(int $milestoneId): array
    {
        if (! is_object($this->milestoneTaskModel) || ! method_exists($this->milestoneTaskModel, 'getTasks')) {
            return [];
        }

        $tasks = $this->milestoneTaskModel->getTasks($milestoneId);

        return is_array($tasks) ? $tasks : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMilestoneProgress(int $milestoneId): array
    {
        $fallback = [
            'total' => 0,
            'completed' => 0,
            'percent' => 0,
            'blocked_count' => 0,
            'is_at_risk' => false,
            'is_overdue' => false,
            'target_date' => 0,
        ];

        if (! is_object($this->milestoneModel) || ! method_exists($this->milestoneModel, 'getProgress')) {
            return $fallback;
        }

        $progress = $this->milestoneModel->getProgress($milestoneId);
        if (! is_array($progress)) {
            return $fallback;
        }

        return array_merge($fallback, $progress);
    }

    private function redirectToPortfolioList()
    {
        return $this->response->redirect($this->helper->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']));
    }

    private function redirectToPortfolioMilestones(int $portfolioId)
    {
        return $this->response->redirect($this->helper->url->href(
                'MilestoneController',
                'index',
                ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']
            ));
    }

    private function redirectToMilestone(int $milestoneId)
    {
        return $this->response->redirect($this->helper->url->href(
                'MilestoneController',
                'show',
                ['milestone_id' => $milestoneId, 'plugin' => 'Portfolio']
            ));
    }

    private function formatTargetDate(mixed $targetDate): string
    {
        if (is_int($targetDate)) {
            return $targetDate > 0 ? date('Y-m-d', $targetDate) : '';
        }

        if (is_string($targetDate)) {
            $trimmedValue = trim($targetDate);
            if ($trimmedValue === '') {
                return '';
            }

            if (ctype_digit($trimmedValue)) {
                $timestamp = (int) $trimmedValue;

                return $timestamp > 0 ? date('Y-m-d', $timestamp) : '';
            }

            return $trimmedValue;
        }

        return '';
    }
}
