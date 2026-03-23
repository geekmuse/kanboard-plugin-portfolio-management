<?php

namespace Kanboard\Plugin\Portfolio\Filter;

use Kanboard\Core\Filter\FilterInterface;
use Kanboard\Filter\BaseFilter;
use ArrayAccess;
use Throwable;

/**
 * Filter tasks by portfolio membership.
 *
 * Usage in search: portfolio:"My Portfolio" or portfolio:3
 */
class TaskPortfolioFilter extends BaseFilter implements FilterInterface
{
    /**
     * @var ArrayAccess|null
     */
    private $container;

    /**
     * @param mixed $value
     */
    public function __construct($value = null)
    {
        parent::__construct($value);
    }

    /**
     * @param ArrayAccess $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getAttributes()
    {
        return ['portfolio'];
    }

    /**
     * @return $this
     */
    public function apply()
    {
        $portfolioId = $this->resolvePortfolioId((string) $this->value);
        $projectIds = $portfolioId > 0 ? $this->getProjectIdsForPortfolio($portfolioId) : [];

        if ($projectIds === []) {
            $this->query->eq('tasks.id', 0);
        } else {
            $this->query->in('tasks.project_id', $projectIds);
        }

        return $this;
    }

    /**
     * @return int
     */
    private function resolvePortfolioId(string $value)
    {
        if ($value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        if ($value === '' || $this->container === null) {
            return 0;
        }

        try {
            $portfolioModel = $this->container['portfolioModel'];
            if (is_object($portfolioModel) && method_exists($portfolioModel, 'getByName')) {
                $portfolio = $portfolioModel->getByName($value);
                return is_array($portfolio) ? (int) ($portfolio['id'] ?? 0) : 0;
            }
        } catch (Throwable $e) {
            // container key not found
        }

        return 0;
    }

    /**
     * @return int[]
     */
    private function getProjectIdsForPortfolio(int $portfolioId)
    {
        if ($portfolioId <= 0 || $this->container === null) {
            return [];
        }

        try {
            $db = $this->container['db'];
            $memberships = $db->table('portfolio_has_projects')
                ->eq('portfolio_id', $portfolioId)
                ->findAll();
        } catch (Throwable $e) {
            return [];
        }

        if (! is_array($memberships)) {
            return [];
        }

        $projectIds = [];
        foreach ($memberships as $membership) {
            $projectId = (int) ($membership['project_id'] ?? 0);
            if ($projectId > 0) {
                $projectIds[] = $projectId;
            }
        }

        return array_values(array_unique($projectIds));
    }
}
