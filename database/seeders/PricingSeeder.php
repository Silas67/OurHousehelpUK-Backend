<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        // Monthly cleaning cost based on UK hourly rate (£13/hr, 1 visit/week)
        $apartmentTypes = [
            ['name' => 'Studio / 1 Room', 'cost' => 110.00, 'hourly_rate' => 13.00],
            ['name' => '1 Bedroom',       'cost' => 140.00, 'hourly_rate' => 13.00],
            ['name' => '2 Bedrooms',      'cost' => 170.00, 'hourly_rate' => 13.00],
            ['name' => '3 Bedrooms',      'cost' => 225.00, 'hourly_rate' => 13.00],
            ['name' => '4 Bedrooms',      'cost' => 280.00, 'hourly_rate' => 13.00],
            ['name' => '5+ Bedrooms',     'cost' => 395.00, 'hourly_rate' => 13.00],
        ];

        foreach ($apartmentTypes as $data) {
            DB::table('apartment_types')->updateOrInsert(
                ['name' => $data['name']],
                ['cost' => $data['cost'], 'hourly_rate' => $data['hourly_rate']]
            );
        }

        // Monthly base cost per service based on UK hourly staff rates
        $services = [
            ['slug' => 'cleaning',     'service_name' => 'Cleaning',    'base_cost' => 0.00],    // apartment-based
            ['slug' => 'cooking',      'service_name' => 'Cooking',      'base_cost' => 280.00], // £13/hr × 1h/day × 5d × 4.3wk
            ['slug' => 'laundry',      'service_name' => 'Laundry',      'base_cost' => 110.00], // £12.50/hr × 2h/wk × 4.3wk
            ['slug' => 'childcare',    'service_name' => 'Childcare',    'base_cost' => 480.00], // £14/hr × 8h/wk × 4.3wk
            ['slug' => 'elderly_care', 'service_name' => 'Elderly Care', 'base_cost' => 480.00], // £14/hr × 8h/wk × 4.3wk
            ['slug' => 'errands',      'service_name' => 'Errands',      'base_cost' => 0.00],    // flat PSS add-on
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
