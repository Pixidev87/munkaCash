<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_deadline_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('previous_due_date');
            $table->date('due_date');
            $table->timestamp('occurred_at')->index();
            $table->index(['invoice_id', 'occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_deadline_events');
    }
};
