<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('closeout_started_at')->nullable();
            $table->timestamp('export_until')->nullable();
            $table->foreignId('closeout_started_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closeout_started_by');
            $table->dropColumn(['closeout_started_at', 'export_until']);
        });
    }
};
