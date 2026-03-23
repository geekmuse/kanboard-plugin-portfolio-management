<?php

declare(strict_types=1);

namespace Kanboard\Core\Controller {
    if (! class_exists(__NAMESPACE__ . '\\AccessForbiddenException')) {
        class AccessForbiddenException extends \RuntimeException
        {
        }
    }
}

namespace Kanboard\Plugin\Portfolio\Test\Controller {

    /**
     * Minimal text helper for template rendering in tests.
     */
    final class FakeLayoutTextHelper
    {
        public function e(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Minimal URL helper for template rendering in tests.
     */
    final class FakeLayoutUrlHelper
    {
        /** @param array<string, mixed> $params */
        public function href(string $controller, string $action, array $params = [], bool $csrf = false): string
        {
            $query = array_merge([
                'controller' => $controller,
                'action' => $action,
            ], $params);

            return '/?' . http_build_query($query);
        }
    }

    /**
     * Minimal form helper for template rendering in tests.
     */
    final class FakeLayoutFormHelper
    {
        public function csrf(): string
        {
            return '<input type="hidden" name="csrf_token" value="test-csrf">';
        }
    }

    /**
     * Minimal asset helper for template rendering in tests.
     */
    final class FakeLayoutAssetHelper
    {
        public function js(string $filename): string
        {
            return '<script defer src="' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '"></script>';
        }

        public function css(string $filename): string
        {
            return '<link rel="stylesheet" href="' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '">';
        }
    }

    /**
     * Fake layout helper for controller tests.
     */
    final class FakeLayout
    {
        public string $lastTemplate = '';

        /** @var array<string, mixed> */
        public array $lastParams = [];

        public FakeLayoutTextHelper $text;
        public FakeLayoutUrlHelper $url;
        public FakeLayoutFormHelper $form;
        public FakeLayoutAssetHelper $asset;

        public function __construct()
        {
            $this->text = new FakeLayoutTextHelper();
            $this->url = new FakeLayoutUrlHelper();
            $this->form = new FakeLayoutFormHelper();
            $this->asset = new FakeLayoutAssetHelper();
        }

        /** @param array<string, mixed> $params */
        public function app(string $template, array $params = []): string
        {
            return $this->doRender($template, $params);
        }

        /** @param array<string, mixed> $params */
        public function config(string $template, array $params = []): string
        {
            return $this->doRender($template, $params);
        }

        /** @param array<string, mixed> $params */
        private function doRender(string $template, array $params): string
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

    /**
     * Fake token service for CSRF validation in controller tests.
     */
    final class FakeToken
    {
        private string $expectedToken;

        public function __construct(string $expectedToken = 'csrf-token')
        {
            $this->expectedToken = $expectedToken;
        }

        public function getCSRFToken(): string
        {
            return $this->expectedToken;
        }

        public function validateCSRFToken(string $token): bool
        {
            return $token === $this->expectedToken;
        }
    }

    final class FakeHelper
    {
        public FakeLayout $layout;
        public FakeLayoutUrlHelper $url;

        public function __construct()
        {
            $this->layout = new FakeLayout();
            $this->url = new FakeLayoutUrlHelper();
        }
    }
}
