<?php /** Seletor "Exibir N por página" (dentro do form de filtros). Recebe $list (App\Http\ListQuery). */ ?>
<label class="form-label small mb-1" for="per_page">Exibir</label>
<select class="form-select" id="per_page" name="per_page">
    <?php foreach (\App\Http\ListQuery::PER_PAGE_OPTIONS as $size): ?>
        <option value="<?= $size ?>" <?= $size === $list->perPage() ? 'selected' : '' ?>><?= $size ?> por página</option>
    <?php endforeach; ?>
</select>
