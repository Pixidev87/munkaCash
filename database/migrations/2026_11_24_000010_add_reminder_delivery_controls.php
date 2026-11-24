<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('reminders_enabled')->default(false);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('do_not_contact')->default(false);
            $table->timestamp('do_not_contact_updated_at')->nullable();
            $table->foreignId('do_not_contact_updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('do_not_contact_updated_by');
            $table->dropColumn(['do_not_contact', 'do_not_contact_updated_at']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('reminders_enabled');
        });
    }
};
