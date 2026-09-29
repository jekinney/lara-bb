<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lower-cased name, so "Mira" and "mira" cannot both register on any database.
            $table->string('username_clean')->nullable()->unique()->after('name');
            // active, awaiting_email or inactive. Only active accounts can log in.
            $table->string('status', 20)->default('active')->after('is_founder');
            $table->foreignId('primary_group_id')->nullable()->after('status')->constrained('groups')->nullOnDelete();
            $table->string('timezone', 64)->nullable();
            $table->string('preferred_theme', 64)->nullable();
            $table->string('preferred_mode', 10)->default('auto');
            $table->string('preferred_editor', 20)->nullable();
            $table->string('signature', 255)->nullable();
            $table->text('about')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->timestamp('last_visit_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_group_id');
            $table->dropUnique(['username_clean']);
            $table->dropColumn([
                'username_clean', 'status', 'timezone', 'preferred_theme', 'preferred_mode',
                'preferred_editor', 'signature', 'about', 'location', 'website', 'last_visit_at',
            ]);
        });
    }
};
