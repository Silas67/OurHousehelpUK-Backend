<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    // Maps a client-facing document "type" to the private-disk path column
    // that stores it — cv/dbs_certificate/id_document all live on the
    // default private disk, unlike profile photos which are public.
    private const DOCUMENT_FIELDS = [
        'cv'              => 'cv_path',
        'dbs_certificate' => 'dbs_certificate_path',
        'id_document'     => 'id_document_path',
    ];

    public function show(Request $request)
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
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
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'specialties'         => ['sometimes', 'array'],
            'specialties.*'       => ['string', 'in:cleaning,laundry,cooking,childcare,elderly_care,errands,window_cleaning'],
            'applicant_type'      => ['sometimes', 'nullable', 'string', 'in:semi-live-in,live-out'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user->update($validator->validated());

        return response()->json(['message' => 'Profile updated.', 'user' => $this->formatUser($user->fresh())]);
    }

    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update([
            'profile_photo_path' => $request->file('photo')->store('profile-photos', 'public'),
        ]);

        return response()->json(['message' => 'Photo updated.', 'user' => $this->formatUser($user->fresh())]);
    }

    /**
     * POST /profile/documents/{type} — (re-)upload a DBS certificate or ID
     * document. Always resets the corresponding status back to 'pending':
     * an old 'clear'/'verified' decision must not carry over to a document
     * nobody has reviewed yet, even though that means the staff member
     * drops out of the marketplace/job matching until it's reviewed again.
     */
    public function uploadDocument(Request $request, string $type)
    {
        $rules = match ($type) {
            'dbs_certificate' => ['document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240']],
            'id_document'     => [
                'document'                    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
                'right_to_work_document_type' => ['sometimes', 'string', 'in:passport,brp,visa'],
            ],
            default => null,
        };

        if (!$rules) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $user   = $request->user();
        $column = self::DOCUMENT_FIELDS[$type];

        if ($user->{$column}) {
            Storage::disk('local')->delete($user->{$column});
        }

        $storageDir = $type === 'dbs_certificate' ? 'dbs_certificates' : 'id_documents';
        $updates = [$column => $request->file('document')->store($storageDir)];

        if ($type === 'dbs_certificate') {
            $updates['dbs_check_status'] = 'pending';
        } else {
            $updates['right_to_work_status'] = 'pending';
            if ($request->filled('right_to_work_document_type')) {
                $updates['right_to_work_document_type'] = $request->right_to_work_document_type;
            }
        }

        $user->update($updates);

        return response()->json(['message' => 'Document uploaded.', 'user' => $this->formatUser($user->fresh())]);
    }

    /**
     * GET /profile/documents/{type}/link — returns a short-lived signed URL
     * the app can open directly (e.g. via Linking.openURL) without needing
     * to attach an auth header, since the signature itself is the proof.
     */
    public function documentLink(Request $request, string $type)
    {
        if (!array_key_exists($type, self::DOCUMENT_FIELDS)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $user = $request->user();
        $path = $user->{self::DOCUMENT_FIELDS[$type]};

        if (!$path) {
            return response()->json(['message' => 'No document uploaded.'], 404);
        }

        $url = URL::temporarySignedRoute('documents.show', now()->addMinutes(10), [
            'type' => $type,
            'user' => $user->id,
        ]);

        return response()->json(['url' => $url]);
    }

    /**
     * GET /documents/{type}/{user} — public route, but requires a valid
     * signature (see documentLink above), so it's only reachable via a URL
     * this controller itself just issued and that hasn't expired.
     */
    public function serveDocument(Request $request, string $type, User $user)
    {
        if (!array_key_exists($type, self::DOCUMENT_FIELDS)) {
            abort(404);
        }

        $path = $user->{self::DOCUMENT_FIELDS[$type]};

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
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

    private function formatUser(User $user): array
    {
        return [
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
            'right_to_work_status'         => $user->right_to_work_status,
            'right_to_work_document_type'  => $user->right_to_work_document_type,
            'right_to_work_checked_at'     => $user->right_to_work_checked_at?->toDateTimeString(),
            'dbs_check_status'             => $user->dbs_check_status,
            'dbs_certificate_number'       => $user->dbs_certificate_number,
            'dbs_check_date'               => $user->dbs_check_date?->toDateString(),
            'is_available'                 => (bool) $user->is_available,
            'profile_photo_url'    => $user->profile_photo_path
                ? url('storage/' . $user->profile_photo_path)
                : null,
            'profile_complete'       => $user->hasCompleteProfile(),
            'missing_profile_fields' => $user->missingProfileFields(),
            'has_cv'                 => (bool) $user->cv_path,
            'has_dbs_certificate'    => (bool) $user->dbs_certificate_path,
            'has_id_document'        => (bool) $user->id_document_path,
        ];
    }
}
