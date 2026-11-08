<?php

namespace Tests\Acceptance\admin\events\files;

use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ViewEventFilesCest extends BaseEventFilesCest
{
    public function emptyStateWithoutFiles(AcceptanceTester $page): void
    {
        $page->wantTo('Visualização dos anexos de um Evento sem anexos');

        $this->openEvent($page);

        $page->see('Nenhum anexo');
        $page->seeElement('input[type="file"][name="file"]');
        $page->dontSeeElement('.card img');
    }

    public function thumbnailsOfUploadedFiles(AcceptanceTester $page): void
    {
        $page->wantTo('Visualização das miniaturas dos anexos enviados');
        $eventId = $this->openEvent($page);
        $this->upload($page, 'anexo_valido.png');
        $this->upload($page, 'anexo_valido.jpg');

        $page->amOnPage("/admin/events/{$eventId}");

        $page->see('anexo_valido.png', '.card');
        $page->see('anexo_valido.jpg', '.card');
        $page->seeNumberOfElements('.card img', 2);
        $png = $this->storedFile($eventId, 'anexo_valido.png');
        $page->seeElement(".card img[src='{$png->path}']");
        // A imagem é servida de verdade pelo servidor (não é um ícone quebrado).
        $loaded = $page->executeJS(
            'return Array.from(document.querySelectorAll(".card img")).map(i => i.complete && i.naturalWidth > 0);'
        );
        Assert::assertSame([true, true], $loaded);
    }
}
