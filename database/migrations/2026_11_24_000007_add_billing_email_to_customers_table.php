<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('billing_email', 254)->nullable();
            $table->timestamp('billing_email_confirmed_at')->nullable();
            $table->foreignId('billing_email_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('billing_email_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billing_email_confirmed_by');
            $table->dropColumn(['billing_email', 'billing_email_confirmed_at', 'billing_email_revision']);
        });
    }
};
