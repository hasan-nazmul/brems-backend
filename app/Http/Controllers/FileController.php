<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AcademicRecord;
use App\Models\FamilyMember;
use App\Models\ProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\EmployeeResource;
class FileController extends Controller
{
    // =========================================================
    // PROFILE PICTURE
    // =========================================================

    /**
     * Upload profile picture.
     * Admin: applied immediately. Employee (own profile): stored in pending until admin approves.
     */
    public function uploadProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $ext = $request->file('photo')->extension();
        $filename = $employee->nid_number . '_' . time() . '.' . $ext;

        // Apply immediately only when an admin is uploading for this employee (not when employee uploads their own)
        $isAdminUploadingForEmployee = $user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id;
        if ($isAdminUploadingForEmployee) {
            if ($employee->profile_picture) {
                Storage::disk('public')->delete($employee->profile_picture);
            }
            $path = $request->file('photo')->storeAs('photos', $filename, 'public');
            $employee->update(['profile_picture' => $path]);
            return response()->json([
                'message' => 'Photo uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);
        }

        // Employee uploading own profile: save to pending and create request (no change until admin approves)
        $path = $request->file('photo')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'profile_picture_' . time() . '.' . $ext,
            'public'
        );
        $this->createDocumentUpdateRequestIfOwnProfile($user, $employee, 'Profile picture', $path, ['employee_field' => 'profile_picture']);

        return response()->json([
            'message' => 'Photo submitted for admin approval. It will appear on your profile once approved.',
            'path' => $path,
            'pending' => true,
        ]);
    }

    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if ($employee->profile_picture) {
            Storage::disk('public')->delete($employee->profile_picture);
            $employee->update(['profile_picture' => null]);
        }

