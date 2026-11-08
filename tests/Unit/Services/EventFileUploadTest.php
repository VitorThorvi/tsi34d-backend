<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use App\Services\EventFileUpload;
use Core\Constants\Constants;
use Tests\TestCase;

class EventFileUploadTest extends TestCase
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
        return (string) Constants::rootPath()->join('/public/assets/uploads/events/' . $this->event->id);
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
     * Service com o passo de mover substituído por uma cópia: move_uploaded_file só funciona com
     * arquivos recebidos por HTTP, e a fixture é preservada para os demais testes.
     */
    private function service(): EventFileUpload
    {
        return new class ($this->event) extends EventFileUpload {
            protected function moveFile(string $source, string $destination): bool
            {
                return copy($source, $destination);
            }
        };
    }

    /**
     * Item de $_FILES apontando para uma fixture de tests/files.
     *
     * @return array<string, mixed>
     */
    private function fileItem(string $fixture, ?string $name = null): array
    {
        $path = (string) Constants::rootPath()->join('/tests/files/' . $fixture);

        return [
            'name' => $name ?? $fixture,
            'type' => 'application/octet-stream',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ];
    }

    /**
     * @return array<int, string> nomes dos arquivos gravados na pasta do Evento
     */
    private function storedFiles(): array
    {
        return array_map('basename', glob($this->eventFolder() . '/*') ?: []);
    }

    public function test_upload_should_store_a_valid_png_and_create_the_event_file(): void
    {
        $service = $this->service();

        $eventFile = $service->upload($this->fileItem('anexo_valido.png', 'cartaz.png'));

        $this->assertInstanceOf(EventFile::class, $eventFile);
        $this->assertNull($service->errors());
        $this->assertFalse($eventFile->newRecord());
        $this->assertEquals($this->event->id, $eventFile->event_id);
        $this->assertEquals('cartaz.png', $eventFile->name);
        $this->assertEquals('image/png', $eventFile->mime_type);
        $this->assertMatchesRegularExpression(
            '#^/assets/uploads/events/' . $this->event->id . '/[0-9a-f]{32}\.png$#',
            $eventFile->path
        );
        $this->assertFileExists($eventFile->absolutePath());
        $this->assertFileEquals(Constants::rootPath()->join('/tests/files/anexo_valido.png'), $eventFile->absolutePath());
        $this->assertEquals($eventFile->id, EventFile::findById($eventFile->id)->id);
    }

    public function test_upload_should_store_a_valid_jpeg_with_a_derived_extension(): void
    {
        $eventFile = $this->service()->upload($this->fileItem('anexo_valido.jpg', 'FOTO.JPEG'));

        $this->assertInstanceOf(EventFile::class, $eventFile);
        $this->assertEquals('image/jpeg', $eventFile->mime_type);
        $this->assertMatchesRegularExpression('#/[0-9a-f]{32}\.jpg$#', $eventFile->path);
        $this->assertFileExists($eventFile->absolutePath());
    }

    public function test_upload_should_create_the_event_folder_when_it_does_not_exist(): void
    {
        $this->assertDirectoryDoesNotExist($this->eventFolder());

        $this->service()->upload($this->fileItem('anexo_valido.png'));

        $this->assertDirectoryExists($this->eventFolder());
    }

    public function test_upload_should_use_a_different_random_name_for_each_file(): void
    {
        $first = $this->service()->upload($this->fileItem('anexo_valido.png', 'mesmo.png'));
        $second = $this->service()->upload($this->fileItem('anexo_valido.png', 'mesmo.png'));

        $this->assertNotEquals($first->path, $second->path);
        $this->assertCount(2, $this->storedFiles());
        $this->assertCount(2, $this->event->files);
    }

    public function test_upload_should_keep_only_the_base_name_of_the_original_name(): void
    {
        $eventFile = $this->service()->upload($this->fileItem('anexo_valido.png', '../../etc/cartaz.png'));

        $this->assertEquals('cartaz.png', $eventFile->name);
        $this->assertStringStartsWith('/assets/uploads/events/' . $this->event->id . '/', $eventFile->path);
    }

    public function test_upload_should_reject_an_extension_outside_the_allowed_list(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload($this->fileItem('anexo_valido.png', 'documento.pdf')));
        $this->assertEquals('deve ter a extensão jpg, jpeg ou png!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_a_php_script(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload($this->fileItem('malicioso.php')));
        $this->assertEquals('deve ter a extensão jpg, jpeg ou png!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_a_script_renamed_to_png_by_its_real_mime_type(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload($this->fileItem('falso.png')));
        $this->assertEquals('não é uma imagem JPEG ou PNG válida!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_ignore_the_mime_type_declared_by_the_client(): void
    {
        $item = $this->fileItem('falso.png');
        $item['type'] = 'image/png';

        $service = $this->service();

        $this->assertNull($service->upload($item));
        $this->assertEquals('não é uma imagem JPEG ou PNG válida!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_a_real_image_whose_extension_does_not_match_its_content(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload($this->fileItem('anexo_valido.png', 'foto.jpg')));
        $this->assertEquals('o conteúdo do arquivo não corresponde à extensão!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_a_file_above_2_mb(): void
    {
        $item = $this->fileItem('anexo_valido.png');
        $item['size'] = EventFileUpload::MAX_SIZE + 1;

        $service = $this->service();

        $this->assertEquals(2 * 1024 * 1024, EventFileUpload::MAX_SIZE);
        $this->assertNull($service->upload($item));
        $this->assertEquals('deve ter no máximo 2 MB!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_accept_a_file_of_exactly_2_mb(): void
    {
        $item = $this->fileItem('anexo_valido.png');
        $item['size'] = EventFileUpload::MAX_SIZE;

        $this->assertInstanceOf(EventFile::class, $this->service()->upload($item));
    }

    public function test_upload_should_report_the_upload_errors_of_php(): void
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'deve ter no máximo 2 MB!',
            UPLOAD_ERR_FORM_SIZE => 'deve ter no máximo 2 MB!',
            UPLOAD_ERR_PARTIAL => 'não foi enviado por completo, tente novamente!',
            UPLOAD_ERR_NO_FILE => 'selecione um arquivo!',
            UPLOAD_ERR_NO_TMP_DIR => 'não foi possível receber o arquivo, tente novamente!',
            UPLOAD_ERR_CANT_WRITE => 'não foi possível receber o arquivo, tente novamente!',
        ];

        foreach ($messages as $code => $message) {
            $item = $this->fileItem('anexo_valido.png');
            $item['error'] = $code;

            $service = $this->service();

            $this->assertNull($service->upload($item), "erro {$code}");
            $this->assertEquals($message, $service->errors(), "erro {$code}");
        }

        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_an_item_without_a_readable_temporary_file(): void
    {
        $item = $this->fileItem('anexo_valido.png');
        $item['tmp_name'] = '/tmp/nao-existe-' . bin2hex(random_bytes(4));

        $service = $this->service();

        $this->assertNull($service->upload($item));
        $this->assertEquals('não foi possível receber o arquivo, tente novamente!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_reject_an_incomplete_item(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload([]));
        $this->assertEquals('selecione um arquivo!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_errors_should_be_cleared_by_a_later_successful_upload(): void
    {
        $service = $this->service();

        $this->assertNull($service->upload($this->fileItem('malicioso.php')));
        $this->assertNotNull($service->errors());

        $this->assertInstanceOf(EventFile::class, $service->upload($this->fileItem('anexo_valido.png')));
        $this->assertNull($service->errors());
    }

    private function assertNothingWasStored(): void
    {
        $this->assertSame([], $this->storedFiles());
        $this->assertSame([], EventFile::all());
        $this->assertDirectoryDoesNotExist($this->eventFolder());
    }

    public function test_upload_should_fail_without_storing_when_the_file_cannot_be_moved(): void
    {
        $service = new class ($this->event) extends EventFileUpload {
            protected function moveFile(string $source, string $destination): bool
            {
                return false;
            }
        };

        $this->assertNull($service->upload($this->fileItem('anexo_valido.png')));
        $this->assertEquals('não foi possível salvar o arquivo, tente novamente!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_the_default_move_should_refuse_a_file_that_was_not_uploaded_by_http(): void
    {
        $service = new class ($this->event) extends EventFileUpload {
            public function move(string $source, string $destination): bool
            {
                return $this->moveFile($source, $destination);
            }
        };

        $source = (string) Constants::rootPath()->join('/tests/files/anexo_valido.png');
        $destination = sys_get_temp_dir() . '/destino-' . bin2hex(random_bytes(4)) . '.png';

        $this->assertFalse($service->move($source, $destination));
        $this->assertFileExists($source);
        $this->assertFileDoesNotExist($destination);
    }

    public function test_upload_should_refuse_the_real_service_with_a_forged_temporary_path(): void
    {
        $service = new EventFileUpload($this->event);

        $this->assertNull($service->upload($this->fileItem('anexo_valido.png')));
        $this->assertEquals('não foi possível salvar o arquivo, tente novamente!', $service->errors());
        $this->assertNothingWasStored();
    }

    public function test_upload_should_remove_the_stored_file_when_the_event_no_longer_exists(): void
    {
        $this->event->destroy();
        $service = $this->service();

        try {
            $service->upload($this->fileItem('anexo_valido.png'));
            $this->fail('A FK deveria recusar o Anexo de um Evento inexistente.');
        } catch (\PDOException $exception) {
            $this->assertSame([], $this->storedFiles());
            $this->assertDirectoryDoesNotExist($this->eventFolder());
        }
    }

    public function test_upload_should_refuse_an_event_that_was_never_saved(): void
    {
        $service = new class (new Event()) extends EventFileUpload {
            protected function moveFile(string $source, string $destination): bool
            {
                return copy($source, $destination);
            }
        };

        $uploads = (string) Constants::rootPath()->join('/public/assets/uploads/events');
        $before = glob($uploads . '/*') ?: [];

        $this->assertNull($service->upload($this->fileItem('anexo_valido.png')));
        $this->assertEquals('o Evento precisa estar salvo antes de receber anexos!', $service->errors());
        $this->assertSame($before, glob($uploads . '/*') ?: []);
    }
}
