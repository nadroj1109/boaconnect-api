<?php

namespace Database\Seeders;

use App\Models\ComplaintType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComplaintTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Noise Complaint',
                'description' => 'Complaints involving excessive or disruptive noise.',
            ],
            [
                'name' => 'Property Dispute',
                'description' => 'Disputes involving ownership, use, or possession of property.',
            ],
            [
                'name' => 'Boundary Dispute',
                'description' => 'Disputes involving property boundaries or land boundaries.',
            ],
            [
                'name' => 'Animal Complaint',
                'description' => 'Complaints involving stray, dangerous, or nuisance animals.',
            ],
            [
                'name' => 'Harassment',
                'description' => 'Complaints involving harassment, threats, or intimidation.',
            ],
            [
                'name' => 'Public Disturbance',
                'description' => 'Complaints involving disturbances affecting public peace and order.',
            ],
            [
                'name' => 'Environmental Complaint',
                'description' => 'Complaints involving waste, pollution, sanitation, or other environmental concerns.',
            ],
            [
                'name' => 'Other',
                'description' => 'Complaints that do not fall under the listed complaint types.',
            ],
        ];

        foreach ($types as $type) {
            ComplaintType::updateOrCreate(
                [
                    'name' => $type['name'],
                ],
                [
                    'description' => $type['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
