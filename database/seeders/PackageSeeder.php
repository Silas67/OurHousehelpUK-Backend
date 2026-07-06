<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name'         => 'Cheryl',
                'days_per_week' => 1,
                'cost'         => 450.00,
                'description'  => 'Perfect for occasional help — one dedicated day per week for cleaning, laundry, or general household tasks.',
                'is_active'    => true,
                'sort_order'   => 1,
            ],
            [
                'name'         => 'Cheryl Plus',
                'days_per_week' => 2,
                'cost'         => 850.00,
                'description'  => 'Twice-weekly support to keep your home consistently clean and organised.',
                'is_active'    => true,
                'sort_order'   => 2,
            ],
            [
                'name'         => 'Jaden',
                'days_per_week' => 3,
                'cost'         => 1250.00,
                'description'  => 'Mid-week domestic cover — ideal for busy families needing regular, reliable home help.',
                'is_active'    => true,
                'sort_order'   => 3,
            ],
            [
                'name'         => 'Jaden Plus',
                'days_per_week' => 4,
                'cost'         => 1600.00,
                'description'  => 'Four days of professional domestic support, covering everything from housekeeping to errands.',
                'is_active'    => true,
                'sort_order'   => 4,
            ],
            [
                'name'         => 'Rex',
                'days_per_week' => 5,
                'cost'         => 1950.00,
                'description'  => 'Full five-day domestic staffing — near full-time, vetted, trusted help for your household.',
                'is_active'    => true,
                'sort_order'   => 5,
            ],
        ];

        foreach ($packages as $data) {
            Package::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
