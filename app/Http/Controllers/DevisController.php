<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DevisController extends Controller
{
    public function pcConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:50'],
            'customer.address' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $items = collect($validated['items']);
        $productIds = $items->pluck('product_id')->unique()->values()->all();

        $products = Product::query()
            ->with(['uploads' => fn ($q) => $q->orderBy('position')->orderBy('id')])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $lines = $items
            ->map(function (array $i) use ($products): array {
                /** @var Product $product */
                $product = $products[(int) $i['product_id']];
                $qty = (int) $i['quantity'];

                $unitPrice = (float) ($product->price ?? 0);
                $lineTotal = $unitPrice * $qty;

                $firstUpload = $product->uploads->first();
                $imagePath = $firstUpload?->path ? Storage::disk('public')->path($firstUpload->path) : null;

                return [
                    'product_id' => (int) $product->id,
                    'title' => (string) ($product->title ?? ''),
                    'image_path' => is_string($imagePath) && file_exists($imagePath) ? $imagePath : null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            })
            ->values();

        $total = $lines->sum('line_total');

        $logoPath = public_path('imgs/logo.jpg');

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper')->loadView('pdf.devis', [
            'generatedAt' => now(),
            'site' => [
                'name' => config('app.name', 'Gametech.ma'),
                'url' => config('app.frontend_url'),
            ],
            'customer' => [
                'name' => $validated['customer']['name'],
                'email' => $validated['customer']['email'],
                'phone' => $validated['customer']['phone'],
                'address' => $validated['customer']['address'],
            ],
            'logoPath' => is_string($logoPath) && file_exists($logoPath) ? $logoPath : null,
            'lines' => $lines,
            'total' => $total,
            'currency' => 'MAD',
        ])->setPaper('a4');

        $filename = 'devis/gametech-devis-' . now()->format('Ymd-His') . '-' . random_int(1000, 9999) . '.pdf';
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->put($filename, $pdf->output());

        return response()->json([
            'url' => $disk->url($filename),
        ]);
    }
}
