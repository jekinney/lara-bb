<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('color', 7)->nullable();
            // System groups are built in and cannot be deleted. Custom groups are made by admins.
            $table->string('type', 10)->default('custom');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->boolean('is_pending')->default(false);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        $now = now();
        DB::table('groups')->insert(array_map(fn (array $group) => $group + ['type' => 'system', 'created_at' => $now, 'updated_at' => $now], [
            ['name' => 'Guests', 'slug' => 'guests', 'description' => 'Visitors who are not logged in', 'color' => null],
            ['name' => 'Registered users', 'slug' => 'registered', 'description' => 'Everyone with an account', 'color' => null],
            ['name' => 'Global moderators', 'slug' => 'global-moderators', 'description' => 'Moderators of every forum', 'color' => '#4ade80'],
            ['name' => 'Administrators', 'slug' => 'administrators', 'description' => 'Board administrators', 'color' => '#fb7185'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
