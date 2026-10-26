<?php

namespace Tests\Unit\Lib\Validations;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PHPUnit\Framework\TestCase;

class NumberAndDateValidationsTest extends TestCase
{
    private Model $model;

    public function setUp(): void
    {
        $this->model = new class () extends Model {
            protected static array $columns = ['quantity', 'day'];
        };
    }

    public function test_integer_min_should_accept_integers_and_numeric_strings_from_the_minimum(): void
    {
        foreach ([1, 2, 100, '1', '25'] as $value) {
            $this->model->quantity = $value; // @phpstan-ignore-line

            $this->assertTrue(Validations::integerMin('quantity', $this->model, 1), (string) $value);
        }
        $this->assertFalse($this->model->hasErrors());
    }

    public function test_integer_min_should_reject_values_below_the_minimum(): void
    {
        foreach ([0, -1, '0', '-3'] as $value) {
            $this->model->quantity = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::integerMin('quantity', $this->model, 1), (string) $value);
            $this->assertEquals('deve ser maior ou igual a 1!', $this->model->errors('quantity'));
        }
    }

    public function test_integer_min_should_use_the_given_minimum(): void
    {
        $this->model->quantity = 4; // @phpstan-ignore-line

        $this->assertFalse(Validations::integerMin('quantity', $this->model, 5));
        $this->assertEquals('deve ser maior ou igual a 5!', $this->model->errors('quantity'));
        $this->assertTrue(Validations::integerMin('quantity', $this->model, 4));
        $this->assertTrue(Validations::integerMin('quantity', $this->model, -10));
    }

    public function test_integer_min_should_reject_non_integers(): void
    {
        foreach ([null, '', 'abc', '2.5', 2.5, '1e3', [1], true] as $value) {
            $this->model->quantity = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::integerMin('quantity', $this->model, 1), var_export($value, true));
            $this->assertEquals('deve ser um número inteiro!', $this->model->errors('quantity'));
        }
    }

    public function test_valid_date_should_accept_real_dates(): void
    {
        foreach (['2030-12-31', '2028-02-29', '1999-01-01'] as $value) {
            $this->model->day = $value; // @phpstan-ignore-line

            $this->assertTrue(Validations::validDate('day', $this->model), $value);
        }
    }

    public function test_valid_date_should_reject_invalid_dates(): void
    {
        $values = ['2030-02-30', '2029-02-29', '2030-13-01', '2030-00-10', '31/12/2030', '2030-1-5', 'hoje', '', null, 20301231];

        foreach ($values as $value) {
            $this->model->day = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::validDate('day', $this->model), var_export($value, true));
            $this->assertEquals('não é uma data válida!', $this->model->errors('day'));
        }
    }

    public function test_valid_date_should_accept_a_custom_format(): void
    {
        $this->model->day = '31/12/2030'; // @phpstan-ignore-line

        $this->assertTrue(Validations::validDate('day', $this->model, 'd/m/Y'));
        $this->assertFalse(Validations::validDate('day', $this->model));
    }

    public function test_not_past_date_should_accept_today_and_future_dates(): void
    {
        foreach (['today', '+1 day', '+1 year'] as $offset) {
            $this->model->day = date('Y-m-d', strtotime($offset)); // @phpstan-ignore-line

            $this->assertTrue(Validations::notPastDate('day', $this->model), $offset);
        }
    }

    public function test_not_past_date_should_reject_past_dates(): void
    {
        foreach (['-1 day', '-1 year'] as $offset) {
            $this->model->day = date('Y-m-d', strtotime($offset)); // @phpstan-ignore-line

            $this->assertFalse(Validations::notPastDate('day', $this->model), $offset);
            $this->assertEquals('não pode estar no passado!', $this->model->errors('day'));
        }
    }

    public function test_not_past_date_should_reject_invalid_dates(): void
    {
        foreach (['2030-02-30', 'ontem', '', null] as $value) {
            $this->model->day = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::notPastDate('day', $this->model), var_export($value, true));
            $this->assertEquals('não é uma data válida!', $this->model->errors('day'));
        }
    }

    public function test_not_past_date_should_accept_a_custom_format(): void
    {
        $this->model->day = date('d/m/Y', strtotime('+2 days')); // @phpstan-ignore-line

        $this->assertTrue(Validations::notPastDate('day', $this->model, 'd/m/Y'));

        $this->model->day = date('d/m/Y', strtotime('-2 days')); // @phpstan-ignore-line
        $this->assertFalse(Validations::notPastDate('day', $this->model, 'd/m/Y'));
    }

    /* ------------------- fuso America/Sao_Paulo ------------------- */

    public function test_php_timezone_should_be_sao_paulo(): void
    {
        $this->assertSame('America/Sao_Paulo', ini_get('date.timezone'));
        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    public function test_not_past_date_should_accept_today_in_brasilia_when_utc_is_already_tomorrow(): void
    {
        // 22:30 de 23/10 em Brasília (UTC-3) já é 01:30 de 24/10 em UTC.
        $now = new \DateTimeImmutable('2026-10-24 01:30:00', new \DateTimeZone('UTC'));
        $original = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');

        try {
            $this->model->day = '2026-10-23'; // @phpstan-ignore-line
            $this->assertTrue(Validations::notPastDate('day', $this->model, 'Y-m-d', $now));
            $this->assertFalse($this->model->hasErrors());

            $this->model->day = '2026-10-22'; // @phpstan-ignore-line
            $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now));
            $this->assertEquals('não pode estar no passado!', $this->model->errors('day'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function test_not_past_date_in_utc_would_reject_today_in_brasilia(): void
    {
        // Mesmo instante do teste anterior, com o fuso padrão em UTC: é o defeito que o fuso fixo evita.
        $now = new \DateTimeImmutable('2026-10-24 01:30:00', new \DateTimeZone('UTC'));
        $original = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $this->model->day = '2026-10-23'; // @phpstan-ignore-line
            $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now));
        } finally {
            date_default_timezone_set($original);
        }
    }
}
