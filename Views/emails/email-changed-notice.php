<?php
/* Email — avis de sécurité envoyé à l'ANCIENNE adresse après un changement
 * d'email confirmé. Purement informatif, aucun lien d'action.
 * Variables : $user, $oldEmail, $appName. */
$prenom = h($user['prenom'] ?? '');
$appName = h($appName ?? 'La Belle Église');
$oldEmail = h($oldEmail ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Votre adresse email a été modifiée</title>
</head>
<body style="margin:0;padding:0;background:#F3F4F8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F8;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(17,24,39,.06);">
        <tr>
          <td style="background:linear-gradient(135deg,#F59E0B,#D97706);padding:32px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:.3px;">⛪ <?= $appName ?></div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 32px 8px;">
            <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Votre adresse email a été modifiée</h1>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $prenom ?>,</p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">
              L'adresse email de connexion associée à votre compte <?= $appName ?> (<?= $oldEmail ?>) vient d'être
              remplacée par une nouvelle adresse, à la suite d'une demande vérifiée depuis votre profil.
            </p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;">
              Cette adresse (<?= $oldEmail ?>) ne permet plus de se connecter à votre compte.
            </p>
            <p style="margin:0;font-size:13px;line-height:1.6;color:#6B7280;">
              Si vous n'êtes pas à l'origine de ce changement, contactez immédiatement l'administration de <?= $appName ?>.
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
