<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;

class PurgeExpiredExportsCommand extends Command
{
    protected $signature = 'exports:purge-expired';

    protected $description = 'Remove ZIPs de exportação expirados';

    public function handle(): int
    {
        $count = 0;
        Export::query()
            ->where('status', 'READY')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    if ($export->storage_path && is_file($export->storage_path)) {
                        @unlink($export->storage_path);
                    }
                    $export->status = 'EXPIRED';
                    $export->storage_path = null;
                    $export->save();
                    $count++;
                }
            });

        $this->info("Expirados: {$count}");

        return self::SUCCESS;
    }
}
