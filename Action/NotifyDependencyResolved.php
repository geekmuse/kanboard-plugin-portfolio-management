<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Action;

use Kanboard\Action\Base;
use Kanboard\Plugin\Portfolio\Notification\DependencyResolvedType;

class NotifyDependencyResolved extends Base
{
    public function getDescription(): string
    {
        return t('Send a notification when a cross-project dependency is resolved');
    }

    /**
     * No configurable parameters for this action.
     *
     * @return array<string, string>
     */
    public function getActionRequiredParameters(): array
    {
        return [];
    }

    /**
     * Keys that must be present in the event payload.
     *
     * @return array<string, string>
     */
    public function getEventRequiredParameters(): array
    {
        return [
            'unblocked_tasks'      => 'unblocked_tasks',
            'resolved_task_id'     => 'resolved_task_id',
            'resolved_task_title'  => 'resolved_task_title',
            'resolved_project_name' => 'resolved_project_name',
        ];
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
        if (! is_object($this->userNotificationModel) || ! method_exists($this->userNotificationModel, 'sendUserNotification')) {
            return false;
        }

        $sent = false;
        $unblockedTasks = $this->getUnblockedTasks($event);

        foreach ($unblockedTasks as $unblockedTask) {
            $ownerId = (int) ($unblockedTask['owner_id'] ?? 0);
            $taskId = (int) ($unblockedTask['task_id'] ?? 0);

            if ($ownerId <= 0 || $taskId <= 0) {
                continue;
            }

            $task = $this->getTaskDetails($taskId, $unblockedTask);
            $payload = DependencyResolvedType::buildNotificationPayload(
                $task,
                [
                    'id' => (int) ($event['resolved_task_id'] ?? 0),
                    'title' => (string) ($event['resolved_task_title'] ?? ''),
                    'project_name' => (string) ($event['resolved_project_name'] ?? ''),
                ]
            );

            $this->userNotificationModel->sendUserNotification(
                $ownerId,
                DependencyResolvedType::TYPE,
                $payload
            );

            $sent = true;
        }

        return $sent;
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

    /**
     * @param array<string, mixed> $fallback
     *
     * @return array<string, mixed>
     */
    private function getTaskDetails(int $taskId, array $fallback): array
    {
        if (is_object($this->taskFinderModel) && method_exists($this->taskFinderModel, 'getDetails')) {
            $task = $this->taskFinderModel->getDetails($taskId);
            if (is_array($task)) {
                return $task;
            }
        }

        return [
            'id' => $taskId,
            'title' => (string) ($fallback['task_title'] ?? ''),
            'project_id' => (int) ($fallback['project_id'] ?? 0),
            'project_name' => (string) ($fallback['project_name'] ?? ''),
            'owner_id' => (int) ($fallback['owner_id'] ?? 0),
        ];
    }
}
