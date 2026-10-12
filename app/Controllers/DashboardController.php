<?php

namespace App\Controllers;

use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $title = 'Painel do Usuário';
        $this->render('dashboard/index', compact('title'));
    }
}
