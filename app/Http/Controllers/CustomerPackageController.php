<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Actions\Marketing\Packages\SellCustomerPackage;
use App\Http\Requests\ConsumePackageSessionRequest;
use App\Http\Requests\SellCustomerPackageRequest;
use App\Models\CustomerPackage;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;

final class CustomerPackageController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function store(SellCustomerPackageRequest $request, TenantContext $context, SellCustomerPackage $sellCustomerPackage): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($sellCustomerPackage, $request, $context, $data): array {
            $package = $sellCustomerPackage->handle($request->user(), $context, $data);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        $customerPackage = CustomerPackage::query()->findOrFail($reference['resource_id']);

        return back()->with('success', 'Pacote atribuído ao cliente com sucesso.');
    }

    public function consume(ConsumePackageSessionRequest $request, TenantContext $context, CustomerPackage $customerPackage, ConsumePackageSession $consumePackageSession): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($consumePackageSession, $request, $context, $customerPackage, $data): array {
            $package = $consumePackageSession->handle($request->user(), $context, $customerPackage, $data);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Sessão consumida com sucesso.');
    }
}
