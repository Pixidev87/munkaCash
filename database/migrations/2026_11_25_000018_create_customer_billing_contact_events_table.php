<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_billing_contact_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->timestamp('occurred_at')->index();
            $table->index(['customer_id', 'occurred_at', 'id'], 'billing_contact_events_customer_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_billing_contact_events');
    }
};
