<?php
/* Email — vérification d'adresse. Variables : $user, $verifyUrl, $expiresHours, $appName. */
$prenom = h($user['prenom'] ?? '');
$nom = h($user['nom'] ?? '');
$appName = h($appName ?? 'La Belle Église');
$verifyUrl = h($verifyUrl);
$expiresHours = (int) ($expiresHours ?? 24);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vérifiez votre adresse email</title>
</head>
<body style="margin:0;padding:0;background:#F3F4F8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F8;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(17,24,39,.06);">
        <tr>
          <td style="background:linear-gradient(135deg,#4F46E5,#6366F1);padding:32px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:.3px;">⛪ <?= $appName ?></div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 32px 8px;">
            <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Vérifiez votre adresse email</h1>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $prenom ?> <?= $nom ?>,</p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">
              Merci de vous être inscrit(e) sur la plateforme de <?= $appName ?>. Pour finaliser votre inscription,
              merci de confirmer votre adresse email en cliquant sur le bouton ci-dessous.
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 32px 28px;">
            <a href="<?= $verifyUrl ?>" style="display:inline-block;background:#4F46E5;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Vérifier mon adresse email</a>
          </td>
        </tr>
        <tr>
          <td style="padding:0 32px 28px;">
            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#6B7280;">
              Ce lien est valable <?= $expiresHours ?> heures. Si le bouton ne fonctionne pas, copiez-collez cette adresse dans votre navigateur :
            </p>
            <p style="margin:0 0 16px;font-size:12.5px;line-height:1.6;color:#4F46E5;word-break:break-all;"><?= $verifyUrl ?></p>
            <p style="margin:0;font-size:12.5px;line-height:1.6;color:#9CA3AF;">
              Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet email en toute sécurité — aucun compte ne sera activé sans cette confirmation.
            </p>
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
