<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResourceVerbsTest extends TestCase
{
    public function test_resource_verbs_are_localised_to_indonesian(): void
    {
        $this->assertSame(['create' => 'buat', 'edit' => 'ubah'], Route::resourceVerbs());
    }

    public function test_a_resource_route_uses_buat_and_ubah_uris(): void
    {
        Route::resource('uji-sumber-daya', Controller::class);

        $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->all();

        $this->assertContains('uji-sumber-daya/buat', $uris);
        $this->assertContains('uji-sumber-daya/{uji_sumber_daya}/ubah', $uris);
        $this->assertNotContains('uji-sumber-daya/create', $uris);
    }
}
