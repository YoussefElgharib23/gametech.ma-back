<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $visitor = $this->resolveVisitor($request);
        $cart = $this->resolveCart($visitor);

        return response()->json($this->serializeCart($cart));
    }

    public function addItem(Request $request): JsonResponse
    {
        $visitor = $this->resolveVisitor($request);
        $cart = $this->resolveCart($visitor);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);
        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => (int) $data['product_id'],
        ]);

        $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
        $item->save();

        return response()->json($this->serializeCart($cart->fresh()));
    }

    public function incrementItem(Request $request, int $itemId): JsonResponse
    {
        $item = $this->findVisitorCartItem($request, $itemId);
        $item->quantity = min(999, $item->quantity + 1);
        $item->save();

        return response()->json($this->serializeCart($item->cart->fresh()));
    }

    public function decrementItem(Request $request, int $itemId): JsonResponse
    {
        $item = $this->findVisitorCartItem($request, $itemId);

        if ($item->quantity <= 1) {
            $cart = $item->cart;
            $item->delete();

            return response()->json($this->serializeCart($cart->fresh()));
        }

        $item->quantity -= 1;
        $item->save();

        return response()->json($this->serializeCart($item->cart->fresh()));
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $item = $this->findVisitorCartItem($request, $itemId);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $item->quantity = (int) $data['quantity'];
        $item->save();

        return response()->json($this->serializeCart($item->cart->fresh()));
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $item = $this->findVisitorCartItem($request, $itemId);
        $cart = $item->cart;
        $item->delete();

        return response()->json($this->serializeCart($cart->fresh()));
    }

    public function clear(Request $request): JsonResponse
    {
        $visitor = $this->resolveVisitor($request);
        $cart = $this->resolveCart($visitor);
        $cart->items()->delete();

        return response()->json($this->serializeCart($cart->fresh()));
    }

    private function resolveVisitor(Request $request): Visitor
    {
        /** @var mixed $user */
        $user = $request->user();

        if (!$user instanceof Visitor) {
            throw new HttpResponseException(
                response()->json(['message' => 'Unauthenticated.'], 401)
            );
        }

        return $user;
    }

    private function resolveCart(Visitor $visitor): Cart
    {
        return Cart::query()->firstOrCreate(['visitor_id' => $visitor->id]);
    }

    private function findVisitorCartItem(Request $request, int $itemId): CartItem
    {
        $visitor = $this->resolveVisitor($request);
        $cart = $this->resolveCart($visitor);

        return CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->firstOrFail();
    }

    private function serializeCart(Cart $cart): array
    {
        $cart->loadMissing([
            'items.product' => fn ($q) => $q->where('status', 'active')->with(['brand', 'uploads']),
        ]);

        $items = $cart->items
            ->filter(fn (CartItem $item) => $item->product instanceof Product)
            ->map(function (CartItem $item) use ($cart) {
                $product = $item->product;
                $firstImage = $product->uploads->first();
                $unitPrice = (float) $product->price;
                $lineTotal = $unitPrice * $item->quantity;

                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'line_total' => $lineTotal,
                    'line_total_label' => $cart->formatMoneyLabel($lineTotal),
                    'product' => [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'title' => $product->title,
                        'image' => $firstImage?->url,
                        'brand' => $product->brand?->name,
                        'price' => $unitPrice,
                        'price_label' => $product->price_label,
                    ],
                ];
            })
            ->values();

        $totals = [
            'items_count' => $items->sum('quantity'),
            'subtotal' => (float) $items->sum('line_total'),
        ];
        $totals['subtotal_label'] = $cart->formatMoneyLabel($totals['subtotal']);
        $totals['shipping'] = $cart->shippingAmount();
        $totals['shipping_label'] = $cart->formatMoneyLabel($totals['shipping']);
        $totals['discount'] = $cart->discountAmount();
        $totals['discount_label'] = $cart->formatMoneyLabel($totals['discount']);
        $totals['grand_total'] = $cart->grandTotal($totals['subtotal']);
        $totals['grand_total_label'] = $cart->formatMoneyLabel($totals['grand_total']);

        return [
            'cart' => [
                'id' => $cart->id,
                'visitor_id' => $cart->visitor_id,
            ],
            'items' => $items,
            'totals' => $totals,
        ];
    }
}
