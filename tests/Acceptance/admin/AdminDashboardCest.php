<?php

namespace Tests\Acceptance\admin;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class AdminDashboardCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();

        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }

    public function accessAdminWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/admin');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessAdminAsCommonUser(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/admin');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }

    public function accessAdminAsAdmin(AcceptanceTester $page): void
    {
        $page->login('admin@example.com', '123456');
        $page->amOnPage('/admin');

        $page->see('Painel Administrativo', '//h1');
        $page->see('Olá, Admin!');
    }
}
