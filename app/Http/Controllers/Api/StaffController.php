<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
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
