<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Model;

use DateTimeImmutable;
use DateTimeZone;
use Kanboard\Core\Base;
use Throwable;

class MilestoneModel extends Base
{
    private const TABLE = 'milestones';

    /**
     * @param array<string, mixed> $values
     *
     * @return int|false
     */
    public function create(array $values)
    {
        $portfolioId = (int) ($values['portfolio_id'] ?? 0);
        $name = $this->normalizeName($values['name'] ?? '');

        if ($portfolioId <= 0 || ! $this->portfolioExists($portfolioId)) {
            return false;
        }

        if (! $this->isValidName($name) || $this->getByNameInPortfolio($portfolioId, $name) !== null) {
            return false;
        }

        $targetDate = $this->normalizeTargetDate($values['target_date'] ?? null);
        if ($targetDate === null) {
            return false;
        }

        $status = array_key_exists('status', $values) && $values['status'] !== null
            ? (int) $values['status']
            : 1;

        if (! $this->isValidStatus($status)) {
            return false;
        }

        $timestamp = time();

        return $this->db->table(self::TABLE)->persist([
            'portfolio_id' => $portfolioId,
            'name' => $name,
            'description' => (string) ($values['description'] ?? ''),
            'target_date' => $targetDate,
            'status' => $status,
            'color_id' => (string) ($values['color_id'] ?? 'blue'),
            'owner_id' => (int) ($values['owner_id'] ?? 0),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $milestoneId): ?array
    {
        $milestone = $this->db->table(self::TABLE)->eq('id', $milestoneId)->findOne();

        return is_array($milestone) ? $milestone : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getByPortfolioId(int $portfolioId): array
    {
        $milestones = $this->db->table(self::TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->asc('target_date')
            ->findAll();

        return is_array($milestones) ? $milestones : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(int $milestoneId, array $values): bool
    {
        $milestone = $this->getById($milestoneId);
        if ($milestone === null) {
            return false;
        }

        $updatedValues = [];

        if (array_key_exists('name', $values) && $values['name'] !== null) {
            $name = $this->normalizeName($values['name']);

            if (! $this->isValidName($name)) {
                return false;
            }

            $existing = $this->getByNameInPortfolio((int) $milestone['portfolio_id'], $name);
            if ($existing !== null && (int) $existing['id'] !== $milestoneId) {
                return false;
            }

            $updatedValues['name'] = $name;
        }

        if (array_key_exists('description', $values) && $values['description'] !== null) {
            $updatedValues['description'] = (string) $values['description'];
        }

        if (array_key_exists('target_date', $values) && $values['target_date'] !== null) {
            $targetDate = $this->normalizeTargetDate($values['target_date']);
            if ($targetDate === null) {
                return false;
            }

            $updatedValues['target_date'] = $targetDate;
        }

        if (array_key_exists('color_id', $values) && $values['color_id'] !== null) {
            $updatedValues['color_id'] = (string) $values['color_id'];
        }

        if (array_key_exists('owner_id', $values) && $values['owner_id'] !== null) {
            $updatedValues['owner_id'] = (int) $values['owner_id'];
        }

        if (array_key_exists('status', $values) && $values['status'] !== null) {
            $status = (int) $values['status'];
            if (! $this->isValidStatus($status)) {
                return false;
            }

            $updatedValues['status'] = $status;
        }

        $updatedValues['updated_at'] = time();

        return (bool) $this->db->table(self::TABLE)->eq('id', $milestoneId)->update($updatedValues);
    }

    public function remove(int $milestoneId): bool
    {
        if ($this->getById($milestoneId) === null) {
            return false;
        }

        return (bool) $this->db->table(self::TABLE)->eq('id', $milestoneId)->remove();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProgress(int $milestoneId, string $weightBy = 'count'): ?array
    {
        $milestone = $this->getById($milestoneId);

        if ($milestone === null) {
            return null;
        }

        $memberships = $this->db->table('milestone_has_tasks')->eq('milestone_id', $milestoneId)->findAll();
        if (! is_array($memberships)) {
            $memberships = [];
        }

        $total = 0;
        $completed = 0;
        $blockedCount = 0;
        $scoreTotal = 0;
        $scoreCompleted = 0;
        $timeTotal = 0;
        $timeCompleted = 0;
        $blockedByLinkIds = $this->getBlockedByLinkIds();

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $taskId = (int) ($membership['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            ++$total;

            $task = $this->db->table('tasks')->eq('id', $taskId)->findOne();
            $isTaskCompleted = is_array($task) && (int) ($task['is_active'] ?? 1) === 0;

            if ($isTaskCompleted) {
                ++$completed;
            }

            if (is_array($task)) {
                $taskScore = (int) ($task['score'] ?? 0);
                $taskTime = (int) ($task['time_estimated'] ?? 0);
                $scoreTotal += $taskScore;
                $timeTotal += $taskTime;

                if ($isTaskCompleted) {
                    $scoreCompleted += $taskScore;
                    $timeCompleted += $taskTime;
                }
            }

            if ($blockedByLinkIds !== [] && $this->hasUnresolvedBlockedByLink($taskId, $blockedByLinkIds)) {
                ++$blockedCount;
            }
        }

        $normalizedWeightBy = $this->normalizeWeightBy($weightBy);
        $noData = false;

        if ($normalizedWeightBy === 'score') {
            if ($scoreTotal <= 0) {
                $percent = 0.0;
                $noData = true;
            } else {
                $percent = round(($scoreCompleted / $scoreTotal) * 100, 2);
            }
        } elseif ($normalizedWeightBy === 'time_estimated') {
            if ($timeTotal <= 0) {
                $percent = 0.0;
                $noData = true;
            } else {
                $percent = round(($timeCompleted / $timeTotal) * 100, 2);
            }
        } else {
            $percent = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
        }

        $targetDate = (int) ($milestone['target_date'] ?? 0);
        $now = time();

        $atRiskDays = max(0, $this->getConfigValueAsInt('portfolio_milestone_at_risk_days', 7));
        $atRiskThreshold = $this->getConfigValueAsInt('portfolio_milestone_at_risk_threshold', 80);

        $isOverdue = $targetDate > 0 && $targetDate < $now && $percent < 100;
        $isAtRisk = ! $isOverdue
            && $targetDate > 0
            && $targetDate < ($now + ($atRiskDays * 86400))
            && $percent < $atRiskThreshold;

        return [
            'milestone_id' => $milestone['id'],
            'milestone_name' => (string) ($milestone['name'] ?? ''),
            'portfolio_id' => $milestone['portfolio_id'],
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'blocked_count' => $blockedCount,
            'is_at_risk' => $isAtRisk,
            'is_overdue' => $isOverdue,
            'target_date' => $milestone['target_date'] ?? 0,
            'weight_by' => $normalizedWeightBy,
            'no_data' => $noData,
            'score_total' => $scoreTotal,
            'score_completed' => $scoreCompleted,
            'time_total' => $timeTotal,
            'time_completed' => $timeCompleted,
        ];
    }

    private function normalizeWeightBy(string $weightBy): string
    {
        $allowed = ['count', 'score', 'time_estimated'];
        $normalized = strtolower(trim($weightBy));

        return in_array($normalized, $allowed, true) ? $normalized : 'count';
    }

    private function portfolioExists(int $portfolioId): bool
    {
        $portfolio = $this->db->table('portfolios')->eq('id', $portfolioId)->findOne();

        return is_array($portfolio);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getByNameInPortfolio(int $portfolioId, string $name): ?array
    {
        $milestone = $this->db->table(self::TABLE)
            ->eq('portfolio_id', $portfolioId)
            ->eq('name', $name)
            ->findOne();

        return is_array($milestone) ? $milestone : null;
    }

    /**
     * @param mixed $name
     */
    private function normalizeName($name): string
    {
        return trim((string) $name);
    }

    private function isValidName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 255;
    }

    /**
     * @param mixed $targetDate
     */
    private function normalizeTargetDate($targetDate): ?int
    {
        if ($targetDate === null || $targetDate === '') {
            return 0;
        }

        if (is_int($targetDate)) {
            return max(0, $targetDate);
        }

        if (is_string($targetDate)) {
            $value = trim($targetDate);
            if ($value === '') {
                return 0;
            }

            if (ctype_digit($value)) {
                return (int) $value;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d') !== $value) {
                return null;
            }

            return $date->getTimestamp();
        }

        if (is_float($targetDate)) {
            return (int) $targetDate;
        }

        return null;
    }

    private function isValidStatus(int $status): bool
    {
        return in_array($status, [0, 1, 2], true);
    }

    private function getConfigValueAsInt(string $key, int $default): int
    {
        $configModel = $this->resolveContainerService('configModel');
        if (! is_object($configModel) || ! method_exists($configModel, 'get')) {
            return $default;
        }

        try {
            return (int) $configModel->get($key, $default);
        } catch (Throwable $exception) {
            return $default;
        }
    }

    private function resolveContainerService(string $serviceKey): mixed
    {
        if (is_array($this->container) && array_key_exists($serviceKey, $this->container)) {
            return $this->container[$serviceKey];
        }

        if ($this->container instanceof \ArrayAccess && isset($this->container[$serviceKey])) {
            return $this->container[$serviceKey];
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function getBlockedByLinkIds(): array
    {
        try {
            $links = $this->db->table('links')->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($links)) {
            return [];
        }

        $ids = [];

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $linkId = (int) ($link['id'] ?? 0);
            if ($linkId <= 0) {
                continue;
            }

            $label = strtolower(trim((string) ($link['label'] ?? '')));
            $oppositeLabel = strtolower(trim((string) ($link['opposite_label'] ?? '')));

            if ($label === 'is blocked by' || $oppositeLabel === 'is blocked by') {
                $ids[] = $linkId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, int> $blockedByLinkIds
     */
    private function hasUnresolvedBlockedByLink(int $taskId, array $blockedByLinkIds): bool
    {
        try {
            $links = $this->db->table('task_has_links')->eq('task_id', $taskId)->findAll();
        } catch (Throwable $exception) {
            return false;
        }

        if (! is_array($links)) {
            return false;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $linkId = (int) ($link['link_id'] ?? 0);
            if (! in_array($linkId, $blockedByLinkIds, true)) {
                continue;
            }

            $oppositeTaskId = (int) ($link['opposite_task_id'] ?? 0);
            if ($oppositeTaskId <= 0) {
                continue;
            }

            $oppositeTask = $this->db->table('tasks')->eq('id', $oppositeTaskId)->findOne();
            if (is_array($oppositeTask) && (int) ($oppositeTask['is_active'] ?? 1) === 1) {
                return true;
            }
        }

        return false;
    }
}
