<?php

namespace Tests\Acceptance\admin\events\files;

use App\Models\EventFile;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class UploadInvalidEventFileCest extends BaseEventFilesCest
{
    public function uploadScriptRenamedToPng(AcceptanceTester $page): void
    {
        $page->wantTo('Envio de um script PHP com extensão .png (recusado, nada gravado)');
        $eventId = $this->openEvent($page);

        $this->upload($page, 'falso.png');

        $page->seeCurrentUrlEquals("/admin/events/{$eventId}");
        $page->see('Anexo não enviado: não é uma imagem JPEG ou PNG válida!');
        $page->dontSee('Anexo enviado com sucesso!');
        $page->see('Nenhum anexo');
        Assert::assertCount(0, EventFile::all());
        Assert::assertDirectoryDoesNotExist($this->eventFolder($eventId));
    }

    public function submitWithoutChoosingFile(AcceptanceTester $page): void
    {
        $page->wantTo('Envio do formulário sem escolher arquivo');
        $eventId = $this->openEvent($page);

        $page->click('Enviar');

        $page->seeCurrentUrlEquals("/admin/events/{$eventId}");
        $page->see('Anexo não enviado: selecione um arquivo!');
        Assert::assertCount(0, EventFile::all());
        Assert::assertDirectoryDoesNotExist($this->eventFolder($eventId));
    }
}
