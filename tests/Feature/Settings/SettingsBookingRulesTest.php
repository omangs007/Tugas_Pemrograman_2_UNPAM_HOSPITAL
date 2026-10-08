<?php

namespace Tests\Feature\Settings;

use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\WebsiteSetting;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingRules;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class SettingsBookingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rules_is_a_final_readonly_snapshot_with_the_assert_bookable_rule_method(): void
    {
        $class = new ReflectionClass(BookingRules::class);

        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->isReadOnly());
        $this->assertTrue($class->hasMethod('assertBookable'), 'assertBookable() ditambahkan fase 6 (F6-R11).');
        $this->assertSame(
            ['windowDays', 'onlineCutoffMinutes', 'cancelCutoffMinutes', 'checkinOpenMinutes', 'maxActivePerPatient', 'autoConfirm'],
            array_map(fn ($parameter) => $parameter->getName(), $class->getConstructor()->getParameters()),
        );
    }

    public function test_assert_bookable_has_the_f6_r11_signature(): void
    {
        $method = new ReflectionMethod(BookingRules::class, 'assertBookable');

        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
        $this->assertSame(['profile', 'session', 'data', 'actor', 'now'], array_map(fn ($parameter) => $parameter->getName(), $method->getParameters()));
        $this->assertSame(
            [PatientProfile::class, PracticeSession::class, BookingData::class, Actor::class, CarbonImmutable::class],
            array_map(fn ($parameter) => (string) $parameter->getType(), $method->getParameters()),
        );
        $this->assertSame('void', (string) $method->getReturnType());
    }

    public function test_booking_rules_reads_the_seeded_defaults(): void
    {
        $this->seed(SettingsSeeder::class);

        $rules = $this->app->make(SettingsService::class)->bookingRules();

        $this->assertInstanceOf(BookingRules::class, $rules);
        $this->assertSame(30, $rules->windowDays);
        $this->assertSame(60, $rules->onlineCutoffMinutes);
        $this->assertSame(120, $rules->cancelCutoffMinutes);
        $this->assertSame(60, $rules->checkinOpenMinutes);
        $this->assertSame(3, $rules->maxActivePerPatient);
        $this->assertFalse($rules->autoConfirm);
    }

    public function test_booking_rules_falls_back_to_the_documented_defaults_when_the_table_is_empty(): void
    {
        $rules = $this->app->make(SettingsService::class)->bookingRules();

        $this->assertEquals(new BookingRules(30, 60, 120, 60, 3, false), $rules);
    }

    public function test_booking_rules_follows_changed_settings_after_the_cache_is_cleared(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = $this->app->make(SettingsService::class);

        $this->assertSame(30, $settings->bookingRules()->windowDays);

        WebsiteSetting::query()->where('key', 'booking.window_days')->update(['value' => '14']);
        WebsiteSetting::query()->where('key', 'booking.online_cutoff_minutes')->update(['value' => '90']);
        WebsiteSetting::query()->where('key', 'booking.auto_confirm')->update(['value' => '1']);
        $settings->forget();

        $rules = $settings->bookingRules();

        $this->assertSame(14, $rules->windowDays);
        $this->assertSame(90, $rules->onlineCutoffMinutes);
        $this->assertTrue($rules->autoConfirm);
    }
}
