<?php

namespace Tests\Feature\Content;

use App\Models\Banner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * Model Banner (09 §6.4, F4A-R25). Review Focus F.1: batas waktu tayang banner.
 */
class BannerModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    private function freeze(): Carbon
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        return now();
    }

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame(
            ['title', 'subtitle', 'button_label', 'button_url', 'sort_order', 'is_active', 'starts_at', 'ends_at'],
            (new Banner)->getFillable(),
        );
    }

    public function test_casts_and_defaults(): void
    {
        $created = Banner::query()->create(['title' => 'Banner Minimal']);
        $banner = Banner::query()->findOrFail($created->id);

        $this->assertTrue($banner->is_active);
        $this->assertSame(0, $banner->sort_order);
        $this->assertNull($banner->starts_at);
        $this->assertNull($banner->ends_at);
        $this->assertNull($banner->button_label);
    }

    public function test_factory_states(): void
    {
        $now = $this->freeze();

        $plain = Banner::factory()->create();
        $expired = Banner::factory()->expired()->create();
        $scheduled = Banner::factory()->scheduled()->create();
        $inactive = Banner::factory()->inactive()->create();

        $this->assertTrue($plain->is_active);
        $this->assertNull($plain->starts_at);
        $this->assertNull($plain->ends_at);
        $this->assertTrue($expired->ends_at->lessThan($now));
        $this->assertTrue($expired->starts_at->lessThan($expired->ends_at));
        $this->assertTrue($scheduled->starts_at->greaterThan($now));
        $this->assertTrue($scheduled->starts_at->lessThan($scheduled->ends_at));
        $this->assertFalse($inactive->is_active);
    }

    public function test_currently_active_honours_the_period_boundaries(): void
    {
        $now = $this->freeze();

        $this->createBanner(['title' => 'Tanpa periode']);
        $this->createBanner(['title' => 'Mulai tepat sekarang', 'starts_at' => $now]);
        $this->createBanner(['title' => 'Selesai tepat sekarang', 'ends_at' => $now]);
        $this->createBanner(['title' => 'Dalam jendela', 'starts_at' => $now->copy()->subDay(), 'ends_at' => $now->copy()->addDay()]);
        $this->createBanner(['title' => 'Berakhir semenit lalu', 'ends_at' => $now->copy()->subMinute()]);
        $this->createBanner(['title' => 'Mulai semenit lagi', 'starts_at' => $now->copy()->addMinute()]);
        $this->createBanner(['title' => 'Mulai sedetik lagi', 'starts_at' => $now->copy()->addSecond()]);
        $this->createBanner(['title' => 'Nonaktif dalam jendela', 'is_active' => false, 'starts_at' => $now->copy()->subDay(), 'ends_at' => $now->copy()->addDay()]);
        $this->createBanner(['title' => 'Nonaktif tanpa periode', 'is_active' => false]);

        $this->assertEqualsCanonicalizing(
            ['Tanpa periode', 'Mulai tepat sekarang', 'Selesai tepat sekarang', 'Dalam jendela'],
            Banner::query()->currentlyActive()->pluck('title')->all(),
        );
    }

    public function test_a_banner_ends_after_the_end_second_has_passed(): void
    {
        $now = $this->freeze();
        $this->createBanner(['title' => 'Promo', 'ends_at' => $now->copy()->addMinute()]);

        $this->travelTo($now->copy()->addMinute());
        $this->assertSame(1, Banner::query()->currentlyActive()->count());

        $this->travelTo($now->copy()->addMinute()->addSecond());
        $this->assertSame(0, Banner::query()->currentlyActive()->count());
    }

    public function test_currently_active_and_ordered_sort_by_sort_order_then_id(): void
    {
        $this->freeze();

        $third = $this->createBanner(['title' => 'Ketiga', 'sort_order' => 5]);
        $firstA = $this->createBanner(['title' => 'Pertama A', 'sort_order' => 1]);
        $firstB = $this->createBanner(['title' => 'Pertama B', 'sort_order' => 1]);
        $second = $this->createBanner(['title' => 'Kedua', 'sort_order' => 2]);

        $expected = [$firstA->id, $firstB->id, $second->id, $third->id];

        $this->assertSame($expected, Banner::query()->currentlyActive()->pluck('id')->all());
        $this->assertSame($expected, Banner::query()->ordered()->pluck('id')->all());
    }

    public function test_schedule_label_has_four_values(): void
    {
        $now = $this->freeze();

        $live = $this->createBanner();
        $liveWindow = $this->createBanner(['starts_at' => $now->copy()->subDay(), 'ends_at' => $now->copy()->addDay()]);
        $scheduled = $this->createBanner(['starts_at' => $now->copy()->addHour()]);
        $ended = $this->createBanner(['ends_at' => $now->copy()->subHour()]);
        $inactive = $this->createBanner(['is_active' => false]);

        $this->assertSame('Tayang', $live->scheduleLabel());
        $this->assertSame('Tayang', $liveWindow->scheduleLabel());
        $this->assertSame('Terjadwal', $scheduled->scheduleLabel());
        $this->assertSame('Berakhir', $ended->scheduleLabel());
        $this->assertSame('Nonaktif', $inactive->scheduleLabel());
    }

    public function test_schedule_label_boundaries_match_the_currently_active_scope(): void
    {
        $now = $this->freeze();

        $startsNow = $this->createBanner(['starts_at' => $now]);
        $endsNow = $this->createBanner(['ends_at' => $now]);
        $startsLater = $this->createBanner(['starts_at' => $now->copy()->addSecond()]);
        $endedBefore = $this->createBanner(['ends_at' => $now->copy()->subSecond()]);
        $inactiveScheduled = $this->createBanner(['is_active' => false, 'starts_at' => $now->copy()->addDay()]);
        $inactiveExpired = $this->createBanner(['is_active' => false, 'ends_at' => $now->copy()->subDay()]);

        $this->assertSame('Tayang', $startsNow->scheduleLabel());
        $this->assertSame('Tayang', $endsNow->scheduleLabel());
        $this->assertSame('Terjadwal', $startsLater->scheduleLabel());
        $this->assertSame('Berakhir', $endedBefore->scheduleLabel());
        $this->assertSame('Nonaktif', $inactiveScheduled->scheduleLabel());
        $this->assertSame('Nonaktif', $inactiveExpired->scheduleLabel());

        $active = Banner::query()->currentlyActive()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$startsNow->id, $endsNow->id], $active);
    }

    public function test_image_url_is_the_placeholder_without_media(): void
    {
        $banner = Banner::query()->with('media')->findOrFail($this->createBanner()->id);

        $this->assertSame(asset('images/placeholders/banner.svg'), $banner->image_url);
    }

    public function test_the_list_with_media_does_not_lazy_load(): void
    {
        foreach (range(1, 3) as $i) {
            $this->createBanner(['title' => "Banner {$i}"]);
        }

        foreach (Banner::query()->with('media')->ordered()->get() as $banner) {
            $this->assertSame(asset('images/placeholders/banner.svg'), $banner->image_url);
        }
    }
}
