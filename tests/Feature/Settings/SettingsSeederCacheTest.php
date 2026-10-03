<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_after_a_warm_cache_makes_the_defaults_visible(): void
    {
        $settings = app(SettingsService::class);

        $this->assertNull($settings->get('site.name'));

        $this->seed(SettingsSeeder::class);

        $this->assertSame('UNPAM HOSPITAL', $settings->get('site.name'));
        $this->assertSame(30, $settings->int('booking.window_days'));
    }
}
