<?php

namespace App\Http\Controllers;

use App\Models\Activity_logs;
use App\Models\product;
use App\Models\sales;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KasirController extends Controller
{
    /**
     * Halaman transaksi baru.
     */
    public function transaksi()
    {
        $products = product::orderBy('id', 'asc')->get();

        return view('Kasir.Transactions', [
            'products' => $products,
            'draft' => null,
            'draftItems' => [],
        ]);
    }

    /**
     * Daftar draft milik kasir yang sedang login.
     */
    public function drafts()
    {
        $drafts = sales::query()
            ->where('user_id', Auth::id())
            ->where('status', 'draft')
            ->withCount('saleItems')
            ->latest('updated_at')
            ->paginate(10);

        return view('Kasir.Drafts', compact('drafts'));
    }

    /**
     * Membuka draft ke halaman transaksi.
     */
    public function showDraft(sales $sale)
    {
        $this->ensureDraftAccessible($sale);

        $products = product::orderBy('id', 'asc')->get();
        $draftItems = $this->getDraftItems($sale);

        return view('Kasir.Transactions', [
            'products' => $products,
            'draft' => $sale,
            'draftItems' => $draftItems,
        ]);
    }

    /**
     * Membuat draft baru tanpa mengurangi stok.
     */
    public function storeDraft(Request $request)
    {
        $validated = $this->validateItems($request);

        $sale = DB::transaction(function () use ($validated) {
            [$itemsData, $totalPrice] = $this->prepareItems(
                $validated['items']
            );

            $sale = sales::create([
                'user_id' => Auth::id(),
                'invoice_number' => $this->generateInvoiceNumber(),
                'sale_date' => now(),
                'total_price' => $totalPrice,
                'payment_method' => 'cash',
                'status' => 'draft',
                'paid_at' => null,
                'confirmed_at' => null,
                'cancelled_at' => null,
            ]);

            $sale->saleItems()->createMany($itemsData);

            return $sale;
        });

        return response()->json(array_merge(
            [
                'success' => true,
                'message' => 'Draft transaksi berhasil disimpan.',
            ],
            $this->getDraftPayload($sale)
        ), 201);
    }

    /**
     * Memperbarui item pada draft.
     *
     * Harga produk diperbarui dari database hanya ketika kasir
     * secara sengaja menekan tombol "Perbarui Draft".
     */
    public function updateDraft(Request $request, sales $sale)
    {
        $validated = $this->validateItems($request);

        $updatedSale = DB::transaction(
            function () use ($sale, $validated) {
                $lockedSale = sales::query()
                    ->whereKey($sale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureDraftAccessible($lockedSale);

                [$itemsData, $totalPrice] = $this->prepareItems(
                    $validated['items']
                );

                $lockedSale->saleItems()->delete();
                $lockedSale->saleItems()->createMany($itemsData);

                $lockedSale->update([
                    'total_price' => $totalPrice,
                ]);

                return $lockedSale->fresh();
            }
        );

        return response()->json(array_merge(
            [
                'success' => true,
                'message' => 'Draft transaksi berhasil diperbarui.',
            ],
            $this->getDraftPayload($updatedSale)
        ));
    }

    /**
     * Membatalkan draft tanpa mengurangi stok.
     */
    public function destroyDraft(Request $request, sales $sale)
    {
        $cancelledSale = DB::transaction(function () use ($sale) {
            $lockedSale = sales::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDraftAccessible($lockedSale);

            $lockedSale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Activity_logs::create([
                'user_id' => Auth::id(),
                'activity_type' => 'sale_cancelled',
                'description' =>
                    'Kasir "' . Auth::user()->name .
                    '" membatalkan draft transaksi ' .
                    $lockedSale->invoice_number,
            ]);

            return $lockedSale;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Draft transaksi berhasil dibatalkan.',
                'invoice' => $cancelledSale->invoice_number,
            ]);
        }

        return redirect()
            ->route('kasir.drafts.index')
            ->with(
                'success',
                'Draft transaksi berhasil dibatalkan.'
            );
    }

    /**
     * Konfirmasi pembayaran dan kurangi stok.
     *
     * Harga tidak dihitung ulang pada proses ini. Sistem menggunakan
     * price_per_unit, subtotal, dan total_price yang sudah tersimpan
     * ketika draft dibuat atau diperbarui.
     */
    public function confirmDraft(sales $sale)
    {
        $confirmedSale = DB::transaction(function () use ($sale) {
            $lockedSale = sales::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDraftAccessible($lockedSale);

            $saleItems = $lockedSale->saleItems()
                ->orderBy('product_id')
                ->get();

            if ($saleItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Draft tidak memiliki item transaksi.',
                ]);
            }

            $productIds = $saleItems
                ->pluck('product_id')
                ->unique()
                ->values();

            /*
             * Produk diurutkan sebelum dikunci untuk memperkecil
             * kemungkinan deadlock ketika ada transaksi bersamaan.
             */
            $products = product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' =>
                        'Salah satu produk draft sudah tidak tersedia.',
                ]);
            }

            /*
             * Periksa seluruh stok terlebih dahulu.
             *
             * Tidak ada stok yang dikurangi sebelum semua produk
             * dipastikan mencukupi.
             */
            foreach ($saleItems as $saleItem) {
                $currentProduct = $products->get(
                    $saleItem->product_id
                );

                if ($currentProduct->stock < $saleItem->quantity) {
                    throw ValidationException::withMessages([
                        'stock' =>
                            'Stok produk "' .
                            $currentProduct->name .
                            '" tidak mencukupi. Stok tersedia: ' .
                            $currentProduct->stock .
                            ', dibutuhkan: ' .
                            $saleItem->quantity .
                            '.',
                    ]);
                }
            }

            /*
             * Kurangi stok setelah semua stok dinyatakan cukup.
             */
            foreach ($saleItems as $saleItem) {
                $currentProduct = $products->get(
                    $saleItem->product_id
                );

                $currentProduct->decrement(
                    'stock',
                    $saleItem->quantity
                );
            }

            $confirmedAt = now();

            $lockedSale->update([
                'status' => 'paid',
                'payment_method' => 'cash',
                'paid_at' => $confirmedAt,
                'confirmed_at' => $confirmedAt,
                'cancelled_at' => null,
            ]);

            Activity_logs::create([
                'user_id' => Auth::id(),
                'activity_type' => 'sale',
                'description' =>
                    'Kasir "' . Auth::user()->name .
                    '" mengonfirmasi transaksi ' .
                    $lockedSale->invoice_number,
            ]);

            return $lockedSale->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dikonfirmasi.',
            'sale_id' => $confirmedSale->id,
            'invoice' => $confirmedSale->invoice_number,
            'total_price' => (float) $confirmedSale->total_price,
            'paid_at' => optional($confirmedSale->paid_at)
                ?->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * Laporan hanya menampilkan transaksi berstatus paid.
     */
    public function LaporanTransaksi(Request $request)
    {
        $query = sales::query()
            ->with('user')
            ->where('status', 'paid')
            ->whereNotNull('paid_at');

        if ($request->filled('start_date')) {
            $query->whereDate(
                'paid_at',
                '>=',
                $request->start_date
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                'paid_at',
                '<=',
                $request->end_date
            );
        }

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        $summaryQuery = clone $query;

        $sales = $query
            ->latest('paid_at')
            ->paginate(10)
            ->withQueryString();

        $totalPenjualan = $summaryQuery->sum('total_price');
        $jumlahTransaksi = $summaryQuery->count();

        $rataRata = $jumlahTransaksi > 0
            ? $totalPenjualan / $jumlahTransaksi
            : 0;

        $kasirs = User::query()
            ->where('role', 'kasir')
            ->orderBy('name')
            ->get();

        return view('kasir.laporanTransaksi', compact(
            'sales',
            'totalPenjualan',
            'jumlahTransaksi',
            'rataRata',
            'kasirs'
        ));
    }

    /**
     * Validasi isi keranjang.
     */
    private function validateItems(Request $request): array
    {
        return $request->validate([
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ], [
            'items.required' =>
                'Keranjang transaksi wajib diisi.',

            'items.min' =>
                'Keranjang transaksi masih kosong.',

            'items.*.product_id.required' =>
                'Product ID wajib diisi.',

            'items.*.product_id.distinct' =>
                'Produk yang sama tidak boleh dikirim dua kali.',

            'items.*.product_id.exists' =>
                'Produk tidak ditemukan di database.',

            'items.*.quantity.required' =>
                'Jumlah produk wajib diisi.',

            'items.*.quantity.min' =>
                'Jumlah produk minimal satu.',
        ]);
    }

    /**
     * Mengambil harga produk terbaru dari database.
     *
     * Method ini hanya digunakan ketika membuat atau memperbarui
     * draft. Method ini tidak digunakan ketika pembayaran dikonfirmasi.
     */
    private function prepareItems(array $items): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' =>
                    'Salah satu produk tidak ditemukan.',
            ]);
        }

        $itemsData = [];
        $totalPrice = 0;

        foreach ($items as $item) {
            $currentProduct = $products->get(
                (int) $item['product_id']
            );

            $quantity = (int) $item['quantity'];
            $pricePerUnit = (float) $currentProduct->price;

            $subtotal = round(
                $pricePerUnit * $quantity,
                2
            );

            $itemsData[] = [
                'product_id' => $currentProduct->id,
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
                'subtotal' => $subtotal,
            ];

            $totalPrice += $subtotal;
        }

        return [
            $itemsData,
            round($totalPrice, 2),
        ];
    }

    /**
     * Item draft yang dikirim ke halaman transaksi.
     */
    private function getDraftItems(sales $sale): array
    {
        $sale->load('saleItems.product');

        return $sale->saleItems
            ->filter(
                fn ($item) => $item->product !== null
            )
            ->map(function ($item) {
                return [
                    'product_id' =>
                        (string) $item->product_id,

                    'name' =>
                        $item->product->name,

                    /*
                     * Gunakan harga yang tersimpan pada sale_items,
                     * bukan harga terbaru dari tabel products.
                     */
                    'price' =>
                        (float) $item->price_per_unit,

                    'quantity' =>
                        (int) $item->quantity,

                    /*
                     * Stok hanya sebagai informasi pada frontend.
                     * Pemeriksaan final tetap dilakukan di server.
                     */
                    'stock' =>
                        (int) $item->product->stock,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Data draft yang dikembalikan setelah draft disimpan.
     */
    private function getDraftPayload(sales $sale): array
    {
        $sale->refresh();

        return [
            'sale_id' => $sale->id,
            'invoice' => $sale->invoice_number,
            'status' => $sale->status,
            'total_price' => (float) $sale->total_price,
            'items' => $this->getDraftItems($sale),
            'edit_url' => route(
                'kasir.drafts.show',
                $sale
            ),
        ];
    }

    /**
     * Hanya kasir pembuat yang boleh mengakses draft.
     */
    private function ensureDraftAccessible(sales $sale): void
    {
        abort_unless(
            (int) $sale->user_id === (int) Auth::id(),
            403,
            'Anda tidak memiliki akses ke draft ini.'
        );

        abort_unless(
            $sale->status === 'draft',
            409,
            'Transaksi ini sudah tidak berstatus draft.'
        );
    }

    /**
     * Membuat nomor invoice unik.
     */
    private function generateInvoiceNumber(): string
    {
        do {
            $invoiceNumber =
                'INV-' .
                now()->format('Ymd-His') .
                '-' .
                Str::upper(Str::random(6));
        } while (
            sales::where(
                'invoice_number',
                $invoiceNumber
            )->exists()
        );

        return $invoiceNumber;
    }
}