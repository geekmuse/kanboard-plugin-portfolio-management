<?php
/**
 * Shared portfolio sidebar — used on top-level pages (list, create)
 * and on portfolio-scoped pages (dashboard, tasks, board, etc.).
 *
 * When $portfolio is set, portfolio-specific navigation is shown.
 *
 * @var string $sidebar_active  Active sidebar item key
 * @var array<string,mixed>|null $portfolio  Portfolio dict (null on list/create pages)
 */
$portfolio   = $portfolio ?? null;
$portfolioId = $portfolio ? (int) ($portfolio['id'] ?? 0) : 0;
$active      = $sidebar_active ?? '';
?>
<div class="sidebar">
    <h2><?= $this->text->e(t('Portfolios')) ?></h2>
    <ul>
        <li <?= $active === 'list' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioListController', 'index', ['plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('All Portfolios')) ?>
            </a>
        </li>
        <li <?= $active === 'create' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioModificationController', 'create', ['plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Create Portfolio')) ?>
            </a>
        </li>
    </ul>

    <?php if ($portfolio !== null && $portfolioId > 0): ?>
    <h2><?= $this->text->e((string) ($portfolio['name'] ?? '')) ?></h2>
    <ul>
        <li <?= $active === 'dashboard' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioViewController', 'show', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Dashboard')) ?>
            </a>
        </li>
        <li <?= $active === 'tasks' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioViewController', 'tasks', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Task List')) ?>
            </a>
        </li>
        <li <?= $active === 'board' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioViewController', 'board', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Board')) ?>
            </a>
        </li>
        <li <?= $active === 'timeline' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioViewController', 'timeline', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Timeline')) ?>
            </a>
        </li>
        <li <?= $active === 'milestones' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('MilestoneController', 'index', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Milestones')) ?>
            </a>
        </li>
        <li <?= $active === 'dependencies' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('DependencyController', 'graph', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Dependencies')) ?>
            </a>
        </li>
        <li <?= $active === 'blocked' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('DependencyController', 'blocked', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Blocked Tasks')) ?>
            </a>
        </li>
        <li <?= $active === 'critical-path' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('DependencyController', 'criticalPath', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Critical Path')) ?>
            </a>
        </li>
        <li <?= $active === 'settings' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioModificationController', 'settings', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Portfolio Settings')) ?>
            </a>
        </li>
        <li <?= $active === 'edit' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioModificationController', 'edit', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Edit Portfolio')) ?>
            </a>
        </li>
        <li <?= $active === 'remove' ? 'class="active"' : '' ?>>
            <a href="<?= $this->url->href('PortfolioModificationController', 'remove', ['portfolio_id' => $portfolioId, 'plugin' => 'Portfolio']) ?>">
                <?= $this->text->e(t('Remove Portfolio')) ?>
            </a>
        </li>
    </ul>
    <?php endif ?>
</div>
