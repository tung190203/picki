<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_team_members', function (Blueprint $table) {
            // user_id nullable để chứa thành viên ảo (ClubVirtualMember) — pattern giống MiniParticipant
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('is_guest');
            $table->string('guest_avatar')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('mini_team_members', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_avatar']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};