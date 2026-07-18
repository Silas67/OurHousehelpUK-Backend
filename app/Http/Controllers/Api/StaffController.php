<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        // form_completed is never set by any registration/profile flow today,
        // so filtering on it here would hide every applicant. Show all
        // registered staff instead — verification status is already
        // surfaced per-card via dbs_verified/rtw_verified.
        $staff = User::where('account_type', 'applicant')
            ->select(['id', 'name', 'last_name', 'bio', 'years_of_experience', 'specialties', 'city', 'profile_photo_path', 'applicant_type', 'dbs_check_status', 'right_to_work_status'])
            ->get()
            ->map(fn($u) => [
                'id'                  => $u->id,
                'name'                => trim($u->name . ' ' . ($u->last_name ? substr($u->last_name, 0, 1) . '.' : '')),
                'bio'                 => $u->bio,
                'years_of_experience' => $u->years_of_experience,
                'specialties'         => $u->specialties ?? [],
                'city'                => $u->city,
                'applicant_type'      => $u->applicant_type,
                'dbs_verified'        => $u->dbs_check_status === 'clear',
                'rtw_verified'        => $u->right_to_work_status === 'verified',
                'profile_photo_url'   => $u->profile_photo_path ? url('storage/' . $u->profile_photo_path) : null,
            ]);

        return response()->json(['staff' => $staff]);
    }

    public function show(Request $request, User $staff)
    {
        if ($staff->account_type !== 'applicant') {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'staff' => [
                'id'                   => $staff->id,
                'name'                 => $staff->name,
                'last_name'            => $staff->last_name,
                'applicant_type'       => $staff->applicant_type,
                'bio'                  => $staff->bio,
                'years_of_experience'  => $staff->years_of_experience,
                'specialties'          => $staff->specialties ?? [],
                'city'                 => $staff->city,
                'right_to_work_status' => $staff->right_to_work_status,
                'dbs_check_status'     => $staff->dbs_check_status,
                'profile_photo_url'    => $staff->profile_photo_path
                    ? url('storage/' . $staff->profile_photo_path)
                    : null,
            ],
        ]);
    }
}
