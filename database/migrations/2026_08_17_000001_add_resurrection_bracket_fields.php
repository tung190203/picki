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
        if (Schema::hasTable('matches') && !Schema::hasColumn('matches', 'bracket_type')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->string('bracket_type')->default('main')->after('is_third_place')->comment('main: Nhánh chính, sub: Nhánh Tái sinh');
            });
        }

        if (Schema::hasTable('tournament_types')) {
            Schema::table('tournament_types', function (Blueprint $table) {
                if (!Schema::hasColumn('tournament_types', 'has_resurrection_bracket')) {
                    $table->boolean('has_resurrection_bracket')->default(false)->after('format');
                }
                if (!Schema::hasColumn('tournament_types', 'main_bracket_name')) {
                    $table->string('main_bracket_name')->nullable()->default('Giải chính')->after('has_resurrection_bracket');
                }
                if (!Schema::hasColumn('tournament_types', 'sub_bracket_name')) {
                    $table->string('sub_bracket_name')->nullable()->default('Giải Tái sinh')->after('main_bracket_name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('matches') && Schema::hasColumn('matches', 'bracket_type')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropColumn('bracket_type');
            });
        }

        if (Schema::hasTable('tournament_types')) {
            Schema::table('tournament_types', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('tournament_types', 'has_resurrection_bracket')) {
                    $columnsToDrop[] = 'has_resurrection_bracket';
                }
                if (Schema::hasColumn('tournament_types', 'main_bracket_name')) {
                    $columnsToDrop[] = 'main_bracket_name';
                }
                if (Schema::hasColumn('tournament_types', 'sub_bracket_name')) {
                    $columnsToDrop[] = 'sub_bracket_name';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
