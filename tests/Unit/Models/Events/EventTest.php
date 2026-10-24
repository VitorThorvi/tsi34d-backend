<?php

namespace Tests\Unit\Models\Events;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventTest extends TestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
    }

    private function createUser(string $name, string $email, string $role = 'participant'): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function params(array $overrides = []): array
    {
        return array_merge([
            'user_id' => $this->admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students,professors',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event($this->params($overrides));
        $this->assertTrue($event->save());

        return $event;
    }

    private function enroll(Event $event, int $total): void
    {
        for ($i = 1; $i <= $total; $i++) {
            $participant = $this->createUser("Participante {$event->id}-{$i}", "p{$event->id}-{$i}@example.com");
            $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $participant->id]);
            $this->assertTrue($enrollment->save());
        }
    }

    /* ------------------- validações ------------------- */

    public function test_valid_event_should_be_valid(): void
    {
        $event = new Event($this->params());

        $this->assertTrue($event->isValid());
        $this->assertFalse($event->hasErrors());
    }

    public function test_empty_event_should_have_error_in_every_field(): void
    {
        $event = new Event();

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());

        foreach (['title', 'description', 'kind', 'modality', 'audience', 'starts_on', 'location', 'capacity'] as $field) {
            $this->assertEquals('não pode ser vazio!', $event->errors($field), $field);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredFieldsProvider(): array
    {
        return [
            'title' => ['title'],
            'description' => ['description'],
            'location' => ['location'],
            'kind' => ['kind'],
            'modality' => ['modality'],
            'audience' => ['audience'],
            'starts_on' => ['starts_on'],
            'capacity' => ['capacity'],
        ];
    }

    #[DataProvider('requiredFieldsProvider')]
    public function test_each_required_field_should_be_validated(string $field): void
    {
        $event = new Event($this->params([$field => '']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não pode ser vazio!', $event->errors($field));
        $this->assertCount(1, $this->errorsOf($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function errorsOf(Event $event): array
    {
        $errors = [];
        foreach (['title', 'description', 'kind', 'modality', 'audience', 'starts_on', 'location', 'capacity'] as $field) {
            if ($event->errors($field) !== null) {
                $errors[$field] = $event->errors($field);
            }
        }

        return $errors;
    }

    public function test_kind_should_accept_every_enum_value(): void
    {
        foreach (array_keys(Event::KINDS) as $kind) {
            $this->assertTrue((new Event($this->params(['kind' => $kind])))->isValid(), $kind);
        }
    }

    public function test_kind_outside_enum_should_be_invalid(): void
    {
        $event = new Event($this->params(['kind' => 'concert']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não é uma opção válida!', $event->errors('kind'));
    }

    public function test_modality_should_accept_every_enum_value(): void
    {
        foreach (array_keys(Event::MODALITIES) as $modality) {
            $this->assertTrue((new Event($this->params(['modality' => $modality])))->isValid(), $modality);
        }
    }

    public function test_modality_outside_enum_should_be_invalid(): void
    {
        $event = new Event($this->params(['modality' => 'telepathy']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não é uma opção válida!', $event->errors('modality'));
    }

    public function test_audience_with_every_value_should_be_valid(): void
    {
        $event = new Event($this->params(['audience' => implode(',', array_keys(Event::AUDIENCES))]));

        $this->assertTrue($event->isValid());
    }

    public function test_audience_with_invalid_value_should_be_invalid(): void
    {
        $event = new Event($this->params(['audience' => 'students,aliens']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('contém uma opção inválida!', $event->errors('audience'));
    }

    public function test_capacity_should_be_an_integer(): void
    {
        $event = new Event($this->params(['capacity' => 'muitas']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('deve ser um número inteiro!', $event->errors('capacity'));

        $event->capacity = '2.5';
        $this->assertFalse($event->isValid());
    }

    public function test_capacity_should_be_at_least_one(): void
    {
        $event = new Event($this->params(['capacity' => 0]));

        $this->assertFalse($event->isValid());
        $this->assertEquals('deve ser maior ou igual a 1!', $event->errors('capacity'));

        $event->capacity = -5;
        $this->assertFalse($event->isValid());

        $event->capacity = '1';
        $this->assertTrue($event->isValid());
    }

    public function test_starts_on_should_be_a_valid_date(): void
    {
        foreach (['amanhã', '2030-02-31', '2030-13-01', '30/12/2030', '2030-1-5'] as $value) {
            $event = new Event($this->params(['starts_on' => $value]));

            $this->assertFalse($event->isValid(), $value);
            $this->assertEquals('não é uma data válida!', $event->errors('starts_on'), $value);
        }
    }

    public function test_starts_on_should_not_be_in_the_past(): void
    {
        $event = new Event($this->params(['starts_on' => date('Y-m-d', strtotime('-1 day'))]));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não pode estar no passado!', $event->errors('starts_on'));
    }

    public function test_starts_on_today_should_be_valid(): void
    {
        $event = new Event($this->params(['starts_on' => date('Y-m-d')]));

        $this->assertTrue($event->isValid());
    }

    public function test_invalid_event_should_not_be_saved(): void
    {
        $event = new Event($this->params(['title' => '']));

        $this->assertFalse($event->save());
        $this->assertTrue($event->newRecord());
        $this->assertCount(0, Event::all());
    }

    /* ------------------- audience (SET) ------------------- */

    public function test_audience_list_should_return_an_array(): void
    {
        $event = new Event($this->params(['audience' => 'students,community']));

        $this->assertEquals(['students', 'community'], $event->audienceList());
        $this->assertTrue($event->hasAudience('students'));
        $this->assertFalse($event->hasAudience('staff'));
    }

    public function test_audience_list_should_be_empty_without_audience(): void
    {
        $this->assertEquals([], (new Event())->audienceList());
        $this->assertEquals([], (new Event(['audience' => '']))->audienceList());
    }

    public function test_set_audience_list_should_store_a_comma_separated_string(): void
    {
        $event = new Event($this->params());
        $event->setAudienceList(['staff', 'students', 'staff', '']);

        $this->assertEquals('staff,students', $event->audience);
        $this->assertEquals(['staff', 'students'], $event->audienceList());
        $this->assertTrue($event->isValid());
    }

    public function test_set_audience_list_with_empty_array_should_be_invalid(): void
    {
        $event = new Event($this->params());
        $event->setAudienceList([]);

        $this->assertFalse($event->isValid());
        $this->assertEquals('não pode ser vazio!', $event->errors('audience'));
    }

    public function test_set_audience_list_with_invalid_value_should_be_invalid(): void
    {
        $event = new Event($this->params());
        $event->setAudienceList(['students', 'aliens']);

        $this->assertFalse($event->isValid());
        $this->assertEquals('contém uma opção inválida!', $event->errors('audience'));
    }

    public function test_audience_should_survive_a_round_trip_in_the_database(): void
    {
        $event = new Event($this->params());
        $event->setAudienceList(['community', 'professors', 'students']);
        $this->assertTrue($event->save());

        $found = Event::findById($event->id);

        $this->assertEquals(['students', 'professors', 'community'], $found->audienceList());
    }

    /* ------------------- save / update / destroy / busca ------------------- */

    public function test_save_should_create_the_event(): void
    {
        $event = $this->createEvent();

        $this->assertFalse($event->newRecord());
        $this->assertCount(1, Event::all());
    }

    public function test_find_by_id_should_return_the_event(): void
    {
        $event = $this->createEvent(['title' => 'Oficina de Git']);

        $found = Event::findById($event->id);

        $this->assertEquals('Oficina de Git', $found->title);
        $this->assertEquals(10, $found->capacity);
    }

    public function test_find_by_id_should_return_null(): void
    {
        $this->assertNull(Event::findById(999));
    }

    public function test_save_on_existing_event_should_update_it(): void
    {
        $event = $this->createEvent();

        $event->title = 'Novo título';
        $event->capacity = 50;
        $this->assertTrue($event->save());

        $found = Event::findById($event->id);
        $this->assertEquals('Novo título', $found->title);
        $this->assertEquals(50, $found->capacity);
        $this->assertCount(1, Event::all());
    }

    public function test_save_on_existing_event_should_not_persist_invalid_data(): void
    {
        $event = $this->createEvent();

        $event->kind = 'concert';
        $this->assertFalse($event->save());

        $this->assertEquals('lecture', Event::findById($event->id)->kind);
    }

    public function test_update_should_change_the_attributes(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->update(['location' => 'Laboratório B203']));

        $this->assertEquals('Laboratório B203', Event::findById($event->id)->location);
    }

    public function test_destroy_should_remove_an_event_without_enrollments(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->destroy());
        $this->assertCount(0, Event::all());
    }

    public function test_where_should_filter_by_conditions(): void
    {
        $this->createEvent(['kind' => 'lecture', 'title' => 'Palestra 1']);
        $this->createEvent(['kind' => 'lecture', 'title' => 'Palestra 2']);
        $this->createEvent(['kind' => 'workshop', 'title' => 'Oficina 1']);

        $this->assertCount(2, Event::where(['kind' => 'lecture']));
        $this->assertCount(1, Event::where(['kind' => 'workshop']));
        $this->assertCount(1, Event::where(['kind' => 'lecture', 'title' => 'Palestra 2']));
        $this->assertCount(0, Event::where(['kind' => 'short_course']));
    }

    public function test_find_by_and_exists(): void
    {
        $event = $this->createEvent(['title' => 'Único']);

        $this->assertEquals($event->id, Event::findBy(['title' => 'Único'])->id);
        $this->assertNull(Event::findBy(['title' => 'Outro']));
        $this->assertTrue(Event::exists(['title' => 'Único']));
        $this->assertFalse(Event::exists(['title' => 'Outro']));
    }

    /* ------------------- paginate ------------------- */

    public function test_paginate_should_return_five_events_per_page(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $page1 = Event::paginate(1, 5, 'admin.events.paginate');
        $page2 = Event::paginate(2, 5, 'admin.events.paginate');
        $page3 = Event::paginate(3, 5, 'admin.events.paginate');

        $this->assertEquals(12, $page1->totalOfRegisters());
        $this->assertEquals(3, $page1->totalOfPages());
        $this->assertEquals(5, $page1->perPage());
        $this->assertCount(5, $page1->registers());
        $this->assertCount(5, $page2->registers());
        $this->assertCount(2, $page3->registers());
        $this->assertInstanceOf(Event::class, $page1->registers()[0]);

        $ids = array_map(
            fn(Event $event) => $event->id,
            array_merge($page1->registers(), $page2->registers(), $page3->registers())
        );
        $this->assertCount(12, array_unique($ids));
    }

    public function test_paginate_should_navigate_between_pages(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $page1 = Event::paginate(1, 5);
        $page2 = Event::paginate(2, 5);

        $this->assertFalse($page1->hasPreviousPage());
        $this->assertTrue($page1->hasNextPage());
        $this->assertTrue($page2->hasPreviousPage());
        $this->assertFalse($page2->hasNextPage());
        $this->assertTrue($page2->isPage(2));
    }

    public function test_paginate_should_use_the_given_route_name(): void
    {
        $this->assertEquals('admin.events.paginate', Event::paginate(1, 5, 'admin.events.paginate')->getRouteName());
    }

    public function test_paginate_should_default_the_route_name_to_the_table(): void
    {
        $this->assertEquals('events.paginate', Event::paginate(1, 5)->getRouteName());
    }

    /* ------------------- relações ------------------- */

    public function test_event_should_belong_to_its_creator(): void
    {
        $event = $this->createEvent();

        $this->assertEquals($this->admin->id, $event->user_id);
        $this->assertEquals($this->admin->id, $event->user()->get()->id);
    }

    public function test_user_should_have_many_events(): void
    {
        $other = $this->createUser('Outro Admin', 'other@example.com', 'admin');
        $this->createEvent(['title' => 'Evento 1']);
        $this->createEvent(['title' => 'Evento 2']);
        $this->createEvent(['title' => 'Evento 3', 'user_id' => $other->id]);

        $this->assertCount(2, $this->admin->events()->get());
        $this->assertCount(1, $other->events()->get());
        $this->assertInstanceOf(Event::class, $this->admin->events()->get()[0]);
    }

    /* ------------------- Inscrições ------------------- */

    public function test_enrollments_count_should_be_zero_for_new_record_and_for_event_without_enrollments(): void
    {
        $this->assertEquals(0, (new Event($this->params()))->enrollmentsCount());
        $this->assertEquals(0, $this->createEvent()->enrollmentsCount());
    }

    public function test_enrollments_count_should_count_only_the_events_enrollments(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent(['title' => 'Outro evento']);
        $this->enroll($event, 3);
        $this->enroll($other, 1);

        $this->assertEquals(3, $event->enrollmentsCount());
        $this->assertEquals(1, $other->enrollmentsCount());
        $this->assertTrue($event->hasEnrollments());
    }

    public function test_available_spots_should_be_capacity_minus_enrollments(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->assertEquals(5, $event->availableSpots());
        $this->assertFalse($event->hasEnrollments());

        $this->enroll($event, 2);
        $this->assertEquals(3, $event->availableSpots());
    }

    /* ------------------- P02: capacity >= Inscrições ------------------- */

    public function test_capacity_below_enrollments_should_be_invalid_on_edit(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event, 3);

        $event->capacity = 2;

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('não pode ser menor que o total de inscrições (3)!', $event->errors('capacity'));
        $this->assertEquals(5, Event::findById($event->id)->capacity);
    }

    public function test_capacity_equal_to_enrollments_should_be_valid_on_edit(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event, 3);

        $event->capacity = 3;

        $this->assertTrue($event->save());
        $this->assertEquals(3, Event::findById($event->id)->capacity);
    }

    public function test_capacity_above_enrollments_should_be_valid_on_edit(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event, 3);

        $event->capacity = '40';

        $this->assertTrue($event->save());
    }

    public function test_capacity_rule_with_enrollments_should_not_apply_to_new_records(): void
    {
        $event = new Event($this->params(['capacity' => 1]));

        $this->assertTrue($event->newRecord());
        $this->assertTrue($event->isValid());
    }

    public function test_full_event_can_be_edited_keeping_its_capacity(): void
    {
        $event = $this->createEvent(['capacity' => 3]);
        $this->enroll($event, 3);

        $event->title = 'Título novo';

        $this->assertTrue($event->save());
    }

    /* ------------------- P03: destroy seguro ------------------- */

    public function test_safe_destroy_should_remove_an_event_without_enrollments(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->safeDestroy());
        $this->assertFalse($event->hasErrors());
        $this->assertNull(Event::findById($event->id));
    }

    public function test_safe_destroy_should_not_remove_an_event_with_enrollments(): void
    {
        $event = $this->createEvent();
        $this->enroll($event, 2);

        $this->assertFalse($event->safeDestroy());

        $this->assertEquals('não é possível remover um evento com inscrições (2)!', $event->errors('base'));
        $this->assertNotNull(Event::findById($event->id));
        $this->assertEquals(2, $event->enrollmentsCount());
    }

    public function test_safe_destroy_should_clear_previous_errors(): void
    {
        $event = $this->createEvent();
        $this->enroll($event, 1);
        $this->assertFalse($event->safeDestroy());

        Enrollment::findBy(['event_id' => $event->id])->destroy();

        $this->assertTrue($event->safeDestroy());
        $this->assertNull($event->errors('base'));
    }

    public function test_safe_destroy_should_fail_for_a_new_record(): void
    {
        $event = new Event($this->params());

        $this->assertFalse($event->safeDestroy());
        $this->assertNotNull($event->errors('base'));
    }

    public function test_plain_destroy_with_enrollments_is_blocked_by_the_foreign_key(): void
    {
        $event = $this->createEvent();
        $this->enroll($event, 1);

        $this->expectException(PDOException::class);

        $event->destroy();
    }
}
