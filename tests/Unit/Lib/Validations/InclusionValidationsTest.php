<?php

namespace Tests\Unit\Lib\Validations;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PHPUnit\Framework\TestCase;

class InclusionValidationsTest extends TestCase
{
    private Model $model;

    public function setUp(): void
    {
        $this->model = new class () extends Model {
            protected static array $columns = ['kind', 'audience'];
        };
    }

    public function test_inclusion_should_accept_a_value_in_the_list(): void
    {
        $this->model->kind = 'lecture'; // @phpstan-ignore-line

        $this->assertTrue(Validations::inclusion('kind', $this->model, ['lecture', 'workshop']));
        $this->assertNull($this->model->errors('kind'));
    }

    public function test_inclusion_should_reject_a_value_outside_the_list(): void
    {
        $this->model->kind = 'concert'; // @phpstan-ignore-line

        $this->assertFalse(Validations::inclusion('kind', $this->model, ['lecture', 'workshop']));
        $this->assertEquals('não é uma opção válida!', $this->model->errors('kind'));
    }

    public function test_inclusion_should_reject_null_empty_and_array_values(): void
    {
        $this->assertFalse(Validations::inclusion('kind', $this->model, ['lecture']));

        $this->model->kind = ''; // @phpstan-ignore-line
        $this->assertFalse(Validations::inclusion('kind', $this->model, ['lecture']));

        $this->model->kind = ['lecture']; // @phpstan-ignore-line
        $this->assertFalse(Validations::inclusion('kind', $this->model, ['lecture']));
    }

    public function test_inclusion_should_be_strict_about_types(): void
    {
        $this->model->kind = '1'; // @phpstan-ignore-line

        $this->assertFalse(Validations::inclusion('kind', $this->model, [1, 2]));
        $this->assertTrue(Validations::inclusion('kind', $this->model, ['1', '2']));
    }

    public function test_inclusion_of_all_should_accept_an_array_in_the_list(): void
    {
        $this->model->audience = ['students', 'staff']; // @phpstan-ignore-line

        $this->assertTrue(Validations::inclusionOfAll('audience', $this->model, ['students', 'staff', 'community']));
    }

    public function test_inclusion_of_all_should_accept_a_comma_separated_string(): void
    {
        $this->model->audience = 'students,community'; // @phpstan-ignore-line

        $this->assertTrue(Validations::inclusionOfAll('audience', $this->model, ['students', 'staff', 'community']));
    }

    public function test_inclusion_of_all_should_accept_a_custom_separator(): void
    {
        $this->model->audience = 'students|staff'; // @phpstan-ignore-line

        $this->assertTrue(Validations::inclusionOfAll('audience', $this->model, ['students', 'staff'], '|'));
        $this->assertFalse(Validations::inclusionOfAll('audience', $this->model, ['students', 'staff']));
    }

    public function test_inclusion_of_all_should_reject_when_any_value_is_outside_the_list(): void
    {
        $this->model->audience = ['students', 'aliens']; // @phpstan-ignore-line

        $this->assertFalse(Validations::inclusionOfAll('audience', $this->model, ['students', 'staff']));
        $this->assertEquals('contém uma opção inválida!', $this->model->errors('audience'));
    }

    public function test_inclusion_of_all_should_require_at_least_one_value(): void
    {
        foreach ([null, '', []] as $empty) {
            $this->model->audience = $empty; // @phpstan-ignore-line

            $this->assertFalse(Validations::inclusionOfAll('audience', $this->model, ['students']));
            $this->assertEquals('selecione ao menos uma opção!', $this->model->errors('audience'));
        }
    }
}
