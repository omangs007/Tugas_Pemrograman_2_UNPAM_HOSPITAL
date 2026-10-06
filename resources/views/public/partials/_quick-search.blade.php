<section class="quick-search">
    <form method="GET" action="{{ route('doctors.index') }}" role="search" aria-label="Cari dokter cepat" id="form-cari-cepat">
        <h2 class="h5 d-none d-md-block mb-3">Cari dokter cepat</h2>
        <button class="btn btn-outline-primary d-md-none w-100" type="button" data-bs-toggle="collapse" data-bs-target="#cari-cepat" aria-expanded="false" aria-controls="cari-cepat">
            <i class="bi bi-search" aria-hidden="true"></i> Cari dokter cepat
        </button>
        <div id="cari-cepat" class="collapse d-md-block mt-3 mt-md-0">
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.input name="q" label="Nama dokter" maxlength="100" autocomplete="off" placeholder="Nama dokter atau spesialisasi" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.select name="spesialisasi" label="Spesialisasi" :options="$specialtyOptions" selected="" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.select name="poli" label="Poliklinik" :options="$clinicOptions" selected="" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.input name="tanggal" label="Tanggal praktik" type="date" :min="$today->toDateString()" :max="$today->addDays($windowDays)->toDateString()" />
                </div>
            </div>
            <x-loading-button icon="bi-search" loading-text="Mencari…">Cari</x-loading-button>
        </div>
    </form>
</section>
