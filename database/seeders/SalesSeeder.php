<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        /*
         * Penjualan hanya boleh dibuat atas nama user kasir.
         */
        $kasirIds = DB::table('users')
            ->where('role', 'kasir')
            ->pluck('id')
            ->toArray();

        $productData = DB::table('products')
            ->select('id', 'price', 'stock')
            ->where('stock', '>', 0)
            ->get();

        if (empty($kasirIds)) {
            $this->command->warn(
                'Tidak ada user kasir. Jalankan UserSeeder terlebih dahulu.'
            );

            return;
        }

        if ($productData->isEmpty()) {
            $this->command->warn(
                'Tidak ada produk dengan stok tersedia. Jalankan ProductSeeder terlebih dahulu.'
            );

            return;
        }

        for ($i = 1; $i <= 30; $i++) {
            $saleDate = Carbon::instance(
                $faker->dateTimeBetween('-1 year', 'now')
            );

            $userId = $faker->randomElement($kasirIds);

            /*
             * Nomor urutan dan timestamp membuat invoice tetap unik,
             * meskipun beberapa transaksi memiliki waktu yang sama.
             */
            $invoiceNumber = sprintf(
                'INV-SEED-%03d-%s',
                $i,
                $saleDate->format('YmdHis')
            );

            /*
             * Buat penjualan sementara dengan total 0.
             * Total akan diperbarui setelah seluruh item dibuat.
             */
            $saleId = DB::table('sales')->insertGetId([
                'user_id' => $userId,
                'invoice_number' => $invoiceNumber,
                'sale_date' => $saleDate,
                'total_price' => 0,
                'status' => 'paid',
                'payment_method' => 'cash',
                'paid_at' => $saleDate,
                'confirmed_at' => $saleDate,
                'cancelled_at' => null,
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ]);

            $totalSalePrice = 0;

            $numberOfItemsInSale = $faker->numberBetween(
                1,
                min(3, $productData->count())
            );

            /*
             * Mengambil produk berbeda untuk setiap transaksi.
             */
            $selectedProducts = $productData
                ->random($numberOfItemsInSale);

            /*
             * Collection::random() dapat menghasilkan satu model
             * jika jumlahnya satu, jadi dibungkus kembali sebagai collection.
             */
            $selectedProducts = collect($selectedProducts);

            foreach ($selectedProducts as $product) {
                $maximumQuantity = min(5, (int) $product->stock);

                if ($maximumQuantity < 1) {
                    continue;
                }

                $quantity = $faker->numberBetween(
                    1,
                    $maximumQuantity
                );

                $pricePerUnit = (float) $product->price;
                $subtotal = $quantity * $pricePerUnit;

                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price_per_unit' => $pricePerUnit,
                    'subtotal' => $subtotal,
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);

                $totalSalePrice += $subtotal;
            }

            DB::table('sales')
                ->where('id', $saleId)
                ->update([
                    'total_price' => $totalSalePrice,
                    'updated_at' => $saleDate,
                ]);
        }

        $this->command->info(
            'Berhasil membuat 30 transaksi penjualan berstatus paid.'
        );
    }
}
