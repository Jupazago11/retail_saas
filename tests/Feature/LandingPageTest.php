<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    // "/" ya no sirve una landing propia (ver routes/web.php:
    // Route::redirect('/', '/login')) — cualquier visitante cae directo al
    // login, sin pagina de mercadeo intermedia.
    public function test_the_root_url_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
