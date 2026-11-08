<?php

namespace App\Controllers\Admin;

use App\Models\Event;
use App\Models\EventFile;
use App\Services\EventFileUpload;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class EventFilesController extends Controller
{
    public function create(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $uploader = $this->eventFileUpload($event);
        $file = $_FILES['file'] ?? [];

        if ($uploader->upload(is_array($file) ? $file : []) !== null) {
            FlashMessage::success('Anexo enviado com sucesso!');
        } else {
            FlashMessage::danger('Anexo não enviado: ' . ($uploader->errors() ?? 'tente novamente!'));
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    public function destroy(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $file = $event->files()->findById((int) $request->getParam('file_id'));

        if (!$file instanceof EventFile) {
            FlashMessage::danger('Anexo não encontrado!');
        } elseif ($file->destroy()) {
            FlashMessage::success('Anexo removido com sucesso!');
        } else {
            FlashMessage::danger('Não foi possível remover o anexo!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    /**
     * Costura do upload: o teste de integração troca o service, porque `is_uploaded_file` só passa em
     * upload HTTP real.
     */
    protected function eventFileUpload(Event $event): EventFileUpload
    {
        return new EventFileUpload($event);
    }

    private function findEvent(Request $request): ?Event
    {
        return Event::findById((int) $request->getParam('id'));
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('admin.events.index'));
    }
}
