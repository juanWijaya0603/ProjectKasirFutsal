<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Nullable sementara agar seeder lama yang belum memiliki
             * invoice_number tetap dapat dijalankan.
             *
             * Transaksi baru dari controller tetap akan selalu
             * mendapatkan nomor invoice.
             */
            $table->string('invoice_number')
                ->nullable()
                ->unique();

            $table->dateTime('sale_date');

            $table->decimal('total_price', 10, 2)
                ->default(0);

            $table->enum('status', [
                'draft',
                'paid',
                'cancelled',
            ])->default('draft');

            $table->string('payment_method', 30)
                ->default('cash');

            $table->dateTime('paid_at')
                ->nullable();

            $table->dateTime('confirmed_at')
                ->nullable();

            $table->dateTime('cancelled_at')
                ->nullable();

            $table->timestamps();

            /*
             * Mempercepat query daftar draft berdasarkan kasir
             * dan laporan berdasarkan status pembayaran.
             */
            $table->index(['user_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};