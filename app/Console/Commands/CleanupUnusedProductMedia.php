<?php

namespace App\Console\Commands;

use App\Support\ProductMediaFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupUnusedProductMedia extends Command
{
    protected $signature = 'app:cleanup-product-media {--dry-run : List the orphaned files without deleting them}';

    protected $description = 'Delete product and template images that are no longer referenced by any record.';

    public function handle(): int
    {
        $orphans = ProductMediaFiles::orphans();

        if ($orphans === []) {
            $this->info('No unused product media found.');

            return Command::SUCCESS;
        }

        $disk = Storage::disk(ProductMediaFiles::DISK);
        $isDryRun = (bool) $this->option('dry-run');
        $bytes = 0;
        $deleted = 0;

        foreach ($orphans as $path) {
            $size = $disk->exists($path) ? $disk->size($path) : 0;

            if ($isDryRun) {
                $this->line(sprintf('  %s (%s)', $path, $this->formatBytes($size)));
                $bytes += $size;

                continue;
            }

            if ($disk->delete($path)) {
                $this->line('  deleted '.$path);
                $bytes += $size;
                $deleted++;
            }
        }

        $this->info($isDryRun
            ? sprintf('%d unused file(s) found, %s would be freed.', count($orphans), $this->formatBytes($bytes))
            : sprintf('Deleted %d unused file(s), freeing %s.', $deleted, $this->formatBytes($bytes)));

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1).' '.$units[$unit];
    }
}
