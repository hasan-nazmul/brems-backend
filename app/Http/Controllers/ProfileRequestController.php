<?php

namespace App\Http\Controllers;

use App\Models\ProfileRequest;
use App\Models\Employee;
use App\Models\FamilyMember;
use App\Models\Address;
use App\Models\AcademicRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Resources\EmployeeResource;
class ProfileRequestController extends Controller
{
    // =========================================================
    // LIST REQUESTS
    // =========================================================
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'reviewedBy'
        ])->latest();

        if ($user->isSuperAdmin()) {
            // Super admin sees all requests
        } elseif ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();

            // Office admin sees:
            // 1. Requests from employees in managed offices (to review)
            // 2. Their own requests (if they submitted any)
            $query->where(function ($q) use ($officeIds, $user) {
                $q->whereHas('employee', function ($eq) use ($officeIds) {
                    $eq->whereIn('current_office_id', $officeIds);
                });

                if ($user->employee_id) {
                    $q->orWhere('employee_id', $user->employee_id);
                }
            });
        } else {
            // Verified user sees only their own requests
            if (!$user->employee_id) {
                return response()->json([]);
            }
            $query->where('employee_id', $user->employee_id);
        }

        // Search: by employee name, request type, id, or details
        if ($request->filled('search')) {
            $search = $request->search;
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term, $search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('request_type', 'like', $term)
                    ->orWhere('details', 'like', $term)
                    ->orWhereHas('employee', function ($eq) use ($term) {
                        $eq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]);
                    });
            });
        }

        // Status filter (optional, keep for API compatibility)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // =========================================================
    // MY REQUESTS (For employees to view their own)
    // =========================================================
    public function myRequests(Request $request)
    {
        $user = $request->user();

        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        $requests = ProfileRequest::with(['reviewedBy'])
            ->where('employee_id', $user->employee_id)
            ->latest()
            ->get();

        return response()->json($requests);
    }

    // =========================================================
    // PENDING REQUESTS (For admin inbox)
    // =========================================================
    public function pending(Request $request)
    {
        $user = $request->user();

        if ($user->isVerifiedUser()) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = ProfileRequest::with([
            'employee.designation',
            'employee.office'
        ])
            ->where('status', 'pending')
            ->latest();

        if (!$user->isSuperAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            $query->whereHas('employee', function ($q) use ($officeIds) {
                $q->whereIn('current_office_id', $officeIds);
            });
        }

        return response()->json($query->get());
    }

    // =========================================================
    // SHOW SINGLE REQUEST
    // =========================================================
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'employee.family',
            'employee.addresses',
            'employee.academics',
            'reviewedBy'
        ])->findOrFail($id);

        // Permission check
        if (!$this->canAccessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Add current employee data for comparison
        $profileRequest->current_data = $this->getCurrentEmployeeData($profileRequest->employee);

        // Ensure proposed_changes is always an array for the frontend (JSON column can sometimes be string)
        $profileRequest->setAttribute(
            'proposed_changes',
            $this->normalizeProposedChanges($profileRequest->proposed_changes, $profileRequest)
        );

        return response()->json($profileRequest);
    }

    /**
     * Normalize proposed_changes so the API always returns an array.
     * For "Document Update" requests, ensure document_update key exists from details if missing.
     */
    private function normalizeProposedChanges($proposedChanges, ProfileRequest $profileRequest): array
    {
        $data = $proposedChanges;
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($data)) {
            $data = [];
        }
        // For Document Update type, ensure frontend can show something even if document_update key was missing
        if ($profileRequest->request_type === 'Document Update' && empty($data['document_update']) && $profileRequest->details) {
            $data['document_update'] = [
                'type' => $profileRequest->details,
                'uploaded_at' => $profileRequest->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        }
        return $data;
    }

    // =========================================================
    // STORE REQUEST (Employee/Office Admin submits)
    // =========================================================
    public function store(Request $request)
    {
        $user = $request->user();

        // Must be linked to an employee
        if (!$user->employee_id) {
            return response()->json([
                'message' => 'Your account is not linked to an employee profile'
            ], 422);
        }

        // Check for existing pending request
        $existingPending = ProfileRequest::where('employee_id', $user->employee_id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'You already have a pending request. Please wait for it to be processed.',
                'existing_request_id' => $existingPending->id
            ], 422);
        }

        $validated = $request->validate([
            'request_type' => 'required|string|max:100',
            'details' => 'nullable|string|max:2000',
            'proposed_changes' => 'required',
        ]);

        try {
            // Parse proposed_changes if it's a JSON string
            $proposedChanges = is_string($validated['proposed_changes'])
                ? json_decode($validated['proposed_changes'], true)
                : $validated['proposed_changes'];

            if (!is_array($proposedChanges)) {
                return response()->json([
                    'message' => 'Invalid proposed_changes format'
                ], 422);
            }

            // Handle file uploads
            $proposedChanges['files'] = $this->handleFileUploads($request);

            $profileRequest = ProfileRequest::create([
                'employee_id' => $user->employee_id,
                'request_type' => $validated['request_type'],
                'details' => $validated['details'] ?? null,
                'proposed_changes' => $proposedChanges,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Request submitted successfully. You will be notified once it is reviewed.',
                'request' => $profileRequest->load('employee')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Profile Request Store Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit request: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // PROCESS REQUEST (Admin approves/rejects)
    // =========================================================
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with('employee')->findOrFail($id);

        // Permission check
        if (!$this->canProcessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status === 'processed') {
            return response()->json([
                'message' => 'This request has already been processed'
            ], 422);
        }

        $validated = $request->validate([
            'is_approved' => 'required|boolean',
            'admin_note' => 'nullable|string|max:2000',
            'approved_changes' => 'nullable|array',
        ]);

        try {
            DB::transaction(function () use ($profileRequest, $validated, $user) {
                $employee = $profileRequest->employee;

                if ($validated['is_approved']) {
                    // If approved, apply the changes
                    $changesToApply = $validated['approved_changes']
                        ?? $profileRequest->proposed_changes;

                    $this->applyChanges($employee, $changesToApply);
                } else {
                    // If rejected, revert document/file changes so they are not left applied
                    $this->revertDocumentUpdateIfAny($profileRequest);
                }

                // Update request status
                $profileRequest->update([
                    'status' => 'processed',
                    'is_approved' => $validated['is_approved'],
                    'admin_note' => $validated['admin_note'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            });

            $status = $validated['is_approved'] ? 'approved' : 'rejected';

            return response()->json([
                'message' => "Request has been {$status} successfully",
                'request' => $profileRequest->fresh()->load(['employee', 'reviewedBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Profile Request Update Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to process request: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // APPLY CHANGES TO EMPLOYEE
    // =========================================================
    private function applyChanges(Employee $employee, array $changes): void
    {
        // 1. Apply Personal Info Changes
        if (isset($changes['personal_info']) && is_array($changes['personal_info'])) {
            $this->applyPersonalInfoChanges($employee, $changes['personal_info']);
        }

        // 2. Apply Family Changes
        if (isset($changes['family']) && is_array($changes['family'])) {
            $this->applyFamilyChanges($employee, $changes['family']);
        }

        // 3. Apply Address Changes
        if (isset($changes['addresses']) && is_array($changes['addresses'])) {
            $this->applyAddressChanges($employee, $changes['addresses']);
        }

        // 4. Apply Academic Changes
        if (isset($changes['academics']) && is_array($changes['academics'])) {
            $this->applyAcademicChanges($employee, $changes['academics']);
        }

        // 5. Apply File Changes (move from temp to permanent)
        if (isset($changes['files']) && is_array($changes['files'])) {
            $this->applyFileChanges($employee, $changes['files']);
        }

        // 6. Apply Document Update (move pending file to final location and update employee/academic/family)
        if (isset($changes['document_update']) && is_array($changes['document_update'])) {
            $this->applyDocumentUpdate($employee, $changes['document_update']);
        }
    }

    /**
     * On approve: move file from pending path to final location and update the employee (or academic/family) record.
     */
    private function applyDocumentUpdate(Employee $employee, array $documentUpdate): void
    {
        $pendingPath = $documentUpdate['file_path'] ?? null;
        if (! $pendingPath || ! Storage::disk('public')->exists($pendingPath)) {
            return;
        }

        $ext = pathinfo($pendingPath, PATHINFO_EXTENSION);
        $isPending = str_contains($pendingPath, 'documents/pending/');

        if (! empty($documentUpdate['employee_field'])) {
            $field = $documentUpdate['employee_field'];
            if (! in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'], true)) {
                return;
            }
            if ($field === 'profile_picture') {
                $finalDir = 'photos';
                $finalFilename = ($employee->nid_number ?: 'emp' . $employee->id) . '_' . time() . '.' . $ext;
            } elseif ($field === 'nid_file_path') {
                $finalDir = 'documents/nid';
                $finalFilename = 'NID_' . ($employee->nid_number ?: $employee->id) . '_' . time() . '.' . $ext;
            } else {
                $finalDir = 'documents/birth';
                $finalFilename = 'BIRTH_' . ($employee->nid_number ?: $employee->id) . '_' . time() . '.' . $ext;
            }
            $finalPath = $finalDir . '/' . $finalFilename;
            if ($isPending) {
                Storage::disk('public')->move($pendingPath, $finalPath);
            } else {
                $contents = Storage::disk('public')->get($pendingPath);
                Storage::disk('public')->put($finalPath, $contents);
                Storage::disk('public')->delete($pendingPath);
            }
            if ($employee->$field) {
                Storage::disk('public')->delete($employee->$field);
            }
            $employee->update([$field => $finalPath]);
            return;
        }

        if (! empty($documentUpdate['academic_id'])) {
            $academic = AcademicRecord::where('employee_id', $employee->id)->find($documentUpdate['academic_id']);
            if ($academic) {
                $examSlug = str_replace(['/', ' '], '_', $academic->exam_name ?? 'cert');
                $finalFilename = 'CERT_' . $employee->nid_number . '_' . $examSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/certificates/' . $finalFilename;
                if ($isPending) {
                    Storage::disk('public')->move($pendingPath, $finalPath);
                } else {
                    $contents = Storage::disk('public')->get($pendingPath);
                    Storage::disk('public')->put($finalPath, $contents);
                    Storage::disk('public')->delete($pendingPath);
                }
                if ($academic->certificate_path) {
                    Storage::disk('public')->delete($academic->certificate_path);
                }
                $academic->update(['certificate_path' => $finalPath]);
            }
            return;
        }

        if (! empty($documentUpdate['family_member_id'])) {
            $member = FamilyMember::where('employee_id', $employee->id)->find($documentUpdate['family_member_id']);
            if ($member) {
                $nameSlug = str_replace(' ', '_', $member->name ?? 'child');
                $finalFilename = 'CHILD_BIRTH_' . $employee->nid_number . '_' . $nameSlug . '_' . time() . '.' . $ext;
                $finalPath = 'documents/children/' . $finalFilename;
                if ($isPending) {
                    Storage::disk('public')->move($pendingPath, $finalPath);
                } else {
                    $contents = Storage::disk('public')->get($pendingPath);
                    Storage::disk('public')->put($finalPath, $contents);
                    Storage::disk('public')->delete($pendingPath);
                }
                if ($member->birth_certificate_path) {
                    Storage::disk('public')->delete($member->birth_certificate_path);
                }
                $member->update(['birth_certificate_path' => $finalPath]);
            }
        }
    }

    /**
     * When a "Document Update" request is rejected: delete the pending file.
     * Only clear employee/academic/family field if it still points at this path (legacy applied-at-upload requests).
     */
    private function revertDocumentUpdateIfAny(ProfileRequest $profileRequest): void
    {
        if ($profileRequest->request_type !== 'Document Update') {
            return;
        }

        $proposed = $profileRequest->proposed_changes;
        if (! is_array($proposed) || empty($proposed['document_update'])) {
            return;
        }

        $doc = $proposed['document_update'];
        $filePath = $doc['file_path'] ?? null;
        $employee = $profileRequest->employee;

        if (! $filePath || ! $employee) {
            return;
        }

        // Delete the file from storage (pending or legacy applied path)
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        // Clear the field only if it still points at this path (legacy: was applied at upload before we moved to pending flow)
        if (! empty($doc['employee_field'])) {
            $field = $doc['employee_field'];
            if (in_array($field, ['profile_picture', 'nid_file_path', 'birth_file_path'], true) && $employee->$field === $filePath) {
                $employee->update([$field => null]);
            }
        } elseif (! empty($doc['academic_id'])) {
            $academic = AcademicRecord::where('employee_id', $employee->id)->find($doc['academic_id']);
            if ($academic && $academic->certificate_path === $filePath) {
                $academic->update(['certificate_path' => null]);
            }
        } elseif (! empty($doc['family_member_id'])) {
            $member = FamilyMember::where('employee_id', $employee->id)->find($doc['family_member_id']);
            if ($member && $member->birth_certificate_path === $filePath) {
                $member->update(['birth_certificate_path' => null]);
            }
        }
    }

    private function applyPersonalInfoChanges(Employee $employee, array $personalInfo): void
    {
        $allowedFields = [
            'first_name', 'last_name', 'name_bn', 'nid_number', 'phone',
            'gender', 'dob', 'religion', 'blood_group', 'marital_status',
            'place_of_birth', 'height', 'passport', 'birth_reg'
        ];

        $updateData = [];

        foreach ($personalInfo as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateData[$field] = in_array($field, ['dob', 'first_name', 'last_name', 'name_bn', 'nid_number', 'phone', 'religion', 'blood_group', 'marital_status', 'place_of_birth', 'height', 'passport', 'birth_reg']) ? $this->emptyToNull($value) : $value;
            }
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
        }
    }

    private function applyFamilyChanges(Employee $employee, array $family): void
    {
        // Father
        if (isset($family['father'])) {
            $this->upsertFamilyMember($employee, 'father', $family['father']);
        }

        // Mother
        if (isset($family['mother'])) {
            $this->upsertFamilyMember($employee, 'mother', $family['mother']);
        }

        // Spouses
        if (isset($family['spouses']) && is_array($family['spouses'])) {
            $this->updateSpouses($employee, $family['spouses']);
        }

        // Children
        if (isset($family['children']) && is_array($family['children'])) {
            $this->updateChildren($employee, $family['children']);
        }
    }

    private function upsertFamilyMember(Employee $employee, string $relation, array $data): void
    {
        if (empty($data['name'])) {
            return;
        }

        $payload = [
            'name' => $data['name'],
            'name_bn' => $this->emptyToNull($data['name_bn'] ?? null),
            'nid' => $this->emptyToNull($data['nid'] ?? null),
            'dob' => $this->emptyToNull($data['dob'] ?? null),
            'occupation' => $this->emptyToNull($data['occupation'] ?? null),
            'is_alive' => $data['is_alive'] ?? true,
        ];
        $employee->family()->updateOrCreate(
            ['relation' => $relation],
            $payload
        );
    }

    private function emptyToNull($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }

    private function updateSpouses(Employee $employee, array $spouses): void
    {
        // Validate spouse count
        $activeCount = collect($spouses)->where('is_active_marriage', true)->count();
        $maxAllowed = $employee->getMaxSpouses();

        if ($activeCount > $maxAllowed) {
            throw ValidationException::withMessages([
                'spouses' => ["Maximum {$maxAllowed} active spouse(s) allowed."]
            ]);
        }

        $employee->family()->where('relation', 'spouse')->delete();

        foreach ($spouses as $spouse) {
            if (empty($spouse['name'])) continue;

            $employee->family()->create([
                'relation' => 'spouse',
                'name' => $spouse['name'],
                'name_bn' => $this->emptyToNull($spouse['name_bn'] ?? null),
                'nid' => $this->emptyToNull($spouse['nid'] ?? null),
                'dob' => $this->emptyToNull($spouse['dob'] ?? null),
                'occupation' => $this->emptyToNull($spouse['occupation'] ?? null),
                'is_active_marriage' => $spouse['is_active_marriage'] ?? true,
                'is_alive' => $spouse['is_alive'] ?? true,
            ]);
        }
    }

    private function updateChildren(Employee $employee, array $children): void
    {
        $employee->family()->where('relation', 'child')->delete();

        foreach ($children as $child) {
            if (empty($child['name'])) continue;

            $employee->family()->create([
                'relation' => 'child',
                'name' => $child['name'],
                'name_bn' => $this->emptyToNull($child['name_bn'] ?? null),
                'gender' => $this->emptyToNull($child['gender'] ?? null),
                'dob' => $this->emptyToNull($child['dob'] ?? null),
                'birth_certificate_path' => $this->emptyToNull($child['birth_certificate_path'] ?? null),
                'is_alive' => $child['is_alive'] ?? true,
            ]);
        }
    }

    private function applyAddressChanges(Employee $employee, array $addresses): void
    {
        foreach (['present', 'permanent'] as $type) {
            if (isset($addresses[$type])) {
                $addr = $addresses[$type];

                $hasData = !empty($addr['division']) || !empty($addr['district']) ||
                    !empty($addr['village_road']) || !empty($addr['house_no']);

                if ($hasData) {
                    $employee->addresses()->updateOrCreate(
                        ['type' => $type],
                        [
                            'division' => $addr['division'] ?? null,
                            'district' => $addr['district'] ?? null,
                            'upazila' => $addr['upazila'] ?? null,
                            'post_office' => $addr['post_office'] ?? null,
                            'house_no' => $addr['house_no'] ?? null,
                            'village_road' => $addr['village_road'] ?? null,
                        ]
                    );
                }
            }
        }
    }

    private function applyAcademicChanges(Employee $employee, array $academics): void
    {
        $employee->academics()->delete();

        foreach ($academics as $record) {
            if (empty($record['exam_name'])) continue;

            $employee->academics()->create([
                'exam_name' => $record['exam_name'],
                'institute' => $record['institute'] ?? null,
                'passing_year' => $record['passing_year'] ?? null,
                'result' => $record['result'] ?? null,
                'certificate_path' => $record['certificate_path'] ?? null,
            ]);
        }
    }

    private function applyFileChanges(Employee $employee, array $files): void
    {
        // NID File
        if (isset($files['nid_file'])) {
            $newPath = $this->moveToPermananentStorage($files['nid_file'], 'documents/nid');
            if ($newPath) {
                if ($employee->nid_file_path) {
                    Storage::disk('public')->delete($employee->nid_file_path);
                }
                $employee->update(['nid_file_path' => $newPath]);
            }
        }

        // Birth Certificate File
        if (isset($files['birth_file'])) {
            $newPath = $this->moveToPermananentStorage($files['birth_file'], 'documents/birth');
            if ($newPath) {
                if ($employee->birth_file_path) {
                    Storage::disk('public')->delete($employee->birth_file_path);
                }
                $employee->update(['birth_file_path' => $newPath]);
            }
        }

        // Profile Picture
        if (isset($files['profile_picture'])) {
            $newPath = $this->moveToPermananentStorage($files['profile_picture'], 'photos');
            if ($newPath) {
                if ($employee->profile_picture) {
                    Storage::disk('public')->delete($employee->profile_picture);
                }
                $employee->update(['profile_picture' => $newPath]);
            }
        }
    }

    private function moveToPermananentStorage(string $tempPath, string $folder): ?string
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }

        $filename = basename($tempPath);
        $newPath = $folder . '/' . $filename;

        Storage::disk('public')->move($tempPath, $newPath);

        return $newPath;
    }

    // =========================================================
    // CANCEL REQUEST (Employee cancels their pending request)
    // =========================================================
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::findOrFail($id);

        // Only the requester can cancel their own request
        if ($profileRequest->employee_id !== $user->employee_id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending requests can be cancelled'
            ], 422);
        }

        // Delete uploaded files
        if (isset($profileRequest->proposed_changes['files'])) {
            $this->deleteUploadedFiles($profileRequest->proposed_changes['files']);
        }

        $profileRequest->delete();

        return response()->json(['message' => 'Request cancelled successfully']);
    }

    // =========================================================
    // DOWNLOAD RESOLUTION REPORT (PDF)
    // =========================================================
    public function downloadReport(Request $request, $id)
    {
        $user = $request->user();

        $profileRequest = ProfileRequest::with([
            'employee.designation',
            'employee.office',
            'reviewedBy'
        ])->findOrFail($id);

        // Permission check
        if (!$this->canAccessRequest($user, $profileRequest)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($profileRequest->status !== 'processed') {
            return response()->json([
                'message' => 'Report is only available for processed requests'
            ], 422);
        }

        $data = [
            'request' => $profileRequest,
            'employee' => $profileRequest->employee,
            'reviewer' => $profileRequest->reviewedBy,
            'date' => now()->format('d M Y'),
            'proposed_changes' => $profileRequest->proposed_changes,
            'status' => $profileRequest->is_approved ? 'APPROVED' : 'REJECTED',
        ];

        $pdf = Pdf::loadView('reports.profile_request_resolution', $data);

        $filename = 'Request_' . $profileRequest->id . '_Resolution_' . date('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // =========================================================
    // HELPER METHODS
    // =========================================================

    /**
     * Check if user can access (view) a request
     */
    private function canAccessRequest($user, ProfileRequest $request): bool
    {
        // Super admin can access all
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Employee can view their own request
        if ($user->employee_id === $request->employee_id) {
            return true;
        }

        // Office admin can view requests from managed offices
        if ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            return in_array($request->employee->current_office_id, $officeIds);
        }

        return false;
    }

    /**
     * Check if user can process (approve/reject) a request
     */
    private function canProcessRequest($user, ProfileRequest $request): bool
    {
        // Verified users cannot process requests
        if ($user->isVerifiedUser()) {
            return false;
        }

        // Cannot process own request
        if ($user->employee_id === $request->employee_id) {
            return false;
        }

        // Super admin can process all
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Office admin can process requests from managed offices
        if ($user->isOfficeAdmin()) {
            $officeIds = $user->getManagedOfficeIds();
            return in_array($request->employee->current_office_id, $officeIds);
        }

        return false;
    }

    /**
     * Handle file uploads from request
     */
    private function handleFileUploads(Request $request): array
    {
        $files = [];

        if (!$request->allFiles()) {
            return $files;
        }

        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $subKey => $subFile) {
                    $path = $subFile->store('profile_requests/temp', 'public');
                    $files[$key][$subKey] = $path;
                }
            } else {
                $path = $file->store('profile_requests/temp', 'public');
                $files[$key] = $path;
            }
        }

        return $files;
    }

    /**
     * Delete uploaded files (for cancelled requests)
     */
    private function deleteUploadedFiles(array $files): void
    {
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $path) {
                    Storage::disk('public')->delete($path);
                }
            } else {
                Storage::disk('public')->delete($value);
            }
        }
    }

    /**
     * Get current employee data for comparison
     */
    private function getCurrentEmployeeData(Employee $employee): array
    {
        return [
            'personal_info' => [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'name_bn' => $employee->name_bn,
                'nid_number' => $employee->nid_number,
                'phone' => $employee->phone,
                'gender' => $employee->gender,
                'dob' => $employee->dob?->format('Y-m-d'),
                'religion' => $employee->religion,
                'blood_group' => $employee->blood_group,
                'marital_status' => $employee->marital_status,
                'place_of_birth' => $employee->place_of_birth,
                'height' => $employee->height,
                'passport' => $employee->passport,
                'birth_reg' => $employee->birth_reg,
            ],
            'family' => [
                'father' => $employee->father?->toArray(),
                'mother' => $employee->mother?->toArray(),
                'spouses' => $employee->spouses->toArray(),
                'children' => $employee->children->toArray(),
            ],
            'addresses' => [
                'present' => $employee->presentAddress?->toArray(),
                'permanent' => $employee->permanentAddress?->toArray(),
            ],
            'academics' => $employee->academics->toArray(),
        ];
    }
}