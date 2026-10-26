<?php

namespace Tests\Unit\Models\Events;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use PDOException;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $this->admin->save();

        $this->participant = new User([
            'name' => 'Participante',
            'email' => 'participante@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $this->participant->save();

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Oficina',
            'description' => 'Descrição',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 5,
        ]);
        $this->event->save();
    }

    public function test_save_should_create_the_enrollment(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertCount(1, Enrollment::all());
        $this->assertEquals('event_user', Enrollment::table());
    }

    public function test_enrollment_should_reference_its_event_and_user(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $enrollment->save();

        $this->assertEquals($this->event->id, $enrollment->event()->get()->id);
        $this->assertEquals($this->participant->id, $enrollment->user()->get()->id);
    }

    public function test_the_same_participant_cannot_enroll_twice_in_an_event(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        (new Enrollment($params))->save();

        $this->expectException(PDOException::class);

        (new Enrollment($params))->save();
    }
}
