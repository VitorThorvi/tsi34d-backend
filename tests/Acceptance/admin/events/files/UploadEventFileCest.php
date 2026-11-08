<?php

namespace Tests\Acceptance\admin\events\files;

use App\Models\EventFile;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class UploadEventFileCest extends BaseEventFilesCest
{
    public function uploadValidImage(AcceptanceTester $page): void
    {
        $page->wantTo('Envio de um anexo válido (PNG)');
        $eventId = $this->openEvent($page);
        $page->see('Nenhum anexo');

        $this->upload($page, 'anexo_valido.png');

        $page->seeCurrentUrlEquals("/admin/events/{$eventId}");
        $page->see('Anexo enviado com sucesso!');
        $page->dontSee('Nenhum anexo');
        $page->see('anexo_valido.png');

        $file = $this->storedFile($eventId, 'anexo_valido.png');
        Assert::assertSame('image/png', $file->mime_type);
        Assert::assertStringStartsWith("/assets/uploads/events/{$eventId}/", $file->path);
        Assert::assertStringEndsWith('.png', $file->path);
        Assert::assertNotSame('anexo_valido.png', basename($file->path));
        Assert::assertFileExists($file->absolutePath());
        Assert::assertSame(
            [basename($file->path)],
            array_map('basename', glob($this->eventFolder($eventId) . '/*') ?: [])
        );
        Assert::assertFileEquals(codecept_data_dir('anexo_valido.png'), $file->absolutePath());
    }
}
