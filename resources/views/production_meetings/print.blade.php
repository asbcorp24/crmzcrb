<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Протокол № {{ $meeting->protocol_number ?: $meeting->id }}</title>
<style>
body{font-family:DejaVu Sans,Arial,sans-serif;color:#111;font-size:12px;margin:24px}.toolbar{margin-bottom:18px}.toolbar button{padding:8px 14px}.head{text-align:center;margin-bottom:18px}.head h2{margin:0 0 6px}.meta{margin:12px 0}.meta div{margin:3px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:6px;vertical-align:top}th{text-align:center;background:#f1f1f1}.num{text-align:center;width:38px}.center{text-align:center}.small{font-size:10px;color:#444}@media print{.toolbar{display:none}body{margin:0;font-size:10px}th,td{padding:4px}}
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Печать</button></div>
<div class="head">
    <h2>Протокол № {{ $meeting->protocol_number ?: $meeting->id }} производственного совещания</h2>
    <div>{{ $meeting->held_at ? $meeting->held_at->format('d.m.Y H:i') : 'Дата не указана' }}</div>
</div>
<div class="meta">
    <div><b>Совещание:</b> {{ $meeting->title }}</div>
    <div><b>Председатель:</b> {{ $meeting->chairman?->full_name ?: '—' }}</div>
    @if($meeting->secretary)<div><b>Секретарь:</b> {{ $meeting->secretary->full_name }}</div>@endif
    @if($meeting->notes)<div><b>Повестка / примечание:</b> {{ $meeting->notes }}</div>@endif
</div>
<table>
    <thead>
        <tr>
            <th>№</th>
            <th>Мероприятие, задача</th>
            <th>Ответственное подразделение</th>
            <th>Соисполнитель</th>
            <th>Начало</th>
            <th>Окончание</th>
            <th>Длит., дн.</th>
            <th>Статус</th>
        </tr>
    </thead>
    <tbody>
        @forelse($meeting->items as $item)
        <tr>
            <td class="num">{{ $item->number }}</td>
            <td>{{ $item->instruction }}@if($item->task_id)<div class="small">Задача CRM #{{ $item->task_id }}</div>@endif</td>
            <td>{{ $item->department?->short_name ?: ($item->department?->name ?: '—') }}</td>
            <td>{{ $item->coexecutor?->full_name ?: '—' }}</td>
            <td class="center">{{ $item->start_at?->format('d.m.Y') ?: '—' }}</td>
            <td class="center">{{ $item->due_at?->format('d.m.Y') ?: '—' }}</td>
            <td class="center">{{ $item->duration_days ?? '—' }}</td>
            <td>{{ ['pending'=>'Не начато','in_progress'=>'В работе','completed'=>'Выполнено','cancelled'=>'Отменено'][$item->status] ?? $item->status }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="center">Мероприятия не добавлены.</td></tr>
        @endforelse
    </tbody>
</table>
<div style="margin-top:28px;display:flex;justify-content:space-between;gap:40px"><div>Председатель: __________________ / {{ $meeting->chairman?->full_name ?: '________________' }}</div><div>Дата формирования: {{ $meeting->protocol_generated_at?->format('d.m.Y H:i') ?: now()->format('d.m.Y H:i') }}</div></div>
</body>
</html>
