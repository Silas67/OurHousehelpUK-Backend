<?php

namespace App\Http\Controllers;

use App\Models\ApartmentType;
use App\Models\ExtraServiceCost;
use App\Models\HouseService;
use App\Models\ManagementPlan;
use App\Models\Package;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // cv/dbs_certificate/id_document all live on the private disk — this is
    // the only map of "type" -> column, shared by applicantDocument() below.
    private const DOCUMENT_FIELDS = [
        'cv'              => 'cv_path',
        'dbs_certificate' => 'dbs_certificate_path',
        'id_document'     => 'id_document_path',
    ];
    // ─── Auth ────────────────────────────────────────────────────────────────

    public function loginForm()
    {
        if (Auth::check() && Auth::user()->account_type === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        if (Auth::user()->account_type !== 'admin') {
            Auth::logout();
            return back()->withErrors(['email' => 'Access denied. Admin accounts only.'])->withInput();
        }

        $request->session()->regenerate();
        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = [
            'clients'              => User::where('account_type', 'client')->count(),
            'applicants'           => User::where('account_type', 'applicant')->count(),
            'pending_rtw'          => User::where('account_type', 'applicant')
                                         ->where('right_to_work_status', '!=', 'verified')
                                         ->count(),
            'pending_dbs'          => User::where('account_type', 'applicant')
                                         ->where('dbs_check_status', '!=', 'clear')
                                         ->count(),
            'open_requests'        => ServiceRequest::where('status', 'open')->count(),
            'requests_without_pay' => ServiceRequest::where('status', 'open')
                                         ->whereNull('pay_rate')
                                         ->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    // ─── Applicants ──────────────────────────────────────────────────────────

    public function applicants(Request $request)
    {
        $query = User::where('account_type', 'applicant')->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('rtw')) {
            $query->where('right_to_work_status', $request->rtw);
        }

        $applicants = $query->paginate(20)->withQueryString();
        return view('admin.applicants.index', compact('applicants'));
    }

    public function applicantShow(User $user)
    {
        abort_if($user->account_type !== 'applicant', 404);
        return view('admin.applicants.show', compact('user'));
    }

    public function applicantDocument(User $user, string $type)
    {
        abort_if($user->account_type !== 'applicant', 404);
        abort_unless(array_key_exists($type, self::DOCUMENT_FIELDS), 404);

        $path = $user->{self::DOCUMENT_FIELDS[$type]};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function applicantVerify(Request $request, User $user)
    {
        abort_if($user->account_type !== 'applicant', 404);

        $validated = Validator::make($request->all(), [
            'right_to_work_status'  => ['nullable', 'string', 'in:not_started,pending,verified,rejected'],
            'dbs_check_status'      => ['nullable', 'string', 'in:not_started,pending,clear,flagged'],
            'dbs_certificate_number'=> ['nullable', 'string', 'max:20'],
            'dbs_check_date'        => ['nullable', 'date'],
            'references_checked'    => ['nullable', 'integer', 'min:0', 'max:20'],
        ])->validate();

        $updates = array_filter($validated, fn($v) => $v !== null);

        if (isset($updates['right_to_work_status'])) {
            $updates['right_to_work_checked_at'] = now();
        }

        $user->update($updates);

        return back()->with('success', 'Verification status updated.');
    }

    // ─── Service Requests ────────────────────────────────────────────────────

    public function requests(Request $request)
    {
        $query = ServiceRequest::with('client')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(20)->withQueryString();
        return view('admin.requests.index', compact('requests'));
    }

    public function requestShow(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load('client', 'applicant', 'applications.applicant');
        return view('admin.requests.show', ['request' => $serviceRequest]);
    }

    public function setPayRate(Request $request, ServiceRequest $serviceRequest)
    {
        $request->validate([
            'pay_rate' => ['required', 'string', 'max:100'],
        ]);

        $serviceRequest->update(['pay_rate' => $request->pay_rate]);

        return back()->with('success', 'Pay rate updated.');
    }

    public function updateRequestStatus(Request $request, ServiceRequest $serviceRequest)
    {
        $request->validate([
            'status' => ['required', 'string', 'in:open,matched,confirmed,active,completed,cancelled'],
        ]);

        $serviceRequest->update(['status' => $request->status]);

        return back()->with('success', 'Status updated.');
    }

    // ─── Packages ────────────────────────────────────────────────────────────

    public function packages()
    {
        $packages = Package::orderBy('sort_order')->get();
        return view('admin.packages', compact('packages'));
    }

    public function updatePackage(Request $request, Package $package)
    {
        $request->validate([
            'cost'        => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable'],
        ]);

        $package->update([
            'cost'        => $request->cost,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active'),
        ]);

        return back()->with('success', $package->name . ' updated.');
    }

    // ─── Services / Pricing Settings ─────────────────────────────────────────

    public function pricingSettings()
    {
        $services       = HouseService::orderBy('sort_order')->get();
        $apartmentTypes = ApartmentType::orderBy('sort_order')->get();
        $mgmtPlans      = ManagementPlan::all();
        $extraCost      = ExtraServiceCost::firstOrCreate([], ['cost' => 50]);
        return view('admin.pricing', compact('services', 'apartmentTypes', 'mgmtPlans', 'extraCost'));
    }

    public function updateService(Request $request, HouseService $houseService)
    {
        $request->validate(['base_cost' => ['required', 'numeric', 'min:0'], 'is_active' => ['nullable']]);
        $houseService->update(['base_cost' => $request->base_cost, 'is_active' => $request->boolean('is_active')]);
        return back()->with('success', $houseService->service_name . ' updated.');
    }

    public function updateApartmentType(Request $request, ApartmentType $apartmentType)
    {
        $request->validate(['cost' => ['required', 'numeric', 'min:0']]);
        $apartmentType->update(['cost' => $request->cost]);
        return back()->with('success', $apartmentType->name . ' updated.');
    }

    public function updateManagementPlan(Request $request, ManagementPlan $managementPlan)
    {
        $request->validate(['platform_markup' => ['required', 'numeric', 'min:0', 'max:100']]);
        $managementPlan->update(['platform_markup' => $request->platform_markup, 'description' => $request->description]);
        return back()->with('success', $managementPlan->name . ' updated.');
    }

    public function updateExtraServiceCost(Request $request)
    {
        $request->validate(['cost' => ['required', 'numeric', 'min:0']]);
        ExtraServiceCost::first()?->update(['cost' => $request->cost]);
        return back()->with('success', 'Extra service cost updated.');
    }
}
