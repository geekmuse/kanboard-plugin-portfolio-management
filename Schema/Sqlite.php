<?php

namespace Kanboard\Plugin\Portfolio\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS portfolios (
            "id" INTEGER PRIMARY KEY,
            "name" TEXT NOT NULL,
            "description" TEXT DEFAULT \'\',
            "owner_id" INTEGER NOT NULL DEFAULT 0,
            "is_active" INTEGER NOT NULL DEFAULT 1,
            "created_at" INTEGER NOT NULL DEFAULT 0,
            "updated_at" INTEGER NOT NULL DEFAULT 0,
            UNIQUE(name)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS portfolio_has_projects (
            "portfolio_id" INTEGER NOT NULL,
            "project_id" INTEGER NOT NULL,
            "position" INTEGER NOT NULL DEFAULT 0,
            "added_at" INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (portfolio_id, project_id),
            FOREIGN KEY(portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_php_portfolio ON portfolio_has_projects(portfolio_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_php_project ON portfolio_has_projects(project_id)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS milestones (
            "id" INTEGER PRIMARY KEY,
            "portfolio_id" INTEGER NOT NULL,
            "name" TEXT NOT NULL,
            "description" TEXT DEFAULT \'\',
            "target_date" INTEGER DEFAULT 0,
            "status" INTEGER NOT NULL DEFAULT 1,
            "color_id" TEXT DEFAULT \'blue\',
            "owner_id" INTEGER NOT NULL DEFAULT 0,
            "created_at" INTEGER NOT NULL DEFAULT 0,
            "updated_at" INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_milestones_portfolio ON milestones(portfolio_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_milestones_status ON milestones(status)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS milestone_has_tasks (
            "milestone_id" INTEGER NOT NULL,
            "task_id" INTEGER NOT NULL,
            "position" INTEGER NOT NULL DEFAULT 0,
            "is_critical" INTEGER NOT NULL DEFAULT 0,
            "added_at" INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (milestone_id, task_id),
            FOREIGN KEY(milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mht_milestone ON milestone_has_tasks(milestone_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mht_task ON milestone_has_tasks(task_id)');
}
