<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseService;
use App\Models\JobApplication;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = User::where('account_type', 'applicant')
            ->select(['id', 'name', 'last_name', 'bio', 'years_of_experience', 'specialties', 'city', 'profile_photo_path', 'applicant_type', 'dbs_check_status', 'right_to_work_status'])
            ->get()
            ->filter(fn($u) => $u->isVettedForPlacement())
            ->values();

        // One aggregate query for all cards rather than N per-staff queries.
        $ratingAgg = Rating::selectRaw('applicant_id, AVG(stars) as avg_stars, COUNT(*) as cnt')
            ->whereIn('applicant_id', $staff->pluck('id'))
            ->groupBy('applicant_id')
            ->get()
            ->keyBy('applicant_id');

        $data = $staff->map(function ($u) use ($ratingAgg) {
            $agg = $ratingAgg->get($u->id);
            return [
                'id'                  => $u->id,
                'name'                => $this->displayName($u),
                'bio'                 => $u->bio,
                'years_of_experience' => $u->years_of_experience,
                'specialties'         => $u->specialties ?? [],
                'city'                => $u->city,
                'applicant_type'      => $u->applicant_type,
                'rating'              => $agg ? round($agg->avg_stars, 1) : null,
                'review_count'        => $agg ? (int) $agg->cnt : 0,
                'dbs_verified'        => $u->dbs_check_status === 'clear',
                'rtw_verified'        => $u->right_to_work_status === 'verified',
                'profile_photo_url'   => $u->profile_photo_path ? url('storage/' . $u->profile_photo_path) : null,
            ];
        });

        return response()->json(['staff' => $data]);
    }

    /** Authenticated clients see the full profile. */
    public function show(Request $request, User $staff)
    {
        if ($staff->account_type !== 'applicant') {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json(['staff' => $this->profilePayload($staff, guest: false)]);
    }

    /** Guests (no login) see a reduced profile — no full surname, hourly rate or availability. */
    public function publicShow(Request $request, User $staff)
    {
        if ($staff->account_type !== 'applicant') {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return response()->json(['staff' => $this->profilePayload($staff, guest: true)]);
    }

    private function profilePayload(User $staff, bool $guest): array
    {
        $ratings     = Rating::where('applicant_id', $staff->id)->get();
        $reviewCount = $ratings->count();
        $rating      = $reviewCount ? round($ratings->avg('stars'), 1) : null;
        $reviews     = Rating::where('applicant_id', $staff->id)
            ->with('client:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'client_name' => $this->shortName($r->client?->name),
                'stars'       => (int) $r->stars,
                'comment'     => $r->comment,
                'date'        => $r->created_at?->format('j M Y'),
            ])
            ->values();

        $jobsCompleted = JobApplication::where('applicant_id', $staff->id)
            ->where('status', 'accepted')
            ->whereHas('serviceRequest', fn($q) => $q->where('status', 'completed'))
            ->count();

        // Indicative hourly rate = average of the seeded rates for the
        // services this staff member offers (null if they've set none).
        $specialties = $staff->specialties ?? [];
        $hourlyRate  = null;
        if (!empty($specialties)) {
            $normalized = array_map(fn($s) => strtolower(str_replace(' ', '_', $s)), $specialties);
            $avgRate    = HouseService::whereIn('slug', $normalized)->avg('hourly_rate');
            $hourlyRate = $avgRate ? round($avgRate) : null;
        }

        return [
            'id'                   => $staff->id,
            // Guests never see the full surname, hourly rate or availability.
            'name'                 => $guest ? $this->displayName($staff) : $staff->name,
            'last_name'            => $guest ? null : $staff->last_name,
            'applicant_type'       => $staff->applicant_type,
            'bio'                  => $staff->bio,
            'years_of_experience'  => $staff->years_of_experience,
            'specialties'          => $staff->specialties ?? [],
            'city'                 => $staff->city,
            'right_to_work_status' => $staff->right_to_work_status,
            'dbs_check_status'     => $staff->dbs_check_status,
            'rtw_verified'         => $staff->right_to_work_status === 'verified',
            'dbs_verified'         => $staff->dbs_check_status === 'clear',
            'id_verified'          => (bool) $staff->id_document_path,
            'profile_photo_url'    => $staff->profile_photo_path
                ? url('storage/' . $staff->profile_photo_path)
                : null,
            'rating'               => $rating,
            'review_count'         => $reviewCount,
            'reviews'              => $reviews,
            'jobs_completed'       => $jobsCompleted,
            'member_since'         => $staff->created_at?->format('F Y'),
            'available_from'       => $guest ? null : ($staff->is_available ? 'Immediately' : null),
            'hourly_rate'          => $guest ? null : $hourlyRate,
            'availability_days'    => $guest ? null : $this->availabilityLabel($staff->availability_days ?? []),
            'references_count'     => $staff->references_checked,
            'household_types'      => $staff->household_types ?: null,
            'languages'            => $staff->languages ?: null,
            'is_guest_view'        => $guest,
        ];
    }

    /** "Silas Ejimonye" -> "Silas E." — first name + last initial. */
    private function displayName(User $staff): string
    {
        return trim($staff->name . ' ' . ($staff->last_name ? substr($staff->last_name, 0, 1) . '.' : ''));
    }

    /** ['Mon','Tue','Wed','Thu','Fri'] -> "Mon–Fri"; non-contiguous -> comma list. */
    private function availabilityLabel(array $days): ?string
    {
        if (empty($days)) {
            return null;
        }

        $order = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $indices = collect($days)
            ->map(fn($d) => array_search($d, $order, true))
            ->filter(fn($i) => $i !== false)
            ->sort()
            ->values()
            ->all();

        if (empty($indices)) {
            return null;
        }
        if (count($indices) === 7) {
            return 'Every day';
        }

        // Contiguous run -> range label
        $contiguous = $indices === range($indices[0], $indices[count($indices) - 1]);
        if ($contiguous && count($indices) > 2) {
            return $order[$indices[0]] . '–' . $order[end($indices)];
        }

        return implode(', ', array_map(fn($i) => $order[$i], $indices));
    }

    /** "Sarah Thompson" -> "Sarah T." for public-facing review attribution. */
    private function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return 'A client';
        }
        $first = $parts[0];
        $initial = count($parts) > 1 ? ' ' . strtoupper(substr(end($parts), 0, 1)) . '.' : '';
        return $first . $initial;
    }
}
