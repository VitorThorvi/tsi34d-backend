<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use Tests\Support\AcceptanceTester;

class ListEventsCest extends BaseEventsCest
{
    public function listAllEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem de todos os registros');
        $page->amOnPage('/admin/events');

        $page->see('Eventos', 'h1');
        $page->see('Novo Evento');
        $page->seeNumberOfElements('table tbody tr', self::PER_PAGE);

        // Primeira página: os 5 primeiros Eventos (ordem do banco, que no populate coincide com a data).
        $rows = $page->grabMultiple('table tbody tr');
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $event = $this->events[$number];
            $kind = Event::KINDS[$event->kind];

            $this->assertContains($this->title($number), $row);
            $this->assertContains($kind, $row);
            $this->assertContains(date('d/m/Y', (int) strtotime($event->starts_on)), $row);
            $this->assertContains($event->location, $row);
            $this->assertContains('Ver', $row);
            $this->assertContains('Editar', $row);
            $this->assertContains('Remover', $row);
        }

        // Vagas no formato inscritos/capacity: Evento 01 lotado (3/3), Evento 03 com 1 Inscrição, Evento 02 sem.
        $page->see('3/3', $this->rowOf('Evento 01'));
        $page->see('0/22', $this->rowOf('Evento 02'));
        $page->see('1/23', $this->rowOf('Evento 03'));

        // Os demais Eventos ficam nas outras páginas.
        $page->dontSee($this->title(6));
        $page->dontSee($this->title(16));
        $page->see(sprintf('Mostrando 1 - %d de %d', self::PER_PAGE, self::TOTAL_EVENTS));
    }

    public function listEventsHasLinksToShowAndEdit(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Listagem de todos os registros (links para ver e editar)');
        $page->amOnPage('/admin/events');

        $page->seeLink('Ver', "/admin/events/{$event->id}");
        $page->seeLink('Editar', "/admin/events/{$event->id}/edit");
        $page->click('Ver', $this->rowOf('Evento 01'));
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see($event->title, 'h1');
        $page->see($event->description);
        $page->seeLink('Editar');
        $page->seeLink('Voltar');
    }

    private function assertContains(string $needle, string $haystack): void
    {
        \PHPUnit\Framework\Assert::assertStringContainsString($needle, $haystack);
    }
}
