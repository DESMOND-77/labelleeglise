<?php
/* Email — compte activé. Variables : $user, $loginUrl, $appName. */
$prenom = h($user['prenom'] ?? '');
$appName = h($appName ?? 'La Belle Église');
$loginUrl = h($loginUrl);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Votre compte est activé</title>
</head>
<body style="margin:0;padding:0;background:#F3F4F8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F8;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(17,24,39,.06);">
        <tr>
          <td style="background:linear-gradient(135deg,#22C55E,#16A34A);padding:32px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:.3px;">⛪ <?= $appName ?></div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 32px 8px;text-align:center;">
            <div style="font-size:42px;margin-bottom:8px;">✅</div>
            <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Votre compte <?= $appName ?> est activé</h1>
            <p style="margin:0 0 8px;font-size:14.5px;line-height:1.6;color:#374151;text-align:left;">Bonjour <?= $prenom ?>,</p>
            <p style="margin:0 0 20px;font-size:14.5px;line-height:1.6;color:#374151;text-align:left;">
              Bonne nouvelle : votre compte a été validé par l'administration de <?= $appName ?>.
              Vous pouvez maintenant vous connecter à la plateforme.
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 32px 32px;">
            <a href="<?= $loginUrl ?>" style="display:inline-block;background:#22C55E;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Se connecter</a>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;border-top:1px solid #F0F1F5;">
            <p style="margin:0;font-size:12px;color:#9CA3AF;text-align:center;"><?= $appName ?> — Plateforme de gestion des membres</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
