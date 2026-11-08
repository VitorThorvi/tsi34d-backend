<?php

namespace Tests\Unit\Lib\Validations;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PHPUnit\Framework\TestCase;

class FileValidationsTest extends TestCase
{
    private Model $model;

    public function setUp(): void
    {
        $this->model = new class () extends Model {
            protected static array $columns = ['title'];
        };
    }

    public function test_file_max_size_should_accept_a_file_up_to_the_limit(): void
    {
        $this->assertTrue(Validations::fileMaxSize('file', $this->model, ['size' => 2048], 2048));
        $this->assertTrue(Validations::fileMaxSize('file', $this->model, ['size' => 0], 2048));
        $this->assertNull($this->model->errors('file'));
    }

    public function test_file_max_size_should_reject_a_file_above_the_limit(): void
    {
        $this->assertFalse(Validations::fileMaxSize('file', $this->model, ['size' => 2049], 2048));
        $this->assertEquals('deve ter no máximo 2 KB!', $this->model->errors('file'));
    }

    public function test_file_max_size_should_format_the_limit_in_megabytes(): void
    {
        $this->assertFalse(Validations::fileMaxSize('file', $this->model, ['size' => 3 * 1048576], 2 * 1048576));
        $this->assertEquals('deve ter no máximo 2 MB!', $this->model->errors('file'));
    }

    public function test_file_max_size_should_reject_a_file_without_a_numeric_size(): void
    {
        $this->assertFalse(Validations::fileMaxSize('file', $this->model, [], 2048));
        $this->assertFalse(Validations::fileMaxSize('file', $this->model, ['size' => 'abc'], 2048));
        $this->assertFalse(Validations::fileMaxSize('file', $this->model, ['size' => -1], 2048));
    }

    public function test_file_extension_should_accept_an_allowed_extension_ignoring_case(): void
    {
        $this->assertTrue(Validations::fileExtension('file', $this->model, ['name' => 'cartaz.png'], ['png', 'jpg']));
        $this->assertTrue(Validations::fileExtension('file', $this->model, ['name' => 'FOTO.JPG'], ['png', 'jpg']));
        $this->assertNull($this->model->errors('file'));
    }

    public function test_file_extension_should_reject_an_extension_outside_the_list(): void
    {
        $this->assertFalse(Validations::fileExtension('file', $this->model, ['name' => 'shell.php'], ['png', 'jpg']));
        $this->assertEquals('deve ter a extensão png ou jpg!', $this->model->errors('file'));
    }

    public function test_file_extension_should_use_only_the_last_extension(): void
    {
        $this->assertFalse(Validations::fileExtension('file', $this->model, ['name' => 'foto.png.php'], ['png']));
        $this->assertTrue(Validations::fileExtension('file', $this->model, ['name' => 'foto.php.png'], ['png']));
    }

    public function test_file_extension_should_reject_a_name_without_extension_or_missing(): void
    {
        $this->assertFalse(Validations::fileExtension('file', $this->model, ['name' => 'png'], ['png']));
        $this->assertFalse(Validations::fileExtension('file', $this->model, ['name' => 'foto.'], ['png']));
        $this->assertFalse(Validations::fileExtension('file', $this->model, [], ['png']));
        $this->assertFalse(Validations::fileExtension('file', $this->model, ['name' => ['a.png']], ['png']));
    }
}
