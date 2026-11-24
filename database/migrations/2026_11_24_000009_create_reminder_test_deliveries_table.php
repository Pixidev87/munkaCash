<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_test_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('reminder_approval_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('reason', 32)->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_test_deliveries');
    }
};
