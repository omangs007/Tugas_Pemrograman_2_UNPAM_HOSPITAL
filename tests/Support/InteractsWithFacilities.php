<?php

namespace Tests\Support;

use App\Models\ContactMessage;
use App\Models\Facility;
use App\Models\RoomCategory;
use Illuminate\Support\Arr;

/**
 * Pembantu tes untuk fasilitas, kategori kamar rawat inap, dan pesan kontak fase 4b. Dipakai bersama
 * RefreshDatabase. Semua pembantu memakai factory sehingga sanitasi, slug, dan observer audit ikut berjalan.
 */
trait InteractsWithFacilities
{
    /**
     * Fasilitas aktif kategori `umum` (slug dibuat otomatis dari nama).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createFacility(array $attributes = []): Facility
    {
        return Facility::factory()->create($attributes);
    }

    /**
     * Kategori kamar aktif, kapasitas 12, tersedia 5, tarif contoh 400000 per malam, diperbarui satu jam lalu
     * oleh siapa pun (`availability_updated_by` NULL). Nilai lain lewat $attributes atau state factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createRoomCategory(array $attributes = []): RoomCategory
    {
        return RoomCategory::factory()->create($attributes);
    }

    /**
     * Pesan kontak baru (`new`) dengan persetujuan dan `ip_hash` terisi. `handled_by` dan `handled_at` boleh ada
     * di $attributes: keduanya tidak fillable sehingga diisi lewat forceFill() setelah baris dibuat.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createContactMessage(array $attributes = []): ContactMessage
    {
        $handled = Arr::only($attributes, ['handled_by', 'handled_at']);
        $message = ContactMessage::factory()->create(Arr::except($attributes, ['handled_by', 'handled_at']));

        if ($handled !== []) {
            $message->forceFill($handled)->save();
        }

        return $message;
    }
}
