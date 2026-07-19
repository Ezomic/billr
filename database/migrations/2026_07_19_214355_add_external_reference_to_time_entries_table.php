<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('external_source')->nullable()->after('billable');
            $table->string('external_ref')->nullable()->after('external_source');
            $table->unique(['external_source', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropUnique(['external_source', 'external_ref']);
            $table->dropColumn(['external_source', 'external_ref']);
        });
    }
};
