<?php
/* Email - rappel de rapport du jour. Variables : $user, $context (type, cibleNom, dateReunion, dueAt, title, message), $formUrl, $appName. */
$prenom = h($user['prenom'] ?? '');
$appName = h($appName ?? 'La Belle Église Intenationale Franceville');
$formUrl = h($formUrl);
$cibleNom = h($context['cibleNom'] ?? '');
$type = (string) ($context['type'] ?? 'preventif');
$dateReunion = !empty($context['dateReunion']) ? date('d/m/Y', strtotime((string) $context['dateReunion'])) : '-';
$dueAt = !empty($context['dueAt']) ? date('d/m/Y à H:i', strtotime((string) $context['dueAt'])) : null;
$message = h($context['message'] ?? '');
$heading = [
    'preventif' => 'Réunion demain',
    'echeance'  => 'Rapport attendu',
    'relance'   => 'Rapport non soumis',
    'manuel'    => 'Relance',
][$type] ?? 'Rapport du jour';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($heading) ?> - <?= $appName ?></title>
</head>
<body style="margin:0;padding:0;background:#F3F4F8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F8;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(17,24,39,.06);">
        <tr>
          <td style="background:linear-gradient(135deg,#B45309,#D97706);padding:28px 32px;text-align:center;">
            <div style="font-size:19px;font-weight:800;color:#fff;letter-spacing:.3px;">⏰ <?= h($heading) ?></div>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 12px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $prenom ?>,</p>
            <p style="margin:0 0 16px;font-size:14.5px;line-height:1.6;color:#374151;"><?= $message ?></p>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px;margin:0 0 20px;">
              <tr>
                <td style="padding:16px 18px;font-size:14px;color:#92400E;line-height:1.7;">
                  <strong>Structure :</strong> <?= $cibleNom ?><br>
                  <strong>Date de la réunion :</strong> <?= h($dateReunion) ?><br>
                  <?php if ($dueAt !== null): ?><strong>Heure limite :</strong> <?= h($dueAt) ?><br><?php endif; ?>
                </td>
              </tr>
            </table>
            <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#6B7280;">
              Le lien ci-dessous ouvre le formulaire, déjà pré-rempli avec la bonne cible et la bonne date.
              L'accès est protégé par votre compte : il ne constitue pas une autorisation à lui seul.
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:0 32px 28px;">
            <a href="<?= $formUrl ?>" style="display:inline-block;background:#B45309;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Compléter le rapport</a>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;border-top:1px solid #F0F1F5;">
            <p style="margin:0;font-size:12px;color:#9CA3AF;text-align:center;"><?= $appName ?> - Ce rappel est automatique, merci de ne pas y répondre.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
