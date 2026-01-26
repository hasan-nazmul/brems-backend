<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index() {
        // Ensure 'parent' relationship is loaded
        return Office::with('parent')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'required|unique:offices',
            'location' => 'required'
        ]);

        $office = Office::create($request->all());
        return response()->json($office, 201);
    }

    public function update(Request $request, $id) {
        $office = Office::findOrFail($id);
        $office->update($request->all());
        return response()->json($office);
    }
}