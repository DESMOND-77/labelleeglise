<?php
/* Email — vérification de la NOUVELLE adresse (changement d'email).
 * Variables : $user, $newEmail, $verifyUrl, $expiresHours, $appName. */
$prenom = h($user['prenom'] ?? '');
$nom = h($user['nom'] ?? '');
$appName = h($appName ?? 'La Belle Église');
$newEmail = h($newEmail ?? '');
$verifyUrl = h($verifyUrl);
$expiresHours = (int) ($expiresHours ?? 24);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmez votre nouvelle adresse email</title>
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
            <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Confirmez votre nouvelle adresse email</h1>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $prenom ?> <?= $nom ?>,</p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">
              Vous avez demandé à remplacer votre adresse email de connexion sur <?= $appName ?> par
              <strong><?= $newEmail ?></strong>. Pour confirmer ce changement, cliquez sur le bouton ci-dessous.
            </p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">
              Votre session a été déconnectée par mesure de sécurité dès cette demande. Vous devrez vous
              reconnecter avec votre <strong>nouvelle</strong> adresse email une fois la vérification effectuée.
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 32px 28px;">
            <a href="<?= $verifyUrl ?>" style="display:inline-block;background:#4F46E5;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Confirmer ma nouvelle adresse</a>
          </td>
        </tr>
        <tr>
          <td style="padding:0 32px 28px;">
            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#6B7280;">
              Ce lien est valable <?= $expiresHours ?> heures et ne peut être utilisé qu'une seule fois. Si le bouton ne fonctionne pas, copiez-collez cette adresse dans votre navigateur :
            </p>
            <p style="margin:0 0 16px;font-size:12.5px;line-height:1.6;color:#4F46E5;word-break:break-all;"><?= $verifyUrl ?></p>
            <p style="margin:0;font-size:12.5px;line-height:1.6;color:#9CA3AF;">
              Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre adresse actuelle
              restera inchangée tant que ce lien n'aura pas été utilisé.
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
