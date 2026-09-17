<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartyIndexRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerLedgerEntryResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\AuditLogService;
use App\Services\CustomerAccountService;
use App\Services\PartyManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function statement(PartyIndexRequest $request, Customer $customer, CustomerAccountService $accounts): AnonymousResourceCollection
    {
        $statement = $accounts->statement($customer, (int) ($request->validated()['per_page'] ?? 25));

        return CustomerLedgerEntryResource::collection($statement['entries']->appends($request->validated()))
            ->additional(['summary' => $statement['summary']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(PartyIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Customer::query()
            ->withSum('orders as invoices_total', 'final_amount')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $matches) use ($search): void {
                    $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('tax_number', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->orderByDesc('id');

        $customers = ($filters['all'] ?? false)
            ? $query->get()
            : $query->paginate((int) ($filters['per_page'] ?? 25))->appends($filters);

        $response = CustomerResource::collection($customers);
        $response->collection->transform(fn (CustomerResource $customer): JsonResource => new JsonResource([
            ...$customer->resolve($request),
            'invoices_total' => bcadd((string) ($customer->resource->invoices_total ?? '0'), '0', 2),
        ]));

        return $response;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request, PartyManagementService $parties): JsonResponse
    {
        return (new CustomerResource($parties->createCustomer($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): CustomerResource
    {
        AuditLogService::viewed(Customer::class, $customer->id);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer, PartyManagementService $parties): CustomerResource
    {
        return new CustomerResource($parties->updateCustomer($customer, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer, PartyManagementService $parties): Response
    {
        $parties->deleteCustomer($customer);

        return response()->noContent();
    }
}