        return response()->json(['message' => 'Photo deleted successfully']);
    }

    // =========================================================
    // DOCUMENT UPLOADS (NID, Birth Certificate)
    // =========================================================

    /**
     * Upload NID document.
     * Admin: applied immediately. Employee (own profile): pending until admin approves.
     */
    public function uploadNidDocument(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $ext = $request->file('document')->extension();
        $filename = 'NID_' . $employee->nid_number . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = $user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id;
        if ($isAdminUploadingForEmployee) {
            if ($employee->nid_file_path) {
                Storage::disk('public')->delete($employee->nid_file_path);
            }
            $path = $request->file('document')->storeAs('documents/nid', $filename, 'public');
            $employee->update(['nid_file_path' => $path]);
            return response()->json([
                'message' => 'NID document uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);
        }

        $path = $request->file('document')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'nid_' . time() . '.' . $ext,
            'public'
        );
        $this->createDocumentUpdateRequestIfOwnProfile($user, $employee, 'NID document', $path, ['employee_field' => 'nid_file_path']);

        return response()->json([
            'message' => 'Document submitted for admin approval.',
            'path' => $path,
            'pending' => true,
        ]);
    }

    /**
     * Upload birth certificate document.
     * Admin: applied immediately. Employee (own profile): pending until admin approves.
     */
    public function uploadBirthCertificate(Request $request, $employeeId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $ext = $request->file('document')->extension();
        $filename = 'BIRTH_' . $employee->nid_number . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = $user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id;
        if ($isAdminUploadingForEmployee) {
            if ($employee->birth_file_path) {
                Storage::disk('public')->delete($employee->birth_file_path);
            }
            $path = $request->file('document')->storeAs('documents/birth', $filename, 'public');
            $employee->update(['birth_file_path' => $path]);
            return response()->json([
                'message' => 'Birth certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);
        }

        $path = $request->file('document')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'birth_' . time() . '.' . $ext,
            'public'
        );
        $this->createDocumentUpdateRequestIfOwnProfile($user, $employee, 'Birth certificate', $path, ['employee_field' => 'birth_file_path']);

        return response()->json([
            'message' => 'Document submitted for admin approval.',
            'path' => $path,
            'pending' => true,
        ]);
    }

    /**
     * Delete document (NID or Birth Certificate)
     */
    public function deleteDocument(Request $request, $employeeId, $type)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $column = $type === 'nid' ? 'nid_file_path' : 'birth_file_path';

        if ($employee->$column) {
            Storage::disk('public')->delete($employee->$column);
            $employee->update([$column => null]);
        }

        return response()->json(['message' => 'Document deleted successfully']);
    }

    // =========================================================
    // ACADEMIC CERTIFICATE UPLOADS
    // =========================================================

    /**
     * Upload academic certificate
     */
    public function uploadAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)
            ->findOrFail($academicId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $examSlug = str_replace(['/', ' '], '_', $academic->exam_name);
        $ext = $request->file('certificate')->extension();
        $filename = 'CERT_' . $employee->nid_number . '_' . $examSlug . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = $user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id;
        if ($isAdminUploadingForEmployee) {
            if ($academic->certificate_path) {
                Storage::disk('public')->delete($academic->certificate_path);
            }
            $path = $request->file('certificate')->storeAs('documents/certificates', $filename, 'public');
            $academic->update(['certificate_path' => $path]);
            return response()->json([
                'message' => 'Certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);
        }

        $path = $request->file('certificate')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'academic_' . $academic->id . '_' . time() . '.' . $ext,
            'public'
        );
        $this->createDocumentUpdateRequestIfOwnProfile($user, $employee, 'Academic certificate: ' . ($academic->exam_name ?? 'certificate'), $path, ['academic_id' => $academic->id]);

        return response()->json([
            'message' => 'Certificate submitted for admin approval.',
            'path' => $path,
            'pending' => true,
        ]);
    }

    /**
     * Delete academic certificate
     */
    public function deleteAcademicCertificate(Request $request, $employeeId, $academicId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $academic = AcademicRecord::where('employee_id', $employeeId)
            ->findOrFail($academicId);

        if ($academic->certificate_path) {
            Storage::disk('public')->delete($academic->certificate_path);
            $academic->update(['certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // CHILD BIRTH CERTIFICATE UPLOADS
    // =========================================================

    /**
     * Upload child's birth certificate
     */
    public function uploadChildBirthCertificate(Request $request, $employeeId, $familyMemberId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $child = FamilyMember::where('employee_id', $employeeId)
            ->where('relation', 'child')
            ->findOrFail($familyMemberId);

        $request->validate([
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $childNameSlug = str_replace(' ', '_', $child->name);
        $ext = $request->file('certificate')->extension();
        $filename = 'CHILD_BIRTH_' . $employee->nid_number . '_' . $childNameSlug . '_' . time() . '.' . $ext;

        $isAdminUploadingForEmployee = $user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id;
        if ($isAdminUploadingForEmployee) {
            if ($child->birth_certificate_path) {
                Storage::disk('public')->delete($child->birth_certificate_path);
            }
            $path = $request->file('certificate')->storeAs('documents/children', $filename, 'public');
            $child->update(['birth_certificate_path' => $path]);
            return response()->json([
                'message' => 'Child birth certificate uploaded successfully',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);
        }

        $path = $request->file('certificate')->storeAs(
            'documents/pending/employee_' . $employee->id,
            'child_birth_' . $child->id . '_' . time() . '.' . $ext,
            'public'
        );
        $this->createDocumentUpdateRequestIfOwnProfile($user, $employee, 'Child birth certificate: ' . ($child->name ?? 'child'), $path, ['family_member_id' => $child->id]);

        return response()->json([
            'message' => 'Certificate submitted for admin approval.',
            'path' => $path,
            'pending' => true,
        ]);
    }

    /**
     * Delete child's birth certificate
     */
    public function deleteChildBirthCertificate(Request $request, $employeeId, $familyMemberId)
    {
        $user = $request->user();
        $employee = Employee::findOrFail($employeeId);

        if (!$user->canManageEmployee($employee) && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $child = FamilyMember::where('employee_id', $employeeId)
            ->where('relation', 'child')
            ->findOrFail($familyMemberId);

        if ($child->birth_certificate_path) {
            Storage::disk('public')->delete($child->birth_certificate_path);
            $child->update(['birth_certificate_path' => null]);
        }

        return response()->json(['message' => 'Certificate deleted successfully']);
    }

    // =========================================================
    // FILE DOWNLOAD / VIEW
    // =========================================================

    /**
     * Get file URL for viewing
     */
    public function getFileUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'exists' => true
        ]);
    }

    /**
     * Download file
     */
    public function downloadFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($path);
    }

    /**
     * Create a "Document Update" profile request when a verified user uploads their own document.
     * Stores file_path and revert info so admin can revert (delete file, clear field) on reject.
     *
     * @param  array  $revertInfo  One of: ['employee_field' => 'nid_file_path'], ['academic_id' => 1], ['family_member_id' => 1]
     */
    private function createDocumentUpdateRequestIfOwnProfile($user, Employee $employee, string $documentType, string $filePath, array $revertInfo = []): void
    {
        if ((int) $user->employee_id !== (int) $employee->id) {
            return;
        }
        if ($user->canManageEmployee($employee)) {
            return; // admin upload, no request needed
        }

        try {
            ProfileRequest::create([
                'employee_id' => $employee->id,
                'request_type' => 'Document Update',
                'details' => 'Uploaded: ' . $documentType,
                'proposed_changes' => [
                    'document_update' => array_merge([
                        'type' => $documentType,
                        'uploaded_at' => now()->toIso8601String(),
                        'file_path' => $filePath,
                    ], $revertInfo),
                ],
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create Document Update profile request: ' . $e->getMessage());
        }
    }
}