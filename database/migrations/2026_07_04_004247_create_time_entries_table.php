<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable(); // null = timer running
            $table->unsignedInteger('duration_minutes')->nullable(); // computed on stop
            $table->unsignedInteger('hourly_rate')->nullable(); // cents, snapshot at log time
            $table->boolean('billable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
