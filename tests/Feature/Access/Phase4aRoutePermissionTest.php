<?php

namespace Tests\Feature\Access;

use App\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 4a), KNS-02 (route admin berizin), KNS-03 (`content.manage`), dan bagian route dari CRD-13:
 * 40 nama route konten, izin, nama parameter, URI Indonesia, 11 route publik terbuka, serta route yang sengaja tidak ada.
 */
class Phase4aRoutePermissionTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const MANAGE = 'permission:content.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $names = ['admin.pages.index', 'admin.pages.edit', 'admin.pages.update', 'admin.galleries.photos.store'];

        foreach (['banners', 'articles', 'article-categories', 'galleries', 'faqs', 'insurance-partners'] as $resource) {
            foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $names[] = "admin.{$resource}.{$action}";
            }
        }

        $cases = [];

        foreach ($names as $name) {
            $cases[$name] = [$name, self::MANAGE];
        }

        return $cases;
    }

    #[DataProvider('permissions')]
    public function test_route_exists_with_the_content_manage_permission_middleware(string $name, string $permission): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");

        $middleware = array_filter($route->gatherMiddleware(), 'is_string');
        $this->assertContains($permission, $middleware, "Route {$name} harus memakai {$permission}.");
        $this->assertContains('auth', $middleware, "Route {$name} harus berada di grup admin yang memakai auth.");
        $this->assertCount(1, array_filter($middleware, fn (string $item): bool => str_starts_with($item, 'permission:')), "Route {$name} hanya boleh memakai satu middleware izin.");
    }

    public function test_the_phase_4a_admin_route_names_are_exactly_the_forty_documented_ones(): void
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (preg_match('/^admin\.(pages|banners|articles|article-categories|galleries|faqs|insurance-partners)\./', $name) === 1) {
                $names[] = $name;
            }
        }

        sort($names);

        $this->assertSame(40, count($names));
        $this->assertSame(array_values(array_unique($names)), $names, 'Nama route tidak boleh ganda.');
        $this->assertEqualsCanonicalizing(array_keys(self::permissions()), $names);
    }

    public function test_the_admin_area_has_ninety_routes_after_phase_4a(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // 31 fase 1-2 + 19 fase 3 + 40 fase 4a (F4A-R28); fase 4b dan seterusnya wajib memperbarui angka ini.
        $this->assertSame(90, $count);
    }

    public function test_route_parameters_are_named_as_documented(): void
    {
        $expected = [
            'admin.pages.edit' => ['page'],
            'admin.banners.edit' => ['banner'],
            'admin.articles.edit' => ['article'],
            'admin.article-categories.edit' => ['articleCategory'],
            'admin.galleries.edit' => ['gallery'],
            'admin.galleries.photos.store' => ['gallery'],
            'admin.faqs.edit' => ['faq'],
            'admin.insurance-partners.edit' => ['insurancePartner'],
        ];

        foreach ($expected as $name => $parameters) {
            $this->assertSame($parameters, Route::getRoutes()->getByName($name)->parameterNames(), $name);
        }
    }

    public function test_pages_have_no_create_store_destroy_or_show_routes(): void
    {
        foreach (['admin.pages.create', 'admin.pages.store', 'admin.pages.destroy', 'admin.pages.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (halaman sistem, 00 C).");
        }

        foreach (['banners', 'articles', 'article-categories', 'galleries', 'faqs', 'insurance-partners'] as $resource) {
            $this->assertNull(Route::getRoutes()->getByName("admin.{$resource}.show"), "admin.{$resource}.show tidak boleh ada (00 I.6).");
        }

        $this->delete('/admin/konten/halaman/1')->assertStatus(405);
        $this->post('/admin/konten/halaman')->assertStatus(405);
        $this->put('/admin/konten/halaman')->assertStatus(405);
        $this->get('/admin/konten/banner/1')->assertStatus(405);
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/konten/halaman', route('admin.pages.index', [], false));
        $this->assertSame('/admin/konten/halaman/3/ubah', route('admin.pages.edit', 3, false));
        $this->assertSame('/admin/konten/halaman/3', route('admin.pages.update', 3, false));
        $this->assertSame('/admin/konten/banner', route('admin.banners.index', [], false));
        $this->assertSame('/admin/konten/banner/buat', route('admin.banners.create', [], false));
        $this->assertSame('/admin/konten/banner/4/ubah', route('admin.banners.edit', 4, false));
        $this->assertSame('/admin/konten/artikel', route('admin.articles.index', [], false));
        $this->assertSame('/admin/konten/artikel/3/ubah', route('admin.articles.edit', 3, false));
        $this->assertSame('/admin/konten/kategori-artikel', route('admin.article-categories.index', [], false));
        $this->assertSame('/admin/konten/kategori-artikel/5/ubah', route('admin.article-categories.edit', 5, false));
        $this->assertSame('/admin/konten/galeri', route('admin.galleries.index', [], false));
        $this->assertSame('/admin/konten/galeri/2/ubah', route('admin.galleries.edit', 2, false));
        $this->assertSame('/admin/konten/galeri/2/foto', route('admin.galleries.photos.store', 2, false));
        $this->assertSame('/admin/konten/faq/buat', route('admin.faqs.create', [], false));
        $this->assertSame('/admin/konten/asuransi', route('admin.insurance-partners.index', [], false));
        $this->assertSame('/admin/konten/asuransi/9/ubah', route('admin.insurance-partners.edit', 9, false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        foreach ([
            'banner' => 'admin.banners.create',
            'artikel' => 'admin.articles.create',
            'kategori-artikel' => 'admin.article-categories.create',
            'galeri' => 'admin.galleries.create',
            'faq' => 'admin.faqs.create',
            'asuransi' => 'admin.insurance-partners.create',
        ] as $segment => $name) {
            $this->assertSame($name, $routes->match(Request::create("/admin/konten/{$segment}/buat", 'GET'))->getName());
        }

        $this->assertSame('admin.galleries.photos.store', $routes->match(Request::create('/admin/konten/galeri/7/foto', 'POST'))->getName());
        $this->assertSame('admin.banners.destroy', $routes->match(Request::create('/admin/konten/banner/7', 'DELETE'))->getName());
        $this->assertSame('admin.articles.update', $routes->match(Request::create('/admin/konten/artikel/7', 'PUT'))->getName());
    }

    public function test_admin_ids_must_be_numeric(): void
    {
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)->get('/admin/konten/artikel/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/konten/kategori-artikel/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/konten/asuransi/abc/ubah')->assertNotFound();
    }

    /** @return array<string, array{string, string}> */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['home', '/'],
            'about' => ['about', 'profil'],
            'vision-mission' => ['vision-mission', 'visi-misi'],
            'insurance' => ['insurance', 'bpjs-asuransi'],
            'registration-guide' => ['registration-guide', 'panduan-pendaftaran'],
            'articles.index' => ['articles.index', 'artikel'],
            'articles.show' => ['articles.show', 'artikel/{article}'],
            'galleries.index' => ['galleries.index', 'galeri'],
            'galleries.show' => ['galleries.show', 'galeri/{gallery}'],
            'faq' => ['faq', 'faq'],
            'privacy' => ['privacy', 'kebijakan-privasi'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_content_routes_are_open_get_routes_without_auth_or_permission_middleware(string $name, string $uri): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame($uri, $route->uri());

        foreach (array_filter($route->gatherMiddleware(), 'is_string') as $middleware) {
            $this->assertStringNotContainsString('permission:', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('auth', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('account:', $middleware, "{$name}: {$middleware}");
        }
    }

    public function test_article_and_gallery_detail_routes_bind_by_slug(): void
    {
        $this->assertSame('slug', Route::getRoutes()->getByName('articles.show')->bindingFieldFor('article'));
        $this->assertSame('slug', Route::getRoutes()->getByName('galleries.show')->bindingFieldFor('gallery'));
    }

    public function test_public_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/', route('home', [], false));
        $this->assertSame('/profil', route('about', [], false));
        $this->assertSame('/visi-misi', route('vision-mission', [], false));
        $this->assertSame('/bpjs-asuransi', route('insurance', [], false));
        $this->assertSame('/panduan-pendaftaran', route('registration-guide', [], false));
        $this->assertSame('/artikel', route('articles.index', [], false));
        $this->assertSame('/artikel/menjaga-jantung', route('articles.show', 'menjaga-jantung', false));
        $this->assertSame('/galeri/lobi', route('galleries.show', 'lobi', false));
        $this->assertSame('/faq', route('faq', [], false));
        $this->assertSame('/kebijakan-privasi', route('privacy', [], false));
    }

    public function test_the_content_module_tabs_follow_the_documented_configuration(): void
    {
        $tabs = config('navigation.modules.content');

        $this->assertSame(
            ['admin.pages.index', 'admin.banners.index', 'admin.articles.index', 'admin.article-categories.index', 'admin.galleries.index', 'admin.faqs.index', 'admin.insurance-partners.index'],
            array_column($tabs, 'route'),
        );
        $this->assertSame(['Halaman', 'Banner', 'Artikel', 'Kategori Artikel', 'Galeri', 'FAQ', 'Mitra Asuransi'], array_column($tabs, 'label'));
        $this->assertSame(array_fill(0, 7, 'content.manage'), array_column($tabs, 'permission'));
    }

    public function test_only_users_with_content_manage_see_the_content_tabs(): void
    {
        $this->assertCount(7, NavigationMenu::items('modules.content', $this->createStaff('content-admin')));
        $this->assertCount(7, NavigationMenu::items('modules.content', $this->createStaff('super-admin')));
        $this->assertSame([], NavigationMenu::items('modules.content', $this->createStaff('registration-officer')));
        $this->assertSame([], NavigationMenu::items('modules.content', null));
    }
}
