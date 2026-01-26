<?php
namespace App\Http\Controllers;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    // List all (For dropdowns)
    public function index() {
        return Designation::orderBy('grade', 'asc')->get();
    }

    // Create new (For Super Admin)
    public function store(Request $request) {
        $request->validate(['title'=>'required', 'grade'=>'required', 'basic_salary'=>'required']);
        return Designation::create($request->all());
    }

    public function update(Request $request, $id) {
        $des = Designation::findOrFail($id);
        
        $request->validate([
            'title' => 'sometimes|required|string',
            'grade' => 'sometimes|required',
            'basic_salary' => 'sometimes|numeric'
        ]);

        $des->update($request->all());
        return response()->json($des);
    }
}