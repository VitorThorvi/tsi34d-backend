<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class UpdateSuccessfullyCest extends BaseEventsCest
{
    public function updateEventSuccessfully(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $data = $this->validEventData([
            'title' => 'Evento 02 Atualizado',
            'kind' => Event::KINDS['short_course'],
            'modality' => 'hybrid',
            'audience' => ['professors', 'community'],
            'starts_on' => date('Y-m-d', strtotime('+90 days')),
            'location' => 'Auditório Central',
            'capacity' => '60',
        ]);

        $page->wantTo('Tentativa de atualização bem-sucedida');
        $page->amOnPage('/admin/events');
        $page->click('Editar', $this->rowOf('Evento 02'));
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/edit");
        $page->see('Editar Evento', 'h1');

        // O formulário abre com os valores atuais do Evento.
        $page->seeInField('#event_title', 'Evento 02');
        $page->seeInField('#event_description', $event->description);
        $page->seeOptionIsSelected('#event_kind', Event::KINDS[$event->kind]);
        $page->seeCheckboxIsChecked("#event_modality_{$event->modality}");
        $page->seeCheckboxIsChecked('#event_audience_students');
        $page->dontSeeCheckboxIsChecked('#event_audience_professors');
        $page->seeInField('#event_starts_on', $event->starts_on);
        $page->seeInField('#event_location', $event->location);
        $page->seeInField('#event_capacity', (string) $event->capacity);

        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!');
        $page->see($data['title'], 'h1');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame($data['title'], $stored->title);
        Assert::assertSame($data['description'], $stored->description);
        Assert::assertSame('short_course', $stored->kind);
        Assert::assertSame('hybrid', $stored->modality);
        Assert::assertSame(['professors', 'community'], $stored->audienceList());
        Assert::assertSame($data['starts_on'], $stored->starts_on);
        Assert::assertSame($data['location'], $stored->location);
        Assert::assertEquals(60, $stored->capacity);
        Assert::assertEquals($this->admin->id, $stored->user_id);
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function updateCapacityOfFullEventToExactlyItsEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Tentativa de atualização bem-sucedida (P02: capacidade igual às Inscrições é aceita)');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $this->fillEventForm($page, ['title' => 'Evento 01 Lotado', 'capacity' => '3']);
        $page->click('Salvar');

        $page->see('Evento atualizado com sucesso!');
        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame('Evento 01 Lotado', $stored->title);
        Assert::assertEquals(3, $stored->capacity);
    }

    public function raiseCapacityOfFullEvent(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Tentativa de atualização bem-sucedida (P02: aumentar a capacidade do Evento lotado)');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $this->fillEventForm($page, ['capacity' => '10']);
        $page->click('Salvar');

        $page->see('Evento atualizado com sucesso!');
        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertEquals(10, $stored->capacity);
        Assert::assertSame(3, $stored->enrollmentsCount());
    }
}
