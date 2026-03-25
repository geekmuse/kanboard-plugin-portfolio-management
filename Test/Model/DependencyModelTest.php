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

use Kanboard\Plugin\Portfolio\Model\DependencyModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/DependencyModel.php';

final class DependencyTestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): DependencyTestQueryBuilder
    {
        return new DependencyTestQueryBuilder($this->pdo, $table);
    }
}

final class DependencyTestEventDispatcher
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $events = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $eventName, array $payload): void
    {
        $this->events[] = [
            'event_name' => $eventName,
            'payload' => $payload,
        ];
    }
}

final class DependencyTestQueryBuilder
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
     * @param array<int, mixed> $values
     */
    public function in(string $column, array $values): self
    {
        $this->conditions[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
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
            if ($condition['operator'] === 'IN') {
                $inValues = (array) $condition['value'];
                if ($inValues === []) {
                    // Empty IN list — nothing can match
                    $clauses[] = '1=0';
                } else {
                    $inPlaceholders = [];
                    foreach ($inValues as $vi => $val) {
                        $placeholder = ':in_' . $index . '_' . $vi;
                        $inPlaceholders[] = $placeholder;
                        $params[$placeholder] = $val;
                    }

                    $clauses[] = sprintf(
                        '%s IN (%s)',
                        $this->quoteIdentifier($condition['column']),
                        implode(', ', $inPlaceholders)
                    );
                }
            } else {
                $placeholder = ':where_' . $index;
                $clauses[] = sprintf(
                    '%s %s %s',
                    $this->quoteIdentifier($condition['column']),
                    $condition['operator'],
                    $placeholder
                );
                $params[$placeholder] = $condition['value'];
            }
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

final class DependencyConfigModelStub
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

class DependencyModelTest extends TestCase
{
    private PDO $pdo;

    private DependencyModel $model;

    private DependencyTestEventDispatcher $dispatcher;

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
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL DEFAULT ''
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE tasks (
                id INTEGER PRIMARY KEY,
                title TEXT NOT NULL DEFAULT '',
                project_id INTEGER NOT NULL DEFAULT 0,
                owner_id INTEGER NOT NULL DEFAULT 0,
                priority INTEGER NOT NULL DEFAULT 0,
                color_id TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1
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

        $this->dispatcher = new DependencyTestEventDispatcher();
        $this->model = new DependencyModel([
            'db' => new DependencyTestDatabase($this->pdo),
            'dispatcher' => $this->dispatcher,
        ]);
    }

    public function testGetDependenciesReturnsEmptyArrayWhenNoDependenciesExist(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertTask(100, 'Task A', 10, 1);

        $this->assertSame([], $this->model->getDependencies(1));
        $this->assertSame([], $this->model->getBlockedTasks(1));
        $this->assertSame([], $this->model->getBlockingTasks(1));
        $this->assertSame([], $this->model->getCriticalPath(1));

        $graph = $this->model->getGraph(1);
        $this->assertSame([], $graph['nodes']);
        $this->assertSame([], $graph['edges']);
        $this->assertSame([], $graph['critical_path']);
    }

    public function testGetDependenciesSupportsCrossProjectFilteringAndDynamicLinkTypes(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(100, 'Alpha blocker', 10, 1);
        $this->insertTask(200, 'Beta dependent', 20, 1);
        $this->insertTask(300, 'Alpha helper', 10, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertLink(88, 'is blocked by', 'blocks');
        $this->insertLink(99, 'relates to', 'relates to');

        $this->insertTaskLink(100, 200, 77);
        $this->insertTaskLink(200, 300, 88);
        $this->insertTaskLink(100, 300, 77);
        $this->insertTaskLink(100, 200, 99);

        $crossProjectDependencies = $this->model->getDependencies(1, true);
        $allDependencies = $this->model->getDependencies(1, false);

        $this->assertCount(2, $crossProjectDependencies);
        $this->assertCount(3, $allDependencies);

        foreach ($crossProjectDependencies as $dependency) {
            $this->assertTrue((bool) $dependency['is_cross_project']);
            $this->assertSame('blocks', strtolower((string) $dependency['link_label']));
        }

        $pairs = array_map(
            static fn (array $dependency): string =>
                (string) $dependency['task_id'] . '->' . (string) $dependency['opposite_task_id'],
            $crossProjectDependencies
        );

        $this->assertContains('100->200', $pairs);
        $this->assertContains('300->200', $pairs);

        $sameProjectCount = 0;
        foreach ($allDependencies as $dependency) {
            if (! (bool) $dependency['is_cross_project']) {
                ++$sameProjectCount;
            }
        }

        $this->assertSame(1, $sameProjectCount);
    }

    public function testGetDependenciesHonorsConfiguredLinkLabels(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(100, 'Alpha blocker', 10, 1);
        $this->insertTask(200, 'Beta dependent', 20, 1);
        $this->insertTask(300, 'Beta related', 20, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertLink(88, 'relates to', 'is related by');

        $this->insertTaskLink(100, 200, 77);
        $this->insertTaskLink(100, 300, 88);

        $this->model = new DependencyModel([
            'db' => new DependencyTestDatabase($this->pdo),
            'dispatcher' => $this->dispatcher,
            'configModel' => new DependencyConfigModelStub([
                'portfolio_dependency_link_types' => 'relates to',
            ]),
        ]);

        $dependencies = $this->model->getDependencies(1, false);

        $this->assertCount(1, $dependencies);
        $this->assertSame('relates to', strtolower((string) $dependencies[0]['link_label']));
        $this->assertSame('100', (string) $dependencies[0]['task_id']);
        $this->assertSame('300', (string) $dependencies[0]['opposite_task_id']);
    }

    public function testResolvedDependenciesAreExcludedFromBlockedAndBlockingLists(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(400, 'Beta task waiting', 20, 1);
        $this->insertTask(500, 'Closed blocker', 10, 0);

        $this->insertLink(88, 'is blocked by', 'blocks');
        $this->insertTaskLink(400, 500, 88);

        $dependencies = $this->model->getDependencies(1, false);

        $this->assertCount(1, $dependencies);
        $this->assertTrue((bool) $dependencies[0]['is_resolved']);
        $this->assertSame([], $this->model->getBlockedTasks(1));
        $this->assertSame([], $this->model->getBlockingTasks(1));
    }

    public function testGetBlockedTasksReturnsUnresolvedTasksWithBlockers(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(600, 'Alpha blocker 1', 10, 1);
        $this->insertTask(601, 'Alpha blocker 2', 10, 1);
        $this->insertTask(602, 'Closed blocker', 10, 0);
        $this->insertTask(700, 'Blocked beta task', 20, 1);
        $this->insertTask(701, 'Resolved beta task', 20, 1);
        $this->insertTask(702, 'Closed beta task', 20, 0);

        $this->insertLink(77, 'blocks', 'is blocked by');

        $this->insertTaskLink(600, 700, 77);
        $this->insertTaskLink(601, 700, 77);
        $this->insertTaskLink(602, 701, 77);
        $this->insertTaskLink(600, 702, 77);

        $blockedTasks = $this->model->getBlockedTasks(1);

        $this->assertCount(1, $blockedTasks);
        $this->assertSame('700', (string) $blockedTasks[0]['id']);
        $this->assertSame('Blocked beta task', $blockedTasks[0]['title']);
        $this->assertSame('Project Beta', $blockedTasks[0]['project_name']);
        $this->assertCount(2, $blockedTasks[0]['blockers']);
        $this->assertSame('600', (string) $blockedTasks[0]['blockers'][0]['task_id']);
        $this->assertSame('601', (string) $blockedTasks[0]['blockers'][1]['task_id']);
    }

    public function testGetBlockingTasksReturnsOpenTasksThatBlockOpenDependents(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(800, 'Open blocker', 10, 1);
        $this->insertTask(801, 'Secondary blocker', 10, 1);
        $this->insertTask(802, 'Closed blocker', 10, 0);
        $this->insertTask(900, 'Open dependent 1', 20, 1);
        $this->insertTask(901, 'Open dependent 2', 20, 1);
        $this->insertTask(902, 'Closed dependent', 20, 0);
        $this->insertTask(903, 'Open dependent 3', 20, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');

        $this->insertTaskLink(800, 900, 77);
        $this->insertTaskLink(800, 901, 77);
        $this->insertTaskLink(801, 902, 77);
        $this->insertTaskLink(802, 903, 77);

        $blockingTasks = $this->model->getBlockingTasks(1);

        $this->assertCount(1, $blockingTasks);
        $this->assertSame('800', (string) $blockingTasks[0]['id']);
        $this->assertSame('Open blocker', $blockingTasks[0]['title']);
        $this->assertCount(2, $blockingTasks[0]['blocking']);
        $this->assertSame('900', (string) $blockingTasks[0]['blocking'][0]['task_id']);
        $this->assertSame('901', (string) $blockingTasks[0]['blocking'][1]['task_id']);
    }

    public function testGetCriticalPathLinearChainReturnsOrderedLongestChain(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(100, 'A', 10, 1);
        $this->insertTask(200, 'B', 20, 1);
        $this->insertTask(300, 'C', 20, 1);
        $this->insertTask(400, 'D', 20, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(100, 200, 77);
        $this->insertTaskLink(200, 300, 77);
        $this->insertTaskLink(300, 400, 77);

        $criticalPath = $this->model->getCriticalPath(1);

        $this->assertSame([100, 200, 300, 400], array_column($criticalPath, 'id'));
        $this->assertSame([1, 2, 3, 4], array_column($criticalPath, 'chain_position'));
        $this->assertSame([3, 2, 1, 0], array_column($criticalPath, 'downstream_count'));
    }

    public function testGetCriticalPathDiamondReturnsOneOfTheLongestPaths(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(10, 'A', 10, 1);
        $this->insertTask(20, 'B', 20, 1);
        $this->insertTask(30, 'C', 20, 1);
        $this->insertTask(40, 'D', 20, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(10, 20, 77);
        $this->insertTaskLink(10, 30, 77);
        $this->insertTaskLink(20, 40, 77);
        $this->insertTaskLink(30, 40, 77);

        $criticalPath = $this->model->getCriticalPath(1);
        $pathIds = array_column($criticalPath, 'id');

        $this->assertCount(3, $pathIds);
        $this->assertSame(10, $pathIds[0]);
        $this->assertSame(40, $pathIds[2]);
        $this->assertContains($pathIds[1], [20, 30]);
    }

    public function testGetCriticalPathHandlesCyclesDefensively(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(1, 'A', 10, 1);
        $this->insertTask(2, 'B', 20, 1);
        $this->insertTask(3, 'C', 20, 1);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(1, 2, 77);
        $this->insertTaskLink(2, 3, 77);
        $this->insertTaskLink(3, 1, 77);

        $criticalPath = $this->model->getCriticalPath(1);
        $pathIds = array_column($criticalPath, 'id');

        $this->assertNotSame([], $pathIds);
        $this->assertSame($pathIds, array_values(array_unique($pathIds)));
        $this->assertGreaterThanOrEqual(2, count($pathIds));
    }

    public function testGetGraphReturnsExpectedNodeEdgePayloadShape(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertUser(5, 'alice');

        $this->insertTask(100, 'Alpha blocker', 10, 1, 5, 2, 'green');
        $this->insertTask(200, 'Beta dependent', 20, 1, 0, 1, 'yellow');

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(100, 200, 77);

        $graph = $this->model->getGraph(1, true);

        $this->assertArrayHasKey('nodes', $graph);
        $this->assertArrayHasKey('edges', $graph);
        $this->assertArrayHasKey('critical_path', $graph);

        $this->assertCount(2, $graph['nodes']);
        $this->assertCount(1, $graph['edges']);
        $this->assertSame([100, 200], $graph['critical_path']);

        $firstNode = $graph['nodes'][0];
        $this->assertArrayHasKey('id', $firstNode);
        $this->assertArrayHasKey('title', $firstNode);
        $this->assertArrayHasKey('project_id', $firstNode);
        $this->assertArrayHasKey('project_name', $firstNode);
        $this->assertArrayHasKey('is_active', $firstNode);
        $this->assertArrayHasKey('priority', $firstNode);
        $this->assertArrayHasKey('color_id', $firstNode);
        $this->assertArrayHasKey('assignee', $firstNode);

        $this->assertSame(100, (int) $firstNode['id']);
        $this->assertSame('alice', (string) $firstNode['assignee']);

        $firstEdge = $graph['edges'][0];
        $this->assertSame(100, (int) $firstEdge['source']);
        $this->assertSame(200, (int) $firstEdge['target']);
        $this->assertSame('blocks', strtolower((string) $firstEdge['label']));
        $this->assertFalse((bool) $firstEdge['is_resolved']);
    }

    public function testOnTaskClosedFiresDependencyResolvedEvent(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertUser(9, 'eve');

        $this->insertTask(500, 'Finalize branding', 10, 0);
        $this->insertTask(600, 'Publish product page', 20, 1, 9);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(500, 600, 77);

        $this->model->onTaskClosed(500);

        $this->assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];

        $this->assertSame('portfolio.dependency.resolved', $event['event_name']);
        $payload = $event['payload'];

        $this->assertSame(500, (int) $payload['resolved_task_id']);
        $this->assertSame('Finalize branding', (string) $payload['resolved_task_title']);
        $this->assertSame(10, (int) $payload['resolved_project_id']);
        $this->assertSame('Project Alpha', (string) $payload['resolved_project_name']);
        $this->assertCount(1, $payload['unblocked_tasks']);
        $this->assertSame(600, (int) $payload['unblocked_tasks'][0]['task_id']);
        $this->assertSame(9, (int) $payload['unblocked_tasks'][0]['owner_id']);
    }

    public function testOnTaskClosedDoesNotFireEventWhenTaskIsStillBlockedByAnotherDependency(): void
    {
        $this->insertPortfolio(1, 'Delivery');
        $this->insertProject(10, 'Project Alpha');
        $this->insertProject(20, 'Project Beta');
        $this->insertPortfolioProject(1, 10, 1);
        $this->insertPortfolioProject(1, 20, 2);

        $this->insertTask(700, 'Closed blocker', 10, 0);
        $this->insertTask(701, 'Open blocker', 10, 1);
        $this->insertTask(800, 'Blocked dependent', 20, 1, 5);

        $this->insertLink(77, 'blocks', 'is blocked by');
        $this->insertTaskLink(700, 800, 77);
        $this->insertTaskLink(701, 800, 77);

        $this->model->onTaskClosed(700);

        $this->assertCount(0, $this->dispatcher->events);
    }

    public function testOnTaskOpenedAndOnLinkChangedAreNoOps(): void
    {
        $this->model->onTaskOpened(0);
        $this->model->onLinkChanged(0);
        $this->model->onTaskOpened(123);
        $this->model->onLinkChanged(123);

        $this->assertCount(0, $this->dispatcher->events);
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

    private function insertUser(int $userId, string $username): void
    {
        $this->pdo
            ->prepare('INSERT INTO users (id, username) VALUES (:id, :username)')
            ->execute([
                ':id' => $userId,
                ':username' => $username,
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

    private function insertTask(
        int $taskId,
        string $title,
        int $projectId,
        int $isActive,
        int $ownerId = 0,
        int $priority = 0,
        string $colorId = ''
    ): void {
        $this->pdo
            ->prepare(
                'INSERT INTO tasks (id, title, project_id, owner_id, priority, color_id, is_active)
                 VALUES (:id, :title, :project_id, :owner_id, :priority, :color_id, :is_active)'
            )
            ->execute([
                ':id' => $taskId,
                ':title' => $title,
                ':project_id' => $projectId,
                ':owner_id' => $ownerId,
                ':priority' => $priority,
                ':color_id' => $colorId,
                ':is_active' => $isActive,
            ]);
    }

    private function insertLink(int $linkId, string $label, string $oppositeLabel): void
    {
        $this->pdo
            ->prepare('INSERT INTO links (id, label, opposite_label) VALUES (:id, :label, :opposite_label)')
            ->execute([
                ':id' => $linkId,
                ':label' => $label,
                ':opposite_label' => $oppositeLabel,
            ]);
    }

    private function insertTaskLink(int $taskId, int $oppositeTaskId, int $linkId): void
    {
        $this->pdo
            ->prepare(
                'INSERT INTO task_has_links (task_id, opposite_task_id, link_id)
                 VALUES (:task_id, :opposite_task_id, :link_id)'
            )
            ->execute([
                ':task_id' => $taskId,
                ':opposite_task_id' => $oppositeTaskId,
                ':link_id' => $linkId,
            ]);
    }
}
