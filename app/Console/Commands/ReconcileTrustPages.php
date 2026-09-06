<?php

namespace App\Console\Commands;

use App\Models\BakeryContentPage;
use App\Support\Content\TrustReconciliationManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ReconcileTrustPages extends Command
{
    protected $signature = 'content:reconcile-trust-pages
        {manifest=database/content/winimi-trust-reconciliation-v1.json : Manifest path; relative paths resolve from the Laravel root}
        {--apply : Apply exact stale-copy replacements}
        {--expected-sha256= : Required SHA-256 pin when --apply is used}
        {--confirm= : Must equal RECONCILE_TRUST_PAGES when --apply is used}';

    protected $description = 'Audit or reconcile capability-aware copy on controlled Winimi trust pages';

    public function handle(): int
    {
        try {
            $manifest = TrustReconciliationManifest::load((string) $this->argument('manifest'));
        } catch (Throwable $exception) {
            $this->error('TRUST_MANIFEST_INVALID='.$exception->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $actualHash = (string) $manifest['sha256'];
        $audit = $this->audit($manifest['pages']);

        $this->line('MODE='.($apply ? 'APPLY' : 'DRY_RUN'));
        $this->line('TRUST_RECONCILIATION_STATUS=ready');
        $this->line('MANIFEST_VERSION='.$manifest['version']);
        $this->line('MANIFEST_SHA256='.$actualHash);
        $this->line('CONTROLLED_PAGES='.count($manifest['pages']));
        $this->line('PENDING_REPLACEMENTS='.$audit['pending']);
        $this->line('ALREADY_RECONCILED='.$audit['reconciled']);
        $this->line('AUDIT_FAILURES='.$audit['failures']);

        foreach ($audit['states'] as $state) {
            $this->line('TRUST_PAGE='.$state);
        }

        if ($audit['failures'] > 0) {
            $this->error('TRUST_PAGE_SOURCE_DRIFT=YES');
            $this->line('DATABASE_MUTATIONS=0');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->line('DATABASE_MUTATIONS=0');
            $this->line('NEXT=REVIEW_THEN_APPLY_WITH_HASH_AND_CONFIRMATION');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'RECONCILE_TRUST_PAGES') {
            $this->error('CONFIRMATION_REQUIRED=Use --confirm=RECONCILE_TRUST_PAGES');

            return self::FAILURE;
        }

        $expectedHash = strtolower(trim((string) $this->option('expected-sha256')));
        if ($expectedHash === '' || ! hash_equals($expectedHash, strtolower($actualHash))) {
            $this->error("MANIFEST_HASH_MISMATCH=actual:{$actualHash}");

            return self::FAILURE;
        }

        try {
            $mutations = DB::transaction(function () use ($manifest): int {
                $mutations = 0;

                foreach ($manifest['pages'] as $definition) {
                    $page = BakeryContentPage::query()
                        ->where('slug', $definition['slug'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $content = (string) $page->content;
                    $changed = false;

                    foreach ($definition['replacements'] as $replacement) {
                        $fromCount = substr_count($content, $replacement['from']);
                        $toCount = substr_count($content, $replacement['to']);

                        if ($fromCount === 1 && $toCount === 0) {
                            $content = str_replace($replacement['from'], $replacement['to'], $content, $count);
                            if ($count !== 1) {
                                throw new RuntimeException('Unexpected replacement count for '.$definition['slug']);
                            }
                            $changed = true;

                            continue;
                        }

                        if ($fromCount === 0 && $toCount >= 1) {
                            continue;
                        }

                        throw new RuntimeException('Trust page changed after audit: '.$definition['slug']);
                    }

                    if ($changed) {
                        $page->content = $content;
                        $page->save();
                        $mutations++;
                    }
                }

                return $mutations;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('TRUST_RECONCILIATION_FAILED='.$exception->getMessage());

            return self::FAILURE;
        }

        $verification = $this->audit($manifest['pages']);
        $this->line('TRUST_RECONCILIATION_STATUS='.($verification['failures'] === 0 && $verification['pending'] === 0 ? 'completed' : 'verification-failed'));
        $this->line('MANIFEST_SHA256='.$actualHash);
        $this->line('DATABASE_MUTATIONS='.$mutations);
        $this->line('VERIFICATION_FAILURES='.$verification['failures']);
        $this->line('REMAINING_STALE_REPLACEMENTS='.$verification['pending']);

        return $verification['failures'] === 0 && $verification['pending'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param array<int, array{slug: string, replacements: array<int, array{from: string, to: string}>}> $pages
     * @return array{pending: int, reconciled: int, failures: int, states: array<int, string>}
     */
    private function audit(array $pages): array
    {
        $pending = 0;
        $reconciled = 0;
        $failures = 0;
        $states = [];

        foreach ($pages as $definition) {
            $page = BakeryContentPage::query()->where('slug', $definition['slug'])->first();
            if (! $page) {
                $failures++;
                $states[] = $definition['slug'].'|STATE=missing';

                continue;
            }

            $content = (string) $page->content;
            $pagePending = 0;
            $pageReconciled = 0;
            $pageFailures = 0;

            foreach ($definition['replacements'] as $replacement) {
                $fromCount = substr_count($content, $replacement['from']);
                $toCount = substr_count($content, $replacement['to']);

                if ($fromCount === 1 && $toCount === 0) {
                    $pending++;
                    $pagePending++;

                    continue;
                }

                if ($fromCount === 0 && $toCount >= 1) {
                    $reconciled++;
                    $pageReconciled++;

                    continue;
                }

                $failures++;
                $pageFailures++;
            }

            $states[] = implode('|', [
                $definition['slug'],
                'STATUS='.$page->status,
                'PENDING='.$pagePending,
                'RECONCILED='.$pageReconciled,
                'FAILURES='.$pageFailures,
            ]);
        }

        return compact('pending', 'reconciled', 'failures', 'states');
    }
}
