<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\Position;
use App\Models\StaffingPosition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffingController extends Controller
{
    public function page(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        return view('staffing.index', [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(),
            'employees' => User::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function positions(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        return response()->json(Position::orderBy('name')->get());
    }

    public function storePosition(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'name'=>'required|string|max:190',
            'category'=>'nullable|string|max:100',
            'code'=>'nullable|string|max:50|unique:positions,code',
            'is_active'=>'required|boolean',
        ]);
        return response()->json(['ok'=>true,'position'=>Position::create($data)], 201);
    }

    public function updatePosition(Request $request, Position $position)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'name'=>'sometimes|required|string|max:190',
            'category'=>'nullable|string|max:100',
            'code'=>['nullable','string','max:50',Rule::unique('positions','code')->ignore($position->id)],
            'is_active'=>'sometimes|boolean',
        ]);
        $position->update($data);
        return response()->json(['ok'=>true,'position'=>$position->fresh()]);
    }

    public function rows(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $q = StaffingPosition::with(['department','position','activeAssignments.user']);
        if ($request->filled('department_id')) $q->where('department_id', $request->integer('department_id'));
        if ($request->boolean('vacant')) {
            $q->whereRaw('(SELECT COALESCE(SUM(rate),0) FROM employee_assignments WHERE staffing_position_id = staffing_positions.id AND ended_at IS NULL) < planned_rate');
        }
        return response()->json($q->orderBy('department_id')->orderBy('position_id')->get());
    }

    public function storeRow(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'department_id'=>'required|exists:departments,id',
            'position_id'=>'required|exists:positions,id',
            'planned_rate'=>'required|numeric|min:0.01|max:999.99',
            'note'=>'nullable|string|max:255',
            'is_active'=>'required|boolean',
        ]);
        $row = StaffingPosition::create($data);
        return response()->json(['ok'=>true,'row'=>$row->load(['department','position','activeAssignments.user'])], 201);
    }

    public function updateRow(Request $request, StaffingPosition $staffingPosition)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'department_id'=>'sometimes|required|exists:departments,id',
            'position_id'=>'sometimes|required|exists:positions,id',
            'planned_rate'=>'sometimes|required|numeric|min:0.01|max:999.99',
            'note'=>'nullable|string|max:255',
            'is_active'=>'sometimes|boolean',
        ]);
        $staffingPosition->update($data);
        return response()->json(['ok'=>true,'row'=>$staffingPosition->fresh()->load(['department','position','activeAssignments.user'])]);
    }

    public function assignments(Request $request, User $employee)
    {
        abort_unless($request->user()->isManager() || $request->user()->id === $employee->id, 403);
        return response()->json(EmployeeAssignment::with(['staffingPosition.department','staffingPosition.position'])
            ->where('user_id',$employee->id)->orderByDesc('started_at')->orderByDesc('id')->get());
    }

    public function assign(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate([
            'user_id'=>'required|exists:users,id',
            'staffing_position_id'=>'required|exists:staffing_positions,id',
            'rate'=>'required|numeric|min:0.01|max:9.99',
            'is_primary'=>'required|boolean',
            'started_at'=>'required|date',
            'order_number'=>'nullable|string|max:100',
            'note'=>'nullable|string|max:5000',
        ]);

        $row = StaffingPosition::findOrFail($data['staffing_position_id']);
        $occupied = (float) $row->activeAssignments()->sum('rate');
        if ($occupied + (float)$data['rate'] > (float)$row->planned_rate + 0.0001) {
            return response()->json(['message'=>'Назначение превышает количество ставок по штатному расписанию'], 422);
        }

        if ($data['is_primary']) {
            EmployeeAssignment::where('user_id',$data['user_id'])->whereNull('ended_at')->update(['is_primary'=>false]);
        }

        $assignment = EmployeeAssignment::create($data);
        return response()->json(['ok'=>true,'assignment'=>$assignment->load(['user','staffingPosition.department','staffingPosition.position'])], 201);
    }

    public function endAssignment(Request $request, EmployeeAssignment $assignment)
    {
        abort_unless($request->user()->isManager(), 403);
        $data = $request->validate(['ended_at'=>'required|date|after_or_equal:'.$assignment->started_at->format('Y-m-d')]);
        $assignment->update(['ended_at'=>$data['ended_at'],'is_primary'=>false]);
        return response()->json(['ok'=>true,'assignment'=>$assignment->fresh()]);
    }
}
