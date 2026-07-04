<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->index('project_id');
            $table->index('user_id');
            $table->index('started_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('workspace_id');
            $table->index('client_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('paid_at');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['started_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['paid_at']);
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });
    }
};
