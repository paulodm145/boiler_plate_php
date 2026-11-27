<?php if (errors()->any()): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-1">Corrija os campos destacados:</p>
        <ul class="mb-0">
            <?php foreach (errors()->flatten() as $validationMessage): ?>
                <li><?= e($validationMessage) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
