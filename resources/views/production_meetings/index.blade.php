@extends('layouts.app')
@section('title','Производственные совещания — CRM')
@section('header','Производственные совещания')
@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="text-muted">Мероприятия → протокол → автоматические задачи ответственным руководителям.</div>
    <button class="btn btn-primary ms-auto" onclick="newMeeting()"><i class="bi bi-plus-lg me-1"></i>Новое совещание</button>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center"><b>Совещания</b><button class="btn btn-sm btn-light ms-auto" onclick="loadMeetings()"><i class="bi bi-arrow-clockwise"></i></button></div>
            <div id="meetingList" class="list-group list-group-flush"><div class="text-center text-muted py-4">Загрузка...</div></div>
        </div>
    </div>
    <div class="col-xl-8">
        <div id="emptyMeeting" class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-text fs-1 d-block mb-2"></i>Выберите совещание или создайте новое.</div></div>
        <div id="meetingEditor" class="d-none">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <div class="flex-grow-1"><div class="small text-muted" id="meetingProtocol"></div><h4 id="meetingTitle" class="mb-1"></h4><div id="meetingMeta" class="text-muted"></div></div>
                        <button class="btn btn-outline-secondary" onclick="editMeeting()"><i class="bi bi-pencil me-1"></i>Редактировать</button>
                        <a id="printProtocolBtn" href="#" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Протокол</a>
                        <button class="btn btn-success" onclick="generateProtocol()"><i class="bi bi-file-earmark-check me-1"></i><span id="generateText">Сформировать протокол</span></button>
                    </div>
                    <div id="meetingNotes" class="mt-3"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center"><div><b>Мероприятия</b><div class="small text-muted">Каждый пункт протокола связан с отдельной задачей.</div></div><button class="btn btn-primary btn-sm ms-auto" onclick="newItem()"><i class="bi bi-plus-lg me-1"></i>Добавить мероприятие</button></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th style="width:55px">№</th><th>Мероприятие, задача</th><th>Ответственный отдел</th><th>Соисполнитель</th><th>Начало</th><th>Окончание</th><th>Дней</th><th>Статус</th><th style="width:90px"></th></tr></thead>
                        <tbody id="itemsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="meetingModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="meetingForm" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Производственное совещание</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" id="meetingId">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Название</label><input name="title" class="form-control" required placeholder="Производственное совещание"></div>
            <div class="col-md-4"><label class="form-label">№ протокола</label><input name="protocol_number" class="form-control" placeholder="Например: 36"></div>
            <div class="col-md-4"><label class="form-label">Дата и время</label><input name="held_at" type="datetime-local" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Председатель</label><select name="chairman_id" class="form-select"><option value="">—</option>@foreach($managers as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Секретарь</label><select name="secretary_id" class="form-select"><option value="">—</option>@foreach($managers as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Примечание / повестка</label><textarea name="notes" rows="4" class="form-control"></textarea></div>
        </div>
        <div id="meetingError" class="alert alert-danger mt-3 d-none"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div>
</form></div></div>

<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="itemForm" class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Мероприятие протокола</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" id="itemId">
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Мероприятие / задача</label><textarea name="instruction" rows="4" class="form-control" required></textarea></div>
            <div class="col-md-6"><label class="form-label">Ответственное подразделение</label><select id="itemDepartment" name="responsible_department_id" class="form-select" required><option value="">Выберите...</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Соисполнитель / руководитель подразделения</label><select id="itemCoexecutor" name="coexecutor_id" class="form-select" required><option value="">Выберите...</option>@foreach($managers as $u)<option value="{{ $u->id }}" data-department="{{ $u->department_id }}">{{ $u->full_name }}{{ $u->department ? ' — '.$u->department->name : '' }}</option>@endforeach</select><div class="form-text">При выборе подразделения его руководитель подставляется автоматически.</div></div>
            <div class="col-md-4"><label class="form-label">Начало</label><input name="start_at" type="date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Окончание</label><input name="due_at" type="date" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Статус</label><select name="status" class="form-select"><option value="pending">Не начато</option><option value="in_progress">В работе</option><option value="completed">Выполнено</option><option value="cancelled">Отменено</option></select></div>
        </div>
        <div id="itemError" class="alert alert-danger mt-3 d-none"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary">Сохранить</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
const departmentHeads=@json($departmentHeads);
let currentMeeting=null;
let meetingRows=[];
const meetingModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('meetingModal'));
const itemModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal'));

function esc(v){return $('<div>').text(v??'').html()}
function fmtDate(v){if(!v)return'—';return new Date(v).toLocaleDateString('ru-RU')}
function fmtDateTime(v){if(!v)return'Дата не указана';return new Date(v).toLocaleString('ru-RU')}
function localDateTime(v){if(!v)return'';const d=new Date(v),z=n=>String(n).padStart(2,'0');return `${d.getFullYear()}-${z(d.getMonth()+1)}-${z(d.getDate())}T${z(d.getHours())}:${z(d.getMinutes())}`}
function statusName(v){return {draft:'Черновик',protocol:'Протокол сформирован',pending:'Не начато',in_progress:'В работе',completed:'Выполнено',cancelled:'Отменено'}[v]||v}
function statusBadge(v){return {protocol:'success',draft:'secondary',pending:'secondary',in_progress:'primary',completed:'success',cancelled:'dark'}[v]||'secondary'}
function fullName(u){return u?`${u.last_name||''} ${u.first_name||''} ${u.middle_name||''}`.trim():'—'}

function loadMeetings(selectId=null){
    $.get('{{ route('production-meetings.index') }}', rows=>{
        meetingRows=rows||[];
        let h='';
        meetingRows.forEach(m=>{h+=`<button type="button" class="list-group-item list-group-item-action ${currentMeeting&&currentMeeting.id===m.id?'active':''}" onclick="openMeeting(${m.id})"><div class="d-flex gap-2"><div class="flex-grow-1"><div class="fw-semibold">${esc(m.title)}</div><div class="small ${currentMeeting&&currentMeeting.id===m.id?'text-white-50':'text-muted'}">${fmtDateTime(m.held_at)} · пунктов: ${m.items_count}</div></div><span class="badge text-bg-${statusBadge(m.status)} align-self-start">${statusName(m.status)}</span></div></button>`});
        $('#meetingList').html(h||'<div class="text-center text-muted py-4">Совещаний пока нет.</div>');
        if(selectId)openMeeting(selectId);
    });
}

function openMeeting(id){
    $.get(`{{ url('/ajax/production-meetings') }}/${id}`, m=>{
        currentMeeting=m;
        $('#emptyMeeting').addClass('d-none');$('#meetingEditor').removeClass('d-none');
        $('#meetingTitle').text(m.title);
        $('#meetingProtocol').text(`Протокол № ${m.protocol_number||m.id} · ${statusName(m.status)}`);
        $('#meetingMeta').text(`${fmtDateTime(m.held_at)} · Председатель: ${fullName(m.chairman)}`);
        $('#meetingNotes').text(m.notes||'').toggleClass('d-none',!m.notes);
        $('#printProtocolBtn').attr('href',`{{ url('/production-meetings') }}/${m.id}/print`);
        $('#generateText').text(m.status==='protocol'?'Обновить протокол и задачи':'Сформировать протокол');
        renderItems(m.items||[]);
        loadMeetingsOnly();
    });
}
function loadMeetingsOnly(){
    $.get('{{ route('production-meetings.index') }}', rows=>{meetingRows=rows||[];let h='';meetingRows.forEach(m=>{h+=`<button type="button" class="list-group-item list-group-item-action ${currentMeeting&&currentMeeting.id===m.id?'active':''}" onclick="openMeeting(${m.id})"><div class="d-flex gap-2"><div class="flex-grow-1"><div class="fw-semibold">${esc(m.title)}</div><div class="small ${currentMeeting&&currentMeeting.id===m.id?'text-white-50':'text-muted'}">${fmtDateTime(m.held_at)} · пунктов: ${m.items_count}</div></div><span class="badge text-bg-${statusBadge(m.status)} align-self-start">${statusName(m.status)}</span></div></button>`});$('#meetingList').html(h||'<div class="text-center text-muted py-4">Совещаний пока нет.</div>')});
}
function renderItems(items){
    let h='';
    items.forEach(i=>{const task=i.task?`<a href="{{ route('tasks.page') }}?task=${i.task.id}" class="small text-decoration-none d-block mt-1">Задача #${i.task.id} · ${statusName(i.task.status)}</a>`:'';h+=`<tr><td class="fw-bold">${i.number}</td><td style="min-width:260px">${esc(i.instruction)}${task}</td><td>${esc(i.department?.short_name||i.department?.name||'—')}</td><td>${esc(fullName(i.coexecutor))}</td><td>${fmtDate(i.start_at)}</td><td>${fmtDate(i.due_at)}</td><td>${i.duration_days??'—'}</td><td><span class="badge text-bg-${statusBadge(i.status)}">${statusName(i.status)}</span></td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" onclick="editItem(${i.id})"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger" onclick="deleteItem(${i.id})"><i class="bi bi-trash"></i></button></div></td></tr>`});
    $('#itemsBody').html(h||'<tr><td colspan="9" class="text-center text-muted py-4">Мероприятия ещё не добавлены.</td></tr>');
}

function newMeeting(){
    $('#meetingId').val('');document.getElementById('meetingForm').reset();$('#meetingError').addClass('d-none');meetingModal.show();
}
function editMeeting(){
    if(!currentMeeting)return;$('#meetingId').val(currentMeeting.id);const f=document.getElementById('meetingForm');f.title.value=currentMeeting.title||'';f.protocol_number.value=currentMeeting.protocol_number||'';f.held_at.value=localDateTime(currentMeeting.held_at);f.chairman_id.value=currentMeeting.chairman_id||'';f.secretary_id.value=currentMeeting.secretary_id||'';f.notes.value=currentMeeting.notes||'';$('#meetingError').addClass('d-none');meetingModal.show();
}
$('#meetingForm').on('submit',function(e){e.preventDefault();const id=$('#meetingId').val();$.ajax({url:id?`{{ url('/ajax/production-meetings') }}/${id}`:'{{ route('production-meetings.store') }}',method:id?'PATCH':'POST',data:$(this).serialize()}).done(r=>{meetingModal.hide();currentMeeting=r.meeting;loadMeetings(r.meeting.id)}).fail(x=>$('#meetingError').removeClass('d-none').text(x.responseJSON?.message||'Ошибка сохранения'))});

function newItem(){if(!currentMeeting)return;$('#itemId').val('');document.getElementById('itemForm').reset();$('#itemError').addClass('d-none');itemModal.show()}
function editItem(id){if(!currentMeeting)return;const i=(currentMeeting.items||[]).find(x=>x.id===id);if(!i)return;$('#itemId').val(i.id);const f=document.getElementById('itemForm');f.instruction.value=i.instruction||'';f.responsible_department_id.value=i.responsible_department_id||'';f.coexecutor_id.value=i.coexecutor_id||'';f.start_at.value=i.start_at?String(i.start_at).substring(0,10):'';f.due_at.value=i.due_at?String(i.due_at).substring(0,10):'';f.status.value=i.status||'pending';$('#itemError').addClass('d-none');itemModal.show()}
$('#itemDepartment').on('change',function(){const head=departmentHeads[String(this.value)];if(head)$('#itemCoexecutor').val(String(head))});
$('#itemForm').on('submit',function(e){e.preventDefault();if(!currentMeeting)return;const id=$('#itemId').val();$.ajax({url:id?`{{ url('/ajax/production-meetings') }}/${currentMeeting.id}/items/${id}`:`{{ url('/ajax/production-meetings') }}/${currentMeeting.id}/items`,method:id?'PATCH':'POST',data:$(this).serialize()}).done(()=>{itemModal.hide();openMeeting(currentMeeting.id)}).fail(x=>$('#itemError').removeClass('d-none').text(x.responseJSON?.message||'Ошибка сохранения мероприятия'))});
function deleteItem(id){if(!currentMeeting||!confirm('Удалить мероприятие из протокола? Связанная незавершённая задача будет отменена.'))return;$.ajax({url:`{{ url('/ajax/production-meetings') }}/${currentMeeting.id}/items/${id}`,method:'DELETE'}).done(()=>openMeeting(currentMeeting.id)).fail(x=>alert(x.responseJSON?.message||'Ошибка удаления'))}
function generateProtocol(){if(!currentMeeting)return;if(!confirm(currentMeeting.status==='protocol'?'Обновить протокол и синхронизировать связанные задачи?':'Сформировать протокол и создать задачи по всем мероприятиям?'))return;$.post(`{{ url('/ajax/production-meetings') }}/${currentMeeting.id}/generate-protocol`).done(()=>{openMeeting(currentMeeting.id);alert('Протокол сформирован. Задачи созданы/синхронизированы.')}).fail(x=>alert(x.responseJSON?.message||'Не удалось сформировать протокол'))}

loadMeetings();
</script>
@endpush
