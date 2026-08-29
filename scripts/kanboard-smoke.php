#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kanboard\Core\Plugin\SchemaHandler;

$kanboardRoot = dirname(__DIR__, 3);
$bootstrap = $kanboardRoot . '/app/common.php';

if (! is_file($bootstrap)) {
    fwrite(STDERR, "Unable to locate the Kanboard bootstrap at {$bootstrap}\n");
    exit(1);
}

require $bootstrap;

/**
 * @param mixed $condition
 */
function assertSmoke($condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$plugins = $container['pluginLoader']->getPlugins();
assertSmoke(isset($plugins['Portfolio']), 'Portfolio was not loaded by Kanboard');

$plugin = $plugins['Portfolio'];
assertSmoke($plugin->getPluginName() === 'Portfolio', 'Unexpected plugin name');
assertSmoke($plugin->getCompatibleVersion() === '>=1.2.20', 'Unexpected Kanboard compatibility declaration');

$requiredServices = [
    'portfolioModel',
    'portfolioProjectModel',
    'milestoneModel',
    'milestoneTaskModel',
    'dependencyModel',
    'portfolioTaskModel',
    'portfolioHelper',
    'portfolioValidator',
];

foreach ($requiredServices as $service) {
    assertSmoke(isset($container[$service]), sprintf('Missing container service: %s', $service));
    assertSmoke(is_object($container[$service]), sprintf('Container service did not resolve: %s', $service));
}

$schemaHandler = new SchemaHandler($container);
assertSmoke($schemaHandler->getSchemaVersion('Portfolio') === 1, 'Portfolio schema did not migrate to version 1');

foreach (['portfolios', 'portfolio_has_projects', 'milestones', 'milestone_has_tasks'] as $table) {
    $count = $container['db']->table($table)->count();
    assertSmoke(is_int($count), sprintf('Unable to query migrated table: %s', $table));
}

$uniqueSuffix = bin2hex(random_bytes(6));
$portfolioId = $container['portfolioModel']->create([
    'name' => 'Integration Smoke ' . $uniqueSuffix,
    'description' => 'Created by scripts/kanboard-smoke.php',
]);
assertSmoke(is_int($portfolioId) && $portfolioId > 0, 'PortfolioModel::create() did not return a persisted ID');

$milestoneId = $container['milestoneModel']->create([
    'portfolio_id' => $portfolioId,
    'name' => 'Integration Milestone ' . $uniqueSuffix,
]);
assertSmoke(is_int($milestoneId) && $milestoneId > 0, 'MilestoneModel::create() did not return a persisted ID');
assertSmoke($container['milestoneModel']->getById($milestoneId) !== null, 'Persisted milestone could not be read');
assertSmoke($container['portfolioModel']->remove($portfolioId), 'Persisted portfolio could not be removed');
assertSmoke($container['milestoneModel']->getById($milestoneId) === null, 'Portfolio deletion did not cascade to milestones');

assertSmoke(
    $container['hook']->exists('template:layout:css'),
    'Portfolio did not register its global CSS hook'
);

foreach (
    [
        \Kanboard\Plugin\Portfolio\Action\NotifyDependencyResolved::class,
        \Kanboard\Plugin\Portfolio\Action\CommentDependencyResolved::class,
    ] as $actionClass
) {
    assertSmoke(
        $container['actionManager']->getAction($actionClass) instanceof $actionClass,
        sprintf('Automatic action was not registered: %s', $actionClass)
    );
}

assertSmoke(
    array_key_exists(
        \Kanboard\Plugin\Portfolio\Notification\DependencyResolvedType::EVENT_NAME,
        $container['eventManager']->getAll()
    ),
    'Dependency-resolved event type was not registered'
);

$dispatcher = $container['dispatcher'];
foreach (['task.close', 'task.open', 'task_internal_link.create_update', 'task_internal_link.delete'] as $eventName) {
    assertSmoke(
        count($dispatcher->getListeners($eventName)) > 0,
        sprintf('No Portfolio listener registered for %s', $eventName)
    );
}

// Symfony changed dispatch() from (name, event) to (event, name). Exercise a
// real Portfolio listener with the signature used by the installed Kanboard.
$taskEvent = new \Kanboard\Event\TaskEvent(['task_id' => 0]);
$dispatchParameters = (new ReflectionMethod($dispatcher, 'dispatch'))->getParameters();
if ($dispatchParameters[0]->getName() === 'eventName') {
    $dispatcher->dispatch('task.close', $taskEvent);
} else {
    $dispatcher->dispatch($taskEvent, 'task.close');
}

fwrite(
    STDOUT,
    sprintf(
        "Portfolio integration smoke test passed (Kanboard %s, PHP %s, %s).\n",
        APP_VERSION,
        PHP_VERSION,
        DB_DRIVER
    )
);
