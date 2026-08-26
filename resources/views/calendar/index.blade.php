@extends('layouts.app')
@section('title','Календарь — CRM ЗЦРБ')
@section('header','Календарь')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>#calendar{background:#fff;padding:16px;border-radius:12px;box-shadow:0 1px 3px rgba(16,24,40,.08)}.fc .fc-toolbar-title{font-size:1.25rem}.fc-event{cursor:pointer}</style>
@endpush
@section('content')
<div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex gap-3 flex-wrap align-items-center"><span class="badge text-bg-primary">Задачи</span><span class="badge text-bg-success">Планы</span><span class="text-muted small">Нажмите на событие, чтобы открыть связанную задачу или раздел планов.</span></div></div>
<div id="calendar"></div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const c=new FullCalendar.Calendar(document.getElementById('calendar'),{
   locale:'ru',firstDay:1,height:'auto',initialView:'dayGridMonth',
   headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,listWeek'},
   buttonText:{today:'Сегодня',month:'Месяц',week:'Неделя',list:'Список'},
   events:'{{ route('calendar.events') }}',
   eventDidMount:function(info){
      const k=info.event.extendedProps.kind;
      info.el.style.backgroundColor=k==='plan'?'#198754':'#0d6efd';
      info.el.style.borderColor='transparent';
      const a=info.event.extendedProps.assignee;if(a)info.el.title=a;
   }
 });c.render();
});
</script>
@endpush
