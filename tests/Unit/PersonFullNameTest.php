<?php

namespace Tests\Unit;

use App\Models\Person;
use PHPUnit\Framework\TestCase;

class PersonFullNameTest extends TestCase
{
    public function test_it_combines_first_and_last_name(): void
    {
        $person = new Person(['first_name' => 'Juan', 'last_name' => 'Perez']);

        $this->assertSame('Juan Perez', $person->full_name);
    }

    public function test_it_falls_back_to_first_name_only(): void
    {
        $person = new Person(['first_name' => 'Ñato', 'last_name' => '']);

        $this->assertSame('Ñato', $person->full_name);
    }

    public function test_it_returns_empty_string_when_both_names_are_blank(): void
    {
        $person = new Person(['first_name' => '', 'last_name' => '']);

        $this->assertSame('', $person->full_name);
    }
}
