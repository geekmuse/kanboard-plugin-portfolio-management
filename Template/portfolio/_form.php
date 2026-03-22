<div class="portfolio-form-row">
    <label for="form-name"><?= $this->text->e(t('Portfolio Name')) ?></label>
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
    <label for="form-description"><?= $this->text->e(t('Portfolio Description')) ?></label>
    <textarea id="form-description" name="description" rows="4"><?= $this->text->e((string) ($values['description'] ?? '')) ?></textarea>
</div>

<input type="hidden" name="owner_id" value="<?= $this->text->e((string) ((int) ($values['owner_id'] ?? 0))) ?>">
<input type="hidden" name="is_active" value="0">

<div class="portfolio-form-row">
    <label>
        <input
            type="checkbox"
            name="is_active"
            value="1"
            <?= ((int) ($values['is_active'] ?? 1) === 1) ? 'checked' : '' ?>
        >
        <?= $this->text->e(t('Active')) ?>
    </label>
</div>
