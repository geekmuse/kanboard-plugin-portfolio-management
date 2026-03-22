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

use Kanboard\Plugin\Portfolio\Model\PortfolioProjectModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/PortfolioProjectModel.php';

final class PortfolioProjectTestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): PortfolioProjectTestQueryBuilder
    {
        return new PortfolioProjectTestQueryBuilder($this->pdo, $table);
    }
}

final class PortfolioProjectTestQueryBuilder
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

        if ($this->orderBy !== []) {
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

        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
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

class PortfolioProjectModelTest extends TestCase
{
    private PDO $pdo;

    private PortfolioProjectModel $model;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec(
            "CREATE TABLE projects (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                description TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE tasks (
                id INTEGER PRIMARY KEY,
                project_id INTEGER NOT NULL DEFAULT 0
            )"
        );

        version_1($this->pdo);

        $this->model = new PortfolioProjectModel([
            'db' => new PortfolioProjectTestDatabase($this->pdo),
        ]);
    }

    public function testAddProjectMembership(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website Refresh');

        $this->assertTrue($this->model->add(1, 10, 3));

        $membership = $this->pdo
            ->query('SELECT portfolio_id, project_id, position, added_at FROM portfolio_has_projects')
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($membership);
        $this->assertSame('1', (string) $membership['portfolio_id']);
        $this->assertSame('10', (string) $membership['project_id']);
        $this->assertSame('3', (string) $membership['position']);
        $this->assertGreaterThan(0, (int) $membership['added_at']);
    }

    public function testAddProjectRejectsMissingEntitiesAndDuplicates(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website Refresh');

        $this->assertFalse($this->model->add(999, 10));
        $this->assertFalse($this->model->add(1, 999));

        $this->assertTrue($this->model->add(1, 10));
        $this->assertFalse($this->model->add(1, 10));
    }

    public function testGetProjectsReturnsOrderedMembershipData(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Gamma');
        $this->insertProject(20, 'Alpha');

        $this->assertTrue($this->model->add(1, 10, 2));
        $this->assertTrue($this->model->add(1, 20, 1));

        $projects = $this->model->getProjects(1);

        $this->assertCount(2, $projects);
        $this->assertSame('20', (string) $projects[0]['id']);
        $this->assertSame('Alpha', $projects[0]['name']);
        $this->assertSame('1', (string) $projects[0]['position']);
        $this->assertArrayHasKey('added_at', $projects[0]);
        $this->assertSame('10', (string) $projects[1]['id']);
        $this->assertSame('2', (string) $projects[1]['position']);
    }

    public function testGetPortfoliosReturnsReverseLookup(): void
    {
        $this->insertPortfolio(1, 'Zeta Portfolio');
        $this->insertPortfolio(2, 'Alpha Portfolio');
        $this->insertProject(10, 'Shared Project');

        $this->assertTrue($this->model->add(1, 10, 5));
        $this->assertTrue($this->model->add(2, 10, 1));

        $portfolios = $this->model->getPortfolios(10);

        $this->assertCount(2, $portfolios);
        $this->assertSame('Alpha Portfolio', $portfolios[0]['name']);
        $this->assertSame('2', (string) $portfolios[0]['id']);
        $this->assertSame('1', (string) $portfolios[0]['position']);
        $this->assertSame('Zeta Portfolio', $portfolios[1]['name']);
        $this->assertSame('5', (string) $portfolios[1]['position']);
    }

    public function testGetProjectIdsReturnsPositionOrder(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Gamma');
        $this->insertProject(20, 'Alpha');

        $this->assertTrue($this->model->add(1, 10, 2));
        $this->assertTrue($this->model->add(1, 20, 1));

        $this->assertSame([20, 10], $this->model->getProjectIds(1));
    }

    public function testRemoveProjectMembershipAndCleanupMilestoneTasks(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website Refresh');
        $this->insertProject(20, 'Mobile App');

        $this->insertTask(100, 10);
        $this->insertTask(200, 20);

        $this->assertTrue($this->model->add(1, 10));
        $this->assertTrue($this->model->add(1, 20));

        $this->pdo
            ->prepare(
                'INSERT INTO milestones (id, portfolio_id, name, status, owner_id, created_at, updated_at)
                 VALUES (:id, :portfolio_id, :name, 1, 1, 1000, 1000)'
            )
            ->execute([
                ':id' => 501,
                ':portfolio_id' => 1,
                ':name' => 'Launch',
            ]);

        $this->insertMilestoneTask(501, 100, 1);
        $this->insertMilestoneTask(501, 200, 2);

        $this->assertTrue($this->model->remove(1, 10));

        $membershipCount = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM portfolio_has_projects WHERE portfolio_id = 1 AND project_id = 10')
            ->fetchColumn();

        $remainingMembershipCount = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM portfolio_has_projects WHERE portfolio_id = 1 AND project_id = 20')
            ->fetchColumn();

        $removedTaskCount = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM milestone_has_tasks WHERE milestone_id = 501 AND task_id = 100')
            ->fetchColumn();

        $remainingTaskCount = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM milestone_has_tasks WHERE milestone_id = 501 AND task_id = 200')
            ->fetchColumn();

        $this->assertSame(0, $membershipCount);
        $this->assertSame(1, $remainingMembershipCount);
        $this->assertSame(0, $removedTaskCount);
        $this->assertSame(1, $remainingTaskCount);
    }

    public function testRemoveReturnsFalseWhenMembershipDoesNotExist(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website Refresh');

        $this->assertFalse($this->model->remove(1, 10));
    }

    private function insertPortfolio(int $portfolioId, string $name): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO portfolios (id, name, description, owner_id, is_active, created_at, updated_at)
                 VALUES (:id, :name, :description, 1, 1, 1000, 1000)'
            )
            ->execute([
                ':id' => $portfolioId,
                ':name' => $name,
                ':description' => $name . ' description',
            ]);
    }

    private function insertProject(int $projectId, string $name): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO projects (id, name, is_active, description)
                 VALUES (:id, :name, 1, :description)'
            )
            ->execute([
                ':id' => $projectId,
                ':name' => $name,
                ':description' => $name . ' project description',
            ]);
    }

    private function insertTask(int $taskId, int $projectId): void
    {
        $this->pdo
            ->prepare('INSERT INTO tasks (id, project_id) VALUES (:id, :project_id)')
            ->execute([
                ':id' => $taskId,
                ':project_id' => $projectId,
            ]);
    }

    private function insertMilestoneTask(int $milestoneId, int $taskId, int $position): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO milestone_has_tasks (milestone_id, task_id, position, is_critical, added_at)
                 VALUES (:milestone_id, :task_id, :position, 0, 1000)'
            )
            ->execute([
                ':milestone_id' => $milestoneId,
                ':task_id' => $taskId,
                ':position' => $position,
            ]);
    }
}
