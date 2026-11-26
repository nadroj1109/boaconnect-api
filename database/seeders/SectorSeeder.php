<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectors = [

            [
                'sector_code' => 'employed',
                'sector' => 'Employed',
            ],

            [
                'sector_code' => 'unemployed',
                'sector' => 'Unemployed',
            ],

            [
                'sector_code' => 'ofw',
                'sector' => 'Overseas Filipino Worker (OFW)',
            ],

            [
                'sector_code' => 'indigenous',
                'sector' => 'Indigenous People (IP)',
            ],

            [
                'sector_code' => 'student',
                'sector' => 'Student',
            ],

            [
                'sector_code' => 'osc',
                'sector' => 'Out of School Children (OSC)',
            ],

            [
                'sector_code' => 'osy',
                'sector' => 'Out of School Youth (OSY)',
            ],

            [
                'sector_code' => 'migrant',
                'sector' => 'Migrant',
            ],

            [
                'sector_code' => 'refugee',
                'sector' => 'Refugee',
            ],

            [
                'sector_code' => 'senior_citizen',
                'sector' => 'Senior Citizen (SC)',
                'is_registrable' => true,
            ],

            [
                'sector_code' => 'pwd',
                'sector' => 'Persons with Disability (PWD)',
                'is_registrable' => true,
            ],

            [
                'sector_code' => 'solo_parent',
                'sector' => 'Single / Solo Parent',
                'is_registrable' => true,
            ],

        ];

        foreach ($sectors as $index => $sector) {

            Sector::updateOrCreate(

                [
                    'sector_code' => $sector['sector_code'],
                ],

                [
                    ...$sector,

                    'sort_order' => $index + 1,

                ]

            );

        }

    }
}
