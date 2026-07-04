<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'stripe_payment_link')) {
                $table->string('stripe_payment_link')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('invoices', 'stripe_session_id')) {
                $table->string('stripe_session_id')->nullable()->after('stripe_payment_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_link', 'stripe_session_id']);
        });
    }
};
