@extends('layouts.app')
@section('title','Справочники — CRM')
@section('header','Справочники')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-primary directory-tab active" data-type="project"><i class="bi bi-kanban me-1"></i>Проекты</button>
    <button class="btn btn-outline-primary directory-tab" data-type="organization"><i class="bi bi-buildings me-1"></i>Организации</button>
    <button class="btn btn-outline-primary directory-tab" data-type="task_status"><i class="bi bi-list-check me-1"></i>Статусы задач</button>
    <button class="btn btn-outline-primary directory-tab" data-type="basis"><i class="bi bi-file-earmark-text me-1"></i>Основания</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <div>
            <b id="directoryTitle">Проекты</b>
            <div class="small text-muted" id="directoryHint">Справочник проектов организации</div>
        </div>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            <input id="directorySearch" class="form-control" style="min-width:240px" placeholder="Поиск по коду и названию">
            <select id="directoryActive" class="form-select" style="width:170px"><option value="">Все записи</option><option value="1">Только активные</option><option value="0">Только отключённые</option></select>
            <button class="btn btn-primary" id="addDirectoryItem"><i class="bi bi-plus-lg me-1"></i>Добавить</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th style="width:70px">#</th><th style="width:130px">Код</th><th>Наименование</th><th id="statusKeyHead" class="d-none">Системный статус</th><th id="colorHead" class="d-none" style="width:110px">Цвет</th><th>Примечание</th><th style="width:110px">Порядок</th><th style="width:110px">Активен</th><th style="width:90px"></th></tr></thead>
            <tbody id="directoryRows"><tr><td colspan="9" class="text-center text-muted py-4">Загрузка...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="directoryModal" tabindex="-1"><div class="modal-dialog modal-lg"><form id="directoryForm" class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="directoryModalTitle">Новая запись</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="directoryId">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Код</label><input id="directoryCode" class="form-control" maxlength="80" placeholder="Например: PRJ-001"></div>
            <div class="col-md-8"><label class="form-label fw-semibold">Наименование</label><input id="directoryName" class="form-control" required maxlength="255"></div>
            <div class="col-md-6 status-only d-none"><label class="form-label">Системный статус</label><select id="directorySystemKey" class="form-select"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="review">На проверке</option><option value="completed">Выполнена</option><option value="cancelled">Отменена</option></select></div>
            <div class="col-md-3 status-only d-none"><label class="form-label">Цвет</label><input id="directoryColor" type="color" class="form-control form-control-color w-100" value="#0d6efd"></div>
            <div class="col-md-3"><label class="form-label">Порядок</label><input id="directorySort" type="number" min="0" class="form-control" value="0"></div>
            <div class="col-12"><label class="form-label">Описание / примечание</label><textarea id="directoryNotes" class="form-control" rows="4"></textarea></div>
            <div class="col-12"><div class="form-check"><input id="directoryEnabled" type="checkbox" class="form-check-input" checked><label class="form-check-label" for="directoryEnabled">Активная запись</label></div></div>
        </div>
        <div id="directoryError" class="alert alert-danger d-none mt-3 mb-0"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Сохранить</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
