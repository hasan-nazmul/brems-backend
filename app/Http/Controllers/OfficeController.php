<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    /**
     * Get available zones for dropdowns
     */
    public function zones()
    {
        return response()->json(Office::getZonesForApi());
    }

    /**
     * List all offices
     * Available to all authenticated users (for dropdowns)
     */
    public function index(Request $request)
    {
        $query = Office::with(['parent'])
            ->withCount(['employees' => function ($q) {
                $q->where('status', 'active');
            }]);

        // Filter by zone
        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        // Filter by parent
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Only root offices
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $offices = $query->orderBy('zone')->orderBy('name')->get();

        // Add computed fields
        $offices->each(function ($office) {
            $office->has_admin = $office->hasAdmin();
            $office->child_count = $office->children()->count();
            $office->zone_label = $office->zone_label;
        });

        return response()->json($offices);
    }

    /**
     * Get office hierarchy tree
     */
    public function tree(Request $request)
    {
        $query = Office::with(['children.children.children'])
            ->whereNull('parent_id')
            ->orderBy('zone')
            ->orderBy('name');

        // Filter by zone
        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        $offices = $query->get();

        return response()->json($this->buildTree($offices));
    }

    /**
     * Build hierarchical tree structure
     */
    private function buildTree($offices)
    {
        return $offices->map(function ($office) {
            return [
                'id' => $office->id,
                'name' => $office->name,
                'code' => $office->code,
                'zone' => $office->zone,
                'zone_label' => $office->zone_label,
                'location' => $office->location,
                'has_admin' => $office->hasAdmin(),
                'employee_count' => $office->employees()->where('status', 'active')->count(),
                'children' => $office->children->isNotEmpty() 
                    ? $this->buildTree($office->children) 
                    : [],
            ];
        });
    }

    /**
     * Show single office details
     */
    public function show($id)
    {
        $office = Office::with([
            'parent',
            'children',
            'employees' => function ($q) {
                $q->where('status', 'active')->with('designation');
            }
        ])->findOrFail($id);

        $office->has_admin = $office->hasAdmin();
        $office->zone_label = $office->zone_label;
        $office->admin = $office->users()
            ->where('role', 'office_admin')
            ->where('is_active', true)
            ->first();

        return response()->json($office);
    }

    /**
     * Create new office (Super Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'zone' => ['nullable', Rule::in(array_keys(Office::ZONES))],
            'code' => 'required|string|max:50|unique:offices,code',
            'location' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:offices,id',
        ]);

        // If parent has a zone and no zone specified, inherit from parent
        if (empty($validated['zone']) && !empty($validated['parent_id'])) {
            $parent = Office::find($validated['parent_id']);
            $validated['zone'] = $parent->zone;
        }

        $office = Office::create($validated);

        return response()->json([
            'message' => 'Office created successfully',
            'office' => $office->load('parent')
        ], 201);
    }

    /**
     * Update office (Super Admin only)
     */
    public function update(Request $request, $id)
    {
        $office = Office::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'zone' => ['nullable', Rule::in(array_keys(Office::ZONES))],
            'code' => 'sometimes|string|max:50|unique:offices,code,' . $id,
            'location' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:offices,id',
        ]);

        // Prevent circular reference
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json([
                'message' => 'An office cannot be its own parent'
            ], 422);
        }

        // Prevent setting child as parent
        if (isset($validated['parent_id'])) {
            $childIds = $office->getAllChildIds();
            if (in_array($validated['parent_id'], $childIds)) {
                return response()->json([
                    'message' => 'Cannot set a child office as parent'
                ], 422);
            }
        }

        $office->update($validated);

        // Optionally update children zones if this is a root office
        if (isset($validated['zone']) && $request->boolean('update_children_zone')) {
            $office->children()->update(['zone' => $validated['zone']]);
        }

        $fresh = $office->fresh()->load('parent');
        $fresh->zone_label = $fresh->zone_label;

        return response()->json([
            'message' => 'Office updated successfully',
            'office' => $fresh
        ]);
    }

    /**
     * Delete office (Super Admin only)
     */
    public function destroy($id)
    {
        $office = Office::withCount('employees')->findOrFail($id);

        if ($office->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete office with assigned employees. Transfer employees first.'
            ], 422);
        }

        if ($office->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete office with child offices. Delete or reassign children first.'
            ], 422);
        }

        $office->delete();

        return response()->json(['message' => 'Office deleted successfully']);
    }

    /**
     * Get offices managed by current user
     */
    public function managed(Request $request)
    {
        $user = $request->user();
        $officeIds = $user->getManagedOfficeIds();

        $offices = Office::whereIn('id', $officeIds)
            ->with('parent')
            ->withCount(['employees' => function ($q) {
                $q->where('status', 'active');
            }])
            ->orderBy('zone')
            ->orderBy('name')
            ->get();

        $offices->each(function ($office) {
            $office->zone_label = $office->zone_label;
        });

        return response()->json($offices);
    }
}