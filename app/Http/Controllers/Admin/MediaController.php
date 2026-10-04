<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorImageUploadRequest;
use App\Http\Requests\Admin\ReorderMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use App\Support\SafeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endpoint media admin (11 §4.4, F-ADM-29): JSON untuk editor/AJAX, redirect + flash untuk form biasa.
 * Izin ditentukan MediaPolicy menurut pemilik media (F2-R16); tidak ada aturan bisnis lain di sini.
 */
class MediaController extends Controller
{
    public const IN_USE_MESSAGE = 'Gambar tidak dapat dihapus karena sedang dipakai.';

    public function __construct(
        private readonly MediaService $media,
        private readonly SettingsService $settings,
    ) {}

    public function editorUpload(EditorImageUploadRequest $request): JsonResponse
    {
        $media = $this->media->store(
            $request->file('file'),
            'editor',
            null,
            ['alt_text' => $request->validated('alt_text')],
            $request->user(),
        );

        return response()->json([
            'url' => $this->publicPath($media),
            'alt' => $media->alt_text,
            'media_id' => $media->id,
        ], 201);
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Keterangan yang tidak dikirim dibiarkan; mengirim kosong menghapusnya.
        $caption = array_key_exists('caption', $validated) ? $validated['caption'] : $media->caption;

        $this->media->updateMeta($media, $validated['alt_text'], $caption);

        $message = __('messages.updated', ['entity' => 'Gambar']);

        if ($request->expectsJson()) {
            return response()->json([
                'media_id' => $media->id,
                'alt' => $media->alt_text,
                'caption' => $media->caption,
                'message' => $message,
            ]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    public function destroy(Request $request, Media $media): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $media);

        // Logo situs dirujuk pengaturan; berlaku juga untuk Super Admin (F2-R17).
        if ($this->settings->int('site.logo_media_id') === $media->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => self::IN_USE_MESSAGE,
                    'errors' => ['media' => [self::IN_USE_MESSAGE]],
                ], 422);
            }

            return redirect()->to(SafeRedirect::back($request))->with('error', self::IN_USE_MESSAGE);
        }

        $this->media->delete($media);

        $message = __('messages.deleted', ['entity' => 'Gambar']);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    public function reorder(ReorderMediaRequest $request): JsonResponse|RedirectResponse
    {
        $this->media->reorder($request->owner(), $request->collection(), $request->orderedIds());

        $message = 'Urutan gambar berhasil disimpan.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    /**
     * Path /storage/... agar <img> editor tetap dimuat dengan CSP img-src 'self' dan lolos HtmlSanitizer.
     */
    private function publicPath(Media $media): string
    {
        $path = parse_url((string) $media->url, PHP_URL_PATH);

        return is_string($path) && $path !== ''
            ? $path
            : '/storage/'.$media->directory.'/'.$media->file_name;
    }
}