let directoryType='project';
let directoryItems=[];
const directoryMeta={
    project:{title:'Проекты',hint:'Справочник проектов организации'},
    organization:{title:'Организации',hint:'Рабочие организации и контрагенты. Не влияет на системные организации CRM.'},
    task_status:{title:'Статусы задач',hint:'Названия и цвета существующих системных статусов задач'},
    basis:{title:'Основания',hint:'Документы, распоряжения, договоры и другие основания для поручений'}
};
function dEsc(v){return $('<div>').text(v??'').html()}
function loadDirectory(){
    $('#directoryRows').html('<tr><td colspan="9" class="text-center text-muted py-4">Загрузка...</td></tr>');
    $.get('{{ route('directories.index') }}',{type:directoryType,q:$('#directorySearch').val(),active:$('#directoryActive').val()},r=>{
        directoryItems=r||[]; renderDirectory();
    }).fail(x=>$('#directoryRows').html(`<tr><td colspan="9" class="text-danger text-center py-4">${dEsc(x.responseJSON?.message||'Ошибка загрузки')}</td></tr>`));
}
function renderDirectory(){
    const status=directoryType==='task_status';
    $('#statusKeyHead,#colorHead').toggleClass('d-none',!status);
    let h='';
    directoryItems.forEach((x,i)=>{
        h+=`<tr class="${x.is_active?'':'opacity-50'}"><td>${i+1}</td><td>${dEsc(x.code||'—')}</td><td><b>${dEsc(x.name)}</b></td>${status?`<td><code>${dEsc(x.system_key||'')}</code></td><td><span class="d-inline-block rounded-circle border" style="width:24px;height:24px;background:${dEsc(x.color||'#6c757d')}"></span></td>`:''}<td>${dEsc(x.notes||'')}</td><td>${x.sort_order||0}</td><td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" ${x.is_active?'checked':''} onchange="toggleDirectory(${x.id},this.checked)"></div></td><td><button class="btn btn-sm btn-outline-primary" onclick="editDirectory(${x.id})"><i class="bi bi-pencil"></i></button></td></tr>`;
    });
    if(!h) h='<tr><td colspan="9" class="text-center text-muted py-4">Записей пока нет</td></tr>';
    $('#directoryRows').html(h);
}
function switchDirectory(type){
    directoryType=type;
    $('.directory-tab').removeClass('btn-primary active').addClass('btn-outline-primary');
    $(`.directory-tab[data-type="${type}"]`).removeClass('btn-outline-primary').addClass('btn-primary active');
    $('#directoryTitle').text(directoryMeta[type].title); $('#directoryHint').text(directoryMeta[type].hint);
    loadDirectory();
}
function openDirectory(item=null){
    $('#directoryError').addClass('d-none').text('');
    $('#directoryId').val(item?.id||''); $('#directoryCode').val(item?.code||''); $('#directoryName').val(item?.name||'');
    $('#directoryNotes').val(item?.notes||''); $('#directorySort').val(item?.sort_order||0); $('#directoryEnabled').prop('checked',item?!!item.is_active:true);
    $('#directorySystemKey').val(item?.system_key||'new'); $('#directoryColor').val(item?.color||'#0d6efd');
    $('.status-only').toggleClass('d-none',directoryType!=='task_status');
    $('#directoryModalTitle').text((item?'Редактирование: ':'Новая запись: ')+directoryMeta[directoryType].title);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('directoryModal')).show();
}
function editDirectory(id){const item=directoryItems.find(x=>Number(x.id)===Number(id));if(item)openDirectory(item)}
function toggleDirectory(id,value){$.ajax({url:`{{ url('/ajax/directories') }}/${id}/toggle`,method:'PATCH',data:{is_active:value?1:0}}).done(loadDirectory).fail(x=>{alert(x.responseJSON?.message||'Ошибка');loadDirectory()})}
$('.directory-tab').on('click',function(){switchDirectory($(this).data('type'))});
$('#directorySearch').on('input',function(){clearTimeout(window.directoryTimer);window.directoryTimer=setTimeout(loadDirectory,250)});
$('#directoryActive').on('change',loadDirectory); $('#addDirectoryItem').on('click',()=>openDirectory());
$('#directoryForm').on('submit',function(e){
    e.preventDefault(); $('#directoryError').addClass('d-none');
    const id=$('#directoryId').val(); const data={type:directoryType,code:$('#directoryCode').val(),name:$('#directoryName').val(),notes:$('#directoryNotes').val(),sort_order:$('#directorySort').val(),is_active:$('#directoryEnabled').is(':checked')?1:0};
    if(directoryType==='task_status'){data.system_key=$('#directorySystemKey').val();data.color=$('#directoryColor').val()}
    $.ajax({url:id?`{{ url('/ajax/directories') }}/${id}`:'{{ route('directories.store') }}',method:id?'PATCH':'POST',data}).done(()=>{bootstrap.Modal.getInstance(document.getElementById('directoryModal')).hide();loadDirectory()}).fail(x=>$('#directoryError').removeClass('d-none').text(x.responseJSON?.message||Object.values(x.responseJSON?.errors||{}).flat()[0]||'Ошибка сохранения'));
});
loadDirectory();
</script>
@endpush
