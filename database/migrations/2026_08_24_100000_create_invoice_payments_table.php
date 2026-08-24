<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('amount'); // cents
            $table->date('paid_on');
            $table->string('method')->nullable(); // bank|card|cash|stripe|other
            $table->string('note')->nullable();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
