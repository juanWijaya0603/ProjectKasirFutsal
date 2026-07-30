<?php

namespace App\Http\Controllers;

use App\Models\Activity_logs;
use App\Models\User;
use App\Models\product as Product;
use App\Models\suppliers;
use App\Models\categories;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\sale_items;
use App\Models\sales;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboardPage()
    {
        $recentActivities = Activity_logs::with('user')
            ->latest()
            ->take(5)
            ->get();

        // Data master
        $totalProduk = Product::count();

        $totalPengguna = User::where('role', '!=', 'admin')
            ->count();

        // Total pembelian dari seluruh transaksi pembelian
        $totalPembelian = Purchase::sum('total_price');

        /*
         * Penjualan hanya boleh dihitung setelah pembayaran
         * berhasil dikonfirmasi.
         */
        $totalPenjualan = sales::query()
            ->where('status', 'paid')
            ->sum('total_price');

        /*
         * Dashboard hanya menerima range 7 atau 30 hari.
         * Nilai lain dikembalikan ke default 7 hari.
         */
        $range = (int) request('range', 7);

        if (!in_array($range, [7, 30], true)) {
            $range = 7;
        }

        /*
         * Dikurangi range - 1 agar range 7 berarti:
         * hari ini + enam hari sebelumnya.
         */
        $fromDate = now()
            ->subDays($range - 1)
            ->startOfDay();

        /*
         * Grafik penjualan menggunakan paid_at karena yang ingin
         * ditampilkan adalah waktu pembayaran dikonfirmasi,
         * bukan waktu draft pertama dibuat.
         */
        $sales = sales::query()
            ->selectRaw(
                'DATE(paid_at) as date, SUM(total_price) as total'
            )
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $fromDate)
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('date')
            ->get();

        $salesDates = $sales->pluck('date');
        $salesTotals = $sales->pluck('total');

        $purchases = Purchase::query()
            ->selectRaw(
                'DATE(purchase_date) as date, SUM(total_price) as total'
            )
            ->where('purchase_date', '>=', $fromDate)
            ->groupByRaw('DATE(purchase_date)')
            ->orderBy('date')
            ->get();

        $purchaseDates = $purchases->pluck('date');
        $purchaseTotals = $purchases->pluck('total');

        return view('Admin.dashboard', [
            'totalPenjualan' => $totalPenjualan,
            'totalPembelian' => $totalPembelian,
            'totalProduk' => $totalProduk,
            'totalPengguna' => $totalPengguna,
            'salesDates' => $salesDates,
            'salesTotals' => $salesTotals,
            'purchaseDates' => $purchaseDates,
            'purchaseTotals' => $purchaseTotals,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function productsPage(Request $request)
    {
        $query = Product::with('category');

        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->category_id
            );
        }

        $products = $query
            ->orderBy('created_at', 'desc')
            ->get();

        $categories = categories::all();

        return view(
            'Admin.product.ViewProduct',
            compact('products', 'categories')
        );
    }

    public function suppliersPage()
    {
        $suppliers = suppliers::all();

        return view(
            'Admin.supplier.ViewSupplier',
            compact('suppliers')
        );
    }

    public function usersPage()
    {
        $users = User::where('role', '!=', 'admin')
            ->orderBy('id', 'asc')
            ->get();

        return view(
            'Admin.user.ViewUser',
            compact('users')
        );
    }

    public function laporanPage(Request $request)
    {
        $tanggal = $request->input(
            'tanggal',
            today()->toDateString()
        );

        /*
         * Barang masuk tetap menggunakan tanggal pembelian.
         */
        $barangMasuk = PurchaseItem::with([
                'product',
                'purchase.supplier',
            ])
            ->whereHas(
                'purchase',
                function ($query) use ($tanggal) {
                    $query->whereDate(
                        'purchase_date',
                        $tanggal
                    );
                }
            )
            ->get();

        /*
         * Barang keluar hanya berasal dari transaksi paid.
         *
         * Tanggalnya menggunakan paid_at karena stok baru dikurangi
         * ketika kasir mengonfirmasi pembayaran.
         */
        $barangKeluar = sale_items::with([
                'product',
                'sale.user',
            ])
            ->whereHas(
                'sale',
                function ($query) use ($tanggal) {
                    $query
                        ->where('status', 'paid')
                        ->whereNotNull('paid_at')
                        ->whereDate('paid_at', $tanggal);
                }
            )
            ->get();

        $stok = Product::all();

        $totalPembelian = Purchase::query()
            ->whereDate('purchase_date', $tanggal)
            ->sum('total_price');

        /*
         * Draft dan transaksi cancelled tidak ikut dihitung.
         */
        $totalPenjualan = sales::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', $tanggal)
            ->sum('total_price');

        return view('admin.laporan', [
            'tanggal' => $tanggal,
            'barangMasuk' => $barangMasuk,
            'barangKeluar' => $barangKeluar,
            'stok' => $stok,
            'totalPembelian' => $totalPembelian,
            'totalPenjualan' => $totalPenjualan,
            'selisih' => $totalPenjualan - $totalPembelian,
        ]);
    }
}