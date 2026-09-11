<?php

namespace App\Console\Commands;

use App\Support\ProductionContentFingerprint;
use Illuminate\Console\Command;

class ProductionContentFingerprintCommand extends Command
{
    protected $signature = 'production:content-fingerprint {--json : Print the complete non-secret fingerprint payload} {--hash-only : Print only the aggregate SHA-256}';

    protected $description = 'Read-only fingerprint of admin-managed storefront and catalog data';

    public function handle(): int
    {
        $fingerprint = ProductionContentFingerprint::capture();

        if ((bool) $this->option('hash-only')) {
            $this->line($fingerprint['aggregate']);

            return self::SUCCESS;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $fingerprint,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $this->info('Production content fingerprint: '.$fingerprint['aggregate']);
        foreach ($fingerprint['tables'] as $label => $table) {
            $this->line(sprintf(
                '%s table=%s count=%d sha256=%s',
                $label,
                $table['table'],
                $table['count'],
                $table['sha256'],
            ));
        }

        return self::SUCCESS;
    }
}
