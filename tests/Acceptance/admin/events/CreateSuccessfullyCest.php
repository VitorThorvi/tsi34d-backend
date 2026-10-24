<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class CreateSuccessfullyCest extends BaseEventsCest
{
    public function createEventSuccessfully(AcceptanceTester $page): void
    {
        $data = $this->validEventData();

        $page->wantTo('Tentativa de cadastro bem-sucedida');
        $page->amOnPage('/admin/events');
        $page->click('Novo Evento');
        $page->seeCurrentUrlEquals('/admin/events/new');
        $page->see('Novo Evento', 'h1');

        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento cadastrado com sucesso!');
        $page->see('Eventos', 'h1');

        // O Evento novo vai para o fim da listagem (ordem do banco): 17 Eventos, 4ª página.
        $page->amOnPage('/admin/events/page/4');
        $page->see($data['title'], 'table');
        $page->see('Mostrando 16 - 17 de 17');

        $event = Event::findBy(['title' => $data['title']]);
        Assert::assertNotNull($event);
        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());
        Assert::assertEquals($this->admin->id, $event->user_id);
        Assert::assertSame($data['description'], $event->description);
        Assert::assertSame('workshop', $event->kind);
        Assert::assertSame('online', $event->modality);
        Assert::assertSame(['students', 'staff'], $event->audienceList());
        Assert::assertSame($data['starts_on'], $event->starts_on);
        Assert::assertSame($data['location'], $event->location);
        Assert::assertEquals(40, $event->capacity);
        Assert::assertSame(0, $event->enrollmentsCount());
    }
}
