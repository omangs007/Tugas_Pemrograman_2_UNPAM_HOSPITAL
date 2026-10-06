{{-- Variabel: $article (Article|null), $categoryOptions (id => nama), $action, $submitLabel. Tanpa input slug (F4A-R7). --}}
@php
    $isEdit = $article !== null;
    $currentCover = $article?->firstMedia('cover');
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="title" label="Judul artikel" :value="$article?->title" :required="true" maxlength="150" help="Alamat artikel (slug) dibuat otomatis dari judul dan tidak berubah saat judul diubah." />
                    <x-form.select name="article_category_id" label="Kategori" :options="['' => 'Tanpa kategori'] + $categoryOptions" :selected="$article?->article_category_id" />
                    <x-form.textarea name="excerpt" label="Ringkasan" :value="$article?->excerpt" :rows="3" maxlength="300" :required="true" help="Satu atau dua kalimat; tampil di kartu artikel dan menjadi deskripsi SEO bila meta description kosong." />
                    <x-form.rich-text name="body" label="Isi artikel" :value="$article?->body" :maxlength="200000" :required="true" help="Minimal 50 karakter. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif. Isi dibersihkan otomatis saat disimpan." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$article?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai judul artikel." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$article?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai ringkasan artikel." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Penerbitan</div>
                <div class="card-body">
                    <x-form.radio-group name="status" legend="Status" :options="\App\Enums\ArticleStatus::options()" :selected="$article?->status ?? 'draft'" :required="true" />
                    <x-form.input name="published_at" type="datetime-local" label="Tanggal terbit" :value="$article?->published_at?->format('Y-m-d\TH:i')" help="Kosong = sekarang saat diterbitkan. Tanggal di masa depan menjadwalkan artikel (belum tampil di situs). Waktu mengikuti zona Asia/Jakarta." />
                    @if ($isEdit)
                        <p class="form-text mb-1">Alamat di situs: /artikel/{{ $article->slug }}</p>
                        <p class="form-text mb-0">Penulis: {{ $article->author?->name ?? '—' }}</p>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Gambar sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Gambar sampul" :current-media="$currentCover" alt-name="cover_alt" :alt-value="old('cover_alt', $currentCover?->alt_text)" help="Opsional. Minimal 800 x 450 piksel (rasio 16:9 disarankan). Tanpa gambar, situs memakai gambar pengganti." />
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
