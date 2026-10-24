<?php

namespace Lib;

use Core\Database\Database;

class Validations
{
    public static function notEmpty($attribute, $obj)
    {
        if ($obj->$attribute === null || $obj->$attribute === '') {
            $obj->addError($attribute, 'não pode ser vazio!');
            return false;
        }

        return true;
    }

    public static function passwordConfirmation($obj)
    {
        if ($obj->password !== $obj->password_confirmation) {
            $obj->addError('password', 'as senhas devem ser idênticas!');
            return false;
        }

        return true;
    }

    /**
     * O valor do atributo deve estar na lista de valores permitidos.
     *
     * @param array<int, mixed> $allowed
     */
    public static function inclusion($attribute, $obj, array $allowed)
    {
        $value = $obj->$attribute;

        if (is_array($value) || !in_array($value, $allowed, true)) {
            $obj->addError($attribute, 'não é uma opção válida!');
            return false;
        }

        return true;
    }

    /**
     * Seleção múltipla: ao menos um valor e todos dentro da lista permitida.
     * Aceita um array ou uma string separada por $separator (formato do tipo SET do MySQL).
     *
     * @param array<int, mixed> $allowed
     */
    public static function inclusionOfAll($attribute, $obj, array $allowed, string $separator = ',')
    {
        $value = $obj->$attribute;

        if (is_string($value)) {
            $value = $value === '' ? [] : explode($separator, $value);
        }

        if (!is_array($value) || count($value) === 0) {
            $obj->addError($attribute, 'selecione ao menos uma opção!');
            return false;
        }

        foreach ($value as $item) {
            if (!in_array($item, $allowed, true)) {
                $obj->addError($attribute, 'contém uma opção inválida!');
                return false;
            }
        }

        return true;
    }

    /**
     * O valor deve ser um número inteiro maior ou igual a $min (aceita string numérica, como vem de um form).
     */
    public static function integerMin($attribute, $obj, int $min)
    {
        $value = $obj->$attribute;
        $number = is_int($value) || is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if ($number === false) {
            $obj->addError($attribute, 'deve ser um número inteiro!');
            return false;
        }

        if ($number < $min) {
            $obj->addError($attribute, "deve ser maior ou igual a {$min}!");
            return false;
        }

        return true;
    }

    /**
     * A data deve existir no calendário e estar no formato $format (padrão Y-m-d).
     */
    public static function validDate($attribute, $obj, string $format = 'Y-m-d')
    {
        if (self::parseDate($obj->$attribute, $format) === null) {
            $obj->addError($attribute, 'não é uma data válida!');
            return false;
        }

        return true;
    }

    /**
     * A data não pode ser anterior a hoje (hoje é permitido). Data inválida também é rejeitada.
     */
    public static function notPastDate($attribute, $obj, string $format = 'Y-m-d')
    {
        $date = self::parseDate($obj->$attribute, $format);

        if ($date === null) {
            $obj->addError($attribute, 'não é uma data válida!');
            return false;
        }

        if ($date < new \DateTimeImmutable('today')) {
            $obj->addError($attribute, 'não pode estar no passado!');
            return false;
        }

        return true;
    }

    private static function parseDate(mixed $value, string $format): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

        if ($date === false || $date->format($format) !== $value) {
            return null;
        }

        return $date;
    }

    public static function uniqueness($fields, $object)
    {
        $dbFieldsValues = [];
        $objFieldValues = [];

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if (!$object->newRecord()) {
            $dbObject = $object::findById($object->id);

            foreach ($fields as $field) {
                $dbFieldsValues[] = $dbObject->$field;
                $objFieldValues[] = $object->$field;
            }

            if (
                !empty($dbFieldsValues) &&
                !empty($objFieldValues) &&
                $dbFieldsValues === $objFieldValues
            ) {
                return true;
            }
        }

        $table = $object::table();
        $conditions = implode(' AND ', array_map(fn($field) => "{$field} = :{$field}", $fields));

        $sql = <<<SQL
            SELECT id FROM {$table} WHERE {$conditions};
        SQL;

        $pdo = Database::getDatabaseConn();
        $stmt = $pdo->prepare($sql);

        foreach ($fields as $field) {
            $stmt->bindValue($field, $object->$field);
        }

        $stmt->execute();

        if ($stmt->rowCount() !== 0) {
            foreach ($fields as $field) {
                $object->addError($field, 'já existe um registro com esse dado');
            }
            return false;
        }

        return true;
    }
}
