<?php
/**
 * Layout de todo e-mail (App\Mail\Mailable::build()). CSS inline e tabelas: os
 * clientes de e-mail ignoram <style> externo e boa parte do CSS moderno.
 *
 * @var string $content HTML do template, ja escapado por ele
 * @var string $subject
 * @var string $siteName
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#2563eb;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:bold;"><?= e($siteName) ?></td>
                    </tr>
                    <tr>
                        <td style="padding:24px;font-size:15px;line-height:1.6;"><?= $content ?></td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0;">
                            Este e-mail foi enviado automaticamente por <?= e($siteName) ?>. Não responda a esta mensagem.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
