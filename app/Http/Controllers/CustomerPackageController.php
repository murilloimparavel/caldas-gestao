<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Packages\CancelCustomerPackage;
use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Actions\Marketing\Packages\ReversePackageUsage;
use App\Actions\Marketing\Packages\SellCustomerPackage;
use App\Http\Requests\CancelCustomerPackageRequest;
use App\Http\Requests\ConsumePackageSessionRequest;
use App\Http\Requests\ReversePackageUsageRequest;
use App\Http\Requests\SellCustomerPackageRequest;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
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

    public function cancel(CancelCustomerPackageRequest $request, TenantContext $context, CustomerPackage $customerPackage, CancelCustomerPackage $cancelCustomerPackage): RedirectResponse
    {
        /** @var array{reason: string} $data */
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($cancelCustomerPackage, $request, $context, $customerPackage, $data): array {
            $package = $cancelCustomerPackage->handle($request->user(), $context, $customerPackage, $data);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Pacote pendente cancelado.');
    }

    public function reverseUsage(ReversePackageUsageRequest $request, TenantContext $context, CustomerPackage $customerPackage, PackageUsage $packageUsage, ReversePackageUsage $reversePackageUsage): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reversePackageUsage, $request, $context, $packageUsage, $data): array {
            $usage = $reversePackageUsage->handle($request->user(), $context, $packageUsage, $data);

            return ['resource_id' => $usage->getKey(), 'resource_type' => 'package_usage'];
        });

        return back()->with('success', 'Consumo revertido com sucesso.');
    }
}
