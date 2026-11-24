<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('customer_id');
            $table->string('source', 32)->default('manual');
            $table->string('invoice_number', 100);
            $table->date('due_date');
            $table->char('currency', 3)->default('HUF');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('outstanding_amount')->nullable();
            $table->string('status', 32)->default('unchecked');
            $table->string('status_source', 32)->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();

            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
            $table->unique(['organization_id', 'source', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
