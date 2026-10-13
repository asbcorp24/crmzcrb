(function(){
  function getCanvas(target){
    return typeof target==='string'?document.getElementById(target):target;
  }
  function normalizeDatasets(datasets){
    return (datasets||[]).map(function(ds,i){return {label:ds.label||('Серия '+(i+1)),data:(ds.data||[]).map(Number)};});
  }
  function bar(target, labels, datasets, options){
    const canvas=getCanvas(target); if(!canvas) return;
    const ctx=canvas.getContext('2d'); const dpr=window.devicePixelRatio||1;
    const cssW=canvas.clientWidth||canvas.parentElement?.clientWidth||600;
    const cssH=Number(canvas.getAttribute('height'))||220;
    canvas.width=Math.max(320,cssW)*dpr; canvas.height=cssH*dpr;
    ctx.scale(dpr,dpr);
    const w=canvas.width/dpr,h=canvas.height/dpr;
    const pad={l:48,r:18,t:28,b:54}; const pw=w-pad.l-pad.r, ph=h-pad.t-pad.b;
    ctx.clearRect(0,0,w,h);
    const sets=normalizeDatasets(datasets); const all=sets.flatMap(x=>x.data);
    const max=Math.max(1,...all); const ticks=5;
    ctx.font='12px system-ui'; ctx.textBaseline='middle';
    for(let i=0;i<=ticks;i++){
      const y=pad.t+ph-(ph*i/ticks); const value=Math.round(max*i/ticks);
      ctx.strokeStyle='rgba(127,154,170,.18)';ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(w-pad.r,y);ctx.stroke();
      ctx.fillStyle='#7f9aaa';ctx.textAlign='right';ctx.fillText(String(value),pad.l-8,y);
    }
    const n=Math.max(1,labels.length), groupW=pw/n, setCount=Math.max(1,sets.length), gap=4;
    const palette=['#3dd6d0','#7df58a','#ffd166','#7fa8ff','#ff8fb1'];
    labels.forEach(function(label,i){
      const baseX=pad.l+i*groupW; const bw=Math.max(5,(groupW-12)/setCount-gap);
      sets.forEach(function(ds,j){
        const v=Number(ds.data[i]||0); const bh=ph*(v/max);
        const x=baseX+6+j*(bw+gap), y=pad.t+ph-bh;
        ctx.fillStyle=palette[j%palette.length];ctx.fillRect(x,y,bw,bh);
      });
      ctx.fillStyle='#9fb8c5';ctx.textAlign='center';ctx.textBaseline='top';
      const text=String(label); const short=text.length>18?text.slice(0,17)+'…':text;
      ctx.fillText(short,baseX+groupW/2,pad.t+ph+10);
    });
    if(sets.length>1){
      let x=pad.l;ctx.textAlign='left';ctx.textBaseline='middle';
      sets.forEach(function(ds,j){ctx.fillStyle=palette[j%palette.length];ctx.fillRect(x,pad.t-18,10,10);ctx.fillStyle='#9fb8c5';ctx.fillText(ds.label,x+15,pad.t-13);x+=ctx.measureText(ds.label).width+42;});
    }
  }
  function auto(){
    document.querySelectorAll('canvas[data-tech-chart="bar"]').forEach(function(c){
      let labels=[],datasets=[];
      try{labels=JSON.parse(c.dataset.labels||'[]');datasets=JSON.parse(c.dataset.datasets||'[]');}catch(e){}
      bar(c,labels,datasets,{});
    });
  }
  window.TechCharts={bar,auto};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',auto);else auto();
  window.addEventListener('resize',function(){clearTimeout(window.__techChartResize);window.__techChartResize=setTimeout(auto,150);});
})();
