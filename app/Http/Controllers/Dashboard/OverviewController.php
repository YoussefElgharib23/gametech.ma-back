<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use NumberFormatter;

class OverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);

        $salesToday = (float) Order::query()
            ->whereDate('created_at', now()->toDateString())
            ->sum('total');

        $pendingOrders = (int) Order::query()
            ->where('status', 'new')
            ->count();

        $outOfStockProducts = (int) Product::query()
            ->where(function ($q): void {
                $q->where('stock_status', 'out_of_stock')
                    ->orWhere(function ($sq): void {
                        $sq->whereNotNull('stock_quantity')->where('stock_quantity', '<=', 0);
                    });
            })
            ->count();

        $newCustomersThisMonth = (int) Customer::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $recentOrders = Order::query()
            ->with('customer')
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(function (Order $order) use ($formatter): array {
                return [
                    'id' => $order->id,
                    'uid' => $order->uid,
                    'status' => $order->status,
                    'customer_name' => $order->customer?->name ?? 'Client inconnu',
                    'total' => (float) $order->total,
                    'total_label' => $formatter->formatCurrency((float) $order->total, 'MAD'),
                    'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->values();

        return response()->json([
            'cards' => [
                'sales_today' => $salesToday,
                'sales_today_label' => $formatter->formatCurrency($salesToday, 'MAD'),
                'pending_orders' => $pendingOrders,
                'out_of_stock_products' => $outOfStockProducts,
                'new_customers_this_month' => $newCustomersThisMonth,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}
