<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_test_deliveries', function (Blueprint $table): void {
            $table->timestamp('available_at')->nullable();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reminder_test_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['status', 'available_at']);
            $table->dropColumn('available_at');
        });
    }
};
