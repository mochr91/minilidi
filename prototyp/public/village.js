'use strict';

(() => {
  const branchNames = {ai:'Vesnice s AI',control:'Vesnice bez AI'};
  const branchInUrl = new URLSearchParams(location.search).get('branch') === 'control' ? 'control' : 'ai';
  const resources = {food:'Jídlo',wood:'Dřevo',stone:'Kámen'};
  const sources = ['AI','Hra','Hráč','Příběh','Víra'];
  const metricNames = {food:'Jídlo',energy:'Energie',fun:'Zábava',mood:'Nálada',productivity:'Výkon',willingness:'Ochota'};
  const beliefs = {faith:'Víra',trust:'Důvěra',fear:'Strach'};
  const icons = {
    food:'<path d="M4 2v5m3-5v5m3-5v5c0 2-1.5 3-3 3S4 9 4 7m3 3v8m8 0V2c-2 2-3 5-3 8h3"/>',
    wood:'<path d="M3 13 12 4l5 3v5l-9 6-5-2Zm0 0 5 2 9-8M8 15v3"/>',
    stone:'<path d="m2 15 3-8 6-4 6 4 1 8-7 3Zm3-8 6 2 6-2m-6 2v9"/>',
    people:'<circle cx="8" cy="6" r="3"/><path d="M2 17v-2a6 6 0 0 1 12 0v2Zm12-14a3 3 0 0 1 0 6m2 2c2 1 2 3 2 6"/>',
    plan:'<circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/>',
    task:'<rect x="4" y="4" width="12" height="14" rx="2"/><path d="M7 4V2h6v2m-6 7 2 2 4-4"/>',
    reward:'<path d="M10 17 3.7 10.6a4 4 0 0 1 5.7-5.7l.6.6.6-.6a4 4 0 0 1 5.7 5.7Z"/>',
    reprimand:'<path d="M11 2 4 11h5l-1 7 8-10h-5Z"/>',
    sleep:'<path d="M4 3a8 8 0 1 0 13 10A8 8 0 0 1 4 3Z"/>',
    idle:'<circle cx="10" cy="10" r="3"/>',
    reset:'<path d="M7 3H3v4m10-4h4v4M3 13v4h4m10-4v4h-4"/>',
  };
  const icon = name => '<svg width="15" height="15" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+icons[name]+'</svg>';
  const number = new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:1});
  const decimalStock = new Intl.NumberFormat('cs-CZ',{minimumFractionDigits:1,maximumFractionDigits:1});
  const fmt = value => Number.isFinite(value) ? number.format(value) : '–';
  const level = value => value<25 ? 'low' : value<50 ? 'mid' : 'good';
  const el = (tag,text='',className='') => {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  };

  const branches = {ai:'AI',control:'Bez AI'};
  const sides = Object.keys(branches);
  const metrics = {...metricNames,...beliefs};
  const fieldsIn = root => Object.fromEntries([...root.querySelectorAll('[data-field]')].map(node=>[node.dataset.field,node]));
  const button = (field,label,content,extra='') => '<button data-field="'+field+'" type="button" aria-label="'+label+'" title="'+label+'" '+extra+'>'+content+'</button>';
  const write = (node,text,title=text) => { node.textContent=text; node.title=title; };
  const states = {};
  const previous = {};
  let selected = '';
  let sending = false;
  let inFlight = null;

  function mapMarkup() {
    return [
      '<div class="map-frame village-map" data-field="map-frame"><div class="map-corner map-top">',
      '<div class="supplies" aria-label="Zásoby a denní spotřeba">'+Object.keys(resources).map(key=>
        '<span class="map-chip" role="group" data-field="'+key+'-chip">'+icon(key)+'<b data-field="'+key+'">–</b><small data-field="'+key+'-use">–/den</small></span>').join('')+'</div>',
      '<div class="map-tools" aria-label="Ovládání mapy">',
      button('map-zoom-out','Oddálit mapu','−','disabled'),'<output data-field="map-zoom-level">100 %</output>',
      button('map-zoom-in','Přiblížit mapu','+'),button('map-reset','Vrátit přehled mapy',icon('reset'),'disabled'),'</div></div>',
      '<canvas data-field="map" width="1294" height="663" tabindex="0" aria-label="Mapa vesnice. Přibliž kolečkem nebo klávesami plus a minus. Přiblíženou mapu posouvají šipky nebo tažení."></canvas>',
      '<div class="map-corner map-bottom"><span class="map-chip">'+icon('people')+'<span data-field="growth">– lidí · – domů</span></span>',
      '<span class="map-chip">'+icon('plan')+'<span data-field="plan">Čeká na stav</span></span></div></div>',
    ].join('');
  }

  function createCard(root,branch) {
    root.dataset.village=branch;
    root.setAttribute('aria-labelledby',branch+'-heading');
    root.innerHTML=[
      '<header class="village-heading"><h2 id="'+branch+'-heading">'+branchNames[branch]+'</h2><span data-field="connection" role="status">Připojuji se…</span></header>',
      mapMarkup(),
      '<div data-field="people" class="village-roster" role="group" aria-label="Obyvatelé · '+branches[branch]+'"></div>',
      '<section class="person-card" aria-labelledby="'+branch+'-person">',
      '<div class="person-profile"><h3 id="'+branch+'-person" data-field="person-name">Vyber postavu</h3>',
      '<p data-field="family">–</p><p class="person-activity" data-field="activity">–</p><p class="person-trait" data-field="trait">–</p>',
      '<div class="person-task"><span data-field="task">Bez úkolu, řídí se denním rytmem</span><progress data-field="progress" max="100" value="0" aria-label="Postup úkolu"></progress><span data-field="percent">–</span></div></div>',
      '<div class="person-stats" data-field="stats" role="group" aria-label="Potřeby, víra, důvěra a strach"></div>',
      '<div class="person-notes"><section><h4>Nejsilnější vztahy</h4><ul data-field="relationships"></ul></section>',
      '<section><h4>Poslední vzpomínky</h4><ul data-field="memory"></ul></section></div></section>',
      '<section class="village-journal" aria-labelledby="'+branch+'-journal">',
      '<header class="journal-heading"><h3 id="'+branch+'-journal">Kronika</h3>',
      '<button type="button" data-field="person-only" aria-pressed="false">Jen vybraná postava</button>',
      '<div class="journal-legend" aria-label="Zdroje záznamů">'+sources.map(source=>'<span><i data-source="'+source+'" aria-hidden="true"></i>'+source+'</span>').join('')+'</div></header>',
      '<ol data-field="journal" tabindex="0" aria-label="Záznamy kroniky · '+branches[branch]+'"></ol></section>',
    ].join('');
    const fields=fieldsIn(root);
    const stats=Object.fromEntries(Object.entries(metrics).map(([key,label])=>{
      const row=el('div','',key==='faith'?'belief-start':'');
      const bar=el('progress');
      bar.max=100;
      bar.value=0;
      bar.setAttribute('aria-label',label);
      const value=el('span','–');
      row.append(el('span',label),bar,value);
      fields.stats.append(row);
      return [key,{bar,value}];
    }));
    const card={branch,fields,stats,setMap:createVillageMap(root),roster:new Map(),journalKey:'',journalScope:'',knownEntries:new Set()};
    fields.map.addEventListener('villager',event=>choose(event.detail));
    fields['person-only'].addEventListener('click',event=>{
      const filter=event.currentTarget;
      filter.setAttribute('aria-pressed',String(filter.getAttribute('aria-pressed')!=='true'));
      drawJournal(card);
    });
    return card;
  }

  const main=document.querySelector('main');
  const maps=[...main.querySelectorAll('[data-village]')].map(root=>createCard(root,root.dataset.village||branchInUrl));
  if (!maps.length) return;
  main.insertAdjacentHTML('afterbegin',[
    '<section class="village-toolbar" aria-label="Ovládání vybrané postavy">',
    '<strong data-field="selected-name">Vyber postavu</strong>',
    '<form class="person-controls" data-field="inspire"><select data-field="action" aria-label="Vyber vnuknutí" disabled><option>Vnuknutí</option></select><button data-field="send" class="primary" type="submit" disabled>Vnuknout</button>',
    button('reward','Odměnit',icon('reward')+' Odměnit','data-judgment="reward" disabled'),
    button('reprimand','Pokárat',icon('reprimand')+' Pokárat','data-judgment="reprimand" disabled'),'</form>',
    '<p data-field="result" class="person-result" role="status"></p></section>',
  ].join(''));
  const fields=fieldsIn(main.querySelector('.village-toolbar'));
  const select=fields.action;
  const judgments=[fields.reward,fields.reprimand];

  function statePeople() {
    return Object.assign({},...maps.map(({branch})=>states[branch]?.people??{}));
  }

  function time(state,minute) {
    return MinilidiTime.label(minute,state.calendar?.originDay??0);
  }

  function choose(id,sync=true) {
    if (!statePeople()[id]) return;
    if (selected!==id) write(fields.result,'');
    selected=id;
    render();
    for (const {roster,fields} of maps) {
      const button=roster.get(id)?.button;
      if (!button) continue;
      const row=button.getBoundingClientRect(), list=fields.people.getBoundingClientRect();
      if (row.top<list.top) fields.people.scrollTop+=row.top-list.top;
      else if (row.bottom>list.bottom) fields.people.scrollTop+=row.bottom-list.bottom;
    }
    if (sync && window.parent!==window) {
      window.parent.postMessage({kind:'select-villager',person:id},location.origin);
    }
  }

  window.addEventListener('message',event=>{
    if(event.origin===location.origin && event.source===window.parent && event.data?.kind==='select-villager') {
      choose(event.data.person,false);
    }
  });

  function renderMap({branch,fields:map,setMap},state) {
    write(map.connection,state.calendar.season+' · '+time(state,state.minute)+' · '+state.weather.condition+' '+state.weather.temperature+' °C'
      +(state.running?'':' · pozastaveno'));
    const frame=map['map-frame'];
    frame.dataset.ground=state.weather.ground;
    frame.dataset.night=String(state.calendar.hour<state.calendar.sunrise||state.calendar.hour>=state.calendar.sunset);
    for (const [key,label] of Object.entries(resources)) {
      const amount=state[key], need=state.dailyNeed[key], used=state.usedToday?.[key];
      const old=previous[branch];
      const trend=old?Math.round((amount-old[key])*10)/10:null;
      map[key].textContent=amount>=100?fmt(Math.round(amount)):Number.isFinite(amount)?decimalStock.format(amount):'–';
      map[key+'-use'].textContent=fmt(need)+'/den';
      const chip=map[key+'-chip'];
      chip.title=label+' '+fmt(amount)+' · potřeba '+fmt(need)+'/den · dnes spotřebováno '+fmt(used)
        +(trend===null?'':' · změna '+(trend>0?'+':'')+fmt(trend));
      chip.setAttribute('aria-label',chip.title);
    }
    const growth=Object.keys(state.people).length+' lidí · '+state.homesBuilt.length+' domů';
    write(map.growth,growth,growth+' · u lomu '+fmt(state.quarryStone)+' kamene');
    const remaining=Math.max(0,Math.ceil(state.nextAutoMinute-state.minute));
    const model=state.ai?.model??'';
    const timing=remaining?'plán za '+remaining+' min':'plán se připravuje';
    const mode=state.ai?.status==='Odpověděla AI'&&model.length<=16?'AI '+model:'AI';
    const summary=branch==='control'?'Pravidla':mode+' · '+timing;
    write(map.plan,summary,branch==='control'?summary:summary+' · '+[state.ai?.status,model,state.ai?.detail].filter(Boolean).join(' · '));
    setMap(state.people,selected,state.minute,state.homesBuilt.length,state.weather,state.calendar);
  }

  function renderRoster(card,people) {
    const {fields,roster}=card;
    for (const [id,item] of roster) if (!people[id]) {
      item.button.remove();
      roster.delete(id);
    }
    for (const [id,person] of Object.entries(people)) {
      if (!roster.has(id)) {
        const avatar=el('button','','village-avatar');
        const swatch=el('span','','avatar-swatch');
        const caption=el('span','','avatar-caption');
        const name=el('span','','avatar-name');
        const activityText=el('span','','avatar-current');
        const activity=el('span','','avatar-activity');
        swatch.setAttribute('aria-hidden','true');
        activity.setAttribute('aria-hidden','true');
        avatar.type='button';
        avatar.dataset.person=id;
        caption.append(name,activityText);
        avatar.append(swatch,caption,activity);
        avatar.addEventListener('click',()=>choose(id));
        fields.people.append(avatar);
        roster.set(id,{button:avatar,name,activityText,activity,activityIcon:''});
      }
      const item=roster.get(id);
      item.button.style.setProperty('--avatar',person.color);
      item.button.dataset.mood=level(person.mood);
      write(item.name,person.name);
      const current=person.activity||'Bez činnosti';
      write(item.activityText,current);
      const activity=person.activity==='Spí'?'sleep':person.task?'task':'idle';
      if (activity!==item.activityIcon) {
        item.activity.innerHTML=icon(activity);
        item.activityIcon=activity;
      }
      item.button.title=person.name+' · '+current+' · nálada '+fmt(Math.round(person.mood));
      item.button.setAttribute('aria-label',item.button.title);
      item.button.setAttribute('aria-pressed',String(selected===id));
    }
  }

  function noteList(node,texts,empty) {
    node.replaceChildren(...(texts.length?texts:[empty]).map(text=>{
      const item=el('li');
      write(item,text);
      return item;
    }));
  }

  function renderPerson({fields,stats},state) {
    const person=state?.people[selected];
    write(fields['person-name'],person?.name??statePeople()[selected]?.name??'Vyber postavu');
    write(fields.family,person?person.age+' let · '+person.family:'–');
    write(fields.activity,person?person.role+' · '+person.activity:'Postava není v této vesnici dostupná');
    write(fields.trait,person?.trait??'–');
    const task=person?.task;
    const pending=state?.pending.some(job=>job.kind==='inspiration'&&job.person===selected);
    const description=pending?'Zvažuje vnuknutí…':task
      ? (state.actions[task.action]?.label??task.action)+(state.minute<task.start?' · začne '+time(state,task.start):'')
      : 'Bez úkolu, řídí se denním rytmem';
    write(fields.task,description);
    const done=task?Math.max(0,Math.min(100,Math.round(100*(state.minute-task.start)/Math.max(1,task.end-task.start)))):0;
    fields.progress.value=done;
    fields.percent.textContent=task?done+' %':'–';
    for (const [key,{bar,value}] of Object.entries(stats)) {
      const amount=Number.isFinite(person?.[key])?Math.round(person[key]):null;
      bar.value=amount??0;
      bar.dataset.color=key in beliefs?key:level(amount??0);
      value.textContent=amount??'–';
    }
    const relationships=Object.entries(person?.bonds??{}).filter(([id])=>state.people[id]).sort((a,b)=>b[1]-a[1]);
    noteList(fields.relationships,relationships.slice(0,3).map(([id,value])=>{
      const other=state.people[id];
      const note=person.crush===id?' · zamilovanost':(person.grudges?.[id]??0)>state.minute?' · zášť':'';
      return other.name+' · '+fmt(value)+note;
    }),'Zatím bez známých vztahů.');
    const saved=person&&(person.memories??state.memories?.[selected]);
    const memories=Array.isArray(saved)&&saved.length
      ? saved.slice(0,3).map(item=>typeof item==='string'?item:item.text)
      : person?state.journal.filter(event=>event.person===selected).slice(0,3).map(event=>event.title+(event.detail?' — '+event.detail:'')):[];
    noteList(fields.memory,memories,'Postava zatím nemá záznamy.');
  }

  function reason(side,kind,value) {
    const state=states[side], person=state?.people[selected];
    if (!state) return 'Simulace není dostupná.';
    if (!person) return 'Postava zde nežije.';
    if (state.pending.some(job=>job.kind===kind&&job.person===selected)) return 'Předchozí příkaz čeká.';
    if (kind==='inspiration') {
      if (person.task&&!person.task.automatic) return 'Postava už plní vnuknutí.';
      if (!person.availableActions.includes(value)) return 'Podmínky úkolu nejsou splněné.';
    }
    return '';
  }

  function reasonText(kind,value) {
    return sides.map(side=>branches[side]+': '+(reason(side,kind,value)||(states[side]?.running?'dostupné':'zařadí se do fronty'))).join(' · ');
  }

  function renderControls() {
    const actions=Object.assign({},...sides.map(side=>states[side]?.actions??{}));
    const keys=Object.keys(actions).join(',');
    if(select.dataset.keys!==keys) {
      const groups=new Map();
      for(const [key,action] of Object.entries(actions)) {
        const label=action.group??(action.resource?'Práce pro vesnici':'Ostatní vnuknutí');
        if(!groups.has(label)) {
          const group=el('optgroup');
          group.label=label;
          groups.set(label,group);
        }
        groups.get(label).append(new Option(action.label,key));
      }
      select.replaceChildren(...groups.values());
      select.dataset.keys=keys;
    }
    for (const option of select.options) {
      option.disabled=sides.every(side=>reason(side,'inspiration',option.value));
      option.title=reasonText('inspiration',option.value);
    }
    if (!select.value || select.selectedOptions[0]?.disabled) {
      select.value=[...select.options].find(option=>!option.disabled)?.value??'';
    }
    select.disabled=sending||!select.value;
    select.title=select.value?reasonText('inspiration',select.value):'Žádné dostupné vnuknutí.';
    fields.send.disabled=select.disabled;
    fields.send.title=select.title;
    for(const control of judgments) {
      control.disabled=sending||sides.every(side=>reason(side,'judgment',control.dataset.judgment));
      control.title=control.getAttribute('aria-label')+' · '+reasonText('judgment',control.dataset.judgment);
    }
  }

  function drawJournal(card) {
    const {branch,fields}=card;
    const state=states[branch];
    const all=state?.journal??[];
    const scope=fields['person-only'].getAttribute('aria-pressed')==='true'?selected:'';
    const entries=scope?all.filter(entry=>entry.person===scope):all;
    const key=scope+':'+entries.map(entry=>entry.id).join(',');
    if(key===card.journalKey)return;
    const journal=fields.journal, scroll=journal.scrollTop;
    const top=journal.getBoundingClientRect().top;
    const anchor=scope===card.journalScope&&scroll>2?[...journal.children].find(row=>row.getBoundingClientRect().bottom>top):null;
    const anchorOffset=anchor?.getBoundingClientRect().top;
    const fragment=document.createDocumentFragment();
    for (const entry of entries) {
      const row=el('li');
      row.dataset.entry=entry.id;
      if (card.journalKey&&!card.knownEntries.has(entry.id)) {
        row.classList.add('journal-new');
        setTimeout(()=>row.classList.remove('journal-new'),1500);
      }
      const clock=el('time',MinilidiTime.hour(entry.minute));
      clock.title=time(state,entry.minute);
      const dot=el('i');
      dot.dataset.source=entry.source;
      dot.title=entry.source;
      dot.setAttribute('aria-label','Zdroj: '+entry.source);
      dot.setAttribute('role','img');
      const body=el('p');
      body.append(el('strong',entry.title),document.createTextNode(entry.detail?' '+entry.detail:''));
      body.title=body.textContent;
      row.append(clock,dot,body);
      fragment.append(row);
    }
    journal.replaceChildren(entries.length?fragment:el('li','Zatím tu není žádná událost.','journal-empty'));
    journal.scrollTop=0;
    if (anchor) {
      const match=[...journal.children].find(row=>row.dataset.entry===anchor.dataset.entry);
      journal.scrollTop=match ? match.getBoundingClientRect().top-anchorOffset : scroll;
    }
    card.knownEntries=new Set(all.map(entry=>entry.id));
    card.journalKey=key;
    card.journalScope=scope;
  }

  function render() {
    const all=statePeople();
    if (!all[selected]) selected=Object.keys(all)[0]??'';
    write(fields['selected-name'],all[selected]?.name??'Vyber postavu');
    for (const card of maps) {
      const state=states[card.branch];
      if (state) renderMap(card,state);
      else write(card.fields.connection,'Vesnice je nedostupná');
      renderRoster(card,state?.people??{});
      renderPerson(card,state);
      drawJournal(card);
    }
    renderControls();
  }

  function refresh() {
    if (inFlight) return inFlight;
    inFlight=Promise.all(maps.map(async({branch})=>{
      try {
        const response=await fetch('api.php?branch='+branch,{cache:'no-store',signal:AbortSignal.timeout(3500)});
        return response.ok?await response.json():null;
      } catch { return null; }
    })).then(results=>{
      maps.forEach(({branch},i)=>{
        previous[branch]=states[branch];
        states[branch]=results[i];
      });
      render();
      document.dispatchEvent(new CustomEvent('village-status',{detail:states}));
    }).finally(()=>{inFlight=null;});
    return inFlight;
  }

  async function send(kind,value,control) {
    if (control.disabled||sending) return;
    const person=selected;
    sending=true;
    write(fields.result,'Odesílám…');
    renderControls();
    try {
      const reply=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({kind,value,person}),signal:AbortSignal.timeout(5000)});
      const body=await reply.json();
      if(!reply.ok) throw Error(body.error);
      if(person===selected) {
        const lines=sides.map(side=>branches[side]+': '+(body.branches?.[side]??(states[side]?.running?'přijato':'zařazeno do fronty')));
        const cells=lines.map(text=>{
          const cell=el('span',text,'branch-result');
          cell.title=text;
          return cell;
        });
        fields.result.title=lines.join(' · ');
        fields.result.replaceChildren(cells[0],el('span',' · '),cells[1]);
      }
      if(inFlight) await inFlight;
      await refresh();
    } catch(error) {
      if(person===selected) write(fields.result,error.message||'Příkaz se nepodařilo odeslat.');
    } finally {
      sending=false;
      renderControls();
    }
  }

  select.addEventListener('change',renderControls);
  fields.inspire.addEventListener('submit',event=>{
    event.preventDefault();
    void send('inspiration',select.value,fields.send);
  });
  for(const control of judgments) control.addEventListener('click',()=>void send('judgment',control.dataset.judgment,control));

  async function poll() {
    try { await refresh(); }
    finally { setTimeout(poll,1800); }
  }
  void poll();
})();
