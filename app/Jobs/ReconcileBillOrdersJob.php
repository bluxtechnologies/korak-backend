<?php

namespace App\Jobs;

use App\Models\BillOrder;
use App\Services\Reconciliation\BillReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileBillOrdersJob implements ShouldQueue
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
    public function handle(BillReconciliationService $service): void
    {
        if ($this->reference !== null) {
            /** @var BillOrder|null $order */
            $order = BillOrder::where('reference', $this->reference)
                ->orWhere('ulid', $this->reference)
                ->first();

            if ($order) {
                $outcome = $service->reconcileSingleOrder($order);
                Log::info("Reconciled single bill order {$this->reference}: {$outcome}");
            }

            return;
        }

        $stats = $service->reconcilePending($this->limit);
        Log::info('ReconcileBillOrdersJob executed', $stats);
    }
}
