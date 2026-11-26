<?php
/* Email - bilan hebdomadaire/mensuel personnalisé. Variables : $user, $context, $ficheUrl, $appName.
   Strictement personnel : ne JAMAIS inclure les résultats d'autres responsables. */
$prenom = h($user['prenom'] ?? '');
$appName = h($appName ?? 'La Belle Église Intenationale Franceville');
$ficheUrl = h($ficheUrl);
$periodLabel = h($context['periodLabel'] ?? '');
$isWeek = ($context['periodType'] ?? '') === 'week';
$expected = (int) ($context['expected'] ?? 0);
$submitted = (int) ($context['submitted'] ?? 0);
$byOther = (int) ($context['filledByOther'] ?? 0);
$onTime = (int) ($context['onTime'] ?? 0);
$late = (int) ($context['late'] ?? 0);
$completion = h($context['completionRate'] ?? 'Non évalué');
$punctuality = h($context['punctualityRate'] ?? 'Non évalué');
$levelLabel = isset($context['levelLabel']) && $context['levelLabel'] !== null ? h($context['levelLabel']) : null;
$levelChanged = !empty($context['levelChanged']);
$message = h($context['message'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Votre bilan <?= $isWeek ? 'hebdomadaire' : 'mensuel' ?> - <?= $appName ?></title>
</head>
<body style="margin:0;padding:0;background:#F3F4F8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F8;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(17,24,39,.06);">
        <tr>
          <td style="background:linear-gradient(135deg,#166534,#16A34A);padding:28px 32px;text-align:center;">
            <div style="font-size:19px;font-weight:800;color:#fff;letter-spacing:.3px;">🏆 Votre bilan <?= $isWeek ? 'hebdomadaire' : 'mensuel' ?></div>
            <div style="font-size:13px;color:#DCFCE7;margin-top:6px;"><?= $periodLabel ?></div>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 32px 8px;">
            <p style="margin:0 0 12px;font-size:14.5px;line-height:1.6;color:#374151;">Bonjour <?= $prenom ?>,</p>
            <?php if ($levelChanged && $levelLabel !== null): ?>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;font-weight:700;color:#166534;">🎉 Nouveau niveau atteint : <?= $levelLabel ?> !</p>
            <?php endif; ?>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 18px;font-size:14px;color:#374151;">
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;">Rapports attendus</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;font-weight:700;"><?= $expected ?></td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;">Rapports soumis</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;font-weight:700;"><?= $submitted ?></td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;color:#6B7280;">… dont soumis par un autre responsable</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;color:#6B7280;"><?= $byOther ?></td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;">Rendus à temps</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;font-weight:700;"><?= $onTime ?></td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;">Rendus en retard</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;font-weight:700;"><?= $late ?></td></tr>
              <tr><td style="padding:6px 0;border-bottom:1px solid #F0F1F5;">Taux de réalisation personnel</td><td align="right" style="padding:6px 0;border-bottom:1px solid #F0F1F5;font-weight:700;"><?= $completion ?></td></tr>
              <tr><td style="padding:6px 0;">Ponctualité</td><td align="right" style="padding:6px 0;font-weight:700;"><?= $punctuality ?></td></tr>
            </table>
            <?php if ($levelLabel !== null): ?>
            <p style="margin:0 0 6px;font-size:14px;color:#374151;"><strong>Niveau :</strong> <?= $levelLabel ?></p>
            <?php endif; ?>
            <p style="margin:0 0 20px;font-size:14.5px;line-height:1.6;color:#374151;"><?= nl2br($message) ?></p>
            <p style="margin:0 0 20px;font-size:12.5px;line-height:1.6;color:#6B7280;">
              Un « rapport soumis par un autre responsable » n'est ni crédité ni compté comme un échec individuel.
              Le taux individuel ne porte que sur les occurrences qui vous sont créditées (rapports crédités / (rapports crédités + échéances manquées)). Un rapport soumis par un autre responsable ne vous est ni crédité ni pénalisant.
            </p>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:0 32px 28px;">
            <a href="<?= $ficheUrl ?>" style="display:inline-block;background:#166534;color:#fff;text-decoration:none;font-weight:700;font-size:14.5px;padding:14px 28px;border-radius:10px;">Voir mon suivi</a>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px;border-top:1px solid #F0F1F5;">
            <p style="margin:0;font-size:12px;color:#9CA3AF;text-align:center;"><?= $appName ?> - Message strictement personnel : merci de ne pas le diffuser.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
