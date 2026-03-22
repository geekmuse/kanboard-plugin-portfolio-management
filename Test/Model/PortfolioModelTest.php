<?php

declare(strict_types=1);

namespace Kanboard\Core;

if (! class_exists(__NAMESPACE__ . '\\Base')) {
    class Base
    {
        /** @var mixed */
        protected $container;

        /** @var mixed */
        protected $db;

        /**
         * @param mixed $container
         */
        public function __construct($container = [])
        {
            $this->container = $container;

            if (is_array($container) && array_key_exists('db', $container)) {
                $this->db = $container['db'];
            }
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Model;

use Kanboard\Plugin\Portfolio\Model\PortfolioModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/PortfolioModel.php';

final class TestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): TestQueryBuilder
    {
        return new TestQueryBuilder($this->pdo, $table);
    }
}

final class TestQueryBuilder
{
    /**
     * @var array<int, array{column: string, operator: string, value: mixed}>
     */
    private array $conditions = [];

    /**
     * @var array<int, string>
     */
    private array $orderBy = [];

    public function __construct(private PDO $pdo, private string $table)
    {
    }

    /**
     * @param mixed $value
     */
    public function eq(string $column, $value): self
    {
        $this->conditions[] = [
            'column' => $column,
            'operator' => '=',
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function neq(string $column, $value): self
    {
        $this->conditions[] = [
            'column' => $column,
            'operator' => '!=',
            'value' => $value,
        ];

        return $this;
    }

    public function asc(string $column): self
    {
        $this->orderBy[] = $this->quoteIdentifier($column) . ' ASC';

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOne(): ?array
    {
        $sql = sprintf('SELECT * FROM %s', $this->quoteIdentifier($this->table));
        [$whereClause, $params] = $this->buildWhereClause();

        $sql .= $whereClause;

        if (! empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = sprintf('SELECT * FROM %s', $this->quoteIdentifier($this->table));
        [$whereClause, $params] = $this->buildWhereClause();

        $sql .= $whereClause;

        if (! empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : [];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return int|false
     */
    public function insert(array $values)
    {
        if ($values === []) {
            return false;
        }

        $columns = array_keys($values);
        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);
        $placeholders = [];
        $params = [];

        foreach ($columns as $index => $column) {
            $placeholder = ':insert_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $values[$column];
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table),
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            return false;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        [$whereClause, $whereParams] = $this->buildWhereClause();
        if ($whereClause === '') {
            return false;
        }

        $setClauses = [];
        $setParams = [];
        $index = 0;

        foreach ($values as $column => $value) {
            $placeholder = ':set_' . $index;
            $setClauses[] = sprintf('%s = %s', $this->quoteIdentifier($column), $placeholder);
            $setParams[$placeholder] = $value;
            ++$index;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($this->table),
            implode(', ', $setClauses),
            $whereClause
        );

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($setParams + $whereParams);
    }

    public function remove(): bool
    {
        [$whereClause, $params] = $this->buildWhereClause();
        if ($whereClause === '') {
            return false;
        }

        $sql = sprintf('DELETE FROM %s%s', $this->quoteIdentifier($this->table), $whereClause);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhereClause(): array
    {
        if ($this->conditions === []) {
            return ['', []];
        }

        $clauses = [];
        $params = [];

        foreach ($this->conditions as $index => $condition) {
            $placeholder = ':where_' . $index;
            $clauses[] = sprintf(
                '%s %s %s',
                $this->quoteIdentifier($condition['column']),
                $condition['operator'],
                $placeholder
            );
            $params[$placeholder] = $condition['value'];
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

class PortfolioModelTest extends TestCase
{
    private PDO $pdo;

    private PortfolioModel $model;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL DEFAULT 0)');

        version_1($this->pdo);

        $this->model = new PortfolioModel([
            'db' => new TestDatabase($this->pdo),
        ]);
    }

    public function testCreatePortfolio(): void
    {
        $portfolioId = $this->model->create([
            'name' => '  Q2 Launch  ',
            'description' => 'Cross-team release',
            'owner_id' => 7,
        ]);

        $this->assertIsInt($portfolioId);
        $this->assertGreaterThan(0, $portfolioId);

        $portfolio = $this->model->getById($portfolioId);

        $this->assertNotNull($portfolio);
        $this->assertSame('Q2 Launch', $portfolio['name']);
        $this->assertSame('Cross-team release', $portfolio['description']);
        $this->assertSame('7', (string) $portfolio['owner_id']);
        $this->assertGreaterThan(0, (int) $portfolio['created_at']);
        $this->assertGreaterThan(0, (int) $portfolio['updated_at']);
    }

    public function testCreateDuplicateName(): void
    {
        $this->assertIsInt($this->model->create(['name' => 'Roadmap']));

        $this->assertFalse($this->model->create(['name' => 'Roadmap']));
    }

    public function testCreateEmptyName(): void
    {
        $this->assertFalse($this->model->create(['name' => '']));
        $this->assertFalse($this->model->create(['name' => '   ']));
        $this->assertFalse($this->model->create(['name' => str_repeat('A', 256)]));
    }

    public function testGetPortfolio(): void
    {
        $portfolioId = $this->model->create(['name' => 'Delivery']);

        $portfolio = $this->model->getById((int) $portfolioId);

        $this->assertNotNull($portfolio);
        $this->assertSame((string) $portfolioId, (string) $portfolio['id']);
        $this->assertSame('Delivery', $portfolio['name']);
    }

    public function testGetPortfolioNotFound(): void
    {
        $this->assertNull($this->model->getById(9999));
    }

    public function testGetPortfolioByName(): void
    {
        $portfolioId = $this->model->create(['name' => 'ExactMatch']);

        $portfolio = $this->model->getByName('ExactMatch');

        $this->assertNotNull($portfolio);
        $this->assertSame((string) $portfolioId, (string) $portfolio['id']);
        $this->assertNull($this->model->getByName('exactmatch'));
    }

    public function testGetAllPortfolios(): void
    {
        $this->assertSame([], $this->model->getAll());

        $this->model->create(['name' => 'Zeta']);
        $this->model->create(['name' => 'Alpha']);

        $portfolios = $this->model->getAll();

        $this->assertCount(2, $portfolios);
        $this->assertSame('Alpha', $portfolios[0]['name']);
        $this->assertSame('Zeta', $portfolios[1]['name']);
    }

    public function testUpdatePortfolio(): void
    {
        $portfolioId = (int) $this->model->create([
            'name' => 'Initial',
            'description' => 'Before update',
            'owner_id' => 1,
        ]);

        $this->pdo
            ->prepare('UPDATE portfolios SET updated_at = :updated_at WHERE id = :id')
            ->execute([':updated_at' => 1, ':id' => $portfolioId]);

        $result = $this->model->update($portfolioId, [
            'name' => 'Updated',
            'description' => 'After update',
            'owner_id' => 2,
            'is_active' => 0,
        ]);

        $this->assertTrue($result);

        $portfolio = $this->model->getById($portfolioId);
        $this->assertNotNull($portfolio);
        $this->assertSame('Updated', $portfolio['name']);
        $this->assertSame('After update', $portfolio['description']);
        $this->assertSame('2', (string) $portfolio['owner_id']);
        $this->assertSame('0', (string) $portfolio['is_active']);
        $this->assertGreaterThan(1, (int) $portfolio['updated_at']);
    }

    public function testUpdateNameConflict(): void
    {
        $firstId = (int) $this->model->create(['name' => 'Platform']);
        $secondId = (int) $this->model->create(['name' => 'Infrastructure']);

        $this->assertGreaterThan(0, $firstId);
        $this->assertGreaterThan(0, $secondId);

        $this->assertFalse($this->model->update($secondId, ['name' => 'Platform']));
    }

    public function testUpdatePortfolioNotFound(): void
    {
        $this->assertFalse($this->model->update(404, ['name' => 'Nope']));
    }

    public function testRemovePortfolio(): void
    {
        $this->pdo->exec('INSERT INTO projects (id) VALUES (500)');
        $this->pdo->exec('INSERT INTO tasks (id, project_id) VALUES (900, 500)');

        $portfolioId = (int) $this->model->create(['name' => 'Cascade Target']);

        $this->pdo
            ->prepare('INSERT INTO portfolio_has_projects (portfolio_id, project_id, position, added_at) VALUES (:portfolio_id, :project_id, 1, 1000)')
            ->execute([
                ':portfolio_id' => $portfolioId,
                ':project_id' => 500,
            ]);

        $this->pdo
            ->prepare('INSERT INTO milestones (portfolio_id, name, status, owner_id, created_at, updated_at) VALUES (:portfolio_id, :name, 1, 1, 1000, 1000)')
            ->execute([
                ':portfolio_id' => $portfolioId,
                ':name' => 'Cross-project release',
            ]);

        $milestoneId = (int) $this->pdo->lastInsertId();

        $this->pdo
            ->prepare('INSERT INTO milestone_has_tasks (milestone_id, task_id, position, is_critical, added_at) VALUES (:milestone_id, :task_id, 1, 0, 1000)')
            ->execute([
                ':milestone_id' => $milestoneId,
                ':task_id' => 900,
            ]);

        $this->assertTrue($this->model->remove($portfolioId));

        $this->assertNull($this->model->getById($portfolioId));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM portfolio_has_projects')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM milestones')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM milestone_has_tasks')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    public function testRemoveNonExistent(): void
    {
        $this->assertFalse($this->model->remove(12345));
    }
}
