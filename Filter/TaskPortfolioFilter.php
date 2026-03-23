<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Filter;

use Kanboard\Core\Base;
use Throwable;

class TaskPortfolioFilter extends Base
{
    private mixed $query = null;

    private string $value = '';

    private string $operator = '=';

    /**
     * @return array<int, string>
     */
    public function getAttributes(): array
    {
        return ['portfolio'];
    }

    public function setAttribute(string $attribute): self
    {
        return $this;
    }

    public function setValue(string $value): self
    {
        $this->value = trim($value);

        return $this;
    }

    public function setOperator(string $operator): self
    {
        $normalized = trim($operator);
        $this->operator = $normalized === '' ? '=' : $normalized;

        return $this;
    }

    public function withQuery(mixed $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function apply(): mixed
    {
        if (! is_object($this->query)) {
            return $this->query;
        }

        $portfolioId = $this->resolvePortfolioId($this->value);
        $projectIds = $portfolioId > 0 ? $this->getProjectIdsForPortfolio($portfolioId) : [];

        if ($this->isNegatedOperator($this->operator)) {
            if ($projectIds === [] || ! method_exists($this->query, 'notIn')) {
                return $this->query;
            }

            $this->query->notIn('tasks.project_id', $projectIds);

            return $this->query;
        }

        if ($projectIds === []) {
            if (method_exists($this->query, 'eq')) {
                $this->query->eq('tasks.id', 0);
            }

            return $this->query;
        }

        if (method_exists($this->query, 'in')) {
            $this->query->in('tasks.project_id', $projectIds);

            return $this->query;
        }

        if (method_exists($this->query, 'eq') && count($projectIds) === 1) {
            $this->query->eq('tasks.project_id', $projectIds[0]);
        }

        return $this->query;
    }

    private function resolvePortfolioId(string $value): int
    {
        if ($value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        if ($value === '' || ! is_object($this->portfolioModel) || ! method_exists($this->portfolioModel, 'getByName')) {
            return 0;
        }

        $portfolio = $this->portfolioModel->getByName($value);

        return is_array($portfolio) ? (int) ($portfolio['id'] ?? 0) : 0;
    }

    /**
     * @return array<int, int>
     */
    private function getProjectIdsForPortfolio(int $portfolioId): array
    {
        if ($portfolioId <= 0) {
            return [];
        }

        try {
            $memberships = $this->db->table('portfolio_has_projects')
                ->eq('portfolio_id', $portfolioId)
                ->findAll();
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_array($memberships)) {
            return [];
        }

        $projectIds = [];

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $projectId = (int) ($membership['project_id'] ?? 0);
            if ($projectId > 0) {
                $projectIds[$projectId] = true;
            }
        }

        return array_map('intval', array_keys($projectIds));
    }

    private function isNegatedOperator(string $operator): bool
    {
        $normalized = strtolower(trim($operator));

        return in_array($normalized, ['!=', '<>', '!', 'not'], true);
    }
}
