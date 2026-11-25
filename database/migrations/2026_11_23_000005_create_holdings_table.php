<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 30);
            $table->string('name');
            $table->string('asset_type', 20)->default('stock'); // stock | mutual_fund | crypto | gold | bond | cash
            $table->decimal('quantity', 20, 8)->default(0);
            $table->decimal('avg_price', 18, 2)->default(0);
            $table->decimal('current_price', 18, 2)->nullable();
            $table->decimal('dividend_received', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asset_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holdings');
    }
};
