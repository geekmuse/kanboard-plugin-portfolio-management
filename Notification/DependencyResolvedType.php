<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Notification;

final class DependencyResolvedType
{
    public const TYPE = 'dependency_resolved';

    public const EVENT_NAME = 'portfolio.dependency.resolved';

    public const TEMPLATE = 'Portfolio:notification/dependency_resolved';

    public static function getType(): string
    {
        return self::TYPE;
    }

    public static function getLabel(): string
    {
        return t('Cross-project dependency resolved');
    }

    public static function getTemplate(): string
    {
        return self::TEMPLATE;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<string, mixed>      $resolvedTask
     *
     * @return array<string, mixed>
     */
    public static function buildNotificationPayload(?array $task, array $resolvedTask): array
    {
        return [
            'task' => is_array($task) ? $task : [],
            'resolved_task' => [
                'id' => (int) ($resolvedTask['id'] ?? 0),
                'title' => (string) ($resolvedTask['title'] ?? ''),
                'project_name' => (string) ($resolvedTask['project_name'] ?? ''),
            ],
        ];
    }
}
