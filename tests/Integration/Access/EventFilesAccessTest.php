<?php

namespace Tests\Integration\Access;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas admin.events.files.*: só o Administrador acessa. Não autenticado vai para /login; Participante é
 * barrado e vai para /dashboard.
 */
class EventFilesAccessTest extends TestCase
{
    private const PASSWORD = '123456';

    private User $admin;
    private User $participant;
    private Event $event;
    private EventFile $file;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Palestra de Acesso',
            'description' => 'Evento usado nos testes de acesso.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Microsoft Teams',
            'capacity' => 10,
        ]);
        $this->event->save();

        $this->file = new EventFile([
            'event_id' => $this->event->id,
            'name' => 'cartaz.png',
            'path' => "/assets/uploads/events/{$this->event->id}/inexistente.png",
            'mime_type' => 'image/png',
        ]);
        $this->file->save();
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function newClient(): Client
    {
        return new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
            'cookies' => true,
        ]);
    }

    private function loggedClient(User $user): Client
    {
        $client = $this->newClient();
        $response = $client->post('/login', [
            'form_params' => ['user' => ['email' => $user->email, 'password' => self::PASSWORD]],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));

        return $client;
    }

    /**
     * As 2 rotas admin.events.files.* (create e destroy). DELETE chega do navegador como POST com `_method`.
     *
     * @return array<string, array{string, string}>
     */
    public static function fileRoutes(): array
    {
        return [
            'admin.events.files.create' => ['POST', '/admin/events/1/files'],
            'admin.events.files.destroy' => ['DELETE', '/admin/events/1/files/1'],
        ];
    }

    #[DataProvider('fileRoutes')]
    public function test_files_route_should_redirect_to_login_without_session(string $method, string $uri): void
    {
        $response = $this->newClient()->post($uri, ['form_params' => ['_method' => $method]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    #[DataProvider('fileRoutes')]
    public function test_files_route_should_redirect_participant_to_dashboard(string $method, string $uri): void
    {
        $response = $this->loggedClient($this->participant)->post($uri, ['form_params' => ['_method' => $method]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));
    }

    public function test_participant_should_not_destroy_files(): void
    {
        $client = $this->loggedClient($this->participant);

        $client->post("/admin/events/{$this->event->id}/files/{$this->file->id}", [
            'form_params' => ['_method' => 'DELETE'],
        ]);

        $this->assertNotNull(EventFile::findById((int) $this->file->id));
    }

    public function test_admin_should_destroy_file_through_method_override(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post("/admin/events/{$this->event->id}/files/{$this->file->id}", [
            'form_params' => ['_method' => 'DELETE'],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/admin/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertNull(EventFile::findById((int) $this->file->id));
    }
}
