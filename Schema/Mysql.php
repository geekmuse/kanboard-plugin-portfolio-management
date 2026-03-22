<?php

namespace Kanboard\Plugin\Portfolio\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS portfolios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            owner_id INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0,
            UNIQUE KEY uk_portfolio_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS portfolio_has_projects (
            portfolio_id INT NOT NULL,
            project_id INT NOT NULL,
            position INT NOT NULL DEFAULT 0,
            added_at INT NOT NULL DEFAULT 0,
            PRIMARY KEY (portfolio_id, project_id),
            CONSTRAINT fk_php_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
            CONSTRAINT fk_php_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            KEY idx_php_portfolio (portfolio_id),
            KEY idx_php_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS milestones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            portfolio_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            target_date INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            color_id VARCHAR(32) NOT NULL DEFAULT \'blue\',
            owner_id INT NOT NULL DEFAULT 0,
            created_at INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_milestones_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
            KEY idx_milestones_portfolio (portfolio_id),
            KEY idx_milestones_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS milestone_has_tasks (
            milestone_id INT NOT NULL,
            task_id INT NOT NULL,
            position INT NOT NULL DEFAULT 0,
            is_critical TINYINT(1) NOT NULL DEFAULT 0,
            added_at INT NOT NULL DEFAULT 0,
            PRIMARY KEY (milestone_id, task_id),
            CONSTRAINT fk_mht_milestone FOREIGN KEY (milestone_id) REFERENCES milestones(id) ON DELETE CASCADE,
            CONSTRAINT fk_mht_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            KEY idx_mht_milestone (milestone_id),
            KEY idx_mht_task (task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}
