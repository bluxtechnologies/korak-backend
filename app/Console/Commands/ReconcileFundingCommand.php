<?php

namespace App\Console\Commands;

use App\Models\FundingRecord;
use App\Services\Reconciliation\FundingReconciliationService;
use Illuminate\Console\Command;

class ReconcileFundingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reconcile-funding 
                            {--reference= : Reconcile a specific funding reference}
                            {--limit=50 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending wallet funding records against payment gateway per Doc 14 §4 & §7';

    /**
     * Execute the console command.
     */
    public function handle(FundingReconciliationService $service): int
    {
        $this->info('Starting pending funding reconciliation...');

        $reference = $this->option('reference');

        if (is_string($reference) && $reference !== '') {
            /** @var FundingRecord|null $record */
            $record = FundingRecord::where('reference', $reference)
                ->orWhere('ulid', $reference)
                ->orWhere('provider_reference', $reference)
                ->first();

            if (! $record) {
                $this->error("Funding record [{$reference}] not found.");

                return Command::FAILURE;
            }

            $this->info("Reconciling funding record [{$record->reference}] (Current status: {$record->status->value})...");
            $outcome = $service->reconcileSingleRecord($record);

            $this->info("Outcome for [{$record->reference}]: {$outcome} (Updated status: {$record->fresh()?->status->value})");

            return Command::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $stats = $service->reconcilePending($limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Reconciled', $stats['reconciled']],
                ['Credited', $stats['credited']],
                ['Failed', $stats['failed']],
                ['Unchanged', $stats['unchanged']],
                ['Errors', $stats['errors']],
            ]
        );

        $this->info('Funding reconciliation completed.');

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
