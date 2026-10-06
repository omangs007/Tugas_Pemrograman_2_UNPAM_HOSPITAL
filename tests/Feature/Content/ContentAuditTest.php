<?php

namespace Tests\Feature\Content;

use App\Enums\ActorType;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F4A-R8 / 12 §8.3: model konten fase 4a diobservasi AuditObserver dengan deskripsi berbahasa Indonesia.
 */
class ContentAuditTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createStaff('content-admin');
        $this->actingAs($this->admin);
    }

    private function log(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_entity_labels_exist_for_all_seven_models(): void
    {
        $this->assertSame('Halaman', __('messages.entities.page'));
        $this->assertSame('Banner', __('messages.entities.banner'));
        $this->assertSame('Artikel', __('messages.entities.article'));
        $this->assertSame('Kategori artikel', __('messages.entities.article_category'));
        $this->assertSame('Album galeri', __('messages.entities.gallery'));
        $this->assertSame('FAQ', __('messages.entities.faq'));
        $this->assertSame('Mitra asuransi', __('messages.entities.insurance_partner'));
        $this->assertSame('Banner berhasil ditambahkan.', __('messages.created', ['entity' => __('messages.entities.banner')]));
        $this->assertSame('Artikel berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.article')]));
    }

    public function test_creating_each_model_writes_its_own_action(): void
    {
        $this->createPage('about');
        $this->createBanner();
        $this->createArticle();
        $this->createArticleCategory();
        $this->createGallery();
        $this->createFaq();
        $this->createInsurancePartner();

        $actions = AuditLog::query()->pluck('action')->all();
        sort($actions);

        $this->assertSame([
            'article.created',
            'article_category.created',
            'banner.created',
            'faq.created',
            'gallery.created',
            'insurance_partner.created',
            'page.created',
        ], $actions);
    }

    public function test_banner_lifecycle(): void
    {
        $banner = $this->createBanner(['title' => 'Promo']);

        $created = $this->log('banner.created');
        $this->assertSame('Banner "Promo" ditambahkan.', $created->description);
        $this->assertSame(ActorType::Staff, $created->actor_type);
        $this->assertSame($this->admin->id, (int) $created->user_id);
        $this->assertSame(Banner::class, $created->auditable_type);
        $this->assertSame($banner->id, (int) $created->auditable_id);
        $this->assertNull($created->old_values);
        $this->assertSame('Promo', $created->new_values['title']);

        AuditLog::query()->delete();
        $banner->update(['is_active' => false]);

        $updated = $this->log('banner.updated');
        $this->assertSame('Banner "Promo" diperbarui.', $updated->description);
        $this->assertEquals(['is_active' => 1], $updated->old_values);
        $this->assertEquals(['is_active' => 0], $updated->new_values);

        AuditLog::query()->delete();
        $banner->delete();

        $deleted = $this->log('banner.deleted');
        $this->assertSame('Banner "Promo" dihapus.', $deleted->description);
        $this->assertSame('Promo', $deleted->old_values['title']);
        $this->assertNull($deleted->new_values);
    }

    public function test_a_banner_loaded_from_the_database_can_be_audited_without_a_name_column(): void
    {
        $this->createBanner(['title' => 'Dimuat Ulang']);
        AuditLog::query()->delete();

        $loaded = Banner::query()->firstOrFail();
        $loaded->update(['subtitle' => 'Baru']);
        $loaded->delete();

        $this->assertSame('Banner "Dimuat Ulang" diperbarui.', $this->log('banner.updated')->description);
        $this->assertSame('Banner "Dimuat Ulang" dihapus.', $this->log('banner.deleted')->description);
    }

    public function test_faq_uses_the_question_as_its_name(): void
    {
        $faq = $this->createFaq(['question' => 'Bagaimana cara mendaftar?']);

        $this->assertSame('FAQ "Bagaimana cara mendaftar?" ditambahkan.', $this->log('faq.created')->description);

        $faq->delete();

        $this->assertSame('FAQ "Bagaimana cara mendaftar?" dihapus.', $this->log('faq.deleted')->description);
    }

    public function test_long_names_are_limited_to_eighty_characters(): void
    {
        $title = str_repeat('Promo Panjang ', 8);
        $this->createBanner(['title' => $title]);

        $expected = Str::limit($title, 80);

        $this->assertSame(83, strlen($expected));
        $this->assertSame('Banner "'.$expected.'" ditambahkan.', $this->log('banner.created')->description);

        $question = 'Bagaimana cara mendaftar kunjungan rawat jalan secara online dengan akun saya yang baru dibuat kemarin sore?';
        $this->createFaq(['question' => $question]);

        $this->assertSame('FAQ "'.Str::limit($question, 80).'" ditambahkan.', $this->log('faq.created')->description);
    }

    public function test_article_category_actions_and_description(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi']);

        $this->assertSame('Kategori artikel "Gizi" ditambahkan.', $this->log('article_category.created')->description);

        $category->update(['description' => 'Informasi gizi.']);

        $this->assertSame('Kategori artikel "Gizi" diperbarui.', $this->log('article_category.updated')->description);

        $category->delete();

        $this->assertSame('Kategori artikel "Gizi" dihapus.', $this->log('article_category.deleted')->description);
        $this->assertSame(ArticleCategory::class, $this->log('article_category.deleted')->auditable_type);
    }

    public function test_article_lifecycle_including_soft_delete(): void
    {
        $article = $this->createArticle(['title' => 'Cara Mencuci Tangan yang Benar']);

        $this->assertSame('Artikel "Cara Mencuci Tangan yang Benar" ditambahkan.', $this->log('article.created')->description);

        AuditLog::query()->delete();
        $article->update(['status' => 'draft']);

        $updated = $this->log('article.updated');
        $this->assertSame('Artikel "Cara Mencuci Tangan yang Benar" diperbarui.', $updated->description);
        $this->assertSame('draft', $updated->new_values['status']);
        $this->assertSame('published', $updated->old_values['status']);

        AuditLog::query()->delete();
        $article->delete();

        $deleted = $this->log('article.deleted');
        $this->assertSame('Artikel "Cara Mencuci Tangan yang Benar" dihapus.', $deleted->description);
        $this->assertSame(Article::class, $deleted->auditable_type);
        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_article_body_is_logged_as_content_changed_only(): void
    {
        $article = $this->createArticle(['body' => '<p>Isi lama artikel ini cukup panjang.</p>']);
        AuditLog::query()->delete();

        $article->update(['body' => '<p>Isi baru yang rahasia.</p>']);

        $log = $this->log('article.updated');

        $this->assertSame(['body' => '[konten diubah]'], $log->new_values);
        $this->assertSame(['body' => '[konten diubah]'], $log->old_values);
        $this->assertStringNotContainsString('rahasia', (string) json_encode($log->new_values));
    }

    public function test_restoring_an_article_is_audited_only_as_an_update_of_deleted_at(): void
    {
        $article = $this->createArticle();
        $article->delete();
        AuditLog::query()->delete();

        $article->restore();

        // Event `restored` tidak diobservasi, tetapi restore() menyimpan `deleted_at = NULL` sehingga `updated` terpicu.
        $this->assertSame(['article.updated'], AuditLog::query()->pluck('action')->all());
        $this->assertSame(['deleted_at' => null], $this->log('article.updated')->new_values);
    }

    public function test_gallery_and_insurance_partner_descriptions(): void
    {
        $gallery = $this->createGallery(['title' => 'Lobi dan Area Pendaftaran (Foto Contoh)']);
        $partner = $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat']);

        $this->assertSame('Album galeri "Lobi dan Area Pendaftaran (Foto Contoh)" ditambahkan.', $this->log('gallery.created')->description);
        $this->assertSame('Mitra asuransi "Asuransi Contoh Sehat" ditambahkan.', $this->log('insurance_partner.created')->description);

        $gallery->update(['is_published' => false]);
        $partner->update(['is_active' => false]);

        $this->assertSame('Album galeri "Lobi dan Area Pendaftaran (Foto Contoh)" diperbarui.', $this->log('gallery.updated')->description);
        $this->assertSame('Mitra asuransi "Asuransi Contoh Sehat" diperbarui.', $this->log('insurance_partner.updated')->description);

        $gallery->delete();
        $partner->delete();

        $this->assertSame('Album galeri "Lobi dan Area Pendaftaran (Foto Contoh)" dihapus.', $this->log('gallery.deleted')->description);
        $this->assertSame('Mitra asuransi "Asuransi Contoh Sehat" dihapus.', $this->log('insurance_partner.deleted')->description);
        $this->assertSame(Gallery::class, $this->log('gallery.deleted')->auditable_type);
        $this->assertSame(InsurancePartner::class, $this->log('insurance_partner.deleted')->auditable_type);
    }

    public function test_page_updates_are_audited_with_the_new_title_and_body_only_marked(): void
    {
        $page = $this->createPage('about', ['body' => '<p>Isi lama.</p>']);

        $this->assertSame('Halaman "Profil Rumah Sakit" ditambahkan.', $this->log('page.created')->description);

        AuditLog::query()->delete();
        $page->update(['title' => 'Tentang Kami', 'body' => '<p>Isi baru.</p>', 'updated_by' => $this->admin->id]);

        $log = $this->log('page.updated');

        $this->assertSame('Halaman "Tentang Kami" diperbarui.', $log->description);
        $this->assertSame('Tentang Kami', $log->new_values['title']);
        $this->assertSame('Profil Rumah Sakit', $log->old_values['title']);
        $this->assertSame('[konten diubah]', $log->new_values['body']);
        $this->assertSame(Page::class, $log->auditable_type);
    }

    public function test_a_save_without_changes_writes_nothing(): void
    {
        $page = $this->createPage('faq');
        $banner = $this->createBanner();
        AuditLog::query()->delete();

        $page->save();
        $banner->save();
        $banner->update(['title' => $banner->title]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_without_a_logged_in_staff_the_actor_is_the_system(): void
    {
        auth()->forgetGuards();

        $this->createFaq(['question' => 'Tanpa login?']);

        $log = $this->log('faq.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame('FAQ "Tanpa login?" ditambahkan.', $log->description);
    }

    public function test_existing_models_keep_using_their_name(): void
    {
        $this->createClinic(['name' => 'Poliklinik Jantung']);
        $this->createSpecialty(['name' => 'Anak']);

        $this->assertSame('Poliklinik "Poliklinik Jantung" ditambahkan.', $this->log('clinic.created')->description);
        $this->assertSame('Spesialisasi "Anak" ditambahkan.', $this->log('specialty.created')->description);
    }
}
