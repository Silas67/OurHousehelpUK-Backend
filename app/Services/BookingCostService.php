<?php

namespace App\Services;

use App\Models\ApartmentType;
use App\Models\ExtraServiceCost;
use App\Models\HouseService;
use App\Models\ManagementPlan;
use App\Models\Package;

class BookingCostService
{
    protected array $durationDiscounts = [
        12 => 8,
        8  => 5,
        4  => 0,
        1  => 0,
    ];

    /**
     * Calculate base monthly service cost from slugs.
     * Main service (most expensive) = full price.
     * Secondary services = half price (PRS/PMS) or flat PSS fee.
     */
    public function calculateServiceCost(
        array $serviceSlugs,
        ?int $packageId = null,
        ?int $apartmentTypeId = null
    ): array {
        $services = HouseService::whereIn('slug', $serviceSlugs)->get();
        $package  = $packageId ? Package::find($packageId) : null;
        $extraFee = (float) (ExtraServiceCost::first()?->cost ?? 50);

        // Apartment cost applies only when a PRS service (Cleaning) is selected
        $apartmentCost = 0.0;
        $hasPRS = $services->contains(fn($s) => $s->service_type === 'PRS');
        if ($hasPRS && $apartmentTypeId) {
            $apartmentCost = (float) (ApartmentType::find($apartmentTypeId)?->cost ?? 0);
        }

        // Effective cost per service
        $effectiveCosts = [];
        foreach ($services as $s) {
            $effectiveCosts[$s->slug] = $s->service_type === 'PRS'
                ? ($apartmentCost ?: (float) $s->base_cost)
                : (float) $s->base_cost;
        }

        // Main = highest cost (full price)
        $mainSlug = !empty($effectiveCosts)
            ? array_keys($effectiveCosts, max($effectiveCosts))[0]
            : null;
        $baseCost = $mainSlug ? $effectiveCosts[$mainSlug] : 0.0;

        $extraTotal = 0.0;
        $breakdown  = [];

        foreach ($services as $s) {
            if ($s->slug === $mainSlug) {
                $breakdown[] = ['service' => $s->service_name, 'cost' => $baseCost, 'type' => 'main'];
                continue;
            }
            $cost = match ($s->service_type) {
                'PRS' => ($apartmentCost ?: (float) $s->base_cost) / 2,
                'PMS' => (float) $s->base_cost / 2,
                default => $extraFee,   // PSS
            };
            $extraTotal += $cost;
            $breakdown[] = ['service' => $s->service_name, 'cost' => $cost, 'type' => 'secondary'];
        }

        $packageCost = (float) ($package?->cost ?? 0);
        $grandTotal  = $baseCost + $packageCost + $extraTotal;

        return [
            'base_cost'          => $baseCost,
            'extra_service_cost' => $extraTotal,
            'package_cost'       => $packageCost,
            'grand_cost'         => $grandTotal,
            'breakdown'          => $breakdown,
            'apartment_cost'     => $apartmentCost,
        ];
    }

    /**
     * Full cost calculation: services + package + management plan markup + duration discount.
     *
     * Staff salary = monthly rate, never discounted.
     * Client cost  = monthly rate × (1 + markup%).
     * Duration discount applies only to company-managed totals.
     * One-off: no package cost, no discount, just single-visit price.
     */
    public function calculate(
        array  $serviceSlugs,
        ?int   $packageId,
        string $managementPlanSlug,
        int    $durationWeeks    = 4,
        ?int   $apartmentTypeId  = null
    ): array {
        $isOneOff       = $durationWeeks === 1;
        $isClientManaged = $managementPlanSlug === 'client-managed';

        $costs = $this->calculateServiceCost(
            $serviceSlugs,
            $isOneOff ? null : $packageId,
            $apartmentTypeId
        );

        $monthlyRate = $costs['grand_cost'];
        $months      = $isOneOff ? 1 : (int) round($durationWeeks / 4);
        $markup      = $this->getMarkup($managementPlanSlug);
        $staffSalary = (int) round($monthlyRate);
        $clientRate  = (int) round($monthlyRate * (1 + $markup / 100));

        if ($isOneOff || $isClientManaged) {
            return [
                'monthly_rate'           => $staffSalary,
                'staff_salary'           => $staffSalary,
                'client_monthly_rate'    => $clientRate,
                'months'                 => $months,
                'discount_percentage'    => 0,
                'discount_amount'        => 0,
                'total_before_discount'  => $clientRate * $months,
                'total_after_discount'   => $clientRate * $months,
                'client_total'           => $clientRate * $months,
                'platform_markup_pct'    => $markup,
                'platform_fee'           => (int) round($monthlyRate * $markup / 100),
                'management_plan'        => $managementPlanSlug,
                'duration_weeks'         => $durationWeeks,
                'is_one_off'             => $isOneOff,
                'breakdown'              => $costs['breakdown'],
                'base_cost'              => $costs['base_cost'],
                'extra_service_cost'     => $costs['extra_service_cost'],
                'package_cost'           => $costs['package_cost'],
                'apartment_cost'         => $costs['apartment_cost'],
            ];
        }

        // Company-managed: discount on total
        $discountPct    = $this->getDurationDiscount($durationWeeks);
        $totalBefore    = $clientRate * $months;
        $discountAmt    = (int) round($totalBefore * $discountPct / 100);
        $totalAfter     = $totalBefore - $discountAmt;
        $monthlyEquiv   = (int) round($totalAfter / $months);

        return [
            'monthly_rate'          => $staffSalary,
            'staff_salary'          => $staffSalary,
            'client_monthly_rate'   => $clientRate,
            'months'                => $months,
            'discount_percentage'   => $discountPct,
            'discount_amount'       => $discountAmt,
            'total_before_discount' => $totalBefore,
            'total_after_discount'  => $totalAfter,
            'monthly_equivalent'    => $monthlyEquiv,
            'client_total'          => $totalAfter,
            'platform_markup_pct'   => $markup,
            'platform_fee'          => (int) round($monthlyRate * $markup / 100),
            'management_plan'       => $managementPlanSlug,
            'duration_weeks'        => $durationWeeks,
            'is_one_off'            => false,
            'breakdown'             => $costs['breakdown'],
            'base_cost'             => $costs['base_cost'],
            'extra_service_cost'    => $costs['extra_service_cost'],
            'package_cost'          => $costs['package_cost'],
            'apartment_cost'        => $costs['apartment_cost'],
        ];
    }

    public function getDurationDiscount(int $weeks): float
    {
        foreach ($this->durationDiscounts as $w => $pct) {
            if ($weeks >= $w) return $pct;
        }
        return 0;
    }

    public function getMarkup(string $slug): float
    {
        $plan = ManagementPlan::where('slug', $slug)->first();
        return $plan ? (float) $plan->platform_markup : ($slug === 'company-managed' ? 20.0 : 0.0);
    }

    /**
     * Format pence/pounds for display: £1,200/month
     */
    public static function format(int|float $amount, string $suffix = '/month'): string
    {
        return '£' . number_format($amount, 2) . $suffix;
    }
}
