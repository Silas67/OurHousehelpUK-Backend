<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApartmentType;
use App\Models\ManagementPlan;
use App\Models\Package;
use App\Services\BookingCostService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(protected BookingCostService $costService) {}

    /** GET /api/pricing-data — everything the booking form needs in one call */
    public function pricingData()
    {
        return response()->json([
            'packages'         => Package::active()->get(['id', 'name', 'days_per_week', 'cost', 'description']),
            'apartment_types'  => ApartmentType::orderBy('id')->get(['id', 'name', 'cost']),
            'management_plans' => ManagementPlan::where('is_active', true)->get(['id', 'name', 'slug', 'description', 'platform_markup']),
        ]);
    }

    /** POST /api/cost-estimate — real-time cost preview before booking is created */
    public function estimate(Request $request)
    {
        $request->validate([
            'service_slugs'      => ['required', 'array', 'min:1'],
            'service_slugs.*'    => ['string', 'in:cleaning,cooking,childcare,elderly_care,laundry,errands,pet_care'],
            'package_id'         => ['nullable', 'integer', 'exists:packages,id'],
            'management_plan'    => ['nullable', 'string', 'in:client-managed,company-managed'],
            'duration_weeks'     => ['required', 'integer', 'in:1,4,8,12'],
            'apartment_type_id'  => ['nullable', 'integer', 'exists:apartment_types,id'],
        ]);

        $result = $this->costService->calculate(
            $request->service_slugs,
            $request->package_id,
            $request->management_plan ?? 'company-managed',
            $request->duration_weeks,
            $request->apartment_type_id
        );

        // Add GBP formatted strings for easy display
        $result['formatted'] = [
            'monthly_rate'        => BookingCostService::format($result['monthly_rate']),
            'client_monthly_rate' => BookingCostService::format($result['client_monthly_rate']),
            'client_total'        => BookingCostService::format($result['client_total'], ''),
            'staff_salary'        => BookingCostService::format($result['staff_salary']),
            'discount_amount'     => BookingCostService::format($result['discount_amount'], ''),
        ];

        return response()->json($result);
    }
}
