<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminCsrfMetaTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_admin_layout_exposes_the_csrf_token_meta_for_ajax_uploads(): void
    {
        $html = $this->actingAs($this->createStaff('content-admin'))->get('/admin')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<meta name="csrf-token" content="[A-Za-z0-9]{40}">/', $html));
        $this->assertLessThan(strpos($html, '<title>'), strpos($html, '<meta name="csrf-token"'), 'meta harus berada di <head> sebelum <title>');
    }
}
