<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,txt,rtf,jpg,jpeg,png,webp,zip,rar,7z',
        ]);

        $file = $request->file('file');
        $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('task-attachments/'.$task->id, $storedName, 'local');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        TaskEvent::create([
            'task_id'=>$task->id,
            'user_id'=>$request->user()->id,
            'type'=>'attachment_added',
            'from_status'=>$task->status,
            'to_status'=>$task->status,
            'message'=>'Прикреплён файл: '.$attachment->original_name,
        ]);

        return response()->json(['ok'=>true,'attachment'=>$attachment->load('user')], 201);
    }

    public function download(Request $request, TaskAttachment $attachment)
    {
        $this->authorizeTask($request, $attachment->task);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, TaskAttachment $attachment)
    {
        $task = $attachment->task;
        $this->authorizeTask($request, $task);
        $user = $request->user();
        abort_unless($user->isManager() || $attachment->user_id === $user->id || $task->assigned_to === $user->id, 403);

        Storage::disk('local')->delete($attachment->path);
        $name = $attachment->original_name;
        $attachment->delete();

        TaskEvent::create([
            'task_id'=>$task->id,
            'user_id'=>$user->id,
            'type'=>'attachment_deleted',
            'from_status'=>$task->status,
            'to_status'=>$task->status,
            'message'=>'Удалён файл: '.$name,
        ]);

        return response()->json(['ok'=>true]);
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        $u = $request->user();
        abort_unless($u->isManager() || $task->assigned_to === $u->id || $task->created_by === $u->id, 403);
    }
}
