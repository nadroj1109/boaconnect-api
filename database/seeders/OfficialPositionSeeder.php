<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OfficialPosition;

class OfficialPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            'Punong Barangay',
            'Barangay Kagawad',
            'Barangay Secretary',
            'Barangay Treasurer',
            'SK Chairperson',
            'SK Kagawad',
        ];

        foreach ($positions as $name) {
            OfficialPosition::firstOrCreate([
                'name' => $name,
            ]);
        }
    }
}
