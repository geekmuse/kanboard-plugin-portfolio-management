<?php

declare(strict_types=1);

namespace Kanboard\Core;

if (! class_exists(__NAMESPACE__ . '\\Base')) {
    class Base
    {
        /** @var array<string, mixed> */
        protected array $container;

        /**
         * @param array<string, mixed> $container
         */
        public function __construct(array $container = [])
        {
            $this->container = $container;
        }

        /** @return mixed */
        public function __get(string $name)
        {
            return $this->container[$name] ?? null;
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Filter;

use Kanboard\Plugin\Portfolio\Filter\TaskPortfolioFilter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Filter/TaskPortfolioFilter.php';

final class TaskPortfolioFilterQuery
{
    /** @var array<int, array{column: string, values: array<int, int>}> */
    public array $inCalls = [];

    /** @var array<int, array{column: string, values: array<int, int>}> */
    public array $notInCalls = [];

    /** @var array<int, array{column: string, value: int}> */
    public array $eqCalls = [];

    /**
     * @param array<int, int> $values
     */
    public function in(string $column, array $values): self
    {
        $this->inCalls[] = ['column' => $column, 'values' => array_values($values)];

        return $this;
    }

    /**
     * @param array<int, int> $values
     */
    public function notIn(string $column, array $values): self
    {
        $this->notInCalls[] = ['column' => $column, 'values' => array_values($values)];

        return $this;
    }

    public function eq(string $column, int $value): self
    {
        $this->eqCalls[] = ['column' => $column, 'value' => $value];

        return $this;
    }
}

final class TaskPortfolioFilterMembershipDb
{
    /**
     * @param array<int, array<string, mixed>> $memberships
     */
    public function __construct(private array $memberships)
    {
    }

    public function table(string $table): TaskPortfolioFilterMembershipTable
    {
        return new TaskPortfolioFilterMembershipTable($table, $this->memberships);
    }
}

final class TaskPortfolioFilterMembershipTable
{
    private int $portfolioId = 0;

    /**
     * @param array<int, array<string, mixed>> $memberships
     */
    public function __construct(private string $table, private array $memberships)
    {
    }

    public function eq(string $column, mixed $value): self
    {
        if ($this->table === 'portfolio_has_projects' && $column === 'portfolio_id') {
            $this->portfolioId = (int) $value;
        }

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        if ($this->table !== 'portfolio_has_projects') {
            return [];
        }

        return array_values(array_filter(
            $this->memberships,
            fn (array $membership): bool => (int) ($membership['portfolio_id'] ?? 0) === $this->portfolioId
        ));
    }
}

final class TaskPortfolioFilterPortfolioModel
{
    /**
     * @param array<string, int> $portfolioMap
     */
    public function __construct(private array $portfolioMap)
    {
    }

    /** @return array<string, int>|null */
    public function getByName(string $name): ?array
    {
        if (! array_key_exists($name, $this->portfolioMap)) {
            return null;
        }

        return ['id' => $this->portfolioMap[$name]];
    }
}

final class TaskPortfolioFilterTest extends TestCase
{
    public function testGetAttributesReturnsPortfolioKeyword(): void
    {
        $filter = new TaskPortfolioFilter(['db' => new TaskPortfolioFilterMembershipDb([])]);

        $this->assertSame(['portfolio'], $filter->getAttributes());
    }

    public function testApplyWithNumericPortfolioAddsProjectInClause(): void
    {
        $query = new TaskPortfolioFilterQuery();
        $filter = new TaskPortfolioFilter([
            'db' => new TaskPortfolioFilterMembershipDb([
                ['portfolio_id' => 7, 'project_id' => 10],
                ['portfolio_id' => 7, 'project_id' => 20],
                ['portfolio_id' => 9, 'project_id' => 30],
            ]),
        ]);

        $filter
            ->setValue('7')
            ->withQuery($query)
            ->apply();

        $this->assertSame(
            [['column' => 'tasks.project_id', 'values' => [10, 20]]],
            $query->inCalls
        );
        $this->assertSame([], $query->eqCalls);
    }

    public function testApplySupportsPortfolioNameLookupAndNegatedOperator(): void
    {
        $query = new TaskPortfolioFilterQuery();
        $filter = new TaskPortfolioFilter([
            'db' => new TaskPortfolioFilterMembershipDb([
                ['portfolio_id' => 4, 'project_id' => 11],
                ['portfolio_id' => 4, 'project_id' => 22],
            ]),
            'portfolioModel' => new TaskPortfolioFilterPortfolioModel([
                'Roadmap' => 4,
            ]),
        ]);

        $filter
            ->setOperator('!=')
            ->setValue('Roadmap')
            ->withQuery($query)
            ->apply();

        $this->assertSame(
            [['column' => 'tasks.project_id', 'values' => [11, 22]]],
            $query->notInCalls
        );
        $this->assertSame([], $query->eqCalls);
    }

    public function testApplyWithUnknownPortfolioCreatesNoResultCondition(): void
    {
        $query = new TaskPortfolioFilterQuery();
        $filter = new TaskPortfolioFilter([
            'db' => new TaskPortfolioFilterMembershipDb([]),
            'portfolioModel' => new TaskPortfolioFilterPortfolioModel([]),
        ]);

        $filter
            ->setValue('unknown')
            ->withQuery($query)
            ->apply();

        $this->assertSame(
            [['column' => 'tasks.id', 'value' => 0]],
            $query->eqCalls
        );
        $this->assertSame([], $query->inCalls);
    }
}
