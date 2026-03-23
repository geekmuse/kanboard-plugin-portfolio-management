<?php

$task = isset($notification['task']) && is_array($notification['task'])
    ? $notification['task']
    : [];

$resolvedTask = isset($notification['resolved_task']) && is_array($notification['resolved_task'])
    ? $notification['resolved_task']
    : [];

$taskId = (int) ($task['id'] ?? 0);
$resolvedTaskId = (int) ($resolvedTask['id'] ?? 0);
$taskTitle = $this->text->e((string) ($task['title'] ?? ''));
$resolvedTaskTitle = $this->text->e((string) ($resolvedTask['title'] ?? ''));
$resolvedProjectName = $this->text->e((string) ($resolvedTask['project_name'] ?? ''));
?>
<?= t(
    'Task #%d "%s" is no longer blocked because task #%d "%s" in project "%s" was completed.',
    $taskId,
    $taskTitle,
    $resolvedTaskId,
    $resolvedTaskTitle,
    $resolvedProjectName
) ?>
