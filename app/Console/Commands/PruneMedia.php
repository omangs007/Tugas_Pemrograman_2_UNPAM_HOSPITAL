<?php

namespace App\Console\Commands;

use App\Services\Media\MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

class PruneMedia extends Command
{
    protected $signature = 'media:prune {--dry-run : Hanya melaporkan, tidak ada yang dihapus}';

    protected $description = 'Hapus media yatim: baris tanpa pemilik lebih dari 24 jam dan berkas tak tercatat lebih dari 1 jam';

    public function handle(MediaService $media): int
    {
        $report = $media->prune((bool) $this->option('dry-run'));

        $this->line("Baris media yatim: {$report->orphanRows}");
        $this->line("Berkas yatim: {$report->orphanFiles}");
        $this->line('Total ukuran: '.Number::fileSize($report->bytes));

        if ($report->dryRun) {
            foreach ($report->items as $item) {
                $this->line(' - '.$item);
            }

            $this->info('Mode uji: tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $this->info('Pembersihan selesai.');

        return self::SUCCESS;
    }
}
