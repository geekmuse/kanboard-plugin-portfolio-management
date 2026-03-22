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

use Kanboard\Plugin\Portfolio\Model\MilestoneTaskModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/MilestoneTaskModel.php';

final class MilestoneTaskTestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): MilestoneTaskTestQueryBuilder
    {
        return new MilestoneTaskTestQueryBuilder($this->pdo, $table);
    }
}

final class MilestoneTaskTestQueryBuilder
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

class MilestoneTaskModelTest extends TestCase
{
    private PDO $pdo;

    private MilestoneTaskModel $model;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec(
            "CREATE TABLE projects (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE columns (
                id INTEGER PRIMARY KEY,
                project_id INTEGER NOT NULL DEFAULT 0,
                title TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL DEFAULT '',
                name TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE tasks (
                id INTEGER PRIMARY KEY,
                title TEXT NOT NULL DEFAULT '',
                project_id INTEGER NOT NULL DEFAULT 0,
                column_id INTEGER NOT NULL DEFAULT 0,
                owner_id INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                date_due INTEGER NOT NULL DEFAULT 0,
                priority INTEGER NOT NULL DEFAULT 0,
                color_id TEXT NOT NULL DEFAULT ''
            )"
        );

        version_1($this->pdo);

        $this->model = new MilestoneTaskModel([
            'db' => new MilestoneTaskTestDatabase($this->pdo),
        ]);
    }

    public function testAddTaskMembership(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertMilestone(501, 1, 'Release 1');
        $this->insertTask(200, 'Publish page', 10, 100, 5, 1, 1717200000, 2, 'yellow');

        $this->assertTrue($this->model->add(501, 200, 1, 4));

        $membership = $this->pdo
            ->query('SELECT milestone_id, task_id, position, is_critical, added_at FROM milestone_has_tasks')
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($membership);
        $this->assertSame('501', (string) $membership['milestone_id']);
        $this->assertSame('200', (string) $membership['task_id']);
        $this->assertSame('4', (string) $membership['position']);
        $this->assertSame('1', (string) $membership['is_critical']);
        $this->assertGreaterThan(0, (int) $membership['added_at']);
    }

    public function testAddRejectsMissingEntitiesOutOfPortfolioAndDuplicates(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website');
        $this->insertProject(20, 'Mobile');
        $this->insertMilestone(501, 1, 'Release 1');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertTask(200, 'In portfolio', 10, 0, 0, 1, 0, 0, 'blue');
        $this->insertTask(300, 'Outside portfolio', 20, 0, 0, 1, 0, 0, 'blue');

        $this->assertFalse($this->model->add(999, 200));
        $this->assertFalse($this->model->add(501, 999));
        $this->assertFalse($this->model->add(501, 300));

        $this->assertTrue($this->model->add(501, 200));
        $this->assertFalse($this->model->add(501, 200));
    }

    public function testRemoveTaskMembership(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertMilestone(501, 1, 'Release 1');
        $this->insertTask(200, 'Publish page', 10, 0, 0, 1, 0, 0, 'blue');

        $this->assertTrue($this->model->add(501, 200));
        $this->assertTrue($this->model->remove(501, 200));
        $this->assertFalse($this->model->remove(501, 200));
    }

    public function testGetTasksReturnsEnrichedRows(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website Project');
        $this->insertProject(20, 'Mobile Project');
        $this->insertColumn(100, 10, 'Backlog');
        $this->insertColumn(200, 20, 'In Progress');
        $this->insertUser(5, 'eve', 'Eve Smith');

        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertMilestone(501, 1, 'Release 1');

        $this->insertTask(1000, 'Second task', 10, 100, 5, 1, 1717200000, 2, 'yellow');
        $this->insertTask(1001, 'First task', 20, 200, 0, 0, 1717300000, 1, 'green');

        $this->assertTrue($this->model->add(501, 1000, 1, 2));
        $this->assertTrue($this->model->add(501, 1001, 0, 1));

        $tasks = $this->model->getTasks(501);

        $this->assertCount(2, $tasks);

        $this->assertSame('1001', (string) $tasks[0]['id']);
        $this->assertSame('First task', $tasks[0]['title']);
        $this->assertSame('20', (string) $tasks[0]['project_id']);
        $this->assertSame('Mobile Project', $tasks[0]['project_name']);
        $this->assertSame('200', (string) $tasks[0]['column_id']);
        $this->assertSame('In Progress', $tasks[0]['column_title']);
        $this->assertSame('0', (string) $tasks[0]['owner_id']);
        $this->assertSame('', $tasks[0]['assignee_username']);
        $this->assertSame('', $tasks[0]['assignee_name']);
        $this->assertSame('0', (string) $tasks[0]['is_active']);
        $this->assertSame('1717300000', (string) $tasks[0]['date_due']);
        $this->assertSame('1', (string) $tasks[0]['priority']);
        $this->assertSame('green', $tasks[0]['color_id']);
        $this->assertSame('1', (string) $tasks[0]['position']);
        $this->assertSame('0', (string) $tasks[0]['is_critical']);
        $this->assertArrayHasKey('added_at', $tasks[0]);

        $this->assertSame('1000', (string) $tasks[1]['id']);
        $this->assertSame('Website Project', $tasks[1]['project_name']);
        $this->assertSame('Backlog', $tasks[1]['column_title']);
        $this->assertSame('eve', $tasks[1]['assignee_username']);
        $this->assertSame('Eve Smith', $tasks[1]['assignee_name']);
        $this->assertSame('2', (string) $tasks[1]['position']);
        $this->assertSame('1', (string) $tasks[1]['is_critical']);
    }

    public function testGetMilestonesReturnsReverseLookup(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Website');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertTask(200, 'Shared task', 10, 0, 0, 1, 0, 0, 'blue');

        $this->insertMilestone(500, 1, 'Alpha');
        $this->insertMilestone(600, 1, 'Beta');

        $this->assertTrue($this->model->add(600, 200, 1, 9));
        $this->assertTrue($this->model->add(500, 200, 0, 3));

        $milestones = $this->model->getMilestones(200);

        $this->assertCount(2, $milestones);
        $this->assertSame('500', (string) $milestones[0]['id']);
        $this->assertSame('Alpha', $milestones[0]['name']);
        $this->assertSame('3', (string) $milestones[0]['position']);
        $this->assertSame('0', (string) $milestones[0]['is_critical']);
        $this->assertArrayHasKey('added_at', $milestones[0]);

        $this->assertSame('600', (string) $milestones[1]['id']);
        $this->assertSame('Beta', $milestones[1]['name']);
        $this->assertSame('9', (string) $milestones[1]['position']);
        $this->assertSame('1', (string) $milestones[1]['is_critical']);

        $this->assertSame([], $this->model->getMilestones(9999));
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
            ->prepare('INSERT INTO projects (id, name) VALUES (:id, :name)')
            ->execute([
                ':id' => $projectId,
                ':name' => $name,
            ]);
    }

    private function insertPortfolioProject(int $portfolioId, int $projectId, int $position): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO portfolio_has_projects (portfolio_id, project_id, position, added_at)
                 VALUES (:portfolio_id, :project_id, :position, 1000)'
            )
            ->execute([
                ':portfolio_id' => $portfolioId,
                ':project_id' => $projectId,
                ':position' => $position,
            ]);
    }

    private function insertMilestone(int $milestoneId, int $portfolioId, string $name): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO milestones (id, portfolio_id, name, status, owner_id, created_at, updated_at)
                 VALUES (:id, :portfolio_id, :name, 1, 1, 1000, 1000)'
            )
            ->execute([
                ':id' => $milestoneId,
                ':portfolio_id' => $portfolioId,
                ':name' => $name,
            ]);
    }

    private function insertColumn(int $columnId, int $projectId, string $title): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO columns (id, project_id, title)
                 VALUES (:id, :project_id, :title)'
            )
            ->execute([
                ':id' => $columnId,
                ':project_id' => $projectId,
                ':title' => $title,
            ]);
    }

    private function insertUser(int $userId, string $username, string $name): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO users (id, username, name)
                 VALUES (:id, :username, :name)'
            )
            ->execute([
                ':id' => $userId,
                ':username' => $username,
                ':name' => $name,
            ]);
    }

    private function insertTask(
        int $taskId,
        string $title,
        int $projectId,
        int $columnId,
        int $ownerId,
        int $isActive,
        int $dateDue,
        int $priority,
        string $colorId
    ): void {
        $this->pdo
            ->prepare(
                'INSERT INTO tasks (id, title, project_id, column_id, owner_id, is_active, date_due, priority, color_id)
                 VALUES (:id, :title, :project_id, :column_id, :owner_id, :is_active, :date_due, :priority, :color_id)'
            )
            ->execute([
                ':id' => $taskId,
                ':title' => $title,
                ':project_id' => $projectId,
                ':column_id' => $columnId,
                ':owner_id' => $ownerId,
                ':is_active' => $isActive,
                ':date_due' => $dateDue,
                ':priority' => $priority,
                ':color_id' => $colorId,
            ]);
    }
}
