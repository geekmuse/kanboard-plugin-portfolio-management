<?php

declare(strict_types=1);

namespace {
    if (! function_exists('t')) {
        function t(string $message, mixed ...$args): string
        {
            if ($args === []) {
                return $message;
            }

            return vsprintf($message, $args);
        }
    }
}

namespace Kanboard\Core {
    if (! class_exists(__NAMESPACE__ . '\\Base')) {
        class Base
        {
            /** @var array<string, mixed> */
            protected array $container;

            /**
             * @param array<string, mixed> $container
             */
            public function __construct(array $container = [])
            {
                $this->container = $container;
            }

            /** @return mixed */
            public function __get(string $name)
            {
                if (! array_key_exists($name, $this->container)) {
                    return null;
                }

                $service = $this->container[$name];

                if ($service instanceof \Closure) {
                    $service = $service($this->container);
                    $this->container[$name] = $service;
                }

                return $service;
            }

            protected function checkCSRFParam(): void
            {
                $expected = (string) ($this->container['csrf_token'] ?? 'csrf-token');
                $actual = (string) $this->request->getValue('csrf_token', '');

                if ($actual !== $expected) {
                    throw new \RuntimeException('Invalid CSRF token');
                }
            }
        }
    }
}

namespace Kanboard\Action {
    if (! class_exists(__NAMESPACE__ . '\\Base')) {
        abstract class Base extends \Kanboard\Core\Base
        {
            /**
             * @param array<string, mixed> $event
             */
            public function hasRequiredCondition(array $event): bool
            {
                return true;
            }

            /**
             * @param array<string, mixed> $event
             */
            abstract public function doAction(array $event): bool;

            abstract public function getEventName(): string;

            /**
             * @return array<int, string>
             */
            abstract public function getCompatibleEvents(): array;

            abstract public function getDescription(): string;
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Action {
    use Kanboard\Plugin\Portfolio\Action\CommentDependencyResolved;
    use Kanboard\Plugin\Portfolio\Action\NotifyDependencyResolved;
    use Kanboard\Plugin\Portfolio\Notification\DependencyResolvedType;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Notification/DependencyResolvedType.php';
    require_once __DIR__ . '/../../Action/NotifyDependencyResolved.php';
    require_once __DIR__ . '/../../Action/CommentDependencyResolved.php';

    final class FakeUserNotificationModel
    {
        /**
         * @var array<int, array{user_id: int, type: string, payload: array<string, mixed>}>
         */
        public array $calls = [];

        /**
         * @param array<string, mixed> $payload
         */
        public function sendUserNotification(int $userId, string $type, array $payload): void
        {
            $this->calls[] = [
                'user_id' => $userId,
                'type' => $type,
                'payload' => $payload,
            ];
        }
    }

    final class FakeTaskFinderModel
    {
        /**
         * @param array<int, array<string, mixed>> $tasksById
         */
        public function __construct(private array $tasksById)
        {
        }

        /**
         * @return array<string, mixed>|null
         */
        public function getDetails(int $taskId): ?array
        {
            return $this->tasksById[$taskId] ?? null;
        }
    }

    final class FakeCommentModel
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $created = [];

        /**
         * @param array<string, mixed> $values
         */
        public function create(array $values): bool
        {
            $this->created[] = $values;

            return true;
        }
    }

    final class FakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class NotificationTemplateContext
    {
        public FakeTextHelper $text;

        public function __construct()
        {
            $this->text = new FakeTextHelper();
        }

        /**
         * @param array<string, mixed> $vars
         */
        public function render(string $templateFile, array $vars): string
        {
            extract($vars, EXTR_SKIP);

            ob_start();
            include $templateFile;

            return (string) ob_get_clean();
        }
    }

    final class DependencyResolvedActionsTest extends TestCase
    {
        public function testNotifyActionSendsNotificationToAssigneeOfUnblockedTasks(): void
        {
            $notificationModel = new FakeUserNotificationModel();
            $taskFinderModel = new FakeTaskFinderModel([
                42 => [
                    'id' => 42,
                    'title' => 'Publish product page',
                    'project_name' => 'Site Project',
                ],
            ]);

            $action = new NotifyDependencyResolved([
                'userNotificationModel' => $notificationModel,
                'taskFinderModel' => $taskFinderModel,
            ]);

            $event = [
                'resolved_task_id' => 15,
                'resolved_task_title' => 'Finalize branding',
                'resolved_project_name' => 'Product Alpha',
                'unblocked_tasks' => [
                    [
                        'task_id' => 42,
                        'task_title' => 'Publish product page',
                        'project_id' => 8,
                        'project_name' => 'Site Project',
                        'owner_id' => 5,
                    ],
                    [
                        'task_id' => 99,
                        'task_title' => 'No assignee task',
                        'owner_id' => 0,
                    ],
                ],
            ];

            $this->assertTrue($action->hasRequiredCondition($event));
            $this->assertTrue($action->doAction($event));
            $this->assertCount(1, $notificationModel->calls);

            $call = $notificationModel->calls[0];
            $this->assertSame(5, $call['user_id']);
            $this->assertSame('dependency_resolved', $call['type']);
            $this->assertSame(42, (int) $call['payload']['task']['id']);
            $this->assertSame('Finalize branding', (string) $call['payload']['resolved_task']['title']);
            $this->assertSame('Product Alpha', (string) $call['payload']['resolved_task']['project_name']);
        }

        public function testNotifyActionReturnsFalseWithoutEligibleUnblockedTasks(): void
        {
            $notificationModel = new FakeUserNotificationModel();
            $action = new NotifyDependencyResolved([
                'userNotificationModel' => $notificationModel,
            ]);

            $event = [
                'resolved_task_id' => 15,
                'resolved_task_title' => 'Finalize branding',
                'resolved_project_name' => 'Product Alpha',
                'unblocked_tasks' => [
                    [
                        'task_id' => 42,
                        'owner_id' => 0,
                    ],
                ],
            ];

            $this->assertTrue($action->hasRequiredCondition($event));
            $this->assertFalse($action->doAction($event));
            $this->assertCount(0, $notificationModel->calls);
        }

        public function testCommentActionAddsSystemCommentForEachUnblockedTask(): void
        {
            $commentModel = new FakeCommentModel();
            $action = new CommentDependencyResolved([
                'commentModel' => $commentModel,
            ]);

            $event = [
                'resolved_task_id' => 15,
                'resolved_task_title' => 'Finalize branding',
                'resolved_project_name' => 'Product Alpha',
                'unblocked_tasks' => [
                    ['task_id' => 42],
                    ['task_id' => 43],
                    ['task_id' => 0],
                ],
            ];

            $this->assertTrue($action->hasRequiredCondition($event));
            $this->assertTrue($action->doAction($event));
            $this->assertCount(2, $commentModel->created);

            $firstComment = $commentModel->created[0];
            $this->assertSame(42, (int) $firstComment['task_id']);
            $this->assertSame(0, (int) $firstComment['user_id']);
            $this->assertStringContainsString('Task #15', (string) $firstComment['comment']);
            $this->assertStringContainsString('Finalize branding', (string) $firstComment['comment']);
            $this->assertStringContainsString('Product Alpha', (string) $firstComment['comment']);
        }

        public function testNotificationTypeAndTemplateRenderEscapedNotificationPayload(): void
        {
            $payload = DependencyResolvedType::buildNotificationPayload(
                [
                    'id' => 42,
                    'title' => 'Publish <script>alert(1)</script>',
                ],
                [
                    'id' => 15,
                    'title' => 'Finalize branding',
                    'project_name' => 'Project <Alpha>',
                ]
            );

            $this->assertSame('dependency_resolved', DependencyResolvedType::getType());
            $this->assertSame('portfolio.dependency.resolved', DependencyResolvedType::EVENT_NAME);
            $this->assertSame('Portfolio:notification/dependency_resolved', DependencyResolvedType::getTemplate());
            $this->assertSame('Cross-project dependency resolved', DependencyResolvedType::getLabel());

            $context = new NotificationTemplateContext();
            $output = $context->render(
                __DIR__ . '/../../Template/notification/dependency_resolved.php',
                ['notification' => $payload]
            );

            $this->assertStringContainsString('Task #42', $output);
            $this->assertStringContainsString('task #15', $output);
            $this->assertStringContainsString('Publish &lt;script&gt;alert(1)&lt;/script&gt;', $output);
            $this->assertStringContainsString('Project &lt;Alpha&gt;', $output);
        }
    }
}
