<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class PaginateEventsCest extends BaseEventsCest
{
    public function paginateEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros');
        $page->amOnPage('/admin/events');

        // Página 1: Eventos 01 a 05.
        $this->seeEventsOfPage($page, 1);
        $page->see('Mostrando 1 - 5 de 16');
        $page->seeElement('.pagination');
        $page->seeNumberOfElements('.pagination .page-item', 6);

        // Página 2 pelo número.
        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2);
        $page->see('Mostrando 6 - 10 de 16');

        // Página 3 por "Próxima".
        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/3');
        $this->seeEventsOfPage($page, 3);
        $page->see('Mostrando 11 - 15 de 16');

        // Última página: só o Evento 16.
        $page->click('4', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/4');
        $this->seeEventsOfPage($page, 4);
        $page->see('Mostrando 16 - 16 de 16');

        // Volta por "Anterior".
        $page->click('Anterior', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/3');
        $this->seeEventsOfPage($page, 3);
    }

    public function openPageTwoAndThreeDirectly(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (acesso direto às páginas 2 e 3)');

        $page->amOnPage('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2);
        $page->dontSee($this->title(1));

        $page->amOnPage('/admin/events/page/3');
        $this->seeEventsOfPage($page, 3);
        $page->dontSee($this->title(6));
    }

    /** Confere que a página mostra exatamente os Eventos dela (5 por página, ordem de data). */
    private function seeEventsOfPage(AcceptanceTester $page, int $number): void
    {
        $first = ($number - 1) * self::PER_PAGE + 1;
        $last = min($number * self::PER_PAGE, self::TOTAL_EVENTS);

        $page->see('Eventos', 'h1');
        $page->seeNumberOfElements('table tbody tr', $last - $first + 1);
        for ($i = 1; $i <= self::TOTAL_EVENTS; $i++) {
            if ($i >= $first && $i <= $last) {
                $page->see($this->title($i), 'table');
            } else {
                $page->dontSee($this->title($i), 'table');
            }
        }
    }
}
