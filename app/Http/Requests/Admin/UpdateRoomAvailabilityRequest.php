<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-29 (BR-112, F4B-R17): `beds_available` bilangan bulat 0 sampai kapasitas kategori. `availability_for` hanyalah
 * penanda baris pada form inline daftar kamar, dipakai view untuk menampilkan galat di baris yang benar.
 */
class UpdateRoomAvailabilityRequest extends FormRequest
{
    /**
     * Dipakai juga controller saat memeriksa ulang kapasitas di bawah kunci baris.
     */
    public static function exceedsCapacityMessage(int $capacity): string
    {
        return "Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas ({$capacity} tempat tidur).";
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $room = $this->route('roomCategory');

        return $user !== null
            && $room instanceof RoomCategory
            && $user->can('updateAvailability', $room);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'beds_available' => ['bail', 'required', 'integer', 'min:0', 'max:'.$this->capacity()],
            'availability_for' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beds_available.required' => 'Jumlah tempat tidur tersedia wajib diisi.',
            'beds_available.integer' => 'Jumlah tempat tidur tersedia harus berupa angka bulat.',
            'beds_available.min' => 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.',
            'beds_available.max' => self::exceedsCapacityMessage($this->capacity()),
            'availability_for.integer' => 'Penanda baris ketersediaan tidak valid.',
        ];
    }

    public function bedsAvailable(): int
    {
        return (int) $this->validated('beds_available');
    }

    private function capacity(): int
    {
        $room = $this->route('roomCategory');

        return $room instanceof RoomCategory ? $room->bed_capacity : 0;
    }
}
