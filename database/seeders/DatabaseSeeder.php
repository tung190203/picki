<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Badge;
use App\Models\Banner;
use App\Models\Club\Club;
use App\Models\CompetitionLocation;
use App\Models\CompetitionLocationYard;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->count(10)->create();
        CompetitionLocation::factory()->count(10)->create();
        CompetitionLocationYard::factory()->count(10)->create();
        Club::factory()->count(10)->create();
        Banner::factory()->count(10)->create();
        Badge::factory()->count(10)->create();

        // Tournament & Club fake data
        $this->call([
            UserBadgeSeeder::class,
            ClubNotificationTypeSeeder::class,
            ClubMemberSeeder::class,
            ClubFakeDataSeeder::class,
            SportSeeder::class,
            RefereeSeeder::class,
            TournamentFullSeeder::class,
            MiniTournamentFullSeeder::class,
            FollowSeeder::class,
            SystemNotificationSeeder::class,
            DinkhausPickleballClubSeeder::class,
        ]);
    }
}
