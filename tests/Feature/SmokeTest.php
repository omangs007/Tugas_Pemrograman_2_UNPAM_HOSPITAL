<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_indonesian_public_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('UNPAM HOSPITAL')
            ->assertSee('Situs ini masih dalam tahap pembangunan.');
    }

    public function test_home_page_has_exactly_one_h1(): void
    {
        $this->assertSame(1, substr_count($this->get('/')->getContent(), '<h1'));
    }

    public function test_welcome_skeleton_view_is_removed(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
