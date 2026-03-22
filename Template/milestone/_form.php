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
    <input
        type="text"
        id="form-color-id"
        name="color_id"
        maxlength="32"
        value="<?= $this->text->e((string) ($values['color_id'] ?? 'blue')) ?>"
    >
</div>

<div class="portfolio-form-row">
    <label for="form-owner-id"><?= $this->text->e(t('Owner ID')) ?></label>
    <input
        type="number"
        id="form-owner-id"
        name="owner_id"
        min="0"
        value="<?= $this->text->e((string) ((int) ($values['owner_id'] ?? 0))) ?>"
    >
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
