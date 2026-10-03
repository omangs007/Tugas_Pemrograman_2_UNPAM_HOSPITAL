<?php

namespace App\View\Components;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class Image extends Component
{
    public const TYPES = ['doctor', 'clinic', 'facility', 'room', 'article', 'banner', 'gallery', 'logo'];

    public const VARIANTS = ['full', 'thumb'];

    /** Rasio yang tersedia di SCSS ($aspect-ratios pada _variables.scss). */
    public const RATIOS = ['1x1', '4x3', '16x9', '21x9', '3x1'];

    public string $imageSrc;

    public ?string $fallbackSrc = null;

    public string $altText;

    public ?int $imageWidth = null;

    public ?int $imageHeight = null;

    public function __construct(
        public string $type,
        public ?Media $media = null,
        public ?string $src = null,
        string $alt = '',
        public string $variant = 'full',
        public ?string $ratio = null,
        public bool $lazy = true,
        public bool $decorative = false,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Tipe gambar tidak dikenal: '.$type);
        }

        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException('Varian gambar tidak dikenal: '.$variant);
        }

        if ($ratio !== null && ! in_array($ratio, self::RATIOS, true)) {
            throw new InvalidArgumentException('Rasio gambar tidak dikenal: '.$ratio);
        }

        $this->altText = $decorative ? '' : ($alt !== '' ? $alt : (string) ($media?->alt_text ?? ''));

        if (! $decorative && $this->altText === '') {
            throw new InvalidArgumentException('Atribut alt wajib diisi pada x-image (atau gunakan decorative).');
        }

        $placeholder = asset('images/placeholders/'.$type.'.svg');
        $mediaUrl = $media === null ? null : ($variant === 'thumb' ? $media->thumb_url : $media->url);

        if ($mediaUrl !== null && $mediaUrl !== '') {
            $this->imageSrc = $mediaUrl;
            $this->fallbackSrc = $placeholder;
            $this->resolveDimensions($media, $variant);
        } elseif ($src !== null && $src !== '') {
            $this->imageSrc = $src;
            $this->fallbackSrc = $placeholder;
        } else {
            $this->imageSrc = $placeholder;
        }
    }

    public function render(): View
    {
        return view('components.image');
    }

    private function resolveDimensions(Media $media, string $variant): void
    {
        if (! $media->width || ! $media->height) {
            return;
        }

        if ($variant === 'thumb' && $media->thumb_path !== null) {
            $width = min(MediaService::THUMB_WIDTH, $media->width);
            $this->imageWidth = $width;
            $this->imageHeight = (int) round($media->height * $width / $media->width);

            return;
        }

        $this->imageWidth = $media->width;
        $this->imageHeight = $media->height;
    }
}
