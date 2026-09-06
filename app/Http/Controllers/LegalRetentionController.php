<?php

namespace App\Http\Controllers;

use App\Actions\Privacy\CreateLegalHold;
use App\Actions\Privacy\ReleaseLegalHold;
use App\Http\Requests\CreateLegalHoldRequest;
use App\Http\Requests\ReleaseLegalHoldRequest;
use App\Models\Customer;
use App\Models\LegalHold;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class LegalRetentionController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function store(CreateLegalHoldRequest $request, TenantContext $context, Customer $customer, CreateLegalHold $create): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $holdData = [
            'reason' => (string) $data['reason'],
            'reference' => $data['reference'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
        ];
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($create, $request, $context, $customer, $holdData): array {
            $hold = $create->handle($request->user(), $context, $customer, $holdData);

            return ['resource_id' => $hold->getKey(), 'resource_type' => 'legal_hold'];
        });
        $hold = LegalHold::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson()
            ? response()->json(['legal_hold' => $hold], 201)
            : back()->with('success', 'Bloqueio legal registrado.');
    }

    public function release(ReleaseLegalHoldRequest $request, TenantContext $context, LegalHold $legalHold, ReleaseLegalHold $release): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($release, $request, $context, $legalHold): array {
            $hold = $release->handle($request->user(), $context, $legalHold);

            return ['resource_id' => $hold->getKey(), 'resource_type' => 'legal_hold'];
        });
        $hold = LegalHold::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson()
            ? response()->json(['legal_hold' => $hold])
            : back()->with('success', 'Bloqueio legal liberado.');
    }
}
