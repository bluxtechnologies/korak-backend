<?php

namespace App\Jobs;

use App\Models\FundingRecord;
use App\Services\Reconciliation\FundingReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcilePendingFundingJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $limit = 50,
        public ?string $reference = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FundingReconciliationService $service): void
    {
        if ($this->reference !== null) {
            /** @var FundingRecord|null $record */
            $record = FundingRecord::where('reference', $this->reference)
                ->orWhere('ulid', $this->reference)
                ->orWhere('provider_reference', $this->reference)
                ->first();

            if ($record) {
                $outcome = $service->reconcileSingleRecord($record);
                Log::info("Reconciled single funding record {$this->reference}: {$outcome}");
            }

            return;
        }

        $stats = $service->reconcilePending($this->limit);
        Log::info('ReconcilePendingFundingJob executed', $stats);
    }
}
