<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_purges', function (Blueprint $table): void {
            // Deliberately independent of the organization being deleted.
            $table->unsignedBigInteger('organization_id')->primary();
            $table->timestamp('purged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_purges');
    }
};
