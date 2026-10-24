<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

/**
 * Base dos cenários do CRUD de Eventos (Área Administrativa).
 *
 * Reproduz o formato do populate: 16 Eventos (4 páginas de 5), um Evento lotado (3/3 vagas, caso P02),
 * Eventos com Inscrições (caso P03) e Eventos sem Inscrições. As datas são relativas a hoje, então os
 * Eventos nunca ficam no passado. O login é feito como Administrador antes de cada cenário.
 */
class BaseEventsCest extends BaseAcceptanceCest
{
    protected const ADMIN_EMAIL = 'admin@example.com';
    protected const PASSWORD = '123456';
    protected const TOTAL_EVENTS = 16;
    protected const PER_PAGE = 5;

    protected User $admin;

    /** @var array<int, User> Participantes (índices 1 a 3) */
    protected array $participants = [];

    /** @var array<int, Event> Eventos indexados de 1 a 16: "Evento 01" começa em hoje + 14 dias, "Evento 02" em +15... */
    protected array $events = [];

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $this->admin = $this->createUser('Administrador', self::ADMIN_EMAIL, 'admin');
        for ($i = 1; $i <= 3; $i++) {
            $this->participants[$i] = $this->createUser("Participante {$i}", "participante{$i}@example.com", 'participant');
        }

        $this->seedEvents();

        // Janela alta: o formulário e a tabela cabem sem rolagem, e o Selenium não clica em elemento fora da tela.
        $page->resizeWindow(1280, 2400);
        $page->login(self::ADMIN_EMAIL, self::PASSWORD);
    }

    protected function createUser(string $name, string $email, string $role): User
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

    protected function title(int $number): string
    {
        return sprintf('Evento %02d', $number);
    }

    protected function startsOn(int $number): string
    {
        return date('Y-m-d', strtotime('+' . (13 + $number) . ' days'));
    }

    /**
     * Cria os Eventos em ordem de data crescente. A listagem segue a ordem do banco (id), sem ORDER BY
     * (contrato-ui-eventos.md), então aqui a ordem de id coincide com a de starts_on, como no populate.
     */
    private function seedEvents(): void
    {
        $kinds = array_keys(Event::KINDS);
        $modalities = array_keys(Event::MODALITIES);

        for ($number = 1; $number <= self::TOTAL_EVENTS; $number++) {
            $event = new Event([
                'user_id' => $this->admin->id,
                'title' => $this->title($number),
                'description' => "Descrição do {$this->title($number)}.",
                'kind' => $kinds[($number - 1) % count($kinds)],
                'modality' => $modalities[($number - 1) % count($modalities)],
                'starts_on' => $this->startsOn($number),
                'location' => "Sala {$number}",
                'capacity' => $number === 1 ? 3 : 20 + $number,
            ]);
            $event->setAudienceList($number % 2 === 1 ? ['students', 'professors'] : ['students']);
            $event->save();

            $this->events[$number] = $event;
        }

        // Evento 01: lotado (3 de 3 vagas), usado em P02 (capacity >= Inscrições) e P03 (remoção barrada).
        foreach ($this->participants as $participant) {
            $this->enroll($this->events[1], $participant);
        }

        // Evento 03: uma Inscrição (também não pode ser removido). Evento 02 fica sem Inscrições.
        $this->enroll($this->events[3], $this->participants[1]);
    }

    private function enroll(Event $event, User $user): void
    {
        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $user->id]);
        $enrollment->save();
    }

    /**
     * Preenche apenas os campos informados. `audience` define exatamente quais checkboxes ficam marcados.
     *
     * Chaves aceitas: title, description, kind (rótulo exibido no select), modality (valor do radio: in_person,
     * online, hybrid), audience (lista de valores), starts_on (Y-m-d), location, capacity.
     *
     * @param array<string, mixed> $data
     */
    protected function fillEventForm(AcceptanceTester $page, array $data): void
    {
        foreach (['title', 'description', 'location', 'capacity'] as $field) {
            if (array_key_exists($field, $data)) {
                $page->fillField("#event_{$field}", (string) $data[$field]);
            }
        }

        if (array_key_exists('kind', $data)) {
            $page->selectOption('#event_kind', (string) $data['kind']);
        }

        if (array_key_exists('modality', $data)) {
            $page->checkOption('#event_modality_' . $data['modality']);
        }

        if (array_key_exists('audience', $data)) {
            foreach (array_keys(Event::AUDIENCES) as $value) {
                if (in_array($value, (array) $data['audience'], true)) {
                    $page->checkOption("#event_audience_{$value}");
                } else {
                    $page->uncheckOption("#event_audience_{$value}");
                }
            }
        }

        if (array_key_exists('starts_on', $data)) {
            // input[type=date] depende do idioma do navegador ao digitar; definir o valor ISO é determinístico.
            $page->executeJS(
                "document.getElementById('event_starts_on').value = arguments[0];",
                [(string) $data['starts_on']]
            );
        }
    }

    /**
     * Dados todos válidos para o formulário.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function validEventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Oficina de Testes Automatizados',
            'description' => 'Oficina prática de testes de aceitação com Codeception.',
            'kind' => Event::KINDS['workshop'],
            'modality' => 'online',
            'audience' => ['students', 'staff'],
            'starts_on' => date('Y-m-d', strtotime('+1 day')),
            'location' => 'Laboratório B203',
            'capacity' => '40',
        ], $overrides);
    }

    /** XPath da linha da listagem que contém o título informado. */
    protected function rowOf(string $title): string
    {
        return "//table//tr[contains(normalize-space(.), '{$title}')]";
    }
}
