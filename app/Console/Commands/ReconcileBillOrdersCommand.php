<?php

namespace App\Console\Commands;

use App\Models\BillOrder;
use App\Services\Reconciliation\BillReconciliationService;
use Illuminate\Console\Command;

class ReconcileBillOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bills:reconcile 
                            {--reference= : Reconcile a specific bill order reference}
                            {--limit=50 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending and processing bill orders against provider API per Doc 14 §3 & Doc 15 §4';

    /**
     * Execute the console command.
     */
    public function handle(BillReconciliationService $service): int
    {
        $this->info('Starting bill order reconciliation...');

        $reference = $this->option('reference');

        if (is_string($reference) && $reference !== '') {
            /** @var BillOrder|null $order */
            $order = BillOrder::where('reference', $reference)
                ->orWhere('ulid', $reference)
                ->first();

            if (! $order) {
                $this->error("Bill order [{$reference}] not found.");

                return Command::FAILURE;
            }

            $this->info("Reconciling single bill order [{$order->reference}] (Current status: {$order->status})...");
            $outcome = $service->reconcileSingleOrder($order);

            $this->info("Outcome for [{$order->reference}]: {$outcome} (Updated status: {$order->fresh()?->status})");

            return Command::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $stats = $service->reconcilePending($limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Reconciled', $stats['reconciled']],
                ['Completed', $stats['completed']],
                ['Refunded', $stats['refunded']],
                ['Unchanged', $stats['unchanged']],
                ['Errors', $stats['errors']],
            ]
        );

        $this->info('Bill order reconciliation completed.');

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
