<?php

namespace Database\Seeders;

use App\Models\CompetitionLocation;
use App\Models\Location;
use Illuminate\Database\Seeder;

class DinkhausPickleballClubSeeder extends Seeder
{
    public function run(): void
    {
        $location = Location::firstOrCreate(
            ['slug' => 'hung-yen'],
            ['name' => 'Hưng Yên']
        );

        $locationHcm = Location::firstOrCreate(
            ['slug' => 'ho-chi-minh'],
            ['name' => 'Hồ Chí Minh']
        );

        $locationHN = Location::firstOrCreate(
            ['slug' => 'ha-noi'],
            ['name' => 'Hà Nội']
        );

        CompetitionLocation::updateOrCreate(
            ['name' => 'Dinkhaus Pickleball Club'],
            [
                'location_id' => $location->id,
                'latitude' => 20.9547805,
                'longitude' => 105.9492412,
                'address' => 'Hồ Trạm Bơm, Long Hưng, Nghĩa Trụ, Hưng Yên 10000, Vietnam',
                'phone' => '+84 328 197 879',
                'opening_time' => '00:00:00',
                'closing_time' => '23:59:00',
                'note_booking' => 'Mở cửa 24/7',
                'image' => null,
                'avatar_url' => null,
                'website' => null,
            ]
        );
    }
}
