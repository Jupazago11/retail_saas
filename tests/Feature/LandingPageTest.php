<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_the_landing_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Retail SaaS');
        $response->assertSee('Multiempresa');
    }
}
