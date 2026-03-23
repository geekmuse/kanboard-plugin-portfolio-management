<?php

declare(strict_types=1);

namespace {
    if (! function_exists('t')) {
        function t(string $message): string
        {
            return $message;
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

namespace Kanboard\Controller {
    if (! class_exists(__NAMESPACE__ . '\\BaseController')) {
        class BaseController extends \Kanboard\Core\Base
        {
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    use Kanboard\Plugin\Portfolio\Controller\MilestoneController;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../Controller/MilestoneController.php';
    require_once __DIR__ . '/FakeLayoutHelper.php';

    final class MilestoneControllerFakeRequest
    {
        /**
         * @param array<string, mixed> $values
         * @param array<string, mixed> $integerParams
         */
        public function __construct(private array $values = [], private array $integerParams = [])
        {
        }

        public function getIntegerParam(string $name, int $default = 0): int
        {
            if (array_key_exists($name, $this->integerParams)) {
                return (int) $this->integerParams[$name];
            }

            if (array_key_exists($name, $this->values)) {
                return (int) $this->values[$name];
            }

            return $default;
        }

        /** @return mixed */
        public function getValue(string $name, $default = null)
        {
            return $this->values[$name] ?? $default;
        }

        /**
         * @return array<string, mixed>
         */
        public function getValues(): array
        {
            return $this->values;
        }
    }

    final class MilestoneControllerFakeResponse
    {
        public ?string $htmlContent = null;

        public ?string $redirectUrl = null;

        public function html(string $content): string
        {
            $this->htmlContent = $content;

            return $content;
        }

        public function redirect(string $url): string
        {
            $this->redirectUrl = $url;

            return $url;
        }
    }

    final class MilestoneControllerFakeTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    final class MilestoneControllerFakeFormHelper
    {
        public function __construct(private string $token)
        {
        }

        public function csrf(): string
        {
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->token, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    final class MilestoneControllerFakeUrlHelper
    {
        /**
         * @param array<string, mixed> $params
         */
        public function href(string $controller, string $action, array $params = [], string $plugin = ''): string
        {
            $query = array_merge([
                'controller' => $controller,
                'action' => $action,
                'plugin' => $plugin,
            ], $params);

            return '/?' . http_build_query($query);
        }
    }

    final class MilestoneControllerFakeTemplate
    {
        public string $lastTemplate = '';

        /**
         * @var array<string, mixed>
         */
        public array $lastParams = [];

        public MilestoneControllerFakeTextHelper $text;

        public MilestoneControllerFakeUrlHelper $url;

        public MilestoneControllerFakeFormHelper $form;

        public function __construct(string $csrfToken)
        {
            $this->text = new MilestoneControllerFakeTextHelper();
            $this->url = new MilestoneControllerFakeUrlHelper();
            $this->form = new MilestoneControllerFakeFormHelper($csrfToken);
        }

        /**
         * @param array<string, mixed> $params
         */
        public function render(string $template, array $params = []): string
        {
            $this->lastTemplate = $template;
            $this->lastParams = $params;

            $resolved = str_replace('Portfolio:', '', $template);
            $templateFile = __DIR__ . '/../../Template/' . $resolved . '.php';
            if (! file_exists($templateFile)) {
                return '';
            }

            extract($params, EXTR_SKIP);

            ob_start();
            include $templateFile;

            return (string) ob_get_clean();
        }
    }

    final class MilestoneControllerFakeFlash
    {
        /** @var array<int, string> */
        public array $successMessages = [];

        /** @var array<int, string> */
        public array $failureMessages = [];

        public function success(string $message): void
        {
            $this->successMessages[] = $message;
        }

        public function failure(string $message): void
        {
            $this->failureMessages[] = $message;
        }
    }

    final class MilestoneControllerFakePortfolioModel
    {
        /**
         * @param array<int, array<string, mixed>> $portfolios
         */
        public function __construct(private array $portfolios = [])
        {
        }

        /** @return array<string, mixed>|null */
        public function getById(int $portfolioId): ?array
        {
            foreach ($this->portfolios as $portfolio) {
                if ((int) ($portfolio['id'] ?? 0) === $portfolioId) {
                    return $portfolio;
                }
            }

            return null;
        }
    }

    final class MilestoneControllerFakeMilestoneModel
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        private array $milestones = [];

        /** @var array<int, int> */
        public array $getByPortfolioIdCalls = [];

        /** @var array<int, int> */
        public array $getProgressCalls = [];

        /** @var array<int, array<string, mixed>> */
        public array $createCalls = [];

        /** @var array<int, array{id: int, values: array<string, mixed>}> */
        public array $updateCalls = [];

        /** @var array<int, int> */
        public array $removeCalls = [];

        public bool $createShouldFail = false;

        public bool $updateShouldFail = false;

        public bool $removeShouldFail = false;

        /**
         * @param array<int, array<string, mixed>> $seedMilestones
         * @param array<int, array<string, mixed>> $progressByMilestone
         */
        public function __construct(array $seedMilestones = [], private array $progressByMilestone = [])
        {
            foreach ($seedMilestones as $milestone) {
                $milestoneId = (int) ($milestone['id'] ?? 0);
                if ($milestoneId > 0) {
                    $this->milestones[$milestoneId] = $milestone;
                }
            }
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getByPortfolioId(int $portfolioId): array
        {
            $this->getByPortfolioIdCalls[] = $portfolioId;

            $results = [];
            foreach ($this->milestones as $milestone) {
                if ((int) ($milestone['portfolio_id'] ?? 0) === $portfolioId) {
                    $results[] = $milestone;
                }
            }

            return $results;
        }

        /** @return array<string, mixed>|null */
        public function getById(int $milestoneId): ?array
        {
            return $this->milestones[$milestoneId] ?? null;
        }

        /**
         * @param array<string, mixed> $values
         *
         * @return int|false
         */
        public function create(array $values)
        {
            $this->createCalls[] = $values;

            if ($this->createShouldFail) {
                return false;
            }

            $nextId = $this->milestones === [] ? 1 : (max(array_keys($this->milestones)) + 1);
            $this->milestones[$nextId] = [
                'id' => $nextId,
                'portfolio_id' => (int) ($values['portfolio_id'] ?? 0),
                'name' => (string) ($values['name'] ?? ''),
                'description' => (string) ($values['description'] ?? ''),
                'target_date' => $this->normalizeTargetDate($values['target_date'] ?? ''),
                'color_id' => (string) ($values['color_id'] ?? 'blue'),
                'owner_id' => (int) ($values['owner_id'] ?? 0),
                'status' => (int) ($values['status'] ?? 1),
            ];

            return $nextId;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function update(int $milestoneId, array $values): bool
        {
            $this->updateCalls[] = [
                'id' => $milestoneId,
                'values' => $values,
            ];

            if ($this->updateShouldFail || ! array_key_exists($milestoneId, $this->milestones)) {
                return false;
            }

            if (array_key_exists('target_date', $values)) {
                $values['target_date'] = $this->normalizeTargetDate($values['target_date']);
            }

            $this->milestones[$milestoneId] = array_merge($this->milestones[$milestoneId], $values);

            return true;
        }

        public function remove(int $milestoneId): bool
        {
            $this->removeCalls[] = $milestoneId;

            if ($this->removeShouldFail || ! array_key_exists($milestoneId, $this->milestones)) {
                return false;
            }

            unset($this->milestones[$milestoneId]);

            return true;
        }

        /** @return array<string, mixed>|null */
        public function getProgress(int $milestoneId): ?array
        {
            $this->getProgressCalls[] = $milestoneId;

            if (array_key_exists($milestoneId, $this->progressByMilestone)) {
                return $this->progressByMilestone[$milestoneId];
            }

            return [
                'milestone_id' => $milestoneId,
                'total' => 0,
                'completed' => 0,
                'percent' => 0,
                'blocked_count' => 0,
                'is_at_risk' => false,
                'is_overdue' => false,
                'target_date' => 0,
            ];
        }

        private function normalizeTargetDate(mixed $targetDate): int
        {
            if (is_int($targetDate)) {
                return $targetDate;
            }

            $value = trim((string) $targetDate);
            if ($value === '') {
                return 0;
            }

            if (ctype_digit($value)) {
                return (int) $value;
            }

            $timestamp = strtotime($value);

            return $timestamp === false ? 0 : $timestamp;
        }
    }

    final class MilestoneControllerFakeMilestoneTaskModel
    {
        /** @var array<int, array<int, array<string, mixed>>> */
        private array $tasksByMilestone;

        /** @var array<int, array{milestone_id: int, task_id: int, is_critical: int, position: int}> */
        public array $addCalls = [];

        /** @var array<int, array{milestone_id: int, task_id: int}> */
        public array $removeCalls = [];

        /** @var array<int, int> */
        public array $getTasksCalls = [];

        public bool $addShouldFail = false;

        public bool $removeShouldFail = false;

        /**
         * @param array<int, array<int, array<string, mixed>>> $tasksByMilestone
         */
        public function __construct(array $tasksByMilestone = [])
        {
            $this->tasksByMilestone = $tasksByMilestone;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getTasks(int $milestoneId): array
        {
            $this->getTasksCalls[] = $milestoneId;

            return $this->tasksByMilestone[$milestoneId] ?? [];
        }

        public function add(int $milestoneId, int $taskId, int $isCritical = 0, int $position = 0): bool
        {
            $this->addCalls[] = [
                'milestone_id' => $milestoneId,
                'task_id' => $taskId,
                'is_critical' => $isCritical,
                'position' => $position,
            ];

            return ! $this->addShouldFail;
        }

        public function remove(int $milestoneId, int $taskId): bool
        {
            $this->removeCalls[] = [
                'milestone_id' => $milestoneId,
                'task_id' => $taskId,
            ];

            return ! $this->removeShouldFail;
        }
    }

    final class MilestoneControllerTest extends TestCase
    {
        private const CSRF_TOKEN = 'csrf-token';

        public function testMilestoneIndexRendersEscapedMilestoneRows(): void
        {
            $request = new MilestoneControllerFakeRequest([], ['portfolio_id' => 9]);
            $portfolioModel = new MilestoneControllerFakePortfolioModel([
                ['id' => 9, 'name' => 'Q2 Release'],
            ]);
            $milestoneModel = new MilestoneControllerFakeMilestoneModel(
                [
                    ['id' => 101, 'portfolio_id' => 9, 'name' => '<script>alert(1)</script>', 'target_date' => 1717200000, 'status' => 1],
                ],
                [
                    101 => ['percent' => 50, 'completed' => 1, 'total' => 2],
                ]
            );

            $services = $this->buildServices($portfolioModel, $milestoneModel, new MilestoneControllerFakeMilestoneTaskModel(), $request);
            $controller = new MilestoneController($services);

            $html = $controller->index();

            $this->assertSame('Portfolio:milestone/index', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Portfolio Milestones', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertSame([9], $milestoneModel->getByPortfolioIdCalls);
            $this->assertSame([101], $milestoneModel->getProgressCalls);
        }

        public function testMilestoneShowRendersTaskListAndProgressWithEscapedContent(): void
        {
            $request = new MilestoneControllerFakeRequest([], ['milestone_id' => 42]);
            $portfolioModel = new MilestoneControllerFakePortfolioModel([
                ['id' => 9, 'name' => 'Q2 Release'],
            ]);
            $milestoneModel = new MilestoneControllerFakeMilestoneModel(
                [
                    [
                        'id' => 42,
                        'portfolio_id' => 9,
                        'name' => 'Release <script>alert(2)</script>',
                        'description' => '<img src=x onerror=alert(1)>',
                        'target_date' => 1717200000,
                        'status' => 1,
                    ],
                ],
                [
                    42 => [
                        'total' => 3,
                        'completed' => 1,
                        'percent' => 33.33,
                        'blocked_count' => 1,
                        'is_at_risk' => true,
                        'is_overdue' => false,
                        'target_date' => 1717200000,
                    ],
                ]
            );

            $milestoneTaskModel = new MilestoneControllerFakeMilestoneTaskModel([
                42 => [
                    [
                        'id' => 1000,
                        'title' => '<script>alert(3)</script>',
                        'project_name' => 'Website',
                        'assignee_name' => 'Alice',
                        'assignee_username' => 'alice',
                        'is_active' => 1,
                        'is_critical' => 1,
                        'position' => 2,
                    ],
                ],
            ]);

            $services = $this->buildServices($portfolioModel, $milestoneModel, $milestoneTaskModel, $request);
            $controller = new MilestoneController($services);

            $html = $controller->show();

            $this->assertSame('Portfolio:milestone/show', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Milestone Details', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
            $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(3)&lt;/script&gt;', $html);
            $this->assertStringContainsString('action=addTask', $html);
            $this->assertStringContainsString('action=removeTask', $html);
            $this->assertSame([42], $milestoneTaskModel->getTasksCalls);
        }

        public function testCreateMilestoneFormDisplay(): void
        {
            $request = new MilestoneControllerFakeRequest([], ['portfolio_id' => 9]);
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                new MilestoneControllerFakeMilestoneModel(),
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $html = $controller->create();

            $this->assertSame('Portfolio:milestone/create', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Create Milestone', $html);
            $this->assertStringContainsString('name="csrf_token"', $html);
            $this->assertStringContainsString('Q2 Release', $html);
        }

        public function testSaveMilestoneRedirectsToMilestonePageOnSuccess(): void
        {
            $request = new MilestoneControllerFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'name' => 'Release Candidate',
                    'description' => 'Cross-project hardening',
                    'target_date' => '2026-08-01',
                    'color_id' => 'green',
                    'owner_id' => 7,
                    'status' => 1,
                ],
                ['portfolio_id' => 9]
            );

            $milestoneModel = new MilestoneControllerFakeMilestoneModel();
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                $milestoneModel,
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $controller->save();

            $this->assertSame(['Milestone created successfully.'], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=MilestoneController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=show', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('milestone_id=1', (string) $services['response']->redirectUrl);
            $this->assertSame(9, (int) ($milestoneModel->createCalls[0]['portfolio_id'] ?? 0));
        }

        public function testEditMilestoneFormDisplaysCurrentValues(): void
        {
            $request = new MilestoneControllerFakeRequest([], ['milestone_id' => 42]);
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                new MilestoneControllerFakeMilestoneModel([
                    [
                        'id' => 42,
                        'portfolio_id' => 9,
                        'name' => 'Release Candidate',
                        'description' => 'Ready to launch',
                        'target_date' => 1789776000,
                        'color_id' => 'yellow',
                        'owner_id' => 6,
                        'status' => 2,
                    ],
                ]),
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $html = $controller->edit();

            $this->assertSame('Portfolio:milestone/edit', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Edit Milestone', $html);
            $this->assertStringContainsString('2026-09-19', $html);
            $this->assertStringContainsString('value="yellow"', $html);
        }

        public function testUpdateMilestoneRedirectsToMilestonePageOnSuccess(): void
        {
            $request = new MilestoneControllerFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'name' => 'RC Final',
                    'description' => 'Final prep',
                    'target_date' => '2026-08-10',
                    'color_id' => 'red',
                    'owner_id' => 11,
                    'status' => 0,
                ],
                ['milestone_id' => 42]
            );

            $milestoneModel = new MilestoneControllerFakeMilestoneModel([
                ['id' => 42, 'portfolio_id' => 9, 'name' => 'RC', 'target_date' => 0, 'status' => 1],
            ]);
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                $milestoneModel,
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $controller->update();

            $this->assertSame(['Milestone updated successfully.'], $services['flash']->successMessages);
            $this->assertStringContainsString('action=show', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('milestone_id=42', (string) $services['response']->redirectUrl);
            $this->assertSame(42, $milestoneModel->updateCalls[0]['id']);
            $this->assertSame(0, (int) ($milestoneModel->updateCalls[0]['values']['status'] ?? 1));
        }

        public function testRemoveConfirmationFlowRendersMilestoneNameAndCsrfForm(): void
        {
            $request = new MilestoneControllerFakeRequest([], ['milestone_id' => 42]);
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                new MilestoneControllerFakeMilestoneModel([
                    ['id' => 42, 'portfolio_id' => 9, 'name' => 'Release Candidate'],
                ]),
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $html = $controller->remove();

            $this->assertSame('Portfolio:milestone/remove', $services['helper']->layout->lastTemplate);
            $this->assertStringContainsString('Do you really want to remove this milestone?', $html);
            $this->assertStringContainsString('Release Candidate', $html);
            $this->assertStringContainsString('name="csrf_token"', $html);
        }

        public function testDeleteMilestoneRedirectsToPortfolioMilestoneListOnSuccess(): void
        {
            $request = new MilestoneControllerFakeRequest(
                ['csrf_token' => self::CSRF_TOKEN],
                ['milestone_id' => 42]
            );
            $milestoneModel = new MilestoneControllerFakeMilestoneModel([
                ['id' => 42, 'portfolio_id' => 9, 'name' => 'Release Candidate'],
            ]);
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel([
                    ['id' => 9, 'name' => 'Q2 Release'],
                ]),
                $milestoneModel,
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);
            $controller->delete();

            $this->assertSame(['Milestone removed successfully.'], $services['flash']->successMessages);
            $this->assertSame([42], $milestoneModel->removeCalls);
            $this->assertStringContainsString('controller=MilestoneController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=index', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('portfolio_id=9', (string) $services['response']->redirectUrl);
        }

        public function testAddTaskDelegatesToModelAndRedirectsToMilestone(): void
        {
            $request = new MilestoneControllerFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'task_id' => 707,
                    'is_critical' => 1,
                    'position' => 8,
                ],
                ['milestone_id' => 42]
            );
            $milestoneTaskModel = new MilestoneControllerFakeMilestoneTaskModel();
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel(),
                new MilestoneControllerFakeMilestoneModel(),
                $milestoneTaskModel,
                $request
            );

            $controller = new MilestoneController($services);
            $controller->addTask();

            $this->assertSame([
                [
                    'milestone_id' => 42,
                    'task_id' => 707,
                    'is_critical' => 1,
                    'position' => 8,
                ],
            ], $milestoneTaskModel->addCalls);
            $this->assertSame(['Task added to milestone.'], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=MilestoneController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=show', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('milestone_id=42', (string) $services['response']->redirectUrl);
        }

        public function testRemoveTaskDelegatesToModelAndRedirectsToMilestone(): void
        {
            $request = new MilestoneControllerFakeRequest(
                [
                    'csrf_token' => self::CSRF_TOKEN,
                    'task_id' => 808,
                ],
                ['milestone_id' => 42]
            );
            $milestoneTaskModel = new MilestoneControllerFakeMilestoneTaskModel();
            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel(),
                new MilestoneControllerFakeMilestoneModel(),
                $milestoneTaskModel,
                $request
            );

            $controller = new MilestoneController($services);
            $controller->removeTask();

            $this->assertSame([
                [
                    'milestone_id' => 42,
                    'task_id' => 808,
                ],
            ], $milestoneTaskModel->removeCalls);
            $this->assertSame(['Task removed from milestone.'], $services['flash']->successMessages);
            $this->assertStringContainsString('controller=MilestoneController', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('action=show', (string) $services['response']->redirectUrl);
            $this->assertStringContainsString('milestone_id=42', (string) $services['response']->redirectUrl);
        }

        public function testAddTaskRejectsInvalidCsrfToken(): void
        {
            $request = new MilestoneControllerFakeRequest(
                [
                    'csrf_token' => 'invalid-token',
                    'task_id' => 100,
                ],
                ['milestone_id' => 42]
            );

            $services = $this->buildServices(
                new MilestoneControllerFakePortfolioModel(),
                new MilestoneControllerFakeMilestoneModel(),
                new MilestoneControllerFakeMilestoneTaskModel(),
                $request
            );

            $controller = new MilestoneController($services);

            $this->expectException(\RuntimeException::class);
            $controller->addTask();
        }

        /**
         * @return array<string, mixed>
         */
        private function buildServices(
            MilestoneControllerFakePortfolioModel $portfolioModel,
            MilestoneControllerFakeMilestoneModel $milestoneModel,
            MilestoneControllerFakeMilestoneTaskModel $milestoneTaskModel,
            ?MilestoneControllerFakeRequest $request = null
        ): array {
            return [
                'request' => $request ?? new MilestoneControllerFakeRequest(),
                'response' => new MilestoneControllerFakeResponse(),
                'template' => new MilestoneControllerFakeTemplate(self::CSRF_TOKEN),
                'helper' => new FakeHelper(),
                'flash' => new MilestoneControllerFakeFlash(),
                'url' => new MilestoneControllerFakeUrlHelper(),
                'portfolioModel' => $portfolioModel,
                'milestoneModel' => $milestoneModel,
                'milestoneTaskModel' => $milestoneTaskModel,
                'csrf_token' => self::CSRF_TOKEN,
            ];
        }
    }
}
