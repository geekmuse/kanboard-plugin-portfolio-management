<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Test\Schema;

use PDO;
use PHPUnit\Framework\TestCase;

class Version1MigrationTest extends TestCase
{
    public function testMigrationFilesDeclareVersionOneEntryPointAndRequiredObjects(): void
    {
        $requiredSnippets = [
            'const VERSION = 1;',
            'function version_1(PDO $pdo): void',
            'CREATE TABLE IF NOT EXISTS portfolios',
            'CREATE TABLE IF NOT EXISTS portfolio_has_projects',
            'CREATE TABLE IF NOT EXISTS milestones',
            'CREATE TABLE IF NOT EXISTS milestone_has_tasks',
            'idx_php_portfolio',
            'idx_php_project',
            'idx_milestones_portfolio',
            'idx_milestones_status',
            'idx_mht_milestone',
            'idx_mht_task',
        ];

        foreach ($this->getMigrationFiles() as $path) {
            $content = file_get_contents($path);
            $this->assertNotFalse($content, sprintf('Unable to read migration file: %s', $path));

            foreach ($requiredSnippets as $snippet) {
                $this->assertStringContainsString(
                    $snippet,
                    $content,
                    sprintf('Missing required snippet "%s" in %s', $snippet, $path)
                );
            }
        }
    }

    public function testSqliteMigrationCreatesTablesIndexesAndCascadeConstraints(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY)');

        require_once __DIR__ . '/../../Schema/Sqlite.php';

        $migration = '\\Kanboard\\Plugin\\Portfolio\\Schema\\version_1';
        $migration($pdo);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('portfolios', $tables);
        $this->assertContains('portfolio_has_projects', $tables);
        $this->assertContains('milestones', $tables);
        $this->assertContains('milestone_has_tasks', $tables);

        $this->assertContains('idx_php_portfolio', $indexes);
        $this->assertContains('idx_php_project', $indexes);
        $this->assertContains('idx_milestones_portfolio', $indexes);
        $this->assertContains('idx_milestones_status', $indexes);
        $this->assertContains('idx_mht_milestone', $indexes);
        $this->assertContains('idx_mht_task', $indexes);

        $portfolioProjectFks = $pdo->query('PRAGMA foreign_key_list(portfolio_has_projects)')->fetchAll(PDO::FETCH_ASSOC);
        $milestoneFks = $pdo->query('PRAGMA foreign_key_list(milestones)')->fetchAll(PDO::FETCH_ASSOC);
        $milestoneTaskFks = $pdo->query('PRAGMA foreign_key_list(milestone_has_tasks)')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertContains('portfolios', array_column($portfolioProjectFks, 'table'));
        $this->assertContains('projects', array_column($portfolioProjectFks, 'table'));
        $this->assertContains('portfolios', array_column($milestoneFks, 'table'));
        $this->assertContains('milestones', array_column($milestoneTaskFks, 'table'));
        $this->assertContains('tasks', array_column($milestoneTaskFks, 'table'));

        $pdo->exec('INSERT INTO projects (id) VALUES (101)');
        $pdo->exec('INSERT INTO tasks (id) VALUES (201)');
        $pdo->exec("INSERT INTO portfolios (name, owner_id, is_active, created_at, updated_at) VALUES ('Portfolio A', 1, 1, 1000, 1000)");

        $portfolioId = (int) $pdo->lastInsertId();

        $pdo->exec(sprintf(
            "INSERT INTO portfolio_has_projects (portfolio_id, project_id, position, added_at) VALUES (%d, 101, 1, 1000)",
            $portfolioId
        ));

        $pdo->exec(sprintf(
            "INSERT INTO milestones (portfolio_id, name, status, owner_id, created_at, updated_at) VALUES (%d, 'Milestone A', 1, 1, 1000, 1000)",
            $portfolioId
        ));

        $milestoneId = (int) $pdo->lastInsertId();

        $pdo->exec(sprintf(
            'INSERT INTO milestone_has_tasks (milestone_id, task_id, position, is_critical, added_at) VALUES (%d, 201, 1, 0, 1000)',
            $milestoneId
        ));

        $pdo->exec(sprintf('DELETE FROM portfolios WHERE id = %d', $portfolioId));

        $portfolioProjectCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_has_projects')->fetchColumn();
        $milestoneCount = (int) $pdo->query('SELECT COUNT(*) FROM milestones')->fetchColumn();
        $milestoneTaskCount = (int) $pdo->query('SELECT COUNT(*) FROM milestone_has_tasks')->fetchColumn();

        $this->assertSame(0, $portfolioProjectCount);
        $this->assertSame(0, $milestoneCount);
        $this->assertSame(0, $milestoneTaskCount);
    }

    /**
     * @return string[]
     */
    private function getMigrationFiles(): array
    {
        return [
            __DIR__ . '/../../Schema/Sqlite.php',
            __DIR__ . '/../../Schema/Mysql.php',
            __DIR__ . '/../../Schema/Postgres.php',
        ];
    }
}
