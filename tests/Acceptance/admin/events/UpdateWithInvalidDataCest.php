<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class UpdateWithInvalidDataCest extends BaseEventsCest
{
    private const FLASH = 'Existem dados incorretos! Por favor, verifique!';

    public function updateWithInvalidDataKeepsValidValues(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Tentativa de atualização com dados incorretos');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->see('Editar Evento', 'h1');

        $this->fillEventForm($page, [
            'title' => '',
            'location' => 'Auditório Novo',
            'kind' => Event::KINDS['lecture'],
            'modality' => 'hybrid',
            'audience' => ['community'],
            'starts_on' => date('Y-m-d', strtotime('-2 days')),
            'capacity' => '0',
        ]);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see(self::FLASH);
        $page->see('Editar Evento', 'h1');

        $page->seeElement('#event_title.is-invalid');
        $page->see('não pode ser vazio!', '#event_title ~ .invalid-feedback');
        $page->seeElement('#event_starts_on.is-invalid');
        $page->see('não pode estar no passado!', '#event_starts_on ~ .invalid-feedback');
        $page->seeElement('#event_capacity.is-invalid');
        $page->see('deve ser maior ou igual a 1!', '#event_capacity ~ .invalid-feedback');
        foreach (['description', 'kind', 'location'] as $field) {
            $page->dontSeeElement("#event_{$field}.is-invalid");
        }

        // Valores válidos enviados continuam no formulário.
        $page->seeInField('#event_location', 'Auditório Novo');
        $page->seeInField('#event_description', $event->description);
        $page->seeOptionIsSelected('#event_kind', Event::KINDS['lecture']);
        $page->seeCheckboxIsChecked('#event_modality_hybrid');
        $page->seeCheckboxIsChecked('#event_audience_community');
        $page->dontSeeCheckboxIsChecked('#event_audience_students');

        // Nada foi gravado.
        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame($event->title, $stored->title);
        Assert::assertSame($event->location, $stored->location);
        Assert::assertEquals($event->capacity, $stored->capacity);
    }

    public function updateCapacityBelowEnrollmentsOfFullEvent(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        Assert::assertSame(3, $event->enrollmentsCount());

        $page->wantTo('Tentativa de atualização com dados incorretos (P02: capacidade menor que as Inscrições)');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->seeInField('#event_capacity', '3');

        $this->fillEventForm($page, ['capacity' => '2']);
        $page->click('Salvar');

        $page->see(self::FLASH);
        $page->seeElement('#event_capacity.is-invalid');
        $page->see('não pode ser menor que o total de inscrições (3)!', '#event_capacity ~ .invalid-feedback');
        $page->seeInField('#event_capacity', '2');
        $page->seeInField('#event_title', $event->title);

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertEquals(3, $stored->capacity);
    }
}
