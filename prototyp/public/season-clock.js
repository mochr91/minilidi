'use strict';

(() => {
  const monthNames=['březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec','leden','únor'];
  const shortMonths=['Bře','Dub','Kvě','Čvn','Čvc','Srp','Zář','Říj','Lis','Pro','Led','Úno'];
  const hour = minute => String(Math.floor(Math.floor(minute)%1440/60)).padStart(2,'0')+':'+String(Math.floor(minute)%60).padStart(2,'0');
  function label(minute,origin=0) {
    const day=Math.floor(minute/1440)-origin;
    if(day<0)return 'Den '+(Math.floor(minute/1440)+1)+' · '+hour(minute);
    return (day%30+1)+'. '+monthNames[Math.floor(day/30)%12]+' · '+hour(minute);
  }
  window.MinilidiTime={hour,label};

  const panel=document.querySelector('[data-season-clock]');
  if(!panel)return;
  const canvas=panel.querySelector('canvas');
  const ctx=canvas.getContext('2d');
  const center=86;
  const offset=-Math.PI/2;
  function hand(progress,length,color,width) {
    const angle=offset+progress*2*Math.PI;
    ctx.beginPath();ctx.moveTo(center,center);
    ctx.lineTo(center+Math.cos(angle)*length,center+Math.sin(angle)*length);
    ctx.strokeStyle=color;ctx.lineWidth=width;ctx.lineCap='round';ctx.stroke();
  }
  function draw(calendar) {
    ctx.clearRect(0,0,172,172);
    ctx.fillStyle='#fff7e5';ctx.beginPath();ctx.arc(center,center,82,0,2*Math.PI);ctx.fill();
    for(const [i,color] of ['#8b9e68','#d3ab56','#ac7355','#879ca6'].entries()) {
      ctx.beginPath();ctx.arc(center,center,78,offset+i*Math.PI/2+.025,offset+(i+1)*Math.PI/2-.025);
      ctx.strokeStyle=color;ctx.lineWidth=6;ctx.lineCap='butt';ctx.stroke();
    }
    ctx.font='10px Segoe UI,sans-serif';
    ctx.textAlign='center';ctx.textBaseline='middle';
    for(let i=0;i<12;i++) {
      const angle=offset+(i+.5)*Math.PI/6;
      ctx.fillStyle=i===calendar.monthIndex?'#25291d':'#5e5d50';
      ctx.fillText(shortMonths[i],center+Math.cos(angle)*62,center+Math.sin(angle)*62);
    }
    hand(calendar.yearProgress,74,'#a66b30',3);
    ctx.fillStyle='#315867';ctx.beginPath();ctx.arc(center,center,4,0,2*Math.PI);ctx.fill();
  }
  const groundNames={snow:'sníh na zemi',mud:'blátivé cesty',dry:'suché cesty'};
  const status=document.querySelector('.game-status');
  const writeStatus=(key,text)=>{status.querySelector('[data-'+key+']').textContent=text;};
  const read=async branch=>{
    const response=await fetch('api.php?branch='+branch,{cache:'no-store',signal:AbortSignal.timeout(3500)});
    if(!response.ok)throw Error('Stav není dostupný');
    return response.json();
  };
  function renderClock(ai, control) {
    try {
      const {calendar,weather,minute,running}=ai;
      if(!calendar||!weather)throw Error('Kalendář se spouští');
      draw(calendar);
      panel.querySelector('[data-date]').textContent=calendar.season+' · '+calendar.day+'. '+calendar.month+' · rok '+calendar.year;
      panel.querySelector('[data-hour]').textContent=hour(minute);
      writeStatus('weather-status',weather.condition+' · '+weather.temperature+' °C · vítr '+weather.wind+' km/h · '+groundNames[weather.ground]+(weather.snow?' '+weather.snow+' cm':''));
      writeStatus('simulation',running&&control?.running?'Obě vesnice běží':!running?'Simulace stojí':'Vesnice bez AI je nedostupná');
      const aiStatus=ai.ai.status==='Odpověděla AI'?(ai.ai.model||'odpověděla'):
        ai.ai.status==='Rozhodla hra'?'rozhodla hra'+(ai.ai.detail?' · '+ai.ai.detail:''):ai.ai.status;
      writeStatus('ai','AI: '+aiStatus);
      const count=s=>Object.keys(s.people).length;
      writeStatus('growth-status',control?'Obyvatelé '+count(ai)+' / '+count(control)+' · nové domy '+ai.homesBuilt.length+' / '+control.homesBuilt.length:'');
      const remaining=Math.max(0,Math.ceil(ai.nextAutoMinute-minute));
      writeStatus('next-task',running?(remaining?'Další úkol za '+remaining+' her. min':'Vesnice vybírá další úkol'):'');
    }catch{
      writeStatus('simulation','Spojení se simulací není dostupné');
      for(const field of ['ai','weather-status','growth-status','next-task'])writeStatus(field,'');
    }
  }
  if (document.body.classList.contains('village-comparison')) {
    document.addEventListener('village-status',event=>renderClock(event.detail.ai,event.detail.control));
    return;
  }
  async function updateClock() {
    const [ai,control]=await Promise.all([read('ai').catch(()=>null),read('control').catch(()=>null)]);
    renderClock(ai,control);
    setTimeout(updateClock,2500);
  }
  void updateClock();
})();
