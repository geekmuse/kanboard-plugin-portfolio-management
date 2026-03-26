<?php
/**
 * Task creation form — optional milestone assignment dropdown.
 *
 * Variables pre-fetched by Plugin::attachCallable():
 *
 * @var array<int, array<string, mixed>> $milestones  Active milestones across all portfolios
 *                                                    that contain this task's project. Empty
 *                                                    when the project belongs to no portfolios.
 */
?>
<?php if (! empty($milestones)): ?>
<div class="form-group portfolio-milestone-form-field">
    <label for="milestone_id"><?= $this->text->e(t('Milestone')) ?></label>
    <select id="milestone_id" name="milestone_id" class="portfolio-milestone-select">
        <option value="0"><?= $this->text->e(t('None')) ?></option>
        <?php foreach ($milestones as $milestone): ?>
        <option value="<?= (int) ($milestone['id'] ?? 0) ?>">
            <?= $this->text->e((string) ($milestone['name'] ?? '')) ?>
        </option>
        <?php endforeach ?>
    </select>
</div>
<?php endif ?>
