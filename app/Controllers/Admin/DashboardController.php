<?php

namespace App\Controllers\Admin;

use Core\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(): void
    {
        $title = 'Painel Administrativo';
        $this->render('admin/dashboard/index', compact('title'));
    }
}
