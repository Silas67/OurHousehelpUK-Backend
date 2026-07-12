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

        // hourly_rate = midpoint of the displayed £min–max/hr range
        $services = [
            ['slug' => 'cleaning',        'service_name' => 'General Cleaning',  'base_cost' => 0.00, 'hourly_rate' => 14.00],
            ['slug' => 'deep_cleaning',   'service_name' => 'Deep Cleaning',     'base_cost' => 0.00, 'hourly_rate' => 17.50],
            ['slug' => 'laundry',         'service_name' => 'Laundry/Ironing',   'base_cost' => 0.00, 'hourly_rate' => 13.50],
            ['slug' => 'ironing',         'service_name' => 'Ironing',           'base_cost' => 0.00, 'hourly_rate' => 13.50],
            ['slug' => 'cooking',         'service_name' => 'Cooking/Meal Prep', 'base_cost' => 0.00, 'hourly_rate' => 16.00],
            ['slug' => 'childcare',       'service_name' => 'Childcare/Nanny',   'base_cost' => 0.00, 'hourly_rate' => 15.00],
            ['slug' => 'elderly_care',    'service_name' => 'Elderly Care',      'base_cost' => 0.00, 'hourly_rate' => 14.00],
            ['slug' => 'errands',         'service_name' => 'Errand Running',    'base_cost' => 0.00, 'hourly_rate' => 13.00],
            ['slug' => 'window_cleaning', 'service_name' => 'Window Cleaning',   'base_cost' => 0.00, 'hourly_rate' => 13.50],
        ];

        foreach ($services as $data) {
            DB::table('house_services')->updateOrInsert(
                ['slug' => $data['slug']],
                ['service_name' => $data['service_name'], 'base_cost' => $data['base_cost'], 'hourly_rate' => $data['hourly_rate']]
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
