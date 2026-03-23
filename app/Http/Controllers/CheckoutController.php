<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var \Illuminate\Http\Request $request */
        $request = request();

        $data = $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'payment_method' => ['required', 'string'],
        ]);

        /** @var mixed $authUser */
        $authUser = $request->user();
        abort_unless($authUser instanceof Visitor, 401, 'Unauthenticated.');
        $visitor = $authUser;

        $cart = $visitor->cart()->with(['items.product'])->first();
        abort_unless($cart !== null, 422, 'Panier introuvable.');
        abort_unless($cart->items->isNotEmpty(), 422, 'Panier vide.');

        $order = DB::transaction(function () use ($data, $visitor, $cart) {
            $shippingPrice = $cart->shippingAmount();

            $cartItems = $cart->items->filter(fn ($item) => $item->product !== null)->values();
            $subTotal = (float) $cartItems->sum(function ($item) {
                return ((float) $item->product->price) * (int) $item->quantity;
            });
            $total = $cart->grandTotal($subTotal);

            $customer = Customer::query()->firstOrCreate(
                ['fingerprint' => $visitor->fingerprint],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make(Str::random(12)),
                ]
            );

            // Keep contact data fresh on repeat orders.
            $customer->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            $order = Order::query()->create([
                'uid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'status' => 'new',
                'sub_total' => $subTotal,
                'total' => $total,
                'shipping_price' => $shippingPrice,
                'address' => $data['address'],
                'city' => $data['city'],
                'phone' => $data['phone'],
                'payment_method' => $data['payment_method'],
            ]);

            $order->items()->createMany(
                $cartItems->map(function ($item) {
                    return [
                        'model_id' => $item->product_id,
                        'model_type' => Product::class,
                        'price' => (float) $item->product->price,
                        'quantity' => (int) $item->quantity,
                        'total' => ((float) $item->product->price) * (int) $item->quantity,
                    ];
                })->toArray()
            );

            $cart->items()->delete();

            return $order->load('items');
        });

        return response()->json([
            'order' => $order,
            'message' => 'Commande effectuee avec succes',
        ]);
    }
}
