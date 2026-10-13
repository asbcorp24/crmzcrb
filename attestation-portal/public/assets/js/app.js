(function(){
  function updateTimers(){
    document.querySelectorAll('[data-countdown-deadline]').forEach(function(el){
      const deadline=new Date(el.dataset.countdownDeadline).getTime();
      if(!deadline)return;
      const s=Math.max(0,Math.floor((deadline-Date.now())/1000));
      el.textContent='Осталось: '+Math.floor(s/60)+':'+String(s%60).padStart(2,'0');
    });
  }
  function init(){
    updateTimers();
    setInterval(updateTimers,1000);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
