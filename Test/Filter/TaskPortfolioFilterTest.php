<?php

declare(strict_types=1);

namespace PicoDb {
    if (! class_exists(__NAMESPACE__ . '\\Table')) {
        class Table
        {
            /** @var array<int, array{column: string, values: array<int, int>}> */
            public array $inCalls = [];

            /** @var array<int, array{column: string, value: int}> */
            public array $eqCalls = [];

            /** @param array<int, int> $values */
            public function in(string $column, array $values): self
            {
                $this->inCalls[] = ['column' => $column, 'values' => array_values($values)];
                return $this;
            }

            public function eq(string $column, int $value): self
            {
                $this->eqCalls[] = ['column' => $column, 'value' => $value];
                return $this;
            }
        }
    }
}

namespace Kanboard\Core\Filter {
    if (! interface_exists(__NAMESPACE__ . '\\FilterInterface')) {
        interface FilterInterface
        {
            public function __construct($value = null);
            public function withValue($value);
            public function withQuery(\PicoDb\Table $query);
            public function getAttributes();
            public function apply();
        }
    }
}

namespace Kanboard\Filter {
    if (! class_exists(__NAMESPACE__ . '\\BaseFilter')) {
        abstract class BaseFilter
        {
            protected $query;
            protected $value;

            public function __construct($value = null)
            {
                $this->value = $value;
            }

            public function withQuery(\PicoDb\Table $query)
            {
                $this->query = $query;
                return $this;
            }

            public function withValue($value)
            {
                $this->value = $value;
                return $this;
            }
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Filter {

    use Kanboard\Plugin\Portfolio\Filter\TaskPortfolioFilter;
    use PHPUnit\Framework\TestCase;
    use PicoDb\Table;

    require_once __DIR__ . '/../../Filter/TaskPortfolioFilter.php';

    // -----------------------------------------------------------------------
    // Simple ArrayAccess container for tests
    // -----------------------------------------------------------------------

    /** @implements \ArrayAccess<string, mixed> */
    final class FakeContainer implements \ArrayAccess
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data = [])
        {
        }

        public function offsetExists(mixed $offset): bool
        {
            return array_key_exists($offset, $this->data);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->data[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->data[$offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->data[$offset]);
        }
    }

    // -----------------------------------------------------------------------
    // Fake DB for portfolio membership lookup
    // -----------------------------------------------------------------------

    final class FakeMembershipTable
    {
        private int $portfolioId = 0;

        /** @param array<int, array<string, mixed>> $memberships */
        public function __construct(private array $memberships)
        {
        }

        public function eq(string $column, mixed $value): self
        {
            if ($column === 'portfolio_id') {
                $this->portfolioId = (int) $value;
            }
            return $this;
        }

        /** @return array<int, array<string, mixed>> */
        public function findAll(): array
        {
            return array_values(array_filter(
                $this->memberships,
                fn (array $m): bool => (int) ($m['portfolio_id'] ?? 0) === $this->portfolioId
            ));
        }
    }

    final class FakeDb
    {
        /** @param array<int, array<string, mixed>> $memberships */
        public function __construct(private array $memberships)
        {
        }

        public function table(string $table): FakeMembershipTable
        {
            return new FakeMembershipTable($this->memberships);
        }
    }

    // -----------------------------------------------------------------------
    // Fake portfolio model for name lookup
    // -----------------------------------------------------------------------

    final class FakePortfolioModel
    {
        /** @param array<string, int> $map  name => id */
        public function __construct(private array $map = [])
        {
        }

        /** @return array<string, mixed>|null */
        public function getByName(string $name): ?array
        {
            return isset($this->map[$name]) ? ['id' => $this->map[$name]] : null;
        }
    }

    // -----------------------------------------------------------------------
    // Helper to build a container with fake services
    // -----------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $memberships
     * @param array<string, int>               $portfolioMap
     */
    function buildContainer(array $memberships = [], array $portfolioMap = []): FakeContainer
    {
        return new FakeContainer([
            'db' => new FakeDb($memberships),
            'portfolioModel' => new FakePortfolioModel($portfolioMap),
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    final class TaskPortfolioFilterTest extends TestCase
    {
        public function testGetAttributesReturnsPortfolioKeyword(): void
        {
            $filter = new TaskPortfolioFilter();
            $this->assertSame(['portfolio'], $filter->getAttributes());
        }

        public function testApplyWithNumericPortfolioAddsProjectInClause(): void
        {
            $query = new Table();
            $container = buildContainer([
                ['portfolio_id' => 7, 'project_id' => 10],
                ['portfolio_id' => 7, 'project_id' => 20],
                ['portfolio_id' => 9, 'project_id' => 30],
            ]);

            $filter = new TaskPortfolioFilter();
            $filter->setContainer($container);
            $filter->withValue('7');
            $filter->withQuery($query);
            $filter->apply();

            $this->assertSame(
                [['column' => 'tasks.project_id', 'values' => [10, 20]]],
                $query->inCalls
            );
            $this->assertSame([], $query->eqCalls);
        }

        public function testApplySupportsPortfolioNameLookup(): void
        {
            $query = new Table();
            $container = buildContainer(
                [
                    ['portfolio_id' => 4, 'project_id' => 11],
                    ['portfolio_id' => 4, 'project_id' => 22],
                ],
                ['Roadmap' => 4]
            );

            $filter = new TaskPortfolioFilter();
            $filter->setContainer($container);
            $filter->withValue('Roadmap');
            $filter->withQuery($query);
            $filter->apply();

            $this->assertSame(
                [['column' => 'tasks.project_id', 'values' => [11, 22]]],
                $query->inCalls
            );
            $this->assertSame([], $query->eqCalls);
        }

        public function testApplyWithUnknownPortfolioCreatesNoResultCondition(): void
        {
            $query = new Table();
            $container = buildContainer([], []);

            $filter = new TaskPortfolioFilter();
            $filter->setContainer($container);
            $filter->withValue('unknown');
            $filter->withQuery($query);
            $filter->apply();

            $this->assertSame(
                [['column' => 'tasks.id', 'value' => 0]],
                $query->eqCalls
            );
            $this->assertSame([], $query->inCalls);
        }

        public function testApplyWithEmptyValueCreatesNoResultCondition(): void
        {
            $query = new Table();

            $filter = new TaskPortfolioFilter();
            $filter->withValue('');
            $filter->withQuery($query);
            $filter->apply();

            $this->assertSame(
                [['column' => 'tasks.id', 'value' => 0]],
                $query->eqCalls
            );
        }
    }
}
