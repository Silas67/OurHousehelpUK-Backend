<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        $apartmentTypes = [
            ['name' => 'Studio / 1 Room', 'cost' => 200.00],
            ['name' => '1 Bedroom',       'cost' => 250.00],
            ['name' => '2 Bedrooms',      'cost' => 320.00],
            ['name' => '3 Bedrooms',      'cost' => 420.00],
            ['name' => '4 Bedrooms',      'cost' => 500.00],
            ['name' => '5+ Bedrooms',     'cost' => 600.00],
        ];

        foreach ($apartmentTypes as $data) {
            DB::table('apartment_types')->updateOrInsert(
                ['name' => $data['name']],
                ['cost' => $data['cost']]
            );
        }

        $services = [
            ['slug' => 'cleaning',     'service_name' => 'Cleaning',    'base_cost' => 0.00],
            ['slug' => 'cooking',      'service_name' => 'Cooking',      'base_cost' => 380.00],
            ['slug' => 'laundry',      'service_name' => 'Laundry',      'base_cost' => 180.00],
            ['slug' => 'childcare',    'service_name' => 'Childcare',    'base_cost' => 650.00],
            ['slug' => 'elderly_care', 'service_name' => 'Elderly Care', 'base_cost' => 550.00],
            ['slug' => 'errands',      'service_name' => 'Errands',      'base_cost' => 0.00],
        ];

        foreach ($services as $data) {
            DB::table('house_services')->updateOrInsert(
                ['slug' => $data['slug']],
                ['service_name' => $data['service_name'], 'base_cost' => $data['base_cost']]
            );
        }

        // Rename old pet_care record to errands if it still exists
        DB::table('house_services')
            ->where('slug', 'pet_care')
            ->update(['slug' => 'errands', 'service_name' => 'Errands']);

        DB::table('extra_service_costs')->update(['cost' => 80.00]);

        $managementPlans = [
            [
                'name'             => 'Client Managed',
                'slug'             => 'client-managed',
                'description'      => 'You manage the relationship directly with your staff. No platform fee.',
                'platform_markup'  => 0.00,
                'is_active'        => true,
            ],
            [
                'name'             => 'Company Managed',
                'slug'             => 'company-managed',
                'description'      => 'We handle payroll, compliance, and replacements. 20% platform fee applies.',
                'platform_markup'  => 20.00,
                'is_active'        => true,
            ],
        ];

        foreach ($managementPlans as $data) {
            DB::table('management_plans')->updateOrInsert(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
