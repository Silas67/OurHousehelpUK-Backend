<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'last_name'            => $user->last_name,
                'email'                => $user->email,
                'phone'                => $user->phone,
                'account_type'         => $user->account_type,
                'applicant_type'       => $user->applicant_type,
                'bio'                  => $user->bio,
                'years_of_experience'  => $user->years_of_experience,
                'specialties'          => $user->specialties ?? [],
                'city'                 => $user->city,
                'address_line_1'       => $user->address_line_1,
                'address_line_2'       => $user->address_line_2,
                'postcode'             => $user->postcode,
                'right_to_work_status' => $user->right_to_work_status,
                'dbs_check_status'     => $user->dbs_check_status,
                'is_available'         => (bool) $user->is_available,
                'profile_photo_url'    => $user->profile_photo_path
                    ? url('storage/' . $user->profile_photo_path)
                    : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'bio'   => ['sometimes', 'nullable', 'string'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode'       => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user->update($validator->validated());

        return response()->json(['message' => 'Profile updated.', 'user' => $user->fresh()]);
    }

    public function toggleAvailability(Request $request)
    {
        $user = $request->user();
        $user->is_available = !$user->is_available;
        $user->save();

        return response()->json(['is_available' => (bool) $user->is_available]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->update(['password' => bcrypt($request->password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
