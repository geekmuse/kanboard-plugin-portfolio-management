<?php

declare(strict_types=1);

namespace Kanboard\Plugin\Portfolio\Controller;

use Kanboard\Controller\BaseController;
use Throwable;

class ConfigController extends BaseController
{
    /**
     * @return array<string, int|string>
     */
    private function getDefaultSettings(): array
    {
        return [
            'portfolio_milestone_at_risk_days' => 7,
            'portfolio_milestone_at_risk_threshold' => 80,
            'portfolio_board_show_blockers' => 1,
            'portfolio_dashboard_widget_enabled' => 1,
            'portfolio_dependency_link_types' => 'blocks',
            'portfolio_tasks_per_page' => 50,
        ];
    }

    public function show()
    {
        return $this->response->html($this->helper->layout->config('Portfolio:config/settings', [
            'title' => t('Portfolio Settings'),
            'settings' => $this->getCurrentSettings(),
        ]));
    }

    public function save()
    {

        $configModel = $this->resolveConfigModel();
        if (! is_object($configModel) || ! method_exists($configModel, 'save')) {
            $this->flash->failure(t('Unable to save portfolio settings.'));

            return $this->response->redirect($this->helper->url->href('ConfigController', 'show', ['plugin' => 'Portfolio']));
        }

        $settings = $this->normalizeSubmittedSettings();
        $hasFailure = false;

        foreach ($settings as $key => $value) {
            try {
                $result = $configModel->save($key, (string) $value);
                if ($result === false) {
                    $hasFailure = true;
                }
            } catch (Throwable $exception) {
                $hasFailure = true;
            }
        }

        if ($hasFailure) {
            $this->flash->failure(t('Unable to save portfolio settings.'));
        } else {
            $this->flash->success(t('Portfolio settings saved successfully.'));
        }

        return $this->response->redirect($this->helper->url->href('ConfigController', 'show', ['plugin' => 'Portfolio']));
    }

    /**
     * @return array<string, int|string>
     */
    private function getCurrentSettings(): array
    {
        $defaults = $this->getDefaultSettings();
        $configModel = $this->resolveConfigModel();

        if (! is_object($configModel) || ! method_exists($configModel, 'get')) {
            return $defaults;
        }

        $settings = [];

        foreach ($defaults as $key => $defaultValue) {
            try {
                $value = $configModel->get($key, $defaultValue);
            } catch (Throwable $exception) {
                $value = $defaultValue;
            }

            if (is_int($defaultValue)) {
                $settings[$key] = (int) $value;
                continue;
            }

            $settings[$key] = (string) $value;
        }

        return $settings;
    }

    /**
     * @return array<string, int|string>
     */
    private function normalizeSubmittedSettings(): array
    {
        return [
            'portfolio_milestone_at_risk_days' => $this->normalizeInteger(
                $this->request->getValue('portfolio_milestone_at_risk_days', 7),
                0,
                365,
                7
            ),
            'portfolio_milestone_at_risk_threshold' => $this->normalizeInteger(
                $this->request->getValue('portfolio_milestone_at_risk_threshold', 80),
                1,
                100,
                80
            ),
            'portfolio_board_show_blockers' => $this->normalizeBoolean(
                $this->request->getValue('portfolio_board_show_blockers', 0)
            ) ? 1 : 0,
            'portfolio_dashboard_widget_enabled' => $this->normalizeBoolean(
                $this->request->getValue('portfolio_dashboard_widget_enabled', 0)
            ) ? 1 : 0,
            'portfolio_dependency_link_types' => $this->normalizeLinkTypeList(
                $this->request->getValue('portfolio_dependency_link_types', 'blocks')
            ),
            'portfolio_tasks_per_page' => $this->normalizeInteger(
                $this->request->getValue('portfolio_tasks_per_page', 50),
                1,
                500,
                50
            ),
        ];
    }

    private function normalizeInteger(mixed $value, int $min, int $max, int $default): int
    {
        $resolvedValue = (int) $value;

        if ($resolvedValue < $min || $resolvedValue > $max) {
            return $default;
        }

        return $resolvedValue;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalizedValue = strtolower(trim((string) $value));

        return in_array($normalizedValue, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeLinkTypeList(mixed $value): string
    {
        $parts = explode(',', (string) $value);
        $normalized = [];

        foreach ($parts as $part) {
            $label = strtolower(trim($part));
            if ($label === '') {
                continue;
            }

            $normalized[$label] = true;
        }

        if ($normalized === []) {
            return 'blocks';
        }

        return implode(', ', array_keys($normalized));
    }

    private function resolveConfigModel(): mixed
    {
        /** @var mixed $container */
        $container = $this->container;

        if (is_array($container) && array_key_exists('configModel', $container)) {
            return $container['configModel'];
        }

        if ($container instanceof \ArrayAccess && isset($container['configModel'])) {
            return $container['configModel'];
        }

        return null;
    }
}
