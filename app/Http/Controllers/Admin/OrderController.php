<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List all orders with filters
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'items.model.uploads'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by order UID, customer name, email, or phone
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uid', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(20);
        $orders->setCollection(
            $orders->getCollection()->map(fn (Order $order) => $this->transformOrder($order))
        );

        return response()->json($orders);
    }

    /**
     * Get single order with all details
     */
    public function show($id)
    {
        $order = Order::with(['customer', 'items.model.uploads'])->findOrFail($id);

        return response()->json($this->transformOrder($order));
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:new,confirmed,delivered,returned,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'order' => $this->transformOrder($order->fresh(['customer', 'items.model.uploads'])),
        ]);
    }

    /**
     * Update order item quantity
     */
    public function updateItemQuantity(Request $request, $orderId, $itemId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $order = Order::findOrFail($orderId);
        $item = OrderItem::where('order_id', $orderId)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->update([
            'quantity' => $data['quantity'],
            'total' => $item->price * $data['quantity'],
        ]);

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return response()->json([
            'message' => 'Quantité mise à jour avec succès',
            'order' => $this->transformOrder($order->fresh(['customer', 'items.model.uploads'])),
        ]);
    }

    /**
     * Add product to existing order
     */
    public function addItem(Request $request, $orderId)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $order = Order::findOrFail($orderId);
        $product = Product::findOrFail($data['product_id']);

        // Check if product already exists in order
        $existingItem = OrderItem::where('order_id', $orderId)
            ->where('model_type', Product::class)
            ->where('model_id', $product->id)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $data['quantity'],
                'total' => $product->price * ($existingItem->quantity + $data['quantity']),
            ]);
        } else {
            OrderItem::create([
                'order_id' => $order->id,
                'model_type' => Product::class,
                'model_id' => $product->id,
                'price' => $product->price,
                'quantity' => $data['quantity'],
                'total' => $product->price * $data['quantity'],
            ]);
        }

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return response()->json([
            'message' => 'Produit ajouté avec succès',
            'order' => $this->transformOrder($order->fresh(['customer', 'items.model.uploads'])),
        ]);
    }

    /**
     * Remove item from order
     */
    public function removeItem($orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);
        $item = OrderItem::where('order_id', $orderId)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        // Recalculate order totals
        $this->recalculateOrderTotals($order);

        return response()->json([
            'message' => 'Produit supprimé avec succès',
            'order' => $this->transformOrder($order->fresh(['customer', 'items.model.uploads'])),
        ]);
    }

    /**
     * Update order customer info and shipping details
     */
    public function updateDetails(Request $request, $id)
    {
        $data = $request->validate([
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'phone' => 'nullable|string',
            'shipping_price' => 'nullable|numeric|min:0',
        ]);

        $order = Order::findOrFail($id);
        $order->update($data);

        // Recalculate totals if shipping changed
        if (isset($data['shipping_price'])) {
            $this->recalculateOrderTotals($order);
        }

        return response()->json([
            'message' => 'Détails mis à jour avec succès',
            'order' => $this->transformOrder($order->fresh(['customer', 'items.model.uploads'])),
        ]);
    }

    /**
     * Recalculate order totals based on items
     */
    protected function recalculateOrderTotals(Order $order)
    {
        $subTotal = $order->items()->sum('total');
        $total = $subTotal + $order->shipping_price;

        $order->update([
            'sub_total' => $subTotal,
            'total' => $total,
        ]);
    }

    /**
     * Get order statistics
     */
    public function statistics()
    {
        $stats = [
            'total_orders' => Order::count(),
            'new_orders' => Order::where('status', 'new')->count(),
            'confirmed_orders' => Order::where('status', 'confirmed')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'total_revenue' => Order::whereIn('status', ['confirmed', 'delivered'])->sum('total'),
        ];

        return response()->json($stats);
    }

    protected function transformOrder(Order $order): Order
    {
        $order->items->each(function (OrderItem $item): void {
            if (! $item->relationLoaded('model') || ! $item->model instanceof Product) {
                return;
            }

            $product = $item->model;
            $firstUpload = $product->relationLoaded('uploads') ? $product->uploads->first() : null;

            // Normalize fields expected by admin frontend.
            $product->setAttribute('name', $product->title);
            $product->setAttribute('image', $firstUpload?->url);
            $product->setAttribute('images', $product->uploads->map(fn ($upload) => $upload->url)->values()->all());
        });

        return $order;
    }
}
