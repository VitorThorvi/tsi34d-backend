<?php

namespace Tests\Support;

use App\Controllers\Admin\EventFilesController;
use App\Models\Event;
use App\Services\EventFileUpload;

/**
 * Controller real, trocando só a costura do upload (eventFileUpload()): o arquivo é copiado em vez de
 * exigir `is_uploaded_file`, que só passa em upload HTTP real. O caminho real é exercido na aceitação.
 */
class EventFilesControllerWithCopy extends EventFilesController
{
    protected function eventFileUpload(Event $event): EventFileUpload
    {
        return new class ($event) extends EventFileUpload {
            protected function moveFile(string $source, string $destination): bool
            {
                return copy($source, $destination);
            }
        };
    }
}
