<?php

namespace App\Models;

use Core\Constants\Constants;
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

/**
 * Anexo: imagem (JPEG ou PNG) que pertence a um Evento (relação 1xN).
 *
 * @property int $id
 * @property int $event_id
 * @property string $name nome original enviado
 * @property string $path caminho público, ex.: /assets/uploads/events/1/abc123.png
 * @property string $mime_type image/jpeg ou image/png
 * @property Event $event Evento dono do Anexo (relação event())
 */
class EventFile extends Model
{
    /** MIME types aceitos, com a extensão usada no nome gravado. */
    public const MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    protected static string $table = 'event_files';
    protected static array $columns = ['event_id', 'name', 'path', 'mime_type'];

    public function validates(): void
    {
        Validations::notEmpty('event_id', $this);
        Validations::notEmpty('name', $this);
        Validations::notEmpty('path', $this);

        Validations::notEmpty('mime_type', $this) &&
            Validations::inclusion('mime_type', $this, array_keys(self::MIME_TYPES));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Caminho do arquivo no disco (a pasta `public` é a raiz do caminho público).
     */
    public function absolutePath(): string
    {
        return (string) Constants::rootPath()->join('/public')->join($this->path);
    }

    /**
     * Remove a linha e o arquivo do disco. Se o arquivo já não existir, não lança exceção.
     */
    public function destroy(): bool
    {
        $removed = parent::destroy();

        if (is_file($this->absolutePath())) {
            unlink($this->absolutePath());
        }

        return $removed;
    }
}
