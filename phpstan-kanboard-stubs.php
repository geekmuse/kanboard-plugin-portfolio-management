<?php

/**
 * PHPStan bootstrap stubs for Kanboard framework symbols.
 *
 * These stubs teach PHPStan about Kanboard core classes, functions, and
 * constants that are available at runtime but are not shipped with the plugin.
 * They are ONLY used during static analysis — never at runtime.
 *
 * Uses bracket-form namespace declarations so multiple namespaces can coexist
 * in a single file (required when mixing global and named namespaces).
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Global namespace — Kanboard translation helper and other globals
// ---------------------------------------------------------------------------
namespace {
    if (! function_exists('t')) {
        function t(string $message, mixed ...$args): string
        {
            return $message;
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Core — Base, Translator
// ---------------------------------------------------------------------------
namespace Kanboard\Core {
    if (! class_exists(\Kanboard\Core\Base::class)) {
        abstract class Base
        {
            /** @var array<string, mixed> */
            protected array $container = [];

            /**
             * @param array<string, mixed> $container
             */
            public function __construct(array $container = [])
            {
                $this->container = $container;
            }

            /** @return mixed */
            public function __get(string $name): mixed
            {
                return $this->container[$name] ?? null;
            }

            protected function checkCSRFParam(): void
            {
            }
        }
    }

    if (! class_exists(\Kanboard\Core\Translator::class)) {
        class Translator
        {
            public static function load(string $lang, string $dir): void
            {
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Core\Plugin — Plugin base
// ---------------------------------------------------------------------------
namespace Kanboard\Core\Plugin {
    if (! class_exists(\Kanboard\Core\Plugin\Base::class)) {
        abstract class Base extends \Kanboard\Core\Base
        {
            /**
             * @param callable(mixed): void $callable
             */
            public function on(string $eventName, callable $callable): void
            {
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Action — Automatic action base class
// ---------------------------------------------------------------------------
namespace Kanboard\Action {
    if (! class_exists(\Kanboard\Action\Base::class)) {
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

// ---------------------------------------------------------------------------
// Kanboard\Controller — BaseController (extends Core\Base, adds controller infra)
// ---------------------------------------------------------------------------
namespace Kanboard\Controller {
    if (! class_exists(\Kanboard\Controller\BaseController::class)) {
        abstract class BaseController extends \Kanboard\Core\Base
        {
            protected function checkCSRFForm(): void
            {
            }

            /** @return mixed */
            protected function redirectResponse(string $url): mixed
            {
                return null;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Core\Filter — FilterInterface
// ---------------------------------------------------------------------------
namespace Kanboard\Core\Filter {
    if (! interface_exists(\Kanboard\Core\Filter\FilterInterface::class)) {
        interface FilterInterface
        {
            /** @return array<int, string> */
            public function getAttributes(): array;

            public function apply(): static;
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Filter — BaseFilter (base class for task filters)
// ---------------------------------------------------------------------------
namespace Kanboard\Filter {
    if (! class_exists(\Kanboard\Filter\BaseFilter::class)) {
        abstract class BaseFilter extends \Kanboard\Core\Base
        {
            /** @param mixed $value */
            public function __construct(mixed $value = null)
            {
            }

            /** @param mixed $container */
            public function setContainer($container): static
            {
                return $this;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Kanboard\Core\Security — Role constants
// ---------------------------------------------------------------------------
namespace Kanboard\Core\Security {
    if (! class_exists(\Kanboard\Core\Security\Role::class)) {
        class Role
        {
            public const APP_USER    = 'app-user';
            public const APP_MANAGER = 'app-manager';
            public const APP_ADMIN   = 'app-admin';
        }
    }
}
