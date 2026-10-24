<?php

namespace App\Models;

use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\Model;

/**
 * Inscrição de um Participante em um Evento (tabela pivô event_user).
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 */
class Enrollment extends Model
{
    protected static string $table = 'event_user';
    protected static array $columns = ['event_id', 'user_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
