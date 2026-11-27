<?php /** @var string $link @var int $minutes */ ?>
<p>Recebemos um pedido de redefinição de senha.</p>
<p>Use o link abaixo para escolher uma nova senha. Ele vale por <?= (int) $minutes ?> minutos.</p>
<p><a href="<?= e($link) ?>" style="display:inline-block;background:#2563eb;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;">Redefinir minha senha</a></p>
<p style="font-size:13px;color:#64748b;">Se o botão não funcionar, copie e cole este endereço no navegador:<br><?= e($link) ?></p>
<p>Se você não pediu isso, ignore este e-mail.</p>
