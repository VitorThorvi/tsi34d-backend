<?php

namespace App\Models;

use Core\Constants\Constants;
use Core\Database\ActiveRecord\BelongsTo;
use Core\Database\ActiveRecord\HasMany;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PDOException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string $kind
 * @property string $modality
 * @property string|null $audience valores separados por vírgula (tipo SET do MySQL); use audienceList()
 * @property string $starts_on
 * @property string $location
 * @property int|string $capacity
 * @property array<int, EventFile> $files Anexos do Evento (relação files())
 */
class Event extends Model
{
    /** Valores do ENUM `kind`, com o rótulo exibido nos formulários. */
    public const KINDS = [
        'lecture' => 'Palestra',
        'workshop' => 'Oficina',
        'short_course' => 'Minicurso',
        'academic_week' => 'Semana acadêmica',
    ];

    /** Valores do ENUM `modality`, com o rótulo exibido nos formulários. */
    public const MODALITIES = [
        'in_person' => 'Presencial',
        'online' => 'Online',
        'hybrid' => 'Híbrido',
    ];

    /** Valores do SET `audience`, com o rótulo exibido nos formulários. */
    public const AUDIENCES = [
        'students' => 'Estudantes',
        'professors' => 'Professores',
        'staff' => 'Servidores',
        'community' => 'Comunidade externa',
    ];

    protected static string $table = 'events';
    protected static array $columns = [
        'user_id',
        'title',
        'description',
        'kind',
        'modality',
        'audience',
        'starts_on',
        'location',
        'capacity',
    ];

    public function validates(): void
    {
        Validations::notEmpty('title', $this);
        Validations::notEmpty('description', $this);
        Validations::notEmpty('location', $this);

        Validations::notEmpty('kind', $this) &&
            Validations::inclusion('kind', $this, array_keys(self::KINDS));

        Validations::notEmpty('modality', $this) &&
            Validations::inclusion('modality', $this, array_keys(self::MODALITIES));

        Validations::notEmpty('audience', $this) &&
            Validations::inclusionOfAll('audience', $this, array_keys(self::AUDIENCES));

        Validations::notEmpty('starts_on', $this) &&
            Validations::validDate('starts_on', $this) &&
            $this->validatesStartsOnNotPast();

        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }
    }

    /**
     * A data não pode estar no passado ao criar. Na edição, só é cobrada quando `starts_on` muda:
     * assim um Evento que já aconteceu ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsOnNotPast(): void
    {
        if (!$this->newRecord()) {
            $original = self::findById($this->id);

            if ($original !== null && $original->starts_on === $this->starts_on) {
                return;
            }
        }

        Validations::notPastDate('starts_on', $this);
    }

    /**
     * P02: na edição, a capacidade não pode ficar abaixo do total de Inscrições.
     */
    private function validatesCapacityAgainstEnrollments(): void
    {
        if ($this->newRecord()) {
            return;
        }

        $enrolled = $this->enrollmentsCount();

        if ((int) $this->capacity < $enrolled) {
            $this->addError('capacity', "não pode ser menor que o total de inscrições ({$enrolled})!");
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Anexos (imagens) do Evento: relação 1xN com EventFile.
     */
    public function files(): HasMany
    {
        return $this->hasMany(EventFile::class, 'event_id');
    }

    /**
     * Pasta pública dos Anexos deste Evento, no disco.
     */
    private function uploadsFolder(): string
    {
        return (string) Constants::rootPath()->join("/public/assets/uploads/events/{$this->id}");
    }

    /**
     * Apaga do disco os arquivos dos Anexos e a pasta do Evento (a FK já removeu as linhas).
     */
    private function removeUploadsFromDisk(): void
    {
        $folder = $this->uploadsFolder();

        foreach (glob($folder . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($folder)) {
            rmdir($folder);
        }
    }

    /**
     * Total de Inscrições (linhas em event_user) deste Evento.
     */
    public function enrollmentsCount(): int
    {
        if ($this->newRecord()) {
            return 0;
        }

        return count(Enrollment::where(['event_id' => $this->id]));
    }

    /**
     * Vagas restantes (capacity - Inscrições), nunca negativo. Calculado, não armazenado.
     */
    public function availableSpots(): int
    {
        return max(0, (int) $this->capacity - $this->enrollmentsCount());
    }

    public function hasEnrollments(): bool
    {
        return $this->enrollmentsCount() > 0;
    }

    /**
     * Remove o Evento sem lançar exceção (P03): se houver Inscrições, não remove,
     * retorna false e registra o motivo em errors('base'). Ao remover, os Anexos somem
     * do banco (FK ON DELETE CASCADE) e seus arquivos e a pasta uploads/events/{id}/ somem do disco.
     */
    public function safeDestroy(): bool
    {
        $this->errors = [];

        if ($this->newRecord()) {
            $this->addError('base', 'o evento não existe e não pode ser removido!');
            return false;
        }

        $enrolled = $this->enrollmentsCount();
        if ($enrolled > 0) {
            $this->addError('base', "não é possível remover um evento com inscrições ({$enrolled})!");
            return false;
        }

        try {
            $destroyed = $this->destroy();
        } catch (PDOException $e) {
            // FK RESTRICT: alguém se inscreveu entre a contagem e o DELETE.
            $this->addError('base', 'não foi possível remover o evento, pois ele possui inscrições!');
            return false;
        }

        // Só depois do DELETE: se ele falhar, os arquivos dos Anexos continuam no disco.
        if ($destroyed) {
            $this->removeUploadsFromDisk();
        }

        return $destroyed;
    }

    /**
     * Público-alvo como array (o banco guarda "students,professors").
     *
     * @return array<int, string>
     */
    public function audienceList(): array
    {
        $audience = $this->audience;

        if ($audience === null || $audience === '') {
            return [];
        }

        return explode(',', $audience);
    }

    /**
     * Define o público-alvo a partir de um array (ex.: checkboxes `audience[]`).
     * Remove duplicados e valores vazios; valores fora de AUDIENCES são mantidos
     * para que a validação os rejeite.
     *
     * @param array<int, mixed> $values
     */
    public function setAudienceList(array $values): void
    {
        $values = array_values(array_unique(array_filter(
            array_map('strval', $values),
            fn(string $value) => $value !== ''
        )));

        $this->audience = implode(',', $values);
    }

    public function hasAudience(string $value): bool
    {
        return in_array($value, $this->audienceList(), true);
    }
}
