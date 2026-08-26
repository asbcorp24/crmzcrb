<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function page() { return view('departments.index'); }

    public function index()
    {
        return response()->json(Department::withCount('users')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'parent_id'=>'nullable|exists:departments,id','name'=>'required|string|max:190','short_name'=>'nullable|string|max:100',
            'type'=>'nullable|string|max:100','is_active'=>'required|boolean','sort_order'=>'nullable|integer|min:0|max:10000'
        ]);
        $department = Department::create($data);
        return response()->json(['ok'=>true,'department'=>$department], 201);
    }

    public function update(Request $request, Department $department)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'parent_id'=>'nullable|exists:departments,id','name'=>'sometimes|required|string|max:190','short_name'=>'nullable|string|max:100',
            'type'=>'nullable|string|max:100','is_active'=>'sometimes|boolean','sort_order'=>'nullable|integer|min:0|max:10000'
        ]);
        if (isset($data['parent_id']) && (int)$data['parent_id'] === $department->id) return response()->json(['message'=>'Подразделение не может быть родителем само себе'], 422);
        $department->update($data);
        return response()->json(['ok'=>true,'department'=>$department->fresh()]);
    }
}
