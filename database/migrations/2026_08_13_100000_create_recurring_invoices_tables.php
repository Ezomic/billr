<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('interval'); // monthly|quarterly|yearly
            $table->date('start_on');
            $table->date('end_on')->nullable();
            $table->date('next_run_on');
            $table->string('currency', 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active|paused
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_run_on']);
        });

        Schema::create('recurring_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->string('unit')->default('fixed');
            $table->unsignedInteger('unit_price'); // cents
            $table->unsignedInteger('amount'); // cents
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Generation idempotency lives here rather than in the command: one
        // invoice per schedule per period, enforced by the database.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recurring_invoice_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->date('recurring_period')->nullable()->after('recurring_invoice_id');

            $table->unique(['recurring_invoice_id', 'recurring_period']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['recurring_invoice_id', 'recurring_period']);
            $table->dropConstrainedForeignId('recurring_invoice_id');
            $table->dropColumn('recurring_period');
        });

        Schema::dropIfExists('recurring_invoice_lines');
        Schema::dropIfExists('recurring_invoices');
    }
};
