<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormField;
use Illuminate\Http\Request;

class FormController extends Controller
{
    // 1. ADMIN: Create a new Form with Fields
    // App\Http\Controllers\FormController.php

    public function store(Request $request) {
        $form = Form::create(['title' => $request->title, 'description' => $request->description]);
        
        if($request->fields) {
            // Eloquent 'createMany' handles the loop internally
            $form->fields()->createMany($request->fields);
        }
        return response()->json($form);
    }

    // 2. EMPLOYEE: Get list of active forms to fill
    public function index() {
        return Form::where('is_active', true)->with('fields')->get();
    }

    // 3. EMPLOYEE: Submit a Form
    public function submit(Request $request, $id) {
        $user = $request->user();
        
        FormSubmission::create([
            'form_id' => $id,
            'employee_id' => $user->employee_id,
            'data' => $request->answers, // JSON data
            'status' => 'pending'
        ]);
        
        return response()->json(['message' => 'Submitted successfully!']);
    }

    // 4. ADMIN: View Submissions
    public function submissions() {
        return FormSubmission::with(['form', 'employee'])->latest()->get();
    }
}