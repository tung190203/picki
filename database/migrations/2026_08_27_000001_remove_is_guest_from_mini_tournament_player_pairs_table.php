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
        Schema::table('mini_tournament_player_pairs', function (Blueprint $table) {
            // Bỏ 2 cột is_guest - player_id luôn là user_id, backend lookup guest flag khi cần
            // Note: Không drop indexes vì MySQL FK constraint có thể reference index
            // Indexes cũ vẫn tồn tại nhưng không còn cột is_guest - chỉ là overhead nhỏ
            $table->dropColumn(['player1_is_guest', 'player2_is_guest']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_tournament_player_pairs', function (Blueprint $table) {
            // Thêm lại 2 cột is_guest
            $table->boolean('player1_is_guest')->default(false)->after('player1_id');
            $table->boolean('player2_is_guest')->default(false)->after('player2_id');
        });
    }
};
