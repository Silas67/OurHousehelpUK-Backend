<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function client(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => bcrypt($validated['password']),
            'account_type' => 'client',
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function applicant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'max:20', 'unique:users'],
            'applicant_type' => ['required', 'string', 'in:semi-live-in,live-out'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'dob' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->format('Y-m-d')],

            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{1,2}\d[A-Za-z\d]?\s*\d[A-Za-z]{2}$/'],

            'bio' => ['required', 'string'],
            'years_of_experience' => ['required', 'string', 'max:50'],
            'specialties' => ['required', 'array', 'min:1'],
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],

            'ni_number' => ['required', 'string', 'max:13', 'unique:users,ni_number', 'regex:/^[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z]\s?\d{2}\s?\d{2}\s?\d{2}\s?[A-D]$/i'],
            'right_to_work_document_type' => ['required', 'string', 'in:passport,brp,share_code,visa'],

            'terms_accepted' => ['required', 'accepted'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();

        try {
            $profilePhotoPath = $request->file('profile_photo')->store('profile_photos', 'public');
            $idDocumentPath = $request->file('id_document')->store('id_documents', 'public');

            $user = User::create([
                'name' => $validated['name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'phone' => $validated['phone'],
                'account_type' => 'applicant',
                'applicant_type' => $validated['applicant_type'],
                'gender' => $validated['gender'] ?? null,
                'dob' => $validated['dob'],
                'profile_photo_path' => $profilePhotoPath,
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'],
                'county' => $validated['county'] ?? null,
                'postcode' => $validated['postcode'],
                'bio' => $validated['bio'],
                'years_of_experience' => $validated['years_of_experience'],
                'specialties' => $validated['specialties'],
                'id_document_path' => $idDocumentPath,
                'ni_number' => strtoupper(str_replace(' ', '', $validated['ni_number'])),
                'right_to_work_document_type' => $validated['right_to_work_document_type'],
                'terms_accepted' => true,
                'form_completed' => true,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'There was an error submitting the form. Please try again.'], 500);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }
}
