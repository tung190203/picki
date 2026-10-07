<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update badges table
        Schema::table('badges', function (Blueprint $table) {
            $table->string('code')->unique()->after('id');
            $table->string('type', 50)->default('role')->after('code');
            $table->integer('priority')->default(0)->after('type');
            $table->boolean('is_active')->default(true)->after('priority');
        });

        // 2. Insert default badges
        $now = now();
        $verifiedId = DB::table('badges')->insertGetId(['code' => 'VERIFIED', 'name' => 'Verified', 'type' => 'role', 'created_at' => $now, 'updated_at' => $now]);
        $anchorId = DB::table('badges')->insertGetId(['code' => 'ANCHOR', 'name' => 'Anchor', 'type' => 'role', 'created_at' => $now, 'updated_at' => $now]);
        $championId = DB::table('badges')->insertGetId(['code' => 'CHAMPION', 'name' => 'Champion', 'type' => 'achievement', 'created_at' => $now, 'updated_at' => $now]);
        $pickiId = DB::table('badges')->insertGetId(['code' => 'PICKI', 'name' => 'Picki', 'type' => 'role', 'created_at' => $now, 'updated_at' => $now]);

        $map = [
            'VERIFIED' => $verifiedId,
            'ANCHOR' => $anchorId,
            'CHAMPION' => $championId,
            'PICKI' => $pickiId,
        ];

        // 3. Update user_badges table
        Schema::table('user_badges', function (Blueprint $table) {
            $table->unsignedBigInteger('badge_id')->nullable()->after('user_id');
            $table->boolean('is_featured')->default(false)->after('badge_id');
            $table->timestamp('acquired_at')->nullable()->after('is_featured');
        });

        // 4. Migrate data
        foreach ($map as $type => $badgeId) {
            DB::table('user_badges')->where('badge_type', $type)->update([
                'badge_id' => $badgeId,
                'acquired_at' => DB::raw('created_at'),
            ]);
        }
        
        // Clean up remaining records that might have invalid badge_type
        DB::table('user_badges')->whereNull('badge_id')->delete();

        // 5. Add new constraints first so that user_id still has an index
        Schema::table('user_badges', function (Blueprint $table) {
            $table->unique(['user_id', 'badge_id']);
            $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
        });

        // 6. Now drop old column and constraints
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'badge_type']);
            $table->dropColumn('badge_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropForeign(['badge_id']);
            $table->dropUnique(['user_id', 'badge_id']);
            
            $table->string('badge_type', 50)->nullable()->after('user_id');
        });

        // Reverse data migration
        $badges = DB::table('badges')->whereIn('code', ['VERIFIED', 'ANCHOR', 'CHAMPION', 'PICKI'])->get();
        foreach ($badges as $badge) {
            DB::table('user_badges')->where('badge_id', $badge->id)->update([
                'badge_type' => $badge->code,
            ]);
        }

        Schema::table('user_badges', function (Blueprint $table) {
            $table->dropColumn(['badge_id', 'is_featured', 'acquired_at']);
            $table->unique(['user_id', 'badge_type']);
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->dropColumn(['code', 'type', 'priority', 'is_active']);
        });
        
        DB::table('badges')->truncate();
    }
};
