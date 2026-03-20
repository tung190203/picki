<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mini_participants', 'is_guest')) {
            Schema::table('mini_participants', function (Blueprint $table) {
                $table->boolean('is_guest')
                    ->default(false)
                    ->after('payment_status')
                    ->comment('true: la khach moi, false: thanh vien binh thuong');
            });
        }

        Schema::table('mini_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('mini_participants', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('is_guest')->comment('Ten hien thi cua khach moi');
            }

            if (!Schema::hasColumn('mini_participants', 'guest_phone')) {
                $table->string('guest_phone', 20)->nullable()->after('guest_name')->comment('So dien thoai cua khach moi');
            }
        });

        if (Schema::hasColumn('mini_participants', 'guest_info')) {
            DB::unprepared("
                UPDATE mini_participants
                SET guest_name   = TRIM(BOTH '\"' FROM JSON_EXTRACT(guest_info, '$.name')),
                    guest_phone  = TRIM(BOTH '\"' FROM JSON_EXTRACT(guest_info, '$.phone'))
                WHERE is_guest = 1 AND guest_info IS NOT NULL AND guest_info != ''
            ");
            Schema::table('mini_participants', function (Blueprint $table) {
                $table->dropColumn('guest_info');
            });
        }

        if (!Schema::hasColumn('mini_participants', 'guarantor_user_id')) {
            if (Schema::hasColumn('mini_participants', 'guarantor_id')) {
                DB::unprepared('ALTER TABLE mini_participants ADD COLUMN guarantor_user_id BIGINT UNSIGNED NULL AFTER user_id');
                DB::unprepared('UPDATE mini_participants SET guarantor_user_id = guarantor_id WHERE guarantor_id IS NOT NULL');
                DB::unprepared('ALTER TABLE mini_participants DROP FOREIGN KEY mini_participants_guarantor_id_foreign');
                DB::unprepared('ALTER TABLE mini_participants DROP COLUMN guarantor_id');
            } else {
                Schema::table('mini_participants', function (Blueprint $table) {
                    $table->unsignedBigInteger('guarantor_user_id')
                        ->nullable()
                        ->after('guest_phone')
                        ->comment('Nguoi bao lanh khach moi');
                });
            }
        }

        Schema::table('mini_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('mini_participants', 'estimated_level_min')) {
                $table->decimal('estimated_level_min', 3, 1)
                    ->nullable()
                    ->after('guarantor_user_id')
                    ->comment('Muc thap nhat trong khoang trinh do uoc tinh (1.0 - 8.0)');
            }
            if (!Schema::hasColumn('mini_participants', 'estimated_level_max')) {
                $table->decimal('estimated_level_max', 3, 1)
                    ->nullable()
                    ->after('estimated_level_min')
                    ->comment('Muc cao nhat trong khoang trinh do uoc tinh (1.0 - 8.0)');
            }
        });

        if (Schema::hasColumn('mini_participants', 'is_guest')) {
            try {
                Schema::table('mini_participants', function (Blueprint $table) {
                    $table->index(['mini_tournament_id', 'is_guest'], 'idx_mini_participants_is_guest');
                });
            } catch (\Exception $e) {
            }
        }

        if (Schema::hasColumn('mini_participants', 'guarantor_user_id')) {
            try {
                Schema::table('mini_participants', function (Blueprint $table) {
                    $table->foreign('guarantor_user_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (\Exception $e) {
            }
        }

        Schema::table('mini_participant_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('mini_participant_payments', 'guest_ids')) {
                $table->json('guest_ids')
                    ->nullable()
                    ->after('confirmed_by')
                    ->comment('Danh sach ID cac guest dong tien cung luc');
            }
        });

        try {
            Schema::table('mini_participant_payments', function (Blueprint $table) {
                $table->dropUnique('unique_participant_payment');
                $table->index(['mini_tournament_id', 'participant_id'], 'idx_mini_participant_payments_participant');
            });
        } catch (\Exception $e) {
        }
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participants', 'estimated_level_min')) {
                $table->dropColumn('estimated_level_min');
            }
            if (Schema::hasColumn('mini_participants', 'estimated_level_max')) {
                $table->dropColumn('estimated_level_max');
            }
            if (Schema::hasColumn('mini_participants', 'guarantor_user_id')) {
                $table->dropForeign(['guarantor_user_id']);
                $table->dropColumn('guarantor_user_id');
            }
            if (Schema::hasColumn('mini_participants', 'guest_phone')) {
                $table->dropColumn('guest_phone');
            }
            if (Schema::hasColumn('mini_participants', 'guest_name')) {
                $table->dropColumn('guest_name');
            }
            if (Schema::hasColumn('mini_participants', 'is_guest')) {
                $table->dropIndex('idx_mini_participants_is_guest');
                $table->dropColumn('is_guest');
            }
        });

        Schema::table('mini_participant_payments', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participant_payments', 'guest_ids')) {
                $table->dropColumn('guest_ids');
            }
            try {
                $table->dropIndex('idx_mini_participant_payments_participant');
                $table->unique(['mini_tournament_id', 'participant_id'], 'unique_participant_payment');
            } catch (\Exception $e) {
            }
        });
    }
};
