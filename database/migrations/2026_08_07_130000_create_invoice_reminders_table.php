<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_overdue');
            $table->string('sent_to');
            $table->timestamp('sent_at');
            $table->timestamps();

            // The idempotency guarantee: one reminder per invoice per milestone,
            // so a double scheduler run cannot mail the client twice.
            $table->unique(['invoice_id', 'days_overdue']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('send_payment_reminders')->default(true)->after('payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('send_payment_reminders');
        });
    }
};
