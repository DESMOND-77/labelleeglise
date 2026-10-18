<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AdminMiddleware;

/**
 * Administration du site public (back-office).
 * Réservé aux administrateurs (contrôle serveur via AdminMiddleware).
 * Organisation par rubriques indépendantes (?page=site_public&tab=…) :
 * vue d'ensemble, identité, centres (liste + fiche), équipe,
 * événements, médias, FAQ, ministères, pages légales.
 */
class AdminSiteController extends Controller
{
    /** Rubriques de gestion (ordre d'affichage) + « centre »
     * (fiche de modification, profondeur 1 — absente de la
     * navigation, rattachée visuellement à « Centres »). */
    private const TABS = ['overview', 'identity', 'centres', 'centre', 'team', 'events', 'media', 'faq', 'ministries', 'legal'];

    private const TAB_LABELS = [
        'overview'   => 'Vue d\'ensemble',
        'identity'   => 'Identité et accueil',
        'centres'    => 'Centres',
        'team'       => 'Équipe pastorale',
        'events'     => 'Événements',
        'media'      => 'Médias et prédications',
        'faq'        => 'FAQ',
        'ministries' => 'Ministères et activités',
        'legal'      => 'Pages légales',
    ];

    /** Icônes par rubrique (FontAwesome, déjà chargé par le shell). */
    private const TAB_ICONS = [
        'overview'   => 'fa-solid fa-chart-pie',
        'identity'   => 'fa-solid fa-id-card',
        'centres'    => 'fa-solid fa-landmark',
        'team'       => 'fa-solid fa-users',
        'events'     => 'fa-solid fa-calendar-days',
        'media'      => 'fa-solid fa-play-circle',
        'faq'        => 'fa-solid fa-circle-question',
        'ministries' => 'fa-solid fa-hands-praying',
        'legal'      => 'fa-solid fa-scale-balanced',
    ];

    /** GET ?page=site_public[&tab=…][&id=…] */
    public function index(): void
    {
        (new AdminMiddleware())->handle();

        // `nav('tab')` a un défaut métier (« membres ») : on lit
        // directement la requête pour le routage de ce back-office.
        $tab = (string) ($_GET['tab'] ?? 'overview');
        if (!in_array($tab, self::TABS, true)) {
            redirect('index.php', ['page' => 'site_public']);
        }

        $service = public_site_service();
        $data = $service->adminData();
        $data['tab'] = $tab;
        $data['tabLabels'] = self::TAB_LABELS;
        $data['tabIcons'] = self::TAB_ICONS;
        $data['errors'] = array_values(array_filter((array) ($_SESSION['site_public_errors'] ?? []), 'is_string'));
        $data['saved'] = isset($_GET['saved']);
        unset($_SESSION['site_public_errors']);

        $view = 'pages/admin_site/' . $tab;
        if ($tab === 'centres') {
            $view = 'pages/admin_site/centres_list';
        } elseif ($tab === 'centre') {
            // Fiche de modification d'un centre (profondeur 1).
            $id = nav('id');
            $centre = $id ? $service->centreForAdmin((int) $id) : null;
            if (!$centre) {
                redirect('index.php', ['page' => 'site_public', 'tab' => 'centres']);
            }
            $data['centre'] = $centre;
            $data['centreId'] = (int) $id;
            $view = 'pages/admin_site/centre_form';
        } elseif ($tab === 'overview') {
            $data['overview'] = $service->adminOverview();
        }

        $data['csrf'] = csrf_field();
        $content = view('pages/admin_site_shell', $data + [
            'content' => view($view, $data),
        ]);
        render_page(SECTION_LABELS['site_public'] . ' — ' . self::TAB_LABELS[$tab], $content);
    }
}
