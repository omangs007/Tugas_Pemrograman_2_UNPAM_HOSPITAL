<?php

declare(strict_types=1);

namespace App\Services\Media;

final readonly class MediaPruneReport
{
    /**
     * @param  list<string>  $items  daftar yang dicetak `media:prune --dry-run`
     */
    public function __construct(
        public int $orphanRows,
        public int $orphanFiles,
        public int $bytes,
        public bool $dryRun,
        public array $items = [],
    ) {}
}
