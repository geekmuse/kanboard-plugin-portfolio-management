<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Action;

use Kanboard\Action\Base;
use Kanboard\Plugin\Portfolio\Notification\DependencyResolvedType;

class CommentDependencyResolved extends Base
{
    public function getDescription(): string
    {
        return t('Add a comment when a cross-project dependency is resolved');
    }

    /**
     * @return array<int, string>
     */
    public function getCompatibleEvents(): array
    {
        return [DependencyResolvedType::EVENT_NAME];
    }

    public function getEventName(): string
    {
        return DependencyResolvedType::EVENT_NAME;
    }

    /**
     * @param array<string, mixed> $event
     */
    public function hasRequiredCondition(array $event): bool
    {
        return $this->getUnblockedTasks($event) !== [];
    }

    /**
     * @param array<string, mixed> $event
     */
    public function doAction(array $event): bool
    {
        if (! is_object($this->commentModel) || ! method_exists($this->commentModel, 'create')) {
            return false;
        }

        $created = false;
        $unblockedTasks = $this->getUnblockedTasks($event);

        foreach ($unblockedTasks as $unblockedTask) {
            $taskId = (int) ($unblockedTask['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $comment = t(
                '✅ Dependency resolved: Task #%d "%s" in project "%s" has been completed. This task is no longer blocked.',
                (int) ($event['resolved_task_id'] ?? 0),
                (string) ($event['resolved_task_title'] ?? ''),
                (string) ($event['resolved_project_name'] ?? '')
            );

            $result = $this->commentModel->create([
                'task_id' => $taskId,
                'user_id' => 0,
                'comment' => $comment,
            ]);

            if ($result !== false) {
                $created = true;
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<int, array<string, mixed>>
     */
    private function getUnblockedTasks(array $event): array
    {
        if (! isset($event['unblocked_tasks']) || ! is_array($event['unblocked_tasks'])) {
            return [];
        }

        $tasks = [];

        foreach ($event['unblocked_tasks'] as $task) {
            if (! is_array($task)) {
                continue;
            }

            $tasks[] = $task;
        }

        return $tasks;
    }
}
