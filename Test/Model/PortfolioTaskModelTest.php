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

use Kanboard\Plugin\Portfolio\Model\PortfolioTaskModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/PortfolioTaskModel.php';

final class PortfolioTaskTestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): PortfolioTaskTestQueryBuilder
    {
        return new PortfolioTaskTestQueryBuilder($this->pdo, $table);
    }
}

final class PortfolioTaskTestQueryBuilder
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

final class PortfolioTaskCriticalPathStub
{
    /**
     * @param array<int, mixed> $criticalPath
     */
    public function __construct(private array $criticalPath)
    {
    }

    /**
     * @return array<int, mixed>
     */
    public function getCriticalPath(int $portfolioId): array
    {
        return $this->criticalPath;
    }
}

final class PortfolioTaskConfigModelStub
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    /** @param mixed $default */
    public function get(string $key, $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

class PortfolioTaskModelTest extends TestCase
{
    private PDO $pdo;

    private PortfolioTaskModel $model;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo->exec(
            "CREATE TABLE projects (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1
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
                date_creation INTEGER NOT NULL DEFAULT 0,
                priority INTEGER NOT NULL DEFAULT 0,
                score INTEGER NOT NULL DEFAULT 0,
                color_id TEXT NOT NULL DEFAULT '',
                category_id INTEGER NOT NULL DEFAULT 0,
                swimlane_id INTEGER NOT NULL DEFAULT 0
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE links (
                id INTEGER PRIMARY KEY,
                label TEXT NOT NULL DEFAULT '',
                opposite_label TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE task_has_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                opposite_task_id INTEGER NOT NULL,
                link_id INTEGER NOT NULL
            )"
        );

        version_1($this->pdo);

        $this->model = $this->createModel();
    }

    public function testGetTasksSupportsFiltersAndEnrichment(): void
    {
        $this->seedPortfolioTaskFixtures();

        $tasks = $this->model->getTasks(1);

        $this->assertSame([202, 101, 201, 102], $this->extractTaskIds($tasks));

        $blockedTask = $this->findTaskById($tasks, 201);
        $this->assertIsArray($blockedTask);
        $this->assertSame('Beta', (string) $blockedTask['project_name']);
        $this->assertSame('Doing', (string) $blockedTask['column_title']);
        $this->assertSame('bob', (string) $blockedTask['assignee_username']);
        $this->assertSame('Bob Brown', (string) $blockedTask['assignee_name']);
        $this->assertTrue((bool) $blockedTask['is_blocked']);
        $this->assertSame(1, (int) $blockedTask['blocked_by_count']);

        $closedTask = $this->findTaskById($tasks, 102);
        $this->assertIsArray($closedTask);
        $this->assertFalse((bool) $closedTask['is_blocked']);
        $this->assertSame(0, (int) $closedTask['blocked_by_count']);

        $this->assertSame([202, 101, 201], $this->extractTaskIds($this->model->getTasks(1, ['status_id' => 1])));
        $this->assertSame([101, 102], $this->extractTaskIds($this->model->getTasks(1, ['assignee_id' => 5])));
        $this->assertSame([202, 201], $this->extractTaskIds($this->model->getTasks(1, ['project_id' => 20])));
        $this->assertSame([101, 201], $this->extractTaskIds($this->model->getTasks(1, ['milestone_id' => 501])));
        $this->assertSame([202, 101, 201], $this->extractTaskIds($this->model->getTasks(1, ['has_dependencies' => true])));
        $this->assertSame([], $this->model->getTasks(1, ['milestone_id' => 999]));
    }

    public function testGetTasksSupportsSortingAndPagination(): void
    {
        $this->seedPortfolioTaskFixtures();

        $projectSorted = $this->model->getTasks(1, [
            'sort' => 'project',
            'direction' => 'ASC',
            'limit' => 2,
            'offset' => 1,
        ]);

        $this->assertSame([102, 201], $this->extractTaskIds($projectSorted));

        $creationSorted = $this->model->getTasks(1, [
            'sort' => 'date_creation',
            'direction' => 'DESC',
        ]);

        $this->assertSame([202, 201, 101, 102], $this->extractTaskIds($creationSorted));
    }

    public function testGetTasksUsesConfiguredDefaultLimitWhenLimitIsMissing(): void
    {
        $this->seedPortfolioTaskFixtures();

        $this->model = $this->createModel([
            'configModel' => new PortfolioTaskConfigModelStub([
                'portfolio_tasks_per_page' => 2,
            ]),
        ]);

        $tasks = $this->model->getTasks(1, [
            'sort' => 'date_creation',
            'direction' => 'DESC',
            'offset' => 0,
        ]);

        $this->assertCount(2, $tasks);
        $this->assertSame([202, 201], $this->extractTaskIds($tasks));
    }

    public function testGetCountsAggregatesTotalsAndStatusFilters(): void
    {
        $this->seedPortfolioTaskFixtures();

        $allCounts = $this->model->getCounts(1);
        $this->assertSame(
            [
                'total' => 4,
                'active' => 3,
                'closed' => 1,
                'blocked' => 2,
            ],
            $allCounts
        );

        $activeCounts = $this->model->getCounts(1, 1);
        $this->assertSame(
            [
                'total' => 3,
                'active' => 3,
                'closed' => 0,
                'blocked' => 2,
            ],
            $activeCounts
        );

        $closedCounts = $this->model->getCounts(1, 0);
        $this->assertSame(
            [
                'total' => 1,
                'active' => 0,
                'closed' => 1,
                'blocked' => 0,
            ],
            $closedCounts
        );
    }

    public function testGetOverviewBuildsSummaryMetrics(): void
    {
        $this->seedPortfolioTaskFixtures();

        $this->insertMilestone(502, 1, 'Overdue Delivery', time() - 86400);
        $this->insertMilestoneTask(502, 202, 1);

        $this->model = $this->createModel([
            'dependencyModel' => new PortfolioTaskCriticalPathStub([100, 200, 300, 400]),
        ]);

        $overview = $this->model->getOverview(1);

        $this->assertSame('1', (string) $overview['portfolio']['id']);
        $this->assertSame(2, (int) $overview['project_count']);
        $this->assertCount(2, $overview['projects']);
        $this->assertSame([10, 20], array_map(static fn (array $project): int => (int) $project['id'], $overview['projects']));

        $this->assertSame(
            [
                'total' => 4,
                'active' => 3,
                'closed' => 1,
                'blocked' => 2,
            ],
            $overview['task_counts']
        );

        $this->assertCount(2, $overview['milestones']);

        $upcomingMilestone = $this->findMilestoneById($overview['milestones'], 501);
        $this->assertIsArray($upcomingMilestone);
        $this->assertTrue((bool) $upcomingMilestone['is_at_risk']);
        $this->assertFalse((bool) $upcomingMilestone['is_overdue']);

        $overdueMilestone = $this->findMilestoneById($overview['milestones'], 502);
        $this->assertIsArray($overdueMilestone);
        $this->assertFalse((bool) $overdueMilestone['is_at_risk']);
        $this->assertTrue((bool) $overdueMilestone['is_overdue']);

        $this->assertSame(1, (int) $overview['at_risk_milestones']);
        $this->assertSame(1, (int) $overview['overdue_milestones']);
        $this->assertSame(4, (int) $overview['critical_path_length']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createModel(array $overrides = []): PortfolioTaskModel
    {
        $container = array_merge([
            'db' => new PortfolioTaskTestDatabase($this->pdo),
        ], $overrides);

        return new PortfolioTaskModel($container);
    }

    private function seedPortfolioTaskFixtures(): void
    {
        $this->insertPortfolio(1, 'Roadmap');

        $this->insertProject(10, 'Alpha');
        $this->insertProject(20, 'Beta');
        $this->insertProject(30, 'Gamma');

        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertColumn(100, 10, 'Backlog');
        $this->insertColumn(200, 20, 'Doing');

        $this->insertUser(5, 'alice', 'Alice Adams');
        $this->insertUser(6, 'bob', 'Bob Brown');
        $this->insertUser(7, 'cara', 'Cara Cole');

        $this->insertTask(101, 'Alpha prepare', 10, 100, 5, 1, 1717200000, 1711000000, 3, 'yellow');
        $this->insertTask(102, 'Alpha done', 10, 100, 5, 0, 1716100000, 1710900000, 1, 'blue');
        $this->insertTask(201, 'Beta blocked', 20, 200, 6, 1, 1717300000, 1712000000, 2, 'green');
        $this->insertTask(202, 'Beta blocker', 20, 200, 7, 1, 1717400000, 1713000000, 5, 'orange');
        $this->insertTask(301, 'Gamma outside', 30, 0, 5, 1, 1717500000, 1714000000, 4, 'purple');

        $this->insertMilestone(501, 1, 'Release Candidate', time() + (2 * 86400));
        $this->insertMilestoneTask(501, 101, 1);
        $this->insertMilestoneTask(501, 201, 2);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(101, 201, 77);
        $this->insertTaskLink(202, 101, 77);
        $this->insertTaskLink(101, 102, 77);
        $this->insertTaskLink(301, 101, 77);
    }

    /**
     * @return array<int, int>
     */
    private function extractTaskIds(array $tasks): array
    {
        return array_map(static fn (array $task): int => (int) ($task['id'] ?? 0), $tasks);
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     *
     * @return array<string, mixed>|null
     */
    private function findTaskById(array $tasks, int $taskId): ?array
    {
        foreach ($tasks as $task) {
            if ((int) ($task['id'] ?? 0) === $taskId) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $milestones
     *
     * @return array<string, mixed>|null
     */
    private function findMilestoneById(array $milestones, int $milestoneId): ?array
    {
        foreach ($milestones as $milestone) {
            if ((int) ($milestone['id'] ?? 0) === $milestoneId) {
                return $milestone;
            }
        }

        return null;
    }

    private function insertPortfolio(int $id, string $name): void
    {
        $this->pdo->prepare(
            'INSERT INTO portfolios (id, name, description, owner_id, is_active, created_at, updated_at)
             VALUES (:id, :name, :description, :owner_id, :is_active, :created_at, :updated_at)'
        )->execute([
            ':id' => $id,
            ':name' => $name,
            ':description' => '',
            ':owner_id' => 0,
            ':is_active' => 1,
            ':created_at' => time(),
            ':updated_at' => time(),
        ]);
    }

    private function insertProject(int $id, string $name): void
    {
        $this->pdo->prepare('INSERT INTO projects (id, name, is_active) VALUES (:id, :name, :is_active)')
            ->execute([
                ':id' => $id,
                ':name' => $name,
                ':is_active' => 1,
            ]);
    }

    private function insertPortfolioProject(int $portfolioId, int $projectId, int $position): void
    {
        $this->pdo->prepare(
            'INSERT INTO portfolio_has_projects (portfolio_id, project_id, position, added_at)
             VALUES (:portfolio_id, :project_id, :position, :added_at)'
        )->execute([
            ':portfolio_id' => $portfolioId,
            ':project_id' => $projectId,
            ':position' => $position,
            ':added_at' => time(),
        ]);
    }

    private function insertColumn(int $id, int $projectId, string $title): void
    {
        $this->pdo->prepare('INSERT INTO columns (id, project_id, title) VALUES (:id, :project_id, :title)')
            ->execute([
                ':id' => $id,
                ':project_id' => $projectId,
                ':title' => $title,
            ]);
    }

    private function insertUser(int $id, string $username, string $name): void
    {
        $this->pdo->prepare('INSERT INTO users (id, username, name) VALUES (:id, :username, :name)')
            ->execute([
                ':id' => $id,
                ':username' => $username,
                ':name' => $name,
            ]);
    }

    private function insertTask(
        int $id,
        string $title,
        int $projectId,
        int $columnId,
        int $ownerId,
        int $isActive,
        int $dateDue,
        int $dateCreation,
        int $priority,
        string $colorId
    ): void {
        $this->pdo->prepare(
            'INSERT INTO tasks (
                id,
                title,
                project_id,
                column_id,
                owner_id,
                is_active,
                date_due,
                date_creation,
                priority,
                score,
                color_id,
                category_id,
                swimlane_id
            ) VALUES (
                :id,
                :title,
                :project_id,
                :column_id,
                :owner_id,
                :is_active,
                :date_due,
                :date_creation,
                :priority,
                :score,
                :color_id,
                :category_id,
                :swimlane_id
            )'
        )->execute([
            ':id' => $id,
            ':title' => $title,
            ':project_id' => $projectId,
            ':column_id' => $columnId,
            ':owner_id' => $ownerId,
            ':is_active' => $isActive,
            ':date_due' => $dateDue,
            ':date_creation' => $dateCreation,
            ':priority' => $priority,
            ':score' => 0,
            ':color_id' => $colorId,
            ':category_id' => 0,
            ':swimlane_id' => 0,
        ]);
    }

    private function insertMilestone(int $id, int $portfolioId, string $name, int $targetDate): void
    {
        $this->pdo->prepare(
            'INSERT INTO milestones (
                id,
                portfolio_id,
                name,
                description,
                target_date,
                status,
                color_id,
                owner_id,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :portfolio_id,
                :name,
                :description,
                :target_date,
                :status,
                :color_id,
                :owner_id,
                :created_at,
                :updated_at
            )'
        )->execute([
            ':id' => $id,
            ':portfolio_id' => $portfolioId,
            ':name' => $name,
            ':description' => '',
            ':target_date' => $targetDate,
            ':status' => 1,
            ':color_id' => 'blue',
            ':owner_id' => 0,
            ':created_at' => time(),
            ':updated_at' => time(),
        ]);
    }

    private function insertMilestoneTask(int $milestoneId, int $taskId, int $position): void
    {
        $this->pdo->prepare(
            'INSERT INTO milestone_has_tasks (milestone_id, task_id, position, is_critical, added_at)
             VALUES (:milestone_id, :task_id, :position, :is_critical, :added_at)'
        )->execute([
            ':milestone_id' => $milestoneId,
            ':task_id' => $taskId,
            ':position' => $position,
            ':is_critical' => 0,
            ':added_at' => time(),
        ]);
    }

    private function insertLink(int $id, string $label, string $oppositeLabel): void
    {
        $this->pdo->prepare('INSERT INTO links (id, label, opposite_label) VALUES (:id, :label, :opposite_label)')
            ->execute([
                ':id' => $id,
                ':label' => $label,
                ':opposite_label' => $oppositeLabel,
            ]);
    }

    private function insertTaskLink(int $taskId, int $oppositeTaskId, int $linkId): void
    {
        $this->pdo->prepare(
            'INSERT INTO task_has_links (task_id, opposite_task_id, link_id)
             VALUES (:task_id, :opposite_task_id, :link_id)'
        )->execute([
            ':task_id' => $taskId,
            ':opposite_task_id' => $oppositeTaskId,
            ':link_id' => $linkId,
        ]);
    }
}
