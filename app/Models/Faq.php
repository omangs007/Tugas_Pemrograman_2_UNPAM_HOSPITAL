<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FAQ (09 §6.6). `answer` berupa teks biasa (dirender dengan `{{ }}`); `category` salah satu `CATEGORIES`.
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /** @var list<string> */
    public const CATEGORIES = ['pendaftaran', 'bpjs', 'layanan', 'umum'];

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        'pendaftaran' => 'Pendaftaran',
        'bpjs' => 'BPJS',
        'layanan' => 'Layanan',
        'umum' => 'Umum',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'question',
        'answer',
        'sort_order',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_published'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }

    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where($this->qualifyColumn('category'), $category);
    }

    /**
     * Label kategori (`CATEGORY_LABELS`), atau nilai mentahnya bila di luar daftar.
     *
     * @return Attribute<string, never>
     */
    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::CATEGORY_LABELS[$this->category] ?? (string) $this->category);
    }
}
