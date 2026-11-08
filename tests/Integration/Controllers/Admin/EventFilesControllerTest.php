<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use Core\Constants\Constants;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;
use Tests\Support\EventFilesControllerWithCopy;

/**
 * Controller com o service de upload trocado por uma subclasse que copia o arquivo em vez de exigir
 * `is_uploaded_file`, que só passa em upload HTTP real. O caminho real é exercido na aceitação.
 */
class EventFilesControllerTest extends ControllerTestCase
{
    private const CONTROLLER = EventFilesControllerWithCopy::class;

    private User $admin;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];
        $_FILES = [];

        $this->admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $this->admin->save();
        Auth::login($this->admin);

        $this->event = $this->createEvent();
    }

    public function tearDown(): void
    {
        $this->removeFolder((int) $this->event->id);
        $_FILES = [];
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_create_with_valid_image_saves_file_and_redirects_to_event(): void
    {
        $_FILES['file'] = $this->uploadOf('anexo_valido.png');

        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id]
        );

        $files = $this->event->files;
        $this->assertCount(1, $files);
        $this->assertSame('anexo_valido.png', $files[0]->name);
        $this->assertSame('image/png', $files[0]->mime_type);
        $this->assertFileExists($files[0]->absolutePath());
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Anexo enviado com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_create_with_script_renamed_to_png_is_refused_and_nothing_is_saved(): void
    {
        $_FILES['file'] = $this->uploadOf('falso.png');

        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id]
        );

        $this->assertCount(0, $this->event->files);
        $this->assertDirectoryDoesNotExist($this->folder((int) $this->event->id));
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals(
            'Anexo não enviado: não é uma imagem JPEG ou PNG válida!',
            $_SESSION['flash']['danger']
        );
        $this->assertArrayNotHasKey('success', $_SESSION['flash']);
    }

    public function test_create_without_file_flashes_error(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id]
        );

        $this->assertCount(0, $this->event->files);
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Anexo não enviado: selecione um arquivo!', $_SESSION['flash']['danger']);
    }

    public function test_create_with_unknown_event_redirects_to_index(): void
    {
        $_FILES['file'] = $this->uploadOf('anexo_valido.png');

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $this->assertCount(0, EventFile::all());
    }

    public function test_destroy_removes_row_and_file_from_disk(): void
    {
        $file = $this->storeFile($this->event);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'file_id' => $file->id]
        );

        $this->assertNull(EventFile::findById((int) $file->id));
        $this->assertFileDoesNotExist($file->absolutePath());
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Anexo removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_destroy_with_unknown_file_redirects_to_event(): void
    {
        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'file_id' => 9999]
        );

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Anexo não encontrado!', $_SESSION['flash']['danger']);
        $this->assertArrayNotHasKey('success', $_SESSION['flash']);
    }

    public function test_destroy_does_not_remove_file_of_another_event(): void
    {
        $other = $this->createEvent();
        $file = $this->storeFile($other);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'file_id' => $file->id]
        );

        $this->assertNotNull(EventFile::findById((int) $file->id));
        $this->assertFileExists($file->absolutePath());
        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Anexo não encontrado!', $_SESSION['flash']['danger']);

        $this->removeFolder((int) $other->id);
    }

    public function test_destroy_with_unknown_event_redirects_to_index(): void
    {
        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => 9999, 'file_id' => 1]
        );

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    /**
     * @return array<string, mixed> item de $_FILES apontando para uma cópia da fixture
     */
    private function uploadOf(string $fixture): array
    {
        $source = (string) Constants::rootPath()->join('/tests/files/' . $fixture);
        $tmpName = (string) tempnam(sys_get_temp_dir(), 'upload');
        copy($source, $tmpName);

        return [
            'name' => $fixture,
            'type' => 'application/octet-stream',
            'tmp_name' => $tmpName,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($tmpName),
        ];
    }

    private function storeFile(Event $event): EventFile
    {
        $directory = $this->folder((int) $event->id);
        mkdir($directory, 0775, true);
        copy((string) Constants::rootPath()->join('/tests/files/anexo_valido.png'), $directory . '/gravado.png');

        $file = new EventFile([
            'event_id' => $event->id,
            'name' => 'cartaz.png',
            'path' => "/assets/uploads/events/{$event->id}/gravado.png",
            'mime_type' => 'image/png',
        ]);
        $file->save();

        return $file;
    }

    private function folder(int $eventId): string
    {
        return (string) Constants::rootPath()->join("/public/assets/uploads/events/{$eventId}");
    }

    private function removeFolder(int $eventId): void
    {
        foreach (glob($this->folder($eventId) . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->folder($eventId))) {
            rmdir($this->folder($eventId));
        }
    }

    private function createEvent(): Event
    {
        $event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Oficina de Testes',
            'description' => 'Aprenda a testar.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Bloco A',
            'capacity' => 20,
        ]);
        $event->save();

        return $event;
    }
}
