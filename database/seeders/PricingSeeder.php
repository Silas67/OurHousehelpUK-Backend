<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingSeeder extends Seeder
{
    public function run(): void
    {
        // Apartment type monthly costs — based on UK average weekly cleaning × 4
        $apartmentTypes = [
            ['name' => 'Studio / 1 Room', 'cost' => 200.00, 'sort_order' => 1],
            ['name' => '1 Bedroom',       'cost' => 250.00, 'sort_order' => 2],
            ['name' => '2 Bedrooms',      'cost' => 320.00, 'sort_order' => 3],
            ['name' => '3 Bedrooms',      'cost' => 420.00, 'sort_order' => 4],
            ['name' => '4 Bedrooms',      'cost' => 500.00, 'sort_order' => 5],
            ['name' => '5+ Bedrooms',     'cost' => 600.00, 'sort_order' => 6],
        ];

        foreach ($apartmentTypes as $data) {
            DB::table('apartment_types')
                ->where('name', $data['name'])
                ->update(['cost' => $data['cost']]);
        }

        // Service add-on monthly costs
        $services = [
            ['slug' => 'cleaning',     'base_cost' => 0.00],    // PRS — cost comes from apartment type
            ['slug' => 'cooking',      'base_cost' => 380.00],
            ['slug' => 'laundry',      'base_cost' => 180.00],
            ['slug' => 'childcare',    'base_cost' => 650.00],
            ['slug' => 'elderly_care', 'base_cost' => 550.00],
            ['slug' => 'pet_care',     'base_cost' => 0.00],    // PSS — flat extra fee applies
        ];

        foreach ($services as $data) {
            DB::table('house_services')
                ->where('slug', $data['slug'])
                ->update(['base_cost' => $data['base_cost']]);
        }

        // Extra service flat fee (PSS services like pet care)
        DB::table('extra_service_costs')->update(['cost' => 80.00]);
    }
}
