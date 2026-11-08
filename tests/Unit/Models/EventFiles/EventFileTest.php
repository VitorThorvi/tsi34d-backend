<?php

namespace Tests\Unit\Models\EventFiles;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use Core\Constants\Constants;
use PDOException;
use Tests\TestCase;

class EventFileTest extends TestCase
{
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        $this->event = new Event([
            'user_id' => $admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ]);
        $this->event->save();
    }

    public function tearDown(): void
    {
        $this->removeEventFolder();

        parent::tearDown();
    }

    private function eventFolder(): string
    {
        return (string) Constants::rootPath()->join("/public/assets/uploads/events/{$this->event->id}");
    }

    private function removeEventFolder(): void
    {
        foreach (glob($this->eventFolder() . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->eventFolder())) {
            rmdir($this->eventFolder());
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function params(array $overrides = []): array
    {
        return array_merge([
            'event_id' => $this->event->id,
            'name' => 'cartaz.png',
            'path' => "/assets/uploads/events/{$this->event->id}/abc123.png",
            'mime_type' => 'image/png',
        ], $overrides);
    }

    private function createEventFile(): EventFile
    {
        $file = new EventFile($this->params());
        $this->assertTrue($file->save());

        return $file;
    }

    private function writeDiskFile(EventFile $file): string
    {
        $absolute = $file->absolutePath();
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, 'conteudo');

        return $absolute;
    }

    /* ------------------- validações ------------------- */

    public function test_valid_event_file_should_be_valid(): void
    {
        $file = new EventFile($this->params());

        $this->assertTrue($file->isValid());
        $this->assertFalse($file->hasErrors());
    }

    public function test_empty_event_file_should_have_error_in_every_field(): void
    {
        $file = new EventFile();

        $this->assertFalse($file->isValid());
        $this->assertFalse($file->save());

        foreach (['event_id', 'name', 'path', 'mime_type'] as $field) {
            $this->assertEquals('não pode ser vazio!', $file->errors($field), $field);
        }
    }

    public function test_mime_type_should_accept_jpeg_and_png(): void
    {
        foreach (['image/jpeg', 'image/png'] as $mime) {
            $file = new EventFile($this->params(['mime_type' => $mime]));

            $this->assertTrue($file->isValid(), $mime);
        }
    }

    public function test_mime_type_should_reject_other_types(): void
    {
        foreach (['application/pdf', 'image/gif', 'text/x-php', 'png'] as $mime) {
            $file = new EventFile($this->params(['mime_type' => $mime]));

            $this->assertFalse($file->isValid(), $mime);
            $this->assertEquals('não é uma opção válida!', $file->errors('mime_type'), $mime);
        }
    }

    public function test_mime_types_constant_maps_mime_to_extension(): void
    {
        $this->assertSame(['image/jpeg' => 'jpg', 'image/png' => 'png'], EventFile::MIME_TYPES);
    }

    /* ------------------- persistência ------------------- */

    public function test_save_should_persist_and_find_by_id(): void
    {
        $file = $this->createEventFile();

        $found = EventFile::findById($file->id);

        $this->assertNotNull($found);
        $this->assertEquals('cartaz.png', $found->name);
        $this->assertEquals('image/png', $found->mime_type);
        $this->assertEquals($this->event->id, $found->event_id);
    }

    public function test_event_file_with_unknown_event_should_be_rejected_by_the_foreign_key(): void
    {
        $file = new EventFile($this->params(['event_id' => 9999]));

        $this->expectException(PDOException::class);

        $file->save();
    }

    /* ------------------- relações ------------------- */

    public function test_event_should_return_the_owner_event(): void
    {
        $file = $this->createEventFile();

        $this->assertEquals($this->event->id, $file->event()->get()->id);
        $this->assertEquals('Semana Acadêmica', $file->event->title);
    }

    /* ------------------- disco ------------------- */

    public function test_absolute_path_should_point_inside_public_directory(): void
    {
        $file = new EventFile($this->params());

        $this->assertStringEndsWith(
            "/public/assets/uploads/events/{$this->event->id}/abc123.png",
            $file->absolutePath()
        );
        $this->assertStringStartsWith('/', $file->absolutePath());
    }

    public function test_destroy_should_remove_the_row_and_the_file_from_disk(): void
    {
        $file = $this->createEventFile();
        $absolute = $this->writeDiskFile($file);
        $this->assertFileExists($absolute);

        $this->assertTrue($file->destroy());

        $this->assertNull(EventFile::findById($file->id));
        $this->assertFileDoesNotExist($absolute);
    }

    public function test_destroy_should_not_fail_when_the_file_is_already_gone(): void
    {
        $file = $this->createEventFile();
        $this->assertFileDoesNotExist($file->absolutePath());

        $this->assertTrue($file->destroy());
        $this->assertNull(EventFile::findById($file->id));
    }
}
