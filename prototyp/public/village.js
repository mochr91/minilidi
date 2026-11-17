'use strict';

(() => {
  const branchNames = {ai:'Vesnice s AI',control:'Vesnice bez AI'};
  const branchInUrl = new URLSearchParams(location.search).get('branch') === 'control' ? 'control' : 'ai';
  const resources = {food:'Jídlo',wood:'Dřevo',stone:'Kámen'};
  const stockLabels = {...resources,quarryStone:'Kámen u lomu',grain:'Obilí',flour:'Mouka',hay:'Seno',planks:'Prkna',tools:'Nářadí',eggs:'Vejce',veg:'Zelenina',kraut:'Kyselé zelí',legumes:'Luštěniny',herbs:'Byliny',herbsDry:'Sušené byliny',forestWood:'Dřevo u lesa',charcoal:'Dřevěné uhlí',game:'Zvěřina',meatSmoked:'Uzené maso',barley:'Ječmen',malt:'Slad',hops:'Chmel',beerYoung:'Mladé pivo',beer:'Pivo',ore:'Železná ruda',iron:'Železo',ironTools:'Železné nářadí',salt:'Sůl',cloth:'Látka',hides:'Kožky',grapes:'Hrozny',wine:'Víno',barrels:'Sudy',leather:'Useň',boots:'Boty',flax:'Len',milk:'Mléko',cheese:'Sýr'};
  const sources = ['AI','Hra','Hráč','Příběh','Víra'];
  const metricNames = {food:'Jídlo',energy:'Energie',fun:'Zábava',mood:'Nálada',productivity:'Výkon',willingness:'Ochota'};
  const beliefs = {faith:'Víra',trust:'Důvěra',fear:'Strach'};
  const icons = {
    food:'<path d="M4 2v5m3-5v5m3-5v5c0 2-1.5 3-3 3S4 9 4 7m3 3v8m8 0V2c-2 2-3 5-3 8h3"/>',
    wood:'<path d="M3 13 12 4l5 3v5l-9 6-5-2Zm0 0 5 2 9-8M8 15v3"/>',
    stone:'<path d="m2 15 3-8 6-4 6 4 1 8-7 3Zm3-8 6 2 6-2m-6 2v9"/>',
    quarryStone:'<path d="m2 17 4-8 5 3 2-4 5 9ZM4 7l8-5m-2 0 5 3"/>',
    grain:'<path d="M10 18V4m0 4C6 8 5 6 5 3c3 0 5 2 5 5Zm0 4c4 0 5-2 5-5-3 0-5 2-5 5Zm0 4c-4 0-5-2-5-5 3 0 5 2 5 5Z"/>',
    flour:'<path d="m7 3 3 2 3-2-1 4c3 3 5 6 5 8 0 2-2 3-7 3s-7-1-7-3c0-2 2-5 5-8Zm1 4h4m-4 6h4"/>',
    hay:'<path d="M2 11 6 6h9l3 5v6H2Zm0 0h16M7 6v11m6-11v11M4 14h2m9 0h2"/>',
    planks:'<path d="M2 12 13 3l5 4-11 9Zm0 0v3l5 4 11-9V7M7 16v3m-2-7 9-7"/>',
    tools:'<path d="m9 8 3 3-6 7-3-3Zm1-1 3-4 5 4-3 4Zm2 8 3 3 3-3-3-3"/>',
    veg:'<path d="M10 17c-3 0-5-2-5-5 0-4 3-7 5-9 2 2 5 5 5 9 0 3-2 5-5 5Zm0-12v12"/>',
    kraut:'<path d="M5 6h10v2H5Zm1 2h8l1 8H5Zm2-4h4"/>',
    legumes:'<path d="M3 13c4 5 11 3 14-5-6-1-11 0-14 5Zm4 0 5-3"/>',
    herbs:'<path d="M10 18V8m0 3C6 11 5 8 5 5c3 0 5 2 5 6Zm0 2c4 0 5-3 5-6-3 0-5 2-5 6Z"/>',
    forestWood:'<path d="M2 15h16M3 12h14M5 9h10M4 18h12"/>',
    charcoal:'<path d="M3 14c2-5 5-8 7-8s5 3 7 8Zm4-3h6M10 6V3"/>',
    game:'<path d="M5 16c0-5 3-9 6-9 2 0 3 1 3 3 0 4-4 6-9 6Zm6-9c0-3 1-5 2-5m2 5c0-3 1-4 2-4"/>',
    meatSmoked:'<path d="M5 5c4-3 10 0 10 5s-5 8-8 7-4-9-2-12Zm5-5v5"/>',
    herbsDry:'<path d="M10 18V6m-3 3 3-3 3 3M6 12l4-3 4 3M6 15h8"/>',
    barley:'<path d="M10 18V3m0 4-3-2m3 4-3-2m3 4-3-2m3-4 3-2m-3 4 3-2m-3 4 3-2"/>',
    malt:'<path d="M10 18V3m0 4-3-2m3 4-3-2m3 4-3-2m3-4 3-2m-3 4 3-2m-3 4 3-2M4 18h12"/>',
    hops:'<path d="M10 2v3m-3 1c0 4 2 6 3 9 1-3 3-5 3-9-2-1-4-1-6 0Zm1 3h4m-4 3h3"/>',
    ore:'<path d="m3 16 3-7 5-3 6 4 1 6Zm3-7 5 2 4-1m-4 1v6"/>',
    iron:'<path d="M3 8h14l-2 4H5Zm2 4v3h10v-3"/>',
    ironTools:'<path d="m4 16 8-8m-2-4 6 6-2 2-6-6Zm-6 12 2 2"/>',
    salt:'<path d="M6 8h8l1 9H5Zm1-4h6v4H7Zm2 1v1m2-1v1"/>',
    cloth:'<path d="M3 6c4-2 10 2 14 0v8c-4 2-10-2-14 0Zm0 4c4-2 10 2 14 0"/>',
    hides:'<path d="M6 3 4 6l2 3-2 5 3 3h6l3-3-2-5 2-3-2-3-2 2H8Z"/>',
    grapes:'<path d="M10 2v4m-3 2a2 2 0 1 0 0 .1m6-.1a2 2 0 1 0 0 .1m-3 3.9a2 2 0 1 0 0 .1m0 3.9a2 2 0 1 0 0 .1"/>',
    wine:'<path d="M6 3h8c0 5-1 8-4 8S6 8 6 3Zm4 8v6m-3 0h6"/>',
    barrels:'<path d="M6 3h8c2 4 2 10 0 14H6C4 13 4 7 6 3Zm-1 4h10M5 13h10"/>',
    leather:'<path d="M4 5h12l-1 10H5Zm3 3h6M7 11h6"/>',
    boots:'<path d="M6 3h5v8l5 2v4H5V3Zm0 9h5"/>',
    flax:'<path d="M10 18V6m0 3-3-3m3 5-3-3m3 3 3-3m-3 5 3-3m-3-2c-2-1-1-4 0-5 1 1 2 4 0 5Z"/>',
    milk:'<path d="M7 3h6l1 4v10H6V7Zm-1 4h8"/>',
    cheese:'<path d="M3 8l7-4 7 4v7H3Zm0 3h14m-9 0v-2m5 2v-2"/>',
    beerYoung:'<path d="M5 6h8v11H5Zm8 2h3v5h-3M5 9h8"/>',
    beer:'<path d="M5 6h8v11H5Zm8 2h3v5h-3M4 4c2-2 5 0 6-1 2 2 4 0 5 2"/>',
    eggs:'<path d="M10 2c3 0 5.5 5 5.5 9a5.5 5.5 0 0 1-11 0C4.5 7 7 2 10 2Zm-2.5 10a2.8 2.8 0 0 0 2.5 2.6"/>',
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
  const counted = (value,one,few,many) => fmt(value)+' '+(value===1?one:value>=2&&value<=4?few:many);
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
  const stockStrips = [...document.querySelectorAll('[data-village-stocks]')].map(strip => {
    const list = strip.querySelector('dl');
    const rows = Object.entries(stockLabels).map(([key,label]) => {
      const row = el('div','','village-stock');
      row.dataset.resource = key;
      const term = el('dt',label);
      const value = el('dd','–');
      row.insertAdjacentHTML('afterbegin',icon(key));
      row.append(term,value);
      list.append(row);
      return {key,label,row,value};
    });
    return {branch:strip.dataset.villageStocks,rows};
  });
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
      button('map-zoom-in','Přiblížit mapu','+'),
      button('map-reset','Vrátit původní pohled (0)',icon('reset'),'disabled'),'</div></div>',
      '<canvas data-field="map" width="1614" height="821" tabindex="0" aria-label="Mapa vesnice. Přiblížení kolečkem nebo klávesami plus a minus, posun šipkami nebo tažením."></canvas>',
      '<div class="map-corner map-bottom"><span class="map-chip">'+icon('people')+'<span data-field="growth">Obyvatelé a domy</span></span>',
      '<span class="map-chip">'+icon('plan')+'<span data-field="plan">Čeká na stav</span></span></div>',
      '<div class="map-updates" data-field="map-updates" hidden>',
      '<div class="map-update" data-field="crisis-status" hidden><strong data-field="crisis-label"></strong><span data-field="crisis-value"></span><progress data-field="crisis-progress" aria-label="Průběh obrany"></progress></div>',
      '<div class="map-update" data-field="project-status" hidden><strong data-field="project-label"></strong><span data-field="project-value"></span><progress data-field="project-progress" aria-label="Průběh stavby"></progress></div></div></div>',
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
    fields.map.addEventListener('building',event=>showBuilding(card,event.detail));
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

  // Okénko budovy po kliknutí na mapě: co to je, co dělá, jak na tom je teď a co stála.
  function closeBuilding(frame) {
    frame.querySelector('.building-popup')?.remove();
  }

  async function showBuilding(card,detail) {
    const frame=card.fields['map-frame'];
    if (!detail?.key) { closeBuilding(frame); return; }
    const token=(card.buildingToken=(card.buildingToken||0)+1);
    let info;
    try {
      const response=await fetch('api.php?branch='+card.branch+'&view=building&key='+encodeURIComponent(detail.key),{cache:'no-store',signal:AbortSignal.timeout(3500)});
      if (!response.ok) return;
      info=await response.json();
    } catch { return; }
    if (token!==card.buildingToken) return;
    closeBuilding(frame);
    const popup=el('aside','','building-popup');
    popup.setAttribute('role','dialog');
    popup.setAttribute('aria-label',info.label);
    const head=el('header');
    head.append(el('h4',info.label));
    const close=el('button','×','building-close');
    close.type='button';
    close.setAttribute('aria-label','Zavřít okénko');
    close.addEventListener('click',event=>{ event.stopPropagation(); closeBuilding(frame); });
    head.append(close);
    popup.append(head);
    popup.dataset.state=info.built?'built':'planned';
    popup.append(el('p',info.what,'building-what'));
    const list=(title,items,className='')=>{
      if (!items?.length) return;
      const section=el('section','',className);
      section.append(el('h5',title));
      const ul=el('ul');
      for (const item of items) ul.append(el('li',item));
      section.append(ul);
      popup.append(section);
    };
    list('Co dělá',info.does);
    list(info.built?'Teď':'Stav',[...(info.status??[]).filter(()=>!info.built),...(info.facts??[])]);
    if (info.built) list('Stavba',info.status);
    if (info.work) {
      const cost=(info.cost??[]).map(item=>item.label+' '+item.amount).join(', ');
      const needs=(info.needs??[]).join(', ');
      popup.append(el('p','Pracnost '+info.work+' člověkohodin'+(cost?' · materiál: '+cost:'')+(needs?' · navazuje na: '+needs:''),'building-cost'));
    }
    frame.append(popup);
    const box=frame.getBoundingClientRect();
    const left=Math.max(6,Math.min(box.width-popup.offsetWidth-6,detail.clientX-box.left+14));
    const top=Math.max(6,Math.min(box.height-popup.offsetHeight-6,detail.clientY-box.top+14));
    popup.style.left=left+'px';
    popup.style.top=top+'px';
  }

  window.addEventListener('keydown',event=>{
    if (event.key==='Escape') for (const frame of document.querySelectorAll('.village-map')) closeBuilding(frame);
  });

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

  function renderMapUpdates(fields,state) {
    const entries=[
      ['crisis',state.crisis?.label,state.crisis?.done,state.crisis?.need],
      ['project',state.project?.label,state.project?.progress,state.project?.work],
    ];
    let visible=false;
    for (const [key,label,done,need] of entries) {
      const valid=!!label && Number.isFinite(done) && Number.isFinite(need) && need>0;
      fields[key+'-status'].hidden=!valid;
      if (!valid) continue;
      visible=true;
      fields[key+'-label'].textContent=key==='project'?'Stavba · '+label:label;
      fields[key+'-value'].textContent=(key==='crisis'?'Obrana ':'')+fmt(done)+' z '+fmt(need)+(key==='project'?' h':'');
      fields[key+'-progress'].max=need;
      fields[key+'-progress'].value=Math.max(0,Math.min(done,need));
    }
    fields['map-updates'].hidden=!visible;
  }

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
    const growth=(state.rankLabel?state.rankLabel+' · ':'')+counted(Object.keys(state.people).length,'obyvatel','obyvatelé','obyvatel')+' · '+
      counted(state.homesBuilt.length,'nový dům','nové domy','nových domů');
    write(map.growth,growth,growth+' · kámen u lomu: '+fmt(state.quarryStone));
    const remaining=Math.max(0,Math.ceil(state.nextAutoMinute-state.minute));
    const model=state.ai?.model??'';
    const timing=remaining?'další plán za '+remaining+' min':'připravuje plán';
    const mode=state.ai?.status==='Odpověděla AI'&&model.length<=16?'AI '+model:'AI';
    const summary=branch==='control'?'Pravidla':mode+' · '+timing;
    write(map.plan,summary,branch==='control'?summary:summary+' · '+[state.ai?.status,model,state.ai?.detail].filter(Boolean).join(' · '));
    setMap(state.people,selected,state.minute,state.homesBuilt.length,state.weather,state.calendar,
      {buildings:state.buildings,project:state.project,crisis:state.crisis,houseProject:state.houseProject,garden:state.garden,kiln:state.kiln,forestWood:state.forestWood,hopYard:state.hopYard,brewProject:state.brewProject,houseKinds:state.houseKinds,vineyard:state.vineyard,roofMap:state.roofMap});
    renderMapUpdates(map,state);
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
    write(fields.family,person?counted(person.age,'rok','roky','let')+' · '+person.family+(person.pregnant?.revealed?' · čeká dítě':'')
      +(state.crisis&&person.task?.action&&['firefight','flood_defense'].includes(person.task.action)?' · zasahuje':''):'–');
    write(fields.activity,person?person.role+' · '+person.activity:'Postava není v této vesnici dostupná');
    const gap=(person?.stubborn??0)-(person?.reason??0);
    const mayorTag=state?.mayorInfo?.mayor?.id===selected?(person?.sex==='f'?'Starostka':'Starosta')+' · ':'';
    write(fields.trait,person?mayorTag+(person.trait??'–')+(person.reason!=null?(gap>=15?' · Povaha: paličatá':gap<=-15?' · Povaha: rozvážná':''):''):'–');
    const task=person?.task;
    const pending=state?.pending.some(job=>job.kind==='inspiration'&&job.person===selected);
    const description=pending?'Zvažuje vnuknutí…':task
      ? (task.activity?.label??state.actions[task.action]?.label??task.action)+(state.minute<task.start?' · začne '+time(state,task.start):'')
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
    noteList(fields.memory,memories,'Zatím žádné vzpomínky.');
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
    for (const {branch,rows} of stockStrips) {
      for (const {key,label,row,value} of rows) {
        const amount=fmt(states[branch]?.[key]);
        write(value,amount,label+' '+amount);
        row.title=value.title;
      }
    }
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
