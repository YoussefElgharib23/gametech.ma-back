<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NumberFormatter;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        $query = Customer::query()
            ->whereNull('deleted_at')
            ->withCount('orders')
            ->withSum('orders as total_spent', 'total')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);

        $data = collect($paginator->items())->map(function (Customer $customer) use ($formatter): array {
            $spent = (float) ($customer->total_spent ?? 0);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'orders_count' => (int) ($customer->orders_count ?? 0),
                'total_spent' => $spent,
                'total_spent_label' => $formatter->formatCurrency($spent, 'MAD'),
                'created_at' => optional($customer->created_at)->format('Y-m-d H:i'),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function orders($id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);

        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->latest('created_at')
            ->get()
            ->map(function (Order $order) use ($formatter): array {
                return [
                    'id' => $order->id,
                    'uid' => $order->uid,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'total_label' => $formatter->formatCurrency((float) $order->total, 'MAD'),
                    'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->values();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'orders' => $orders,
        ]);
    }
}
