<?php

namespace Tests\Acceptance\admin\events\files;

use App\Models\EventFile;
use Core\Constants\Constants;
use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * Base dos cenários de Anexos. Usa os Eventos de BaseEventsCest: o Evento 02 não tem Inscrições e é o
 * Evento dos cenários. As fixtures (`anexo_valido.png|jpg`, `falso.png`) ficam em tests/Support/Data.
 *
 * O upload é sempre feito pelo formulário (upload HTTP real, `is_uploaded_file` de verdade), e os
 * arquivos gravados em public/assets/uploads/events/{id}/ são removidos ao fim de cada cenário.
 */
class BaseEventFilesCest extends BaseEventsCest
{
    public function _after(AcceptanceTester $page): void
    {
        foreach ($this->events as $event) {
            $folder = $this->eventFolder((int) $event->id);
            foreach (glob($folder . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($folder)) {
                rmdir($folder);
            }
        }

        parent::_after($page);
    }

    protected function eventFolder(int $eventId): string
    {
        return (string) Constants::rootPath()->join("/public/assets/uploads/events/{$eventId}");
    }

    protected function openEvent(AcceptanceTester $page): int
    {
        $eventId = (int) $this->events[2]->id;
        $page->amOnPage("/admin/events/{$eventId}");
        $page->see('Anexos', 'h2');

        return $eventId;
    }

    protected function upload(AcceptanceTester $page, string $fixture): void
    {
        $page->attachFile('input[name="file"]', $fixture);
        $page->click('Enviar');
    }

    /**
     * Anexo gravado no banco para o Evento, com o nome original informado.
     */
    protected function storedFile(int $eventId, string $name): EventFile
    {
        foreach (EventFile::where(['event_id' => $eventId]) as $file) {
            if ($file->name === $name) {
                return $file;
            }
        }

        throw new \RuntimeException("Anexo {$name} não encontrado para o Evento {$eventId}");
    }
}
