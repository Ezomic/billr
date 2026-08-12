<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Clients already override the workspace currency; terms follow the same
    // shape, so the workspace holds the default and the client may override it.
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->default(30)->after('currency');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });
    }
};
