<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SecurityActivityResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditEvent;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityActivityController extends Controller
{
    /**
     * Get recent security activity for the authenticated customer.
     *
     * Per Doc 12 §5 and Roadmap B11.4:
     * Scoped strictly to the customer's own activity (actor or subject).
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $query = AuditEvent::query()
            ->where(function (Builder $q) use ($customer): void {
                $q->where(function (Builder $sub) use ($customer): void {
                    $sub->where('actor_type', $customer->getMorphClass())
                        ->where('actor_id', $customer->id);
                })->orWhere(function (Builder $sub) use ($customer): void {
                    $sub->where('subject_type', $customer->getMorphClass())
                        ->where('subject_id', $customer->id);
                });
            })
            ->latest('occurred_at');

        $paginator = $query->paginate($perPage);

        $transformed = $paginator->through(
            fn (AuditEvent $event): array => (new SecurityActivityResource($event))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }
}
