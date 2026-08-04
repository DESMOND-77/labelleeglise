<?php
/**
 * Script ponctuel : remplace les emojis/icônes UI par Font Awesome.
 * Court et jetable — ne pas conserver.
 */

$root = __DIR__;

// Mapping emoji -> markup Font Awesome (utilisé dans les vues HTML)
$map = [
    '✎' => '<i class="fa-solid fa-pen"></i>',
    '🗑' => '<i class="fa-solid fa-trash"></i>',
    '⛪' => '<i class="fa-solid fa-church"></i>',
    '🏛️' => '<i class="fa-solid fa-landmark"></i>',
    '🏫' => '<i class="fa-solid fa-school"></i>',
    '🙏' => '<i class="fa-solid fa-hands-praying"></i>',
    '🎤' => '<i class="fa-solid fa-microphone"></i>',
    '💰' => '<i class="fa-solid fa-sack-dollar"></i>',
    '📊' => '<i class="fa-solid fa-chart-column"></i>',
    '👥' => '<i class="fa-solid fa-users"></i>',
    '🧑‍🤝‍🧑' => '<i class="fa-solid fa-people-group"></i>',
    '🎯' => '<i class="fa-solid fa-bullseye"></i>',
    '🌱' => '<i class="fa-solid fa-seedling"></i>',
    '🚀' => '<i class="fa-solid fa-rocket"></i>',
    '🙌' => '<i class="fa-solid fa-hands"></i>',
    '🕊️' => '<i class="fa-solid fa-dove"></i>',
    '🔑' => '<i class="fa-solid fa-key"></i>',
    '🪪' => '<i class="fa-solid fa-id-card"></i>',
    '🔒' => '<i class="fa-solid fa-lock"></i>',
    '👤' => '<i class="fa-solid fa-user"></i>',
    '🌙' => '<i class="fa-solid fa-moon"></i>',
    '🎓' => '<i class="fa-solid fa-graduation-cap"></i>',
    '📭' => '<i class="fa-solid fa-inbox"></i>',
    '🔎' => '<i class="fa-solid fa-magnifying-glass"></i>',
    '😕' => '<i class="fa-solid fa-face-frown"></i>',
    '🚫' => '<i class="fa-solid fa-ban"></i>',
    '📋' => '<i class="fa-solid fa-clipboard-list"></i>',
    '📅' => '<i class="fa-solid fa-calendar-days"></i>',
    '✔' => '<i class="fa-solid fa-check"></i>',
    '✖' => '<i class="fa-solid fa-xmark"></i>',
    '☰' => '<i class="fa-solid fa-bars"></i>',
    '⎋' => '<i class="fa-solid fa-arrow-right-from-bracket"></i>',
];

// Mapping emoji -> classe Font Awesome (pour empty_state() en 1er argument)
$emptyMap = [
    '📭' => 'fa-inbox',
    '🔎' => 'fa-magnifying-glass',
    '😕' => 'fa-face-frown',
    '🚫' => 'fa-ban',
    '🌙' => 'fa-moon',
    '🎓' => 'fa-graduation-cap',
    '🙏' => 'fa-hands-praying',
    '🏫' => 'fa-school',
    '⛪' => 'fa-church',
    '🏛️' => 'fa-landmark',
    '🎤' => 'fa-microphone',
];

$files = [
    'pages_sections.php',
    'pages_apropos.php',
    'pages_parametres.php',
    'pages_bergers.php',
    'pages.php',
    'views/templates/berger_veillees.php',
    'views/templates/berger_examens.php',
    'views/templates/centre_article.php',
    'views/templates/bacenta_suivi.php',
    'views/templates/centre_offrandes.php',
    'views/templates/culte_detail.php',
    'views/templates/finances.php',
    'views/templates/suivi_admin_stats.php',
    'views/templates/profile.php',
    'views/templates/recherche.php',
    'views/templates/centres_presentation.php',
    'views/templates/apropos.php',
    'views/templates/access_gate.php',
    'views/templates/berger_infos.php',
    'views/templates/forms/member.php',
];

foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "SKIP (absent): $rel\n";
        continue;
    }
    $content = file_get_contents($path);
    $orig = $content;

    // 1) empty_state('emoji', ...) -> empty_state('fa-xxx', ...)
    foreach ($emptyMap as $emoji => $cls) {
        $content = str_replace("empty_state('" . $emoji . "'", "empty_state('" . $cls . "'", $content);
    }

    // 2) Remplacement HTML générique des emojis UI
    foreach ($map as $emoji => $html) {
        $content = str_replace($emoji, $html, $content);
    }

    if ($content !== $orig) {
        file_put_contents($path, $content);
        echo "UPDATED: $rel\n";
    } else {
        echo "no change: $rel\n";
    }
}
echo "DONE\n";

