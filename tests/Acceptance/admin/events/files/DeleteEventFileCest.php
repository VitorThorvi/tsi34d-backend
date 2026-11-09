<?php

namespace Tests\Acceptance\admin\events\files;

use App\Models\EventFile;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class DeleteEventFileCest extends BaseEventFilesCest
{
    public function deleteFileRemovesRowAndDiskFile(AcceptanceTester $page): void
    {
        $page->wantTo('Remoção de um anexo (linha e arquivo somem)');
        $eventId = $this->openEvent($page);
        $this->upload($page, 'anexo_valido.png');
        $this->upload($page, 'anexo_valido.jpg');
        $png = $this->storedFile($eventId, 'anexo_valido.png');
        $jpg = $this->storedFile($eventId, 'anexo_valido.jpg');
        Assert::assertFileExists($png->absolutePath());

        $page->click('Remover', "//div[contains(@class, 'card')][contains(normalize-space(.), 'anexo_valido.png')]");

        $page->seeCurrentUrlEquals("/admin/events/{$eventId}");
        $page->see('Anexo removido com sucesso!');
        $page->dontSee('anexo_valido.png', '.card');
        $page->see('anexo_valido.jpg', '.card');
        Assert::assertNull(EventFile::findById((int) $png->id));
        Assert::assertFileDoesNotExist($png->absolutePath());
        Assert::assertNotNull(EventFile::findById((int) $jpg->id));
        Assert::assertFileExists($jpg->absolutePath());
    }
}
