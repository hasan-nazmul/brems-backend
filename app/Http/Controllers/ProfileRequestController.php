<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProfileRequest; // <--- MAKE SURE THIS IS HERE

class ProfileRequestController extends Controller
{
    // 1. GET ALL REQUESTS
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Base Query
        $query = ProfileRequest::with('employee')->latest();

        // 1. Office Admin: Filter by Office
        if ($user->role === 'office_admin') {
            $query->whereHas('employee', function($q) use ($user) {
                $q->where('current_office_id', $user->office_id);
            });
        }

        // 2. Regular Employee: Should only see their own requests (for the portal)
        if ($user->role === 'verified_user') {
            $query->where('employee_id', $user->employee_id);
        }

        return response()->json($query->get());
    }

    // 2. SUBMIT REQUEST
    public function store(Request $request) {
        $user = $request->user();
        
        // Safety check: Is this user actually an employee?
        if (!$user->employee_id) {
            return response()->json(['message' => 'User is not linked to an employee profile'], 403);
        }

        $path = null;
        if($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('documents', 'public');
        }

        $profileRequest = ProfileRequest::create([
            'employee_id' => $user->employee_id,
            'request_type' => 'Profile Update',
            'details' => $request->details, 
            'proposed_changes' => $request->changes ? json_decode($request->changes, true) : null,
            'attachment' => $path,
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Submitted', 'data' => $profileRequest], 201);
    }
    
    // 3. UPDATE (Needed for Admin to approve)
        // App\Http\Controllers\ProfileRequestController.php

    public function update(Request $request, $id)
    {
        // Load the request AND the employee
        $profileRequest = ProfileRequest::with('employee')->findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string'
        ]);

        // === THE MISSING LOGIC ===
        // If Admin is approving, we must apply the changes to the Employee table
        if ($validated['status'] === 'approved' && $profileRequest->status !== 'approved') {
            
            // Get the JSON data (Laravel automatically converts JSON to Array because of your Model casts)
            $changes = $profileRequest->proposed_changes;

            if ($changes) {
                // Update the Employee's actual profile
                $profileRequest->employee->update([
                    'first_name' => $changes['first_name'] ?? $profileRequest->employee->first_name,
                    'last_name'  => $changes['last_name']  ?? $profileRequest->employee->last_name,
                    'phone'      => $changes['phone']      ?? $profileRequest->employee->phone,
                    'address'    => $changes['address']    ?? $profileRequest->employee->address,
                ]);
            }
        }
        // =========================

        // Finally, update the request status to 'approved'
        $profileRequest->update([
            'status' => $validated['status'],
            'admin_note' => $request->admin_note ?? null
        ]);

        return response()->json(['message' => 'Request updated and changes applied', 'data' => $profileRequest]);
    }
}