<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('is_invite_by_organizer');
            $table->string('guest_name', 255)->nullable()->after('is_guest');
            $table->string('guest_phone', 20)->nullable()->after('guest_name');
            $table->string('guest_avatar')->nullable()->after('guest_phone');
            $table->foreignId('guarantor_user_id')->nullable()->constrained('users')->nullOnDelete()->after('guest_avatar');
            $table->decimal('estimated_level', 2, 1)->nullable()->comment('Trinh do guest 1.0-2.5')->after('guarantor_user_id');
            $table->boolean('is_pending_confirmation')->default(false)->after('estimated_level');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropForeign(['guarantor_user_id']);
            $table->dropColumn([
                'is_guest',
                'guest_name',
                'guest_phone',
                'guest_avatar',
                'guarantor_user_id',
                'estimated_level',
                'is_pending_confirmation',
            ]);
        });
    }
};
