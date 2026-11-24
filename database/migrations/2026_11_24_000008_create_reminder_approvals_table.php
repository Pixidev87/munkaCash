<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('fingerprint', 64);
            $table->string('template_version', 32);
            $table->string('invoice_number', 100);
            $table->unsignedBigInteger('outstanding_amount');
            $table->char('currency', 3);
            $table->timestamp('approved_at')->index();
            $table->timestamp('expires_at');
            $table->index(['invoice_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_approvals');
    }
};
