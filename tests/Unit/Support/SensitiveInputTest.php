<?php

namespace Tests\Unit\Support;

use App\Support\SensitiveInput;
use PHPUnit\Framework\TestCase;

/**
 * F6-R36: satu sumber daftar input sensitif untuk `dontFlash`, redirect 419/429, handler booking, dan
 * controller wizard (SEK-11, Review Focus 5).
 */
class SensitiveInputTest extends TestCase
{
    public function test_fields_are_the_six_documented_sensitive_inputs(): void
    {
        $this->assertSame(
            ['password', 'password_confirmation', 'current_password', 'bpjs_number', 'policy_number', 'payment_reference'],
            SensitiveInput::FIELDS,
        );
    }

    public function test_bootstrap_and_the_register_controller_use_the_single_source(): void
    {
        $root = dirname(__DIR__, 3);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
        $register = (string) file_get_contents($root.'/app/Http/Controllers/Auth/RegisterController.php');

        $this->assertStringContainsString('$sensitive = SensitiveInput::FIELDS;', $bootstrap);
        $this->assertStringNotContainsString("'payment_reference'", $bootstrap);
        $this->assertStringContainsString('SensitiveInput::FIELDS', $register);
        $this->assertStringNotContainsString('SENSITIVE_INPUT', $register);
        $this->assertStringNotContainsString("'payment_reference'", $register);
    }
}
