<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

/**
 * Classes / écoles post-culte : grille + détail d'une classe.
 */
class ClasseController extends Controller
{
    private function guard(): void
    {
        if (!current_user()) {
            $this->redirect('index.php', ['page' => 'apropos']);
        }
        if (!auth_can_manage_classes()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
    }

    public function index(): void
    {
        $this->guard();
        $editId = (int) (Request::get('edit') ?? 0);
        render_page(SECTION_LABELS['classes'], view('pages/classes', [
            'classes'    => classe_service()->all(),
            'edit'       => $editId ? classe_service()->find($editId) : null,
            'formateurs' => classe_service()->formateurCandidates(),
            'errors'     => [],
            'old'        => [],
            'csrf'       => csrf_field(),
        ]));
    }

    public function detail(): void
    {
        $this->guard();
        $id = (int) (Request::get('id') ?? 0);
        $classe = $id ? classe_service()->find($id) : null;
        if (!$classe) {
            $this->redirect('index.php', ['page' => 'classes']);
        }
        render_page($classe['nom'], view('pages/classe_detail', [
            'classe'     => $classe,
            'inscrits'   => classe_service()->inscrits($id),
            'candidates' => classe_service()->candidates($id),
            'statuts'    => EXAM_STATUTS,
            'errors'     => [],
            'old'        => [],
            'csrf'       => csrf_field(),
        ]));
    }
}
