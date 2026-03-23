<?php
/**
 * Shared milestone form fields (create + edit).
 *
 * @var array<string, mixed>  $values  Current form values
 * @var array<int, string>    $users   User list [id => username]
 * @var array<string, string> $colors  Color list [id => label]
 */
$users  = $users ?? [];
$colors = $colors ?? [];
?>
<div class="portfolio-form-row">
    <label for="form-name"><?= $this->text->e(t('Milestone Name')) ?></label>
    <input
        type="text"
        id="form-name"
        name="name"
        maxlength="255"
        required
        value="<?= $this->text->e((string) ($values['name'] ?? '')) ?>"
    >
</div>

<div class="portfolio-form-row">
    <label for="form-description"><?= $this->text->e(t('Milestone Description')) ?></label>
    <textarea id="form-description" name="description" rows="4"><?= $this->text->e((string) ($values['description'] ?? '')) ?></textarea>
</div>

<div class="portfolio-form-row">
    <label for="form-target-date"><?= $this->text->e(t('Target Date')) ?></label>
    <input
        type="date"
        id="form-target-date"
        name="target_date"
        value="<?= $this->text->e((string) ($values['target_date'] ?? '')) ?>"
    >
</div>

<div class="portfolio-form-row">
    <label for="form-color-id"><?= $this->text->e(t('Color')) ?></label>
    <select id="form-color-id" name="color_id">
        <?php $selectedColor = (string) ($values['color_id'] ?? 'blue'); ?>
        <?php foreach ($colors as $colorId => $colorLabel): ?>
            <option value="<?= $this->text->e((string) $colorId) ?>"<?= (string) $colorId === $selectedColor ? ' selected' : '' ?>>
                <?= $this->text->e($colorLabel) ?>
            </option>
        <?php endforeach ?>
        <?php if (empty($colors)): ?>
            <option value="blue" selected><?= $this->text->e(t('Blue')) ?></option>
        <?php endif ?>
    </select>
</div>

<div class="portfolio-form-row">
    <label for="form-owner-id"><?= $this->text->e(t('Owner')) ?></label>
    <select id="form-owner-id" name="owner_id">
        <option value="0"><?= $this->text->e(t('Unassigned')) ?></option>
        <?php $selectedOwner = (int) ($values['owner_id'] ?? 0); ?>
        <?php foreach ($users as $userId => $userName): ?>
            <option value="<?= $this->text->e((string) $userId) ?>"<?= (int) $userId === $selectedOwner ? ' selected' : '' ?>>
                <?= $this->text->e($userName) ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="portfolio-form-row">
    <label for="form-status"><?= $this->text->e(t('Status')) ?></label>
    <select id="form-status" name="status">
        <option value="1"<?= ((int) ($values['status'] ?? 1) === 1) ? ' selected' : '' ?>>
            <?= $this->text->e(t('Active')) ?>
        </option>
        <option value="0"<?= ((int) ($values['status'] ?? 1) === 0) ? ' selected' : '' ?>>
            <?= $this->text->e(t('Completed')) ?>
        </option>
        <option value="2"<?= ((int) ($values['status'] ?? 1) === 2) ? ' selected' : '' ?>>
            <?= $this->text->e(t('Archived')) ?>
        </option>
    </select>
</div>
