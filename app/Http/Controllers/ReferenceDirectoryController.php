<?php

namespace App\Http\Controllers;

use App\Models\ReferenceItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferenceDirectoryController extends Controller
{
    private const TYPES = ['project','organization','task_status','basis'];
    private const STATUS_KEYS = ['new','in_progress','review','completed','cancelled'];

    public function page(Request $request)
    {
        $this->authorizeManager($request);
        return view('directories.index');
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $type = $request->string('type')->toString();
        abort_unless(in_array($type, self::TYPES, true), 422, 'Неизвестный справочник.');

        $q = ReferenceItem::withoutGlobalScope('organization')
            ->where('organization_id', $request->user()->organization_id)
            ->where('type', $type);
        if ($request->filled('q')) {
            $term = trim((string)$request->q);
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', '%'.$term.'%')
                    ->orWhere('code', 'like', '%'.$term.'%')
                    ->orWhere('notes', 'like', '%'.$term.'%');
            });
        }
        if ($request->has('active') && $request->active !== '') {
            $q->where('is_active', $request->boolean('active'));
        }

        return response()->json($q->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);
        $data['organization_id'] = $request->user()->organization_id;
        $item = ReferenceItem::create($data);
        return response()->json(['ok'=>true,'item'=>$item], 201);
    }

    public function update(Request $request, ReferenceItem $referenceItem)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request, $referenceItem);
        abort_unless((int)$referenceItem->organization_id === (int)$request->user()->organization_id, 404);
        $referenceItem->update($data);
        return response()->json(['ok'=>true,'item'=>$referenceItem->fresh()]);
    }

    public function toggle(Request $request, ReferenceItem $referenceItem)
    {
        $this->authorizeManager($request);
        abort_unless((int)$referenceItem->organization_id === (int)$request->user()->organization_id, 404);
        $data = $request->validate(['is_active'=>'required|boolean']);
        $referenceItem->update(['is_active'=>$data['is_active']]);
        return response()->json(['ok'=>true,'item'=>$referenceItem->fresh()]);
    }

    private function validated(Request $request, ?ReferenceItem $item = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'code' => 'nullable|string|max:80',
            'name' => 'required|string|max:255',
            'system_key' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $data['code'] = trim((string)($data['code'] ?? '')) ?: null;
        $data['notes'] = trim((string)($data['notes'] ?? '')) ?: null;
        $data['sort_order'] = (int)($data['sort_order'] ?? 0);
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true;

        if ($data['type'] === 'task_status') {
            abort_unless(in_array($data['system_key'] ?? '', self::STATUS_KEYS, true), 422, 'Для статуса задачи выберите системный статус.');
            $duplicate = ReferenceItem::withoutGlobalScope('organization')
                ->where('organization_id', request()->user()->organization_id)
                ->where('type','task_status')->where('system_key',$data['system_key']);
            if ($item) $duplicate->whereKeyNot($item->id);
            abort_if($duplicate->exists(), 422, 'Этот системный статус уже есть в справочнике.');
        } else {
            $data['system_key'] = null;
            $data['color'] = null;
        }

        if ($data['code']) {
            $duplicate = ReferenceItem::withoutGlobalScope('organization')
                ->where('organization_id', request()->user()->organization_id)
                ->where('type',$data['type'])->where('code',$data['code']);
            if ($item) $duplicate->whereKeyNot($item->id);
            abort_if($duplicate->exists(), 422, 'Запись с таким кодом уже существует.');
        }

        return $data;
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user() && $request->user()->isManager(), 403);
    }
}
