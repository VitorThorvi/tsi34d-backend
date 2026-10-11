<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class Admin implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // Sem usuário logado, quem redireciona é o middleware Authenticate.
        if ($user !== null && !$user->isAdmin()) {
            FlashMessage::danger('Acesso restrito a administradores');
            $this->redirectTo(route('dashboard.index'));
        }
    }

    private function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
