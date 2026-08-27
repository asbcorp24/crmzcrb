@extends('layouts.app')
@section('title','Push и офлайн — CRM ЗЦРБ')
@section('header','Push и офлайн')
@section('content')
<div class="row g-4">
  <div class="col-xl-7">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
      <div class="d-flex align-items-start gap-3"><div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:54px;height:54px"><i class="bi bi-bell-fill fs-4"></i></div><div class="flex-grow-1"><h4 class="mb-1">Push-уведомления</h4><p class="text-muted mb-3">Получайте уведомления CRM на телефон или компьютер, даже когда вкладка CRM закрыта.</p><div id="pushStatus" class="alert alert-light border mb-3">Проверяем поддержку...</div><div class="d-flex flex-wrap gap-2"><button id="enablePush" class="btn btn-primary" type="button" disabled><i class="bi bi-bell me-1"></i>Включить уведомления</button><button id="disablePush" class="btn btn-outline-danger d-none" type="button"><i class="bi bi-bell-slash me-1"></i>Отключить на этом устройстве</button><button id="testPush" class="btn btn-outline-primary d-none" type="button"><i class="bi bi-send me-1"></i>Тестовое уведомление</button></div></div></div>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-body p-4"><div class="d-flex align-items-start gap-3"><div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width:54px;height:54px"><i class="bi bi-wifi-off fs-4"></i></div><div><h4 class="mb-1">Безопасный офлайн-режим</h4><p class="text-muted">CRM не сохраняет в браузере списки сотрудников, задачи, отчёты или другие рабочие данные. Если связь пропадёт, вместо ошибки откроется безопасная офлайн-страница.</p><ul class="mb-0"><li>доступна общая справка по работе с CRM;</li><li>доступна оболочка установленного PWA;</li><li>видно состояние подключения и кнопка повторной попытки;</li><li>после восстановления сети рабочая страница открывается заново с сервера.</li></ul></div></div></div></div>
  </div>
  <div class="col-xl-5">
    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><b>Что будет приходить</b></div><div class="list-group list-group-flush">
      <div class="list-group-item"><i class="bi bi-check2-square me-2 text-primary"></i>Новая задача или поручение</div>
      <div class="list-group-item"><i class="bi bi-chat-left-text me-2 text-primary"></i>Комментарий и возврат на доработку</div>
      <div class="list-group-item"><i class="bi bi-alarm me-2 text-warning"></i>Приближение срока</div>
      <div class="list-group-item"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Просрочка и задача без движения</div>
      <div class="list-group-item"><i class="bi bi-calendar-x me-2"></i>Скорое отсутствие или окончание замещения</div>
      <div class="list-group-item"><i class="bi bi-calendar-check me-2"></i>Завершение плана</div>
    </div></div>
  </div>
</div>
@endsection
@push('scripts')
<script>
let pushReg=null,pushSub=null,pushPublicKey=null;
function b64ToUint8Array(base64){const padding='='.repeat((4-base64.length%4)%4),safe=(base64+padding).replace(/-/g,'+').replace(/_/g,'/'),raw=atob(safe);return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)))}
function setPushState(text,type='light'){$('#pushStatus').attr('class',`alert alert-${type} border mb-3`).text(text)}
async function refreshPushState(){
  if(!window.isSecureContext){setPushState('Web Push требует HTTPS. Откройте CRM по защищённому адресу https://...','danger');return}
  if(!('serviceWorker' in navigator)||!('PushManager' in window)||!('Notification' in window)){setPushState('Этот браузер не поддерживает Web Push. Используйте актуальный Chrome/Edge/Android.','warning');return}
  try{
    const status=await $.get('{{ route('push.status') }}');
    if(!status.configured){setPushState('Сервер ещё не настроен для Web Push. Администратору нужно создать VAPID-ключи.','warning');return}
    pushPublicKey=status.public_key;
    pushReg=await navigator.serviceWorker.ready;
    pushSub=await pushReg.pushManager.getSubscription();
    if(Notification.permission==='denied'){setPushState('Уведомления запрещены в настройках браузера. Разрешите уведомления для этого сайта в Chrome.','danger');return}
    if(pushSub){setPushState('Push-уведомления включены на этом устройстве.','success');$('#enablePush').addClass('d-none');$('#disablePush,#testPush').removeClass('d-none');}
    else{setPushState(Notification.permission==='granted'?'Разрешение есть, но это устройство ещё не подписано.':'Нажмите «Включить уведомления» и подтвердите разрешение Chrome.','info');$('#enablePush').prop('disabled',false).removeClass('d-none');$('#disablePush,#testPush').addClass('d-none');}
  }catch(e){setPushState('Не удалось проверить push-настройки: '+e,'danger')}
}
$('#enablePush').on('click',async function(){
  try{
    const permission=await Notification.requestPermission(); if(permission!=='granted'){setPushState('Chrome не выдал разрешение на уведомления.','warning');return}
    pushReg=await navigator.serviceWorker.ready;
    pushSub=await pushReg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:b64ToUint8Array(pushPublicKey)});
    const json=pushSub.toJSON();
    await $.post('{{ route('push.subscribe') }}',{endpoint:pushSub.endpoint,keys:json.keys,contentEncoding:(PushManager.supportedContentEncodings||['aes128gcm'])[0]});
    await refreshPushState();
  }catch(e){setPushState('Не удалось включить push: '+e,'danger')}
});
$('#disablePush').on('click',async function(){if(!pushSub)return;try{await $.ajax({url:'{{ route('push.unsubscribe') }}',method:'DELETE',data:{endpoint:pushSub.endpoint}});await pushSub.unsubscribe();pushSub=null;await refreshPushState()}catch(e){setPushState('Не удалось отключить push: '+e,'danger')}});
$('#testPush').on('click',async function(){try{await $.post('{{ route('push.test') }}');setPushState('Тестовое уведомление отправлено. Оно должно появиться через несколько секунд.','success')}catch(e){setPushState('Не удалось отправить тестовое уведомление.','danger')}});
refreshPushState();
</script>
@endpush
