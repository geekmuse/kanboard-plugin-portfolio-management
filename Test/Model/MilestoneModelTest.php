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

use Kanboard\Plugin\Portfolio\Model\MilestoneModel;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use function Kanboard\Plugin\Portfolio\Schema\version_1;

require_once __DIR__ . '/../../Schema/Sqlite.php';
require_once __DIR__ . '/../../Model/MilestoneModel.php';

final class MilestoneTestDatabase
{
    public function __construct(private PDO $pdo)
    {
    }

    public function table(string $table): MilestoneTestQueryBuilder
    {
        return new MilestoneTestQueryBuilder($this->pdo, $table);
    }
}

final class MilestoneTestQueryBuilder
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

        foreach (array_keys($values) as $index => $column) {
            $placeholder = ':set_' . $index;
            $setClauses[] = sprintf('%s = %s', $this->quoteIdentifier($column), $placeholder);
            $setParams[$placeholder] = $values[$column];
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

final class MilestoneConfigModelStub
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

class MilestoneModelTest extends TestCase
{
    private PDO $pdo;

    private MilestoneModel $model;

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
            "CREATE TABLE tasks (
                id INTEGER PRIMARY KEY,
                project_id INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                score INTEGER NOT NULL DEFAULT 0,
                time_estimated INTEGER NOT NULL DEFAULT 0
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

        $this->model = new MilestoneModel([
            'db' => new MilestoneTestDatabase($this->pdo),
        ]);
    }

    public function testCreateMilestoneParsesDateAndTimestampInputs(): void
    {
        $this->insertPortfolio(1, 'Roadmap');

        $milestoneId = $this->model->create([
            'portfolio_id' => 1,
            'name' => '  Release 1  ',
            'description' => 'Feature complete',
            'target_date' => '2026-06-01',
            'color_id' => 'yellow',
            'owner_id' => 7,
        ]);

        $this->assertIsInt($milestoneId);
        $this->assertGreaterThan(0, $milestoneId);

        $milestone = $this->model->getById((int) $milestoneId);

        $this->assertNotNull($milestone);
        $this->assertSame('Release 1', $milestone['name']);
        $this->assertSame('Feature complete', $milestone['description']);
        $this->assertSame('1', (string) $milestone['portfolio_id']);
        $this->assertSame('1', (string) $milestone['status']);
        $this->assertSame('yellow', $milestone['color_id']);
        $this->assertSame('7', (string) $milestone['owner_id']);
        $this->assertGreaterThan(0, (int) $milestone['target_date']);

        $timestampMilestoneId = $this->model->create([
            'portfolio_id' => 1,
            'name' => 'Release 2',
            'target_date' => '1717200000',
        ]);

        $this->assertIsInt($timestampMilestoneId);
        $timestampMilestone = $this->model->getById((int) $timestampMilestoneId);

        $this->assertNotNull($timestampMilestone);
        $this->assertSame('1717200000', (string) $timestampMilestone['target_date']);
    }

    public function testCreateRejectsInvalidInputs(): void
    {
        $this->insertPortfolio(1, 'Roadmap');

        $this->assertFalse($this->model->create([
            'portfolio_id' => 999,
            'name' => 'Missing portfolio',
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => '',
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => '   ',
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => str_repeat('A', 256),
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => 'Bad Date',
            'target_date' => '2026-13-40',
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => 'Bad Status',
            'status' => 9,
        ]));
    }

    public function testCreateEnforcesUniqueNameWithinPortfolio(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertPortfolio(2, 'Delivery');

        $this->assertIsInt($this->model->create([
            'portfolio_id' => 1,
            'name' => 'Release',
        ]));

        $this->assertFalse($this->model->create([
            'portfolio_id' => 1,
            'name' => 'Release',
        ]));

        $this->assertIsInt($this->model->create([
            'portfolio_id' => 2,
            'name' => 'Release',
        ]));
    }

    public function testGetByPortfolioIdReturnsTargetDateOrder(): void
    {
        $this->insertPortfolio(1, 'Roadmap');

        $firstId = $this->createMilestone(1, 'Later', time() + 3600);
        $secondId = $this->createMilestone(1, 'Earlier', time() + 600);
        $thirdId = $this->createMilestone(1, 'No Date', 0);

        $milestones = $this->model->getByPortfolioId(1);

        $this->assertCount(3, $milestones);
        $this->assertSame((string) $thirdId, (string) $milestones[0]['id']);
        $this->assertSame((string) $secondId, (string) $milestones[1]['id']);
        $this->assertSame((string) $firstId, (string) $milestones[2]['id']);
    }

    public function testUpdateMilestone(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $milestoneId = $this->createMilestone(1, 'Initial', '2026-06-01');

        $this->pdo
            ->prepare('UPDATE milestones SET updated_at = :updated_at WHERE id = :id')
            ->execute([':updated_at' => 1, ':id' => $milestoneId]);

        $result = $this->model->update($milestoneId, [
            'name' => 'Updated',
            'description' => 'Updated description',
            'target_date' => '1717200000',
            'status' => 0,
            'color_id' => 'green',
            'owner_id' => 4,
        ]);

        $this->assertTrue($result);

        $milestone = $this->model->getById($milestoneId);

        $this->assertNotNull($milestone);
        $this->assertSame('Updated', $milestone['name']);
        $this->assertSame('Updated description', $milestone['description']);
        $this->assertSame('1717200000', (string) $milestone['target_date']);
        $this->assertSame('0', (string) $milestone['status']);
        $this->assertSame('green', $milestone['color_id']);
        $this->assertSame('4', (string) $milestone['owner_id']);
        $this->assertGreaterThan(1, (int) $milestone['updated_at']);
    }

    public function testUpdateRejectsDuplicateNameInvalidValuesAndMissingMilestone(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $alphaId = $this->createMilestone(1, 'Alpha', 0);
        $betaId = $this->createMilestone(1, 'Beta', 0);

        $this->assertGreaterThan(0, $alphaId);
        $this->assertGreaterThan(0, $betaId);

        $this->assertFalse($this->model->update($betaId, ['name' => 'Alpha']));
        $this->assertFalse($this->model->update($alphaId, ['target_date' => 'invalid-date']));
        $this->assertFalse($this->model->update($alphaId, ['status' => 12]));
        $this->assertFalse($this->model->update(9999, ['name' => 'Ghost']));
    }

    public function testRemoveMilestoneCascadesMembershipRows(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');
        $this->insertTask(200, 10, 1);

        $milestoneId = $this->createMilestone(1, 'Delete me', 0);
        $this->insertMilestoneTask($milestoneId, 200, 1);

        $this->assertTrue($this->model->remove($milestoneId));

        $this->assertNull($this->model->getById($milestoneId));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM milestone_has_tasks')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
        $this->assertFalse($this->model->remove(9999));
    }

    public function testGetProgressReturnsNullWhenMilestoneDoesNotExist(): void
    {
        $this->assertNull($this->model->getProgress(9999));
    }

    public function testGetProgressReturnsZeroWhenMilestoneHasNoTasks(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $milestoneId = $this->createMilestone(1, 'No Tasks', 0);

        $progress = $this->model->getProgress($milestoneId);

        $this->assertNotNull($progress);
        $this->assertSame(0, $progress['total']);
        $this->assertSame(0, $progress['completed']);
        $this->assertSame(0.0, $progress['percent']);
        $this->assertSame(0, $progress['blocked_count']);
        $this->assertFalse($progress['is_at_risk']);
        $this->assertFalse($progress['is_overdue']);
        $this->assertSame('0', (string) $progress['target_date']);
    }

    public function testGetProgressPartialAtRiskAndBlocked(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'At Risk', time() + (3 * 86400));

        $this->insertTask(100, 10, 0);
        $this->insertTask(200, 10, 0);
        $this->insertTask(300, 10, 0);
        $this->insertTask(400, 10, 1);
        $this->insertTask(500, 10, 1);
        $this->insertTask(900, 10, 1);

        $this->insertMilestoneTask($milestoneId, 100, 1);
        $this->insertMilestoneTask($milestoneId, 200, 2);
        $this->insertMilestoneTask($milestoneId, 300, 3);
        $this->insertMilestoneTask($milestoneId, 400, 4);
        $this->insertMilestoneTask($milestoneId, 500, 5);

        $this->insertLink(1, 'is blocked by', 'blocks');
        $this->insertTaskLink(400, 900, 1);

        $progress = $this->model->getProgress($milestoneId);

        $this->assertNotNull($progress);
        $this->assertSame(5, $progress['total']);
        $this->assertSame(3, $progress['completed']);
        $this->assertSame(60.0, $progress['percent']);
        $this->assertSame(1, $progress['blocked_count']);
        $this->assertTrue($progress['is_at_risk']);
        $this->assertFalse($progress['is_overdue']);
    }

    public function testGetProgressUsesConfiguredRiskWindowAndThreshold(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'Custom Risk', time() + (2 * 86400));

        $this->insertTask(1100, 10, 0);
        $this->insertTask(1101, 10, 1);
        $this->insertMilestoneTask($milestoneId, 1100, 1);
        $this->insertMilestoneTask($milestoneId, 1101, 2);

        $this->model = new MilestoneModel([
            'db' => new MilestoneTestDatabase($this->pdo),
            'configModel' => new MilestoneConfigModelStub([
                'portfolio_milestone_at_risk_days' => 1,
                'portfolio_milestone_at_risk_threshold' => 60,
            ]),
        ]);

        $progress = $this->model->getProgress($milestoneId);

        $this->assertNotNull($progress);
        $this->assertSame(50.0, $progress['percent']);
        $this->assertFalse($progress['is_at_risk']);
    }

    public function testGetProgressCompletedAndOverdueStates(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $completedMilestoneId = $this->createMilestone(1, 'Completed', time() - 86400);
        $this->insertTask(1000, 10, 0);
        $this->insertTask(1001, 10, 0);
        $this->insertMilestoneTask($completedMilestoneId, 1000, 1);
        $this->insertMilestoneTask($completedMilestoneId, 1001, 2);

        $completedProgress = $this->model->getProgress($completedMilestoneId);

        $this->assertNotNull($completedProgress);
        $this->assertSame(100.0, $completedProgress['percent']);
        $this->assertFalse($completedProgress['is_overdue']);
        $this->assertFalse($completedProgress['is_at_risk']);

        $overdueMilestoneId = $this->createMilestone(1, 'Overdue', time() - 86400);
        $this->insertTask(2000, 10, 0);
        $this->insertTask(2001, 10, 1);
        $this->insertMilestoneTask($overdueMilestoneId, 2000, 1);
        $this->insertMilestoneTask($overdueMilestoneId, 2001, 2);

        $overdueProgress = $this->model->getProgress($overdueMilestoneId);

        $this->assertNotNull($overdueProgress);
        $this->assertSame(50.0, $overdueProgress['percent']);
        $this->assertTrue($overdueProgress['is_overdue']);
        $this->assertFalse($overdueProgress['is_at_risk']);
    }

    public function testGetProgressWeightByCountIsBackwardCompatible(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'Weighted Count', 0);

        // 2 completed, 1 active; scores set but should be ignored in count mode
        $this->insertTask(601, 10, 0, 5, 4);
        $this->insertTask(602, 10, 0, 3, 2);
        $this->insertTask(603, 10, 1, 10, 8);

        $this->insertMilestoneTask($milestoneId, 601, 1);
        $this->insertMilestoneTask($milestoneId, 602, 2);
        $this->insertMilestoneTask($milestoneId, 603, 3);

        $progressDefault = $this->model->getProgress($milestoneId);
        $progressCount = $this->model->getProgress($milestoneId, 'count');

        $this->assertNotNull($progressDefault);
        $this->assertNotNull($progressCount);

        // Count mode: 2/3 = 66.67%
        $this->assertSame(66.67, $progressDefault['percent']);
        $this->assertSame(66.67, $progressCount['percent']);
        $this->assertSame('count', $progressDefault['weight_by']);
        $this->assertSame('count', $progressCount['weight_by']);
        $this->assertFalse($progressDefault['no_data']);
        $this->assertFalse($progressCount['no_data']);

        // Score/time totals are still returned even in count mode
        $this->assertSame(18, $progressCount['score_total']);
        $this->assertSame(8, $progressCount['score_completed']);
        $this->assertSame(14, $progressCount['time_total']);
        $this->assertSame(6, $progressCount['time_completed']);
    }

    public function testGetProgressWeightByScore(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'Score Weighted', 0);

        // Completed task: score=8, time=4
        $this->insertTask(701, 10, 0, 8, 4);
        // Active tasks: score=2 and score=10
        $this->insertTask(702, 10, 1, 2, 3);
        $this->insertTask(703, 10, 1, 10, 5);

        $this->insertMilestoneTask($milestoneId, 701, 1);
        $this->insertMilestoneTask($milestoneId, 702, 2);
        $this->insertMilestoneTask($milestoneId, 703, 3);

        $progress = $this->model->getProgress($milestoneId, 'score');

        $this->assertNotNull($progress);
        $this->assertSame('score', $progress['weight_by']);
        $this->assertFalse($progress['no_data']);
        // score_total = 8+2+10 = 20, score_completed = 8
        // percent = 8/20 * 100 = 40.0
        $this->assertSame(20, $progress['score_total']);
        $this->assertSame(8, $progress['score_completed']);
        $this->assertSame(40.0, $progress['percent']);
    }

    public function testGetProgressWeightByTimeEstimated(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'Time Weighted', 0);

        // Completed tasks: time=6 and time=4 (total completed=10)
        $this->insertTask(801, 10, 0, 3, 6);
        $this->insertTask(802, 10, 0, 2, 4);
        // Active task: time=10
        $this->insertTask(803, 10, 1, 5, 10);

        $this->insertMilestoneTask($milestoneId, 801, 1);
        $this->insertMilestoneTask($milestoneId, 802, 2);
        $this->insertMilestoneTask($milestoneId, 803, 3);

        $progress = $this->model->getProgress($milestoneId, 'time_estimated');

        $this->assertNotNull($progress);
        $this->assertSame('time_estimated', $progress['weight_by']);
        $this->assertFalse($progress['no_data']);
        // time_total = 6+4+10 = 20, time_completed = 6+4 = 10
        // percent = 10/20 * 100 = 50.0
        $this->assertSame(20, $progress['time_total']);
        $this->assertSame(10, $progress['time_completed']);
        $this->assertSame(50.0, $progress['percent']);
    }

    public function testGetProgressNoDataFlagWhenWeightedTotalsAreZero(): void
    {
        $this->insertPortfolio(1, 'Roadmap');
        $this->insertProject(10, 'Site');

        $milestoneId = $this->createMilestone(1, 'No Data', 0);

        // Tasks with score=0 and time_estimated=0
        $this->insertTask(901, 10, 0, 0, 0);
        $this->insertTask(902, 10, 1, 0, 0);

        $this->insertMilestoneTask($milestoneId, 901, 1);
        $this->insertMilestoneTask($milestoneId, 902, 2);

        $scoreProgress = $this->model->getProgress($milestoneId, 'score');
        $timeProgress = $this->model->getProgress($milestoneId, 'time_estimated');

        $this->assertNotNull($scoreProgress);
        $this->assertTrue($scoreProgress['no_data']);
        $this->assertSame(0.0, $scoreProgress['percent']);
        $this->assertSame('score', $scoreProgress['weight_by']);

        $this->assertNotNull($timeProgress);
        $this->assertTrue($timeProgress['no_data']);
        $this->assertSame(0.0, $timeProgress['percent']);
        $this->assertSame('time_estimated', $timeProgress['weight_by']);

        // Count mode should NOT set no_data even when score/time are zero
        $countProgress = $this->model->getProgress($milestoneId, 'count');
        $this->assertNotNull($countProgress);
        $this->assertFalse($countProgress['no_data']);
        $this->assertSame(50.0, $countProgress['percent']);
    }

    private function createMilestone(int $portfolioId, string $name, $targetDate): int
    {
        $milestoneId = $this->model->create([
            'portfolio_id' => $portfolioId,
            'name' => $name,
            'target_date' => $targetDate,
        ]);

        $this->assertIsInt($milestoneId);

        return (int) $milestoneId;
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

    private function insertTask(
        int $taskId,
        int $projectId,
        int $isActive,
        int $score = 0,
        int $timeEstimated = 0
    ): void {
        $this->pdo
            ->prepare(
                'INSERT INTO tasks (id, project_id, is_active, score, time_estimated)
                 VALUES (:id, :project_id, :is_active, :score, :time_estimated)'
            )
            ->execute([
                ':id' => $taskId,
                ':project_id' => $projectId,
                ':is_active' => $isActive,
                ':score' => $score,
                ':time_estimated' => $timeEstimated,
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
