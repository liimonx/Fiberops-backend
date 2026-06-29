<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsForOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\DomainIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use RespondsForOrganization;

    public function index(): JsonResponse
    {
        $customers = Customer::query()
            ->forOrganization($this->organizationId())
            ->orderBy('name')
            ->get();

        return response()->json([
            'items' => CustomerResource::collection($customers),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $customer = $this->findCustomer($id);
        if (! $customer) {
            return $this->notFound('Customer not found');
        }

        return response()->json(new CustomerResource($customer));
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2'],
            'plan' => ['required', Rule::in(['Fiber 50Mbps', 'Fiber 100Mbps', 'Fiber 200Mbps', 'Fiber 500Mbps', 'Fiber 1Gbps'])],
            'status' => ['required', Rule::in(['online', 'offline', 'unstable'])],
            'email' => ['nullable', 'email'],
            'relatedOnuId' => ['nullable', 'string'],
            'pppoeUsername' => ['nullable', 'string', 'max:255'],
            'location.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $organizationId = $this->organizationId();
        $customer = Customer::query()->create([
            'id' => DomainIdGenerator::nextCustomerId($organizationId),
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'plan' => $validated['plan'],
            'status' => $validated['status'],
            'billing_status' => 'paid',
            'email' => ! empty($validated['email']) ? $validated['email'] : null,
            'related_onu_id' => $validated['relatedOnuId'] ?? null,
            'pppoe_username' => $validated['pppoeUsername'] ?? null,
            'location_lat' => $validated['location']['lat'] ?? null,
            'location_lng' => $validated['location']['lng'] ?? null,
        ]);

        return response()->json(new CustomerResource($customer), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->ensureCanWrite()) {
            return $response;
        }

        $customer = $this->findCustomer($id);
        if (! $customer) {
            return $this->notFound('Customer not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2'],
            'plan' => ['sometimes', Rule::in(['Fiber 50Mbps', 'Fiber 100Mbps', 'Fiber 200Mbps', 'Fiber 500Mbps', 'Fiber 1Gbps'])],
            'status' => ['sometimes', Rule::in(['online', 'offline', 'unstable'])],
            'billingStatus' => ['sometimes', Rule::in(['paid', 'overdue', 'unpaid'])],
            'email' => ['nullable', 'email'],
            'relatedOnuId' => ['nullable', 'string'],
            'pppoeUsername' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'location.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location.lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $updates = [];
        foreach (['name', 'plan', 'status', 'notes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }
        if (array_key_exists('billingStatus', $validated)) {
            $updates['billing_status'] = $validated['billingStatus'];
        }
        if (array_key_exists('email', $validated)) {
            $updates['email'] = $validated['email'] === '' ? null : $validated['email'];
        }
        if (array_key_exists('relatedOnuId', $validated)) {
            $updates['related_onu_id'] = $validated['relatedOnuId'] ?: null;
        }
        if (array_key_exists('pppoeUsername', $validated)) {
            $updates['pppoe_username'] = $validated['pppoeUsername'] ?: null;
        }
        if (isset($validated['location'])) {
            $updates['location_lat'] = $validated['location']['lat'] ?? null;
            $updates['location_lng'] = $validated['location']['lng'] ?? null;
        }

        $customer->update($updates);

        return response()->json(new CustomerResource($customer->fresh()));
    }

    private function findCustomer(string $id): ?Customer
    {
        return Customer::query()
            ->forOrganization($this->organizationId())
            ->where('id', $id)
            ->first();
    }
}
