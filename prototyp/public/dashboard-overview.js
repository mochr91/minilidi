(() => {
  const $ = id => document.getElementById(id);
  const num = new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:1});
  const fmt = n => Number.isFinite(n) ? num.format(n) : '—';
  const sides = ['ai','control'], label = {ai:'S AI',control:'Bez AI'};
  const metrics = {food:'Jídlo',wood:'Dřevo',stone:'Kámen',grain:'Obilí',flour:'Mouka',hay:'Seno',planks:'Prkna',tools:'Nářadí',eggs:'Vejce',hens:'Slepice',veg:'Zelenina',kraut:'Kyselé zelí',legumes:'Luštěniny',herbs:'Byliny',forestWood:'Dřevo u lesa',charcoal:'Dřevěné uhlí',meatSmoked:'Uzené maso',barley:'Ječmen',malt:'Slad',hops:'Chmel',beer:'Pivo',ore:'Železná ruda',iron:'Železo',ironTools:'Železné nářadí',salt:'Sůl',cloth:'Látka',wine:'Víno',leather:'Useň',boots:'Boty',flax:'Len',milk:'Mléko',cheese:'Sýr',carp:'Kapři v rybníku',carts:'Vozy',prep:'Příprava na zimu (%)',variety:'Pestrost stravy (%)',population:'Obyvatelé',houses:'Nové domy',buildings:'Provozní budovy',score:'Skóre',satiation:'Nasycení',energy:'Energie',fun:'Zábava'};
  const colors = {ai:'#087356',control:'#a7501e'};
  let states = {}, view = null, followLatest = true, pending = false, selectedMinute = null;
  for (const [key,text] of Object.entries(metrics)) $('metric').add(new Option(text,key));

  function live(state) {
    const people = Object.values(state.people ?? {});
    const avg = key => people.length ? people.reduce((sum,p) => sum + Number(p[key] ?? 0),0) / people.length : null;
    return {...state,minute:Math.floor(state.minute),population:people.length,houses:state.homesBuilt?.length,
      buildings:state.buildings && Object.keys(state.buildings).length,satiation:avg('food'),energy:avg('energy'),fun:avg('fun')};
  }
  function kpis() {
    const cards = [
      ['population', 'Obyvatelé'], ['reserveDays', 'Rezerva jídla (dny)'],
      ['buildings', 'Provozní budovy'], ['score', 'Skóre'],
      ['food', 'Zásoba jídla'], ['wood', 'Zásoba dřeva'], ['stone', 'Zásoba kamene'],
      ['energy', 'Průměrná energie', 100],
    ];
    $('overview-kpis').replaceChildren(...cards.map(([key, title, max]) =>
      MinilidiCharts.comparison({title, max, values: {ai: states.ai[key], control: states.control[key]}})
    ));
  }
  function svgNode(tag,attrs={},value) {
    const node = document.createElementNS('http://www.w3.org/2000/svg',tag);
    Object.entries(attrs).forEach(([key,v]) => node.setAttribute(key,v));
    if (value !== undefined) node.textContent = value;
    return node;
  }
  function time(minute) { return MinilidiTime.label(minute,states.ai.calendar?.originDay ?? 0); }

  function render() {
    if (!states.ai) return;
    const key = $('metric').value, active = sides.filter(side => $('series-' + side).checked);
    const latest = Math.max(...sides.map(side => states[side].minute)), from = latest - Number($('period').value);
    const series = Object.fromEntries(active.map(side => {
      const current = states[side];
      const points = [...(current.history ?? []).filter(p => p.minute < current.minute && p.minute >= from),current]
        .filter(p => p.minute >= from).sort((a,b) => a.minute - b.minute);
      return [side,points];
    }));
    const minutes = [...new Set(Object.values(series).flatMap(points => points.filter(p => Number.isFinite(p[key])).map(p => p.minute)))].sort((a,b) => a-b);
    const plot = $('overview-plot'), slider = $('sample');
    if (!minutes.length) {
      plot.textContent = active.length ? 'Pro tento ukazatel zatím nejsou údaje.' : 'Zapni alespoň jednu vesnici.';
      slider.disabled = true; $('sample-value').textContent = ''; view = null; return;
    }
    const values = Object.values(series).flatMap(p => p.map(row => row[key]).filter(Number.isFinite));
    const lower = Math.min(0,...values), upper = Math.max(['satiation','energy','fun'].includes(key)?100:1,...values);
    const targetStep = (upper-lower)/4, scale = 10 ** Math.floor(Math.log10(targetStep));
    const step = ([1,2,2.5,5,10].find(n => n * scale >= targetStep) ?? 10) * scale;
    const min = Math.floor(lower/step)*step, max = Math.ceil(upper/step)*step;
    const left = 56,right = 740,top = 18,bottom = 207;
    const x = minute => left + (minute-from)/(latest-from)*(right-left);
    const y = value => bottom - (value-min)/(max-min)*(bottom-top);
    const svg = svgNode('svg',{viewBox:'0 0 760 239',preserveAspectRatio:'none',role:'img','aria-label':'Srovnávací graf: ' + metrics[key]});
    for (let tick=0; tick<=Math.round((max-min)/step); tick++) {
      const value = min + tick*step, row = y(value);
      svg.append(svgNode('line',{x1:left,x2:right,y1:row,y2:row,stroke:'#d9e6df'}),svgNode('text',{x:left-8,y:row+4,'text-anchor':'end'},fmt(value)));
    }
    svg.append(svgNode('text',{x:left,y:229},time(Math.max(0,from))),svgNode('text',{x:right,y:229,'text-anchor':'end'},time(latest)));
    for (const side of active) {
      let d = '', connected = false;
      const attributes = {stroke:colors[side],'stroke-width':2.5,fill:'none','stroke-linecap':'round'};
      if (side === 'control') attributes['stroke-dasharray'] = '6 4';
      for (const p of series[side]) {
        if (!Number.isFinite(p[key])) { connected = false; continue; }
        const px=x(p.minute), py=y(p[key]);
        d += (connected ? 'L' : 'M') + px + ' ' + py;
        connected = true;
        svg.append(svgNode('circle',{cx:px,cy:py,r:1.65,fill:colors[side]}));
      }
      svg.append(svgNode('path',{...attributes,d}));
    }
    const cursor = svgNode('line',{y1:top,y2:bottom,stroke:'#52665e','stroke-dasharray':'3 3'});
    svg.append(cursor); plot.replaceChildren(svg);
    slider.max = minutes.length - 1; slider.disabled = minutes.length < 2;
    const index = followLatest || selectedMinute == null ? minutes.length-1 : minutes.reduce((best,m,i) =>
      Math.abs(m-selectedMinute)<Math.abs(minutes[best]-selectedMinute) ? i : best,0);
    slider.value = index;
    view = {minutes,series,key,active,x,cursor};
    show(index);
    svg.addEventListener('pointermove',event => {
      const rect = svg.getBoundingClientRect(), px = (event.clientX-rect.left)/rect.width*760;
      const i = minutes.reduce((best,m,index) => Math.abs(x(m)-px)<Math.abs(x(minutes[best])-px)?index:best,0);
      slider.value = i; followLatest = i === minutes.length - 1; show(i);
    });
  }
  function show(index) {
    if (!view) return;
    const {minutes,series,key,active,x,cursor} = view, minute = minutes[index];
    selectedMinute = minute;
    cursor.setAttribute('x1',x(minute)); cursor.setAttribute('x2',x(minute));
    const value = time(minute) + ' · ' + active.map(side => {
      const point = series[side].find(p => p.minute === minute);
      return label[side] + ': ' + fmt(point?.[key]);
    }).join(' · ');
    $('sample-value').textContent = value;
    $('sample').setAttribute('aria-valuetext',value);
  }

  async function refresh() {
    if (pending) return;
    pending = true; $('overview-reload').disabled = true;
    try {
      const worlds = await Promise.all(sides.map(async side => {
        const res = await fetch('api.php?view=overview&branch=' + side,{cache:'no-store',signal:AbortSignal.timeout(6000)});
        if (!res.ok) throw new Error();
        return live(await res.json());
      }));
      states = {ai:worlds[0],control:worlds[1]};
      $('overview-feedback').textContent = sides.map(side => label[side] + ': ' + time(states[side].minute) +
        (states[side].running ? '' : ' (simulace stojí)')).join('  |  ') + ' · aktualizováno ' + new Date().toLocaleTimeString('cs-CZ');
      kpis(); render();
    } catch {
      $('overview-feedback').textContent = states.ai ? 'Spojení se přerušilo. Zobrazuji poslední načtený stav.' : 'Přehled se nepodařilo načíst. Za chvíli to zkusím znovu.';
    } finally {
      pending = false; $('overview-reload').disabled = false;
    }
  }
  $('sample').addEventListener('input',() => {
    const i = Number($('sample').value); followLatest = i === view?.minutes.length - 1; show(i);
  });
  ['metric','period','series-ai','series-control'].forEach(id => $(id).addEventListener('change',render));
  $('overview-reload').addEventListener('click',refresh);
  refresh(); setInterval(refresh,12000);
})();