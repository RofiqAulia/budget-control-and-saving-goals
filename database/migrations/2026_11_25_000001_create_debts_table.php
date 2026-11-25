<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 12)->default('debt'); // debt = you owe, receivable = owed to you
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('minimum_payment', 15, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_settled')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_settled']);
        });

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('debts');
    }
};
