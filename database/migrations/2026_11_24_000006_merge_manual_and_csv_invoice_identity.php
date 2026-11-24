<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['organization_id', 'invoice_number']);
            $table->dropUnique(['organization_id', 'source', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['organization_id', 'source', 'invoice_number']);
            $table->dropUnique(['organization_id', 'invoice_number']);
        });
    }
};
