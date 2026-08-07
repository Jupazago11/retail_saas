<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingEnvironmentIsolationTest extends TestCase
{
    public function test_automated_tests_use_an_isolated_in_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.connections.sqlite.driver'));
    }
}
