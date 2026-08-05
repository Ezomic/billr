<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Mirrors invoice_time_entries: marks which fixed-price projects have been
    // billed, so the same fee cannot be invoiced twice.
    public function up(): void
    {
        Schema::create('invoice_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_projects');
    }
};
