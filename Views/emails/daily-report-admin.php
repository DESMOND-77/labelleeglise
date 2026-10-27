<?php
/* Email admin - nouveau rapport journalier enregistré. Variables : $admin, $context, $reviewUrl, $appName. */
$adminPrenom = h($admin['prenom'] ?? '');
$auteurNom = h($context['auteurNom'] ?? '');
$roleLabel = h($context['roleLabel'] ?? '');
$centreNom = h($context['centreNom'] ?? '');
$bacentaNom = h($context['bacentaNom'] ?? '');
$appName = h($appName ?? 'La Belle Église Intenationale Franceville');
$reviewUrl = h($reviewUrl);
$dateRapport = !empty($context['dateRapport']) ? date('d/m/Y', strtotime($context['dateRapport'])) : '-';
$enregistreLe = !empty($context['enregistreLe']) ? date('d/m/Y à H:i', strtotime($context['enregistreLe'])) : '-';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau rapport journalier enregistré</title>
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
            <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">Nouveau rapport journalier enregistré</h1>
            <p style="margin:0 0 20px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $adminPrenom ?>,</p>
            <p style="margin:0 0 20px;font-size:14.5px;line-height:1.6;color:#374151;">
              Un nouveau rapport journalier vient d'être enregistré.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 32px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F9FAFB;border-radius:12px;">
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;border-bottom:1px solid #EEF0F5;">Auteur</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #EEF0F5;"><?= $auteurNom ?></td></tr>
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;border-bottom:1px solid #EEF0F5;">Fonction</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #EEF0F5;"><?= $roleLabel ?></td></tr>
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;border-bottom:1px solid #EEF0F5;">Centre</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #EEF0F5;"><?= $centreNom ?></td></tr>
              <?php if ($bacentaNom !== ''): ?>
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;border-bottom:1px solid #EEF0F5;">Bacenta</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #EEF0F5;"><?= $bacentaNom ?></td></tr>
              <?php endif; ?>
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;border-bottom:1px solid #EEF0F5;">Date du rapport</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #EEF0F5;"><?= h($dateRapport) ?></td></tr>
              <tr><td style="padding:14px 18px;font-size:13.5px;color:#6B7280;">Enregistré le</td><td style="padding:14px 18px;font-size:13.5px;color:#111827;font-weight:600;text-align:right;"><?= h($enregistreLe) ?></td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:8px 32px 28px;">
            <a href="<?= $reviewUrl ?>" style="display:inline-block;background:#4F46E5;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Consulter le rapport</a>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;border-top:1px solid #F0F1F5;">
            <p style="margin:0;font-size:12px;color:#9CA3AF;text-align:center;"><?= $appName ?> - Plateforme de gestion des membres</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
