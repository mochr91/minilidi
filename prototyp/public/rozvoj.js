'use strict';

const $ = id => document.getElementById(id);
const make = (tag, text = '', cls = '') => {
  const node = document.createElement(tag);
  if (text !== '') node.textContent = text;
  if (cls) node.className = cls;
  return node;
};
const number = new Intl.NumberFormat('cs-CZ', {maximumFractionDigits: 1});
const fmt = value => Number.isFinite(value) ? number.format(value) : '–';
const SIDES = [['ai', 'Vesnice s AI'], ['control', 'Vesnice bez AI']];
const states = {};
let chosenWeek = 'live';

const METRICS = [
  ['Obyvatelé', 'population', 1], ['Děti do 14 let', 'children', 0], ['Narozená miminka', 'births', 1],
  ['Úmrtí', 'deathsTotal', -1], ['Svatby', 'weddings', 1], ['Budovy', 'buildings', 1], ['Domy', 'homes', 1],
  ['Rezerva jídla (dny)', 'reserveDays', 1], ['Nálada', 'avgMood', 1], ['Zábava', 'avgFun', 1], ['Energie', 'avgEnergy', 1],
  ['Nejnižší nálada', 'minMood', 1], ['Odvedená práce (úkolů)', 'tasksTotal', 0], ['Hladová jídla', 'hungryMeals', -1],
  ['Splněné cíle', 'goalsDone', 1], ['Přežité zimy', 'winters', 1], ['Skóre vesnice', 'score', 1],
];

function bar(value, max) {
  const wrap = make('div', '', 'dev-bar');
  const fill = make('i');
  fill.style.width = Math.max(0, Math.min(100, max ? value / max * 100 : 0)) + '%';
  wrap.append(fill);
  return wrap;
}

function sideBox(side, title) {
  const box = make('div', '', 'dev-side');
  box.dataset.side = side;
  box.append(make('h3', title));
  return box;
}

function days(minutes) {
  const d = minutes / 1440;
  return d < 1 ? Math.max(1, Math.round(minutes / 60)) + ' h' : fmt(d) + ' dní';
}

function renderGoals() {
  $('goals').replaceChildren(...SIDES.map(([side, title]) => {
    const state = states[side], box = sideBox(side, title);
    if (!state) return box;
    const main = state.goalsState.find(goal => !goal.complete);
    box.append(make('p', main ? 'Hlavní cíl: ' + main.label + ' (' + fmt(main.current) + ' z ' + main.target + ' ' + main.unit + ')' : 'Všechny cíle jsou splněny.', 'dev-line'));
    for (const goal of state.goalsState) {
      const row = make('div', '', 'dev-goal');
      row.append(make('b', goal.label), make('small', ' · ' + fmt(goal.current) + ' / ' + goal.target + ' ' + goal.unit + (goal.complete ? ' · splněno' : '')));
      row.append(bar(goal.current, goal.target));
      box.append(row);
    }
    box.append(make('p', 'Skóre vesnice ' + state.score + ' · kapacita bydlení ' + state.housingCapacity + ' · obyvatel ' + Object.keys(state.people).length, 'dev-note'));
    return box;
  }));
}

function liveRecord(state) {
  const people = Object.values(state.people), n = Math.max(1, people.length);
  const sum = key => people.reduce((acc, p) => acc + p[key], 0);
  const c = state.counters || {};
  const deaths = Object.values(c.deaths || {}).reduce((a, b) => a + b, 0);
  const tasks = Object.values(c.tasks || {}).reduce((a, b) => a + b, 0);
  return {
    population: people.length, children: people.filter(p => p.age < 14).length, births: c.births || 0, deathsTotal: deaths,
    weddings: c.weddings || 0, buildings: Object.keys(state.buildings || {}).length, homes: state.homesBuilt.length,
    reserveDays: state.reserveDays, avgMood: Math.round(sum('mood') / n * 10) / 10, avgFun: Math.round(sum('fun') / n * 10) / 10,
    avgEnergy: Math.round((100 - sum('fatigue') / n) * 10) / 10, minMood: Math.round(Math.min(...people.map(p => p.mood), 100) * 10) / 10,
    tasksTotal: tasks, hungryMeals: c.hungryMeals || 0, goalsDone: Object.keys(state.goals?.done || {}).length,
    winters: state.winters || 0, score: state.score, text: 'Probíhající týden (od posledního souhrnu).',
  };
}

function weekRecord(side, week) {
  const state = states[side];
  if (!state) return null;
  if (week === 'live') return liveRecord(state);
  return (state.weekly || []).find(row => String(row.week) === week) || null;
}

function renderWeekSelect() {
  const weeks = new Set();
  for (const [side] of SIDES) for (const row of states[side]?.weekly || []) weeks.add(row.week);
  const sorted = [...weeks].sort((a, b) => b - a);
  const select = $('week');
  const keys = ['live', ...sorted.map(String)].join(',');
  if (select.dataset.keys !== keys) {
    select.replaceChildren(new Option('Právě teď (probíhající týden)', 'live'), ...sorted.map(week => new Option('Týden ' + week, String(week))));
    select.dataset.keys = keys;
  }
  if (![...select.options].some(option => option.value === chosenWeek)) chosenWeek = 'live';
  select.value = chosenWeek;
}

function renderCompare() {
  renderWeekSelect();
  const [ai, control] = [weekRecord('ai', chosenWeek), weekRecord('control', chosenWeek)];
  $('compare-chart').replaceChildren(...METRICS.map(([title, key]) =>
    MinilidiCharts.comparison({
      title, values: {ai: ai?.[key], control: control?.[key]},
      max: ['avgMood', 'avgFun', 'avgEnergy', 'minMood'].includes(key) ? 100 : undefined,
    })
  ));
  const body = $('compare').tBodies[0];
  body.replaceChildren(...METRICS.map(([label, key, better]) => {
    const row = make('tr');
    const a = ai?.[key], c = control?.[key];
    const diff = Number.isFinite(a) && Number.isFinite(c) ? Math.round((a - c) * 10) / 10 : null;
    const cell = make('td', diff === null ? '–' : (diff > 0 ? '+' : '') + fmt(diff));
    if (diff && better) cell.dataset.better = String((diff > 0) === (better > 0));
    row.append(make('th', label), make('td', fmt(a)), make('td', fmt(c)), cell);
    row.firstChild.scope = 'row';
    return row;
  }));
  $('text-ai').textContent = 'S AI: ' + (ai?.text || 'Zatím bez souhrnu.');
  $('text-control').textContent = 'Bez AI: ' + (control?.text || 'Zatím bez souhrnu.');
  const weeks = new Set();
  for (const [side] of SIDES) for (const row of states[side]?.weekly || []) weeks.add(row.week);
  const sorted = [...weeks].sort((a, b) => b - a).slice(0, 8);
  MinilidiCharts.weeklyScores($('trend-chart'), [...sorted].reverse().map(week => ({
    week, values: {ai: weekRecord('ai', String(week))?.score, control: weekRecord('control', String(week))?.score},
  })));
  $('trend').tBodies[0].replaceChildren(...(sorted.length ? sorted : []).map(week => {
    const a = weekRecord('ai', String(week))?.score, c = weekRecord('control', String(week))?.score;
    const row = make('tr');
    const diff = Number.isFinite(a) && Number.isFinite(c) ? a - c : null;
    const cell = make('td', diff === null ? '–' : (diff > 0 ? '+' : '') + diff);
    if (diff) cell.dataset.better = String(diff > 0);
    row.append(make('th', 'Týden ' + week), make('td', fmt(a)), make('td', fmt(c)), cell);
    row.firstChild.scope = 'row';
    return row;
  }));
  if (!sorted.length) {
    const row = make('tr');
    const cell = make('td', 'První týdenní souhrn vznikne po sedmi herních dnech (zhruba 2 h 20 min běhu).');
    cell.colSpan = 4;
    row.append(cell);
    $('trend').tBodies[0].append(row);
  }
}

function renderEconomy() {
  $('economy').replaceChildren(...SIDES.map(([side, title]) => {
    const state = states[side], box = sideBox(side, title);
    if (!state) return box;
    const chips = make('div', '', 'dev-chips');
    for (const [key, label] of Object.entries(state.resourceLabels)) {
      const chip = make('span');
      chip.append(document.createTextNode(label + ' '), make('b', fmt(state[key])));
      chips.append(chip);
    }
    box.append(chips, make('p', 'Rezerva jídla na ' + fmt(state.reserveDays) + ' dní · denní potřeba ' + fmt(state.dailyNeed.food) + ' jídla'
      + (state.hayShort ? ' · chybí seno pro dobytek' : '')
      + (state.hens != null && state.buildings?.kurnik !== undefined ? ' · slepic ' + fmt(Math.round(state.hens)) + (state.coopEggs >= 1 ? ', ve kurníku čeká ' + fmt(state.coopEggs) + ' vajec ke sběru' : '') : ''), 'dev-note'));
    if (state.winterPrepDetail) {
      const d = state.winterPrepDetail, pc = value => Math.round((value ?? 0) * 100) + ' %', g = state.garden ?? {};
      const garden = state.buildings?.zahrada === undefined ? ''
        : ' · zahrada: ' + ((g.crop ?? 0) >= 1 ? 'úroda čeká na sklizeň (' + fmt(g.crop) + ')' : (g.sown ?? 0) > 0 ? 'zaseto ' + pc(g.sown) + ', péče ' + pc(g.care) : 'čeká na jaro');
      box.append(make('p', 'Příprava na zimu ' + pc(d.min) + ' (jídlo ' + pc(d.food) + ', topivo ' + pc(d.fuel) + ', seno ' + pc(d.hay) + ') · pestrost stravy ' + pc(state.variety) + garden, 'dev-note'));
    }
    if (state.carts !== undefined && (state.buildings?.dilna !== undefined || state.carts > 0)) {
      const kiln = state.kiln?.state === 'burning' ? 'milíř hoří' : state.buildings?.milir !== undefined ? 'milíř nehoří' : '';
      box.append(make('p', 'Vozy ' + state.carts + ' ze 3 · dřevo u lesa ' + fmt(state.forestWood) + ' · kámen u lomu ' + fmt(state.quarryStone) + (kiln ? ' · ' + kiln : '') + (state.gameStock !== undefined ? ' · zvěř v lese ' + Math.round(state.gameStock) + ' %' : ''), 'dev-note'));
    }
    if (state.buildings?.chmelnice !== undefined || state.buildings?.pivovar !== undefined || (state.beer ?? 0) + (state.beerYoung ?? 0) > 0) {
      const yard = state.hopYard ?? {}, log = state.beerLog ?? {};
      const hops = state.buildings?.chmelnice === undefined ? '' : (yard.crop ?? 0) >= 1 ? ' · chmel čeká na česání (' + fmt(yard.crop) + ')' : (yard.care ?? 0) > 0 ? ' · chmel vázaný z ' + Math.round(yard.care * 100) + ' %' : '';
      const brew = state.brewProject?.paid ? ' · vaří se (' + Math.round((state.brewProject.progress ?? 0) / 18 * 100) + ' %)' : '';
      box.append(make('p', 'Pivo ' + fmt((state.beer ?? 0) + (state.beerYoung ?? 0)) + ' (zralé ' + fmt(state.beer ?? 0) + ') · slad ' + fmt(state.malt ?? 0) + ' · ječmen ' + fmt(state.barley ?? 0) + ' · chmel ' + fmt(state.hops ?? 0)
        + hops + brew + (log.brews ? ' · uvařeno várek ' + log.brews + ', vypito ' + fmt(log.drunk ?? 0) + ' dávek' : ''), 'dev-note'));
    }
    if (state.buildings?.kovarna !== undefined || (state.trade?.visits ?? 0) > 0 || state.buildings?.vinice !== undefined) {
      const v = state.vineyard ?? {}, batch = state.wineBatch ?? {}, last = (state.tribute?.log ?? []).slice(-1)[0];
      const vine = state.buildings?.vinice === undefined ? '' : ' · vinice: ' + ((v.crop ?? 0) >= 1 ? 'hrozny čekají na česání (' + fmt(v.crop) + ')' : v.frost ? 'letos pomrzla' : 'péče ' + Math.round((v.care ?? 0) * 100) + ' %')
        + ' · víno ' + fmt(state.wine ?? 0) + (batch.state === 'fermenting' ? ' (kvasí dalších ' + fmt(batch.yield) + ')' : '') + ' · sudy ' + fmt(state.barrels ?? 0);
      box.append(make('p', 'Železné nářadí ' + fmt(state.ironTools ?? 0) + ' · železo ' + fmt(state.iron ?? 0) + ' · ruda ' + fmt(state.ore ?? 0)
        + ' · obchodník: návštěv ' + (state.trade?.visits ?? 0) + ', na účtu ' + fmt(state.trade?.credit ?? 0) + ' grošů' + (state.trade?.kettle ? ', pivovar má měděný kotel' : '')
        + ' · sůl ' + fmt(state.salt ?? 0) + ' · látka ' + fmt(state.cloth ?? 0) + ' · kožky ' + fmt(state.hides ?? 0)
        + (last ? ' · poslední úrok: ' + fmt(last.grain) + ' obilí' + (last.hens ? ', ' + last.hens + ' slepice' : '') + (last.missing > .2 ? ' (nedoplatek)' : '') : '') + vine, 'dev-note'));
    }
    if (['spychar', 'mlekarna', 'rybnik', 'kozeluzna', 'tkalcovna', 'fara'].some(key => state.buildings?.[key] !== undefined)) {
      const has = key => state.buildings?.[key] !== undefined;
      const parts = [];
      if (has('spychar')) parts.push('špýchar chrání obilí a mouku');
      if (has('mlekarna')) parts.push('mléko ' + fmt(state.milk ?? 0) + ' · sýr ' + fmt(state.cheese ?? 0));
      if (has('rybnik')) parts.push('kapři v rybníku ' + fmt(state.pond?.fish ?? 0));
      if (has('kozeluzna')) parts.push('kožky ' + fmt(state.hides ?? 0) + ' · useň ' + fmt(state.leather ?? 0) + ' · boty ' + fmt(state.boots ?? 0));
      if (has('tkalcovna')) parts.push('len ' + fmt(state.flax ?? 0) + ' · látka ' + fmt(state.cloth ?? 0));
      if (has('fara')) parts.push('lekcí ve škole ' + (state.school?.lessons ?? 0));
      box.append(make('p', 'Řemesla a zásobárny: ' + parts.join(' · '), 'dev-note'));
    }
    if (state.rankLabel) {
      const blockers = { rozestup: 'čeká se na rozestup', zima: 'zima', krize: 'krize', strop: 'nejsou volná místa v domech', jidlo: 'málo jídla', drevo: 'málo dřeva', nalada: 'špatná nálada', hlad: 'nedávný hlad' };
      const day = Math.floor(state.minute / 1440);
      const people = Object.keys(state.people ?? {}).length;
      const next = state.settlers?.nextDay ?? 0;
      box.append(make('p', 'Růst vesnice: ' + state.rankLabel + ' · ' + people + ' lidí z ' + state.popCap + ' možných · přistěhovaných skupin ' + (state.settlers?.arrived ?? 0)
        + ' · další příchod ' + (next > day ? 'nejdřív za ' + (next - day) + ' dní' : 'možný hned') + ' (rozestup ' + (state.settlerInterval ?? '–') + ' dní)'
        + (state.settlerBlocked ? ' · teď brzdí: ' + (blockers[state.settlerBlocked] ?? state.settlerBlocked) : ' · nic nebrzdí'), 'dev-note'));
    }
    if (['vcelin', 'cihelna', 'lazen'].some(key => state.buildings?.[key] !== undefined)) {
      const has = key => state.buildings?.[key] !== undefined;
      const parts = [];
      if (has('vcelin')) parts.push('úlů ' + (state.hives ?? 0) + ' · med ' + fmt(state.honey ?? 0) + ' · vosk ' + fmt(state.wax ?? 0) + ' · svíček ' + fmt(state.candles ?? 0));
      if (has('cihelna')) {
        const roofs = Object.values(state.roofMap ?? {});
        parts.push('hlína ' + fmt(state.clay ?? 0) + ' · cihly a tašky ' + fmt(state.bricks ?? 0) + ' · střechy z tašek ' + roofs.filter(r => r === 'tile').length + ' z ' + roofs.length);
      }
      if (has('lazen')) parts.push('hygiena ' + Math.round((state.hygiene ?? 0) * 100) + ' %');
      box.append(make('p', 'Včely, cihly a lázeň: ' + parts.join(' · '), 'dev-note'));
    }
    const list = make('ul', '', 'dev-buildings');
    for (const [key, def] of Object.entries(state.buildingDefs)) {
      const built = state.buildings?.[key] !== undefined;
      const project = state.project?.key === key;
      const missingNeeds = def.needs.filter(need => state.buildings?.[need] === undefined);
      const item = make('li');
      item.dataset.state = built ? 'built' : project ? 'project' : missingNeeds.length ? 'locked' : 'open';
      item.append(make('strong', def.label), make('span', def.text));
      const cost = make('div', '', 'dev-line');
      const parts = Object.entries(def.cost).map(([res, amount]) => {
        const span = make('span', amount + ' ' + state.resourceLabels[res].toLowerCase());
        if (!built && state[res] < amount) span.className = 'short';
        return span;
      });
      const log = state.buildLog?.[key];
      if (built) cost.append(make('small', 'Postaveno' + (log ? ' · stavěli: ' + log.builders.map(b => b.name + ' ' + fmt(b.hours) + ' h').join(', ') + ' (odpracováno ' + fmt(log.worked) + ' h, norma ' + log.work + ' h)' : '')));
      else if (project) cost.append(make('small', 'Rozestavěno ' + fmt(state.project.progress) + ' z ' + state.project.work + ' člověkohodin'
        + (Object.keys(state.project.hours ?? {}).length ? ' · staví: ' + Object.entries(state.project.hours).sort((a, b) => b[1] - a[1]).map(([id, h]) => (state.people[id]?.name ?? id) + ' ' + fmt(h) + ' h').join(', ') : '')
        + (state.project.paid ? '' : ' · čeká na materiál: ')));
      else if (missingNeeds.length) cost.append(make('small', 'Nejdřív: ' + missingNeeds.map(need => state.buildingDefs[need].label).join(', ') + ' · práce ' + def.work + ' h · cena: '));
      else cost.append(make('small', 'Práce ' + def.work + ' člověkohodin · cena: '));
      if (!built && !(project && state.project.paid)) parts.forEach((span, i) => { if (i) cost.append(document.createTextNode(', ')); cost.append(span); });
      item.append(cost);
      list.append(item);
    }
    const houseLine = make('li');
    const kindName = kind => kind === 'domek' ? 'domek' : 'dům';
    houseLine.append(make('strong', 'Domy'), make('span', state.homesBuilt.length + ' z 4 postavených · kapacita ' + state.housingCapacity + ' lidí'
      + (state.houseProject ? ' · rozestavěný ' + kindName(state.houseProject.kind) + ': ' + fmt(state.houseProject.progress) + ' z ' + state.houseProject.work + ' člověkohodin'
        : state.houseWanted ? ' · vesnice potřebuje ' + kindName(state.houseWanted) : '')));
    list.append(houseLine);
    if (state.housing) {
      const names = id => state.people?.[id]?.name?.split(' ')[0] ?? id;
      const lodging = make('li');
      lodging.append(make('strong', 'Bydlení'), make('span', state.housing.filter(h => h.residents.length).map(h =>
        h.label.replace('dům rodiny ', '') + ' ' + h.residents.length + '/' + h.capacity + ' (' + h.residents.map(names).join(', ') + ')').join(' · ')
        + (state.housing.some(h => !h.residents.length) ? ' · volné: ' + state.housing.filter(h => !h.residents.length).map(h => h.label.replace('dům rodiny ', '')).join(', ') : '')));
      const engaged = Object.values(state.people ?? {}).filter(p => p.betrothed && p.sex === 'm');
      if (engaged.length) lodging.append(make('small', ' · snoubenci: ' + engaged.map(p => p.name.split(' ')[0] + ' a ' + (state.people[p.betrothed]?.name?.split(' ')[0] ?? '?')
        + (p.wedding ? ' (svatba za ' + Math.max(0, p.wedding.day - Math.floor(state.minute / 1440)) + ' dny)' : ' (čekají na bydlení)')).join(', ')));
      list.append(lodging);
    }
    box.append(list);
    return box;
  }));
}

function ageGroup(state) {
  const people = Object.values(state.people);
  return {children: people.filter(p => p.age < 14).length, adults: people.filter(p => p.age >= 14 && p.age < 60).length, elders: people.filter(p => p.age >= 60).length};
}

function renderLife() {
  const labels = [['children', 'Děti do 14 let'], ['adults', 'Obyvatelé ve věku 14–59 let'], ['elders', 'Obyvatelé od 60 let']];
  const groupsBySide = Object.fromEntries(SIDES.map(([side]) => [side, states[side] ? ageGroup(states[side]) : null]));
  const max = Math.max(1, ...Object.values(groupsBySide).flatMap(group => group ? Object.values(group) : []));
  $('life-chart').replaceChildren(...labels.map(([key, title]) =>
    MinilidiCharts.comparison({title, max, values: {ai: groupsBySide.ai?.[key], control: groupsBySide.control?.[key]}})
  ));
  $('life').replaceChildren(...SIDES.map(([side, title]) => {
    const state = states[side], box = sideBox(side, title);
    if (!state) return box;
    const pregnant = Object.values(state.people).filter(p => p.pregnant?.revealed);
    const unknown = Object.values(state.people).some(p => p.pregnant && !p.pregnant.revealed);
    box.append(make('h3', 'Čekají dítě'));
    if (pregnant.length) {
      const list = make('ul', '', 'dev-list');
      for (const p of pregnant) list.append(make('li', p.name + ' · porod za ' + days(Math.max(0, p.pregnant.due - state.minute)) + (p.pregnant.father ? ' · otec ' + (state.people[p.pregnant.father]?.name || 'zemřel') : '')));
      box.append(list);
    } else box.append(make('p', unknown ? 'Některé těhotenství ještě není veřejně známé.' : 'Nikdo.', 'dev-note'));
    box.append(make('h3', 'Hřbitov'));
    const graves = (state.graveyard || []).slice(-6).reverse();
    if (graves.length) {
      const list = make('ul', '', 'dev-list');
      for (const g of graves) list.append(make('li', g.name + ' (' + g.age + ') · ' + g.cause + ' · den ' + (Math.floor(g.minute / 1440) - state.calendar.originDay + 1)));
      box.append(list);
    } else box.append(make('p', 'Nikdo zatím nezemřel.', 'dev-note'));
    const c = state.counters || {};
    box.append(make('p', 'Od posledního souhrnu: narozeno ' + (c.births || 0) + ', zemřelo ' + Object.values(c.deaths || {}).reduce((a, b) => a + b, 0) + ', úrazů ' + (c.injuries || 0) + ', svateb ' + (c.weddings || 0) + '.', 'dev-note'));
    return box;
  }));
}

function renderCrisis() {
  $('crisis').replaceChildren(...SIDES.map(([side, title]) => {
    const state = states[side], box = sideBox(side, title);
    if (!state) return box;
    if (state.crisis) {
      const alert = make('div', '', 'dev-alert');
      alert.dataset.kind = state.crisis.type;
      alert.append(make('strong', state.crisis.label), document.createTextNode(' · obrana ' + fmt(state.crisis.done) + ' z ' + state.crisis.need + ' · konec za ' + days(Math.max(0, state.crisis.end - state.minute))));
      alert.append(bar(state.crisis.done, state.crisis.need));
      box.append(alert);
    } else box.append(make('p', 'Právě žádná krize.', 'dev-line'));
    if (state.harvestUntil > state.minute) {
      const alert = make('div', '', 'dev-alert');
      alert.append(make('strong', 'Špatná úroda'), document.createTextNode(' · výnos obilí a sena ' + Math.round(state.harvestFactor * 100) + ' % ještě ' + days(state.harvestUntil - state.minute)));
      box.append(alert);
    }
    if (state.fieldBlockedUntil > state.minute) box.append(make('p', 'Pole jsou zatopená ještě ' + days(state.fieldBlockedUntil - state.minute) + '.', 'dev-line'));
    const history = (state.weekly || []).flatMap(row => (row.crises || []).map(c => ({week: row.week, ...c})));
    const current = (state.counters?.crises || []).map(c => ({week: 'probíhá', ...c}));
    const all = [...current, ...history].slice(0, 8);
    box.append(make('h3', 'Dřívější krize'));
    if (all.length) {
      const list = make('ul', '', 'dev-list');
      for (const c of all) {
        const label = {fire: 'Požár', flood: 'Povodeň', harvest: 'Špatná úroda'}[c.type] || c.type;
        list.append(make('li', label + ' · týden ' + c.week + (c.ratio !== undefined && c.type !== 'harvest' ? ' · obrana ' + Math.round(c.ratio * 100) + ' %' : '')));
      }
      box.append(list);
    } else box.append(make('p', 'Zatím žádné.', 'dev-note'));
    return box;
  }));
}

const WHY = [
  ['model', 'Model zvolil jiný úkol než pravidla a prošel'],
  ['agree', 'Model zvolil totéž co pravidla'],
  ['guard', 'Přepsala pojistka potřeb nebo krize'],
  ['food', 'Přepsala potřeba jídla'],
  ['logistics', 'Přepsala stavba nebo odvoz materiálu'],
  ['invalid', 'Model navrhl nepovolený úkol'],
  ['missing', 'Model neodpověděl'],
];

function renderInfluence() {
  const stats = states.ai?.planStats;
  const table = $('influence').tBodies[0], batches = $('batches').tBodies[0];
  if (!stats || !stats.total) {
    $('influence-note').textContent = states.ai ? 'Zatím není žádná dávka s auditem. Záznamy vznikají od prvního plánu po nasazení nové verze.' : 'AI větev je nedostupná.';
    table.replaceChildren();
    batches.replaceChildren();
    $('influence-chart').replaceChildren();
    return;
  }
  const by = stats.byWhy, total = stats.total;
  const share = key => Math.round((by[key] || 0) / total * 100);
  $('influence-note').textContent = 'Za posledních 24 herních hodin rozhodl model jinak než pravidla u ' + share('model') + ' % osob, ' +
    'shodně s pravidly u ' + share('agree') + ' % a hra přepsala nebo model neodpověděl u ' + (100 - share('model') - share('agree')) + ' %. ' +
    'Blízko nuly u prvního čísla by znamenalo, že plán AI téměř nic neovlivňuje.';
  table.replaceChildren(...WHY.filter(([key]) => by[key]).map(([key, label]) => {
    const row = make('tr');
    row.append(make('th', label), make('td', String(by[key])), make('td', share(key) + ' %'));
    row.firstChild.scope = 'row';
    return row;
  }));
  const origin = states.ai.calendar?.originDay || 0;
  const group = counts => ({
    model: counts.model || 0,
    agree: counts.agree || 0,
    override: (counts.guard || 0) + (counts.food || 0) + (counts.logistics || 0),
    fallback: (counts.invalid || 0) + (counts.missing || 0),
  });
  const totals = group(by);
  const series = [['model', 'Model změnil volbu'], ['agree', 'Shoda s pravidly'],
    ['override', 'Rozhodla hra'], ['fallback', 'Chyběl použitelný návrh']];
  const recent = stats.batches.map(batch => {
    const day = Math.floor(batch.minute / 1440) - origin + 1;
    const hour = String(Math.floor(batch.minute % 1440 / 60)).padStart(2, '0');
    return {label: day + '. den ' + hour + ':00', counts: group(batch.why)};
  });
  MinilidiCharts.audit($('influence-chart'), series.map(([key, label]) =>
    ({key, label, count: totals[key]})
  ), recent);
  batches.replaceChildren(...recent.map(batch => {
    const row = make('tr');
    row.append(make('th', batch.label), ...series.map(([key]) => make('td', String(batch.counts[key]))));
    row.firstChild.scope = 'row';
    return row;
  }));
}

function renderSkills() {
  const state = states.ai, table = $('skills'), note = $('skills-note');
  if (!state || !state.categories) {
    note.textContent = state ? 'Dovednosti se objeví po restartu hry s novou verzí.' : 'AI větev je nedostupná.';
    table.tHead.replaceChildren(); table.tBodies[0].replaceChildren();
    return;
  }
  const categories = Object.entries(state.categories);
  const people = Object.values(state.people).sort((a, b) => b.age - a.age);
  note.textContent = 'Zkušenost 0 až 100 v každém oboru. Nezkušený dělá polovinu výnosu, kolem 77 bodů plný výnos, při 100 o 15 % víc. ' +
    'Roste prací (mladí rychleji, s rodičem v oboru ještě rychleji), děti po rodičích zdědí třetinu průměru. Vesnice s AI.';
  const head = make('tr');
  head.append(make('th', 'Obyvatel'));
  head.firstChild.scope = 'col';
  for (const [, label] of categories) { const th = make('th', label); th.scope = 'col'; head.append(th); }
  table.tHead.replaceChildren(head);
  const heat = level => 'hsl(' + Math.round(40 + level * 0.9) + ', 45%, ' + Math.round(94 - level * 0.28) + '%)';
  const rows = people.map(person => {
    const row = make('tr');
    const name = make('th', person.name + ' (' + person.age + ')');
    name.scope = 'row';
    row.append(name);
    for (const [key] of categories) {
      const level = Number(person.skills?.[key] ?? 0);
      const cell = make('td', fmt(level));
      cell.style.background = heat(level);
      row.append(cell);
    }
    return row;
  });
  const best = make('tr');
  best.append(make('th', 'Nejlepší (od 14 let)'));
  best.firstChild.scope = 'row';
  for (const [key] of categories) {
    const adults = people.filter(person => person.age >= 14).map(person => [person, Number(person.skills?.[key] ?? 0)]).sort((a, b) => b[1] - a[1]);
    best.append(make('td', adults.length ? adults[0][0].name.split(' ')[0] + ' ' + fmt(adults[0][1]) : '–'));
  }
  table.tBodies[0].replaceChildren(...rows, best);
}

const PACK_EFFECTS = {food: 'jídlo', harvest: 'společná sklizeň', visit_sick: 'návštěva nemocného', mediate: 'smíření sousedů', games: 'hra v hospodě', leisure: 'obnova energie a zábavy', walk: 'nálada a zábava', study: 'rozvoj pracovní dovednosti'};

function renderPack() {
  const state = states.ai, status = state?.activityPackStatus;
  const table = $('pack').tBodies[0], note = $('pack-note');
  if (!state) { note.textContent = 'AI větev je nedostupná.'; table.replaceChildren(); return; }
  const time = ts => new Date(ts * 1000).toLocaleTimeString('cs-CZ', {hour: '2-digit', minute: '2-digit'});
  if (!status) {
    note.textContent = 'Worker zatím balíček nenačetl (první načtení proběhne po restartu hry s novou verzí).';
  } else if (status.ok) {
    note.textContent = 'Soubor ' + status.file + ' je v pořádku: ' + status.count + ' činností, naposledy zkontrolován v ' + time(status.checkedAt) +
      (status.change ? ', poslední změna v ' + time(status.loadedAt) + ' (' + status.change + ')' : '') + '. Hra ho kontroluje sama, restart není potřeba.';
  } else {
    note.textContent = 'Soubor ' + status.file + ' se nepodařilo načíst, hra používá poslední funkční katalog (' + status.count + ' činností). Chyba: ' +
      status.error + ' Zkontrolováno v ' + time(status.checkedAt) + '.';
  }
  note.classList.toggle('dev-warn', Boolean(status && !status.ok));
  table.replaceChildren(...Object.values(state.activityPack || {}).map(def => {
    const row = make('tr');
    row.append(make('th', def.label), make('td', PACK_EFFECTS[def.effect] || def.effect),
      make('td', def.from + ' až ' + def.until + ' h' + (def.season === 'any' ? '' : ', ' + def.season.toLowerCase())),
      make('td', def.age + ' let'), make('td', def.minutes + ' min'));
    row.firstChild.scope = 'row';
    return row;
  }));
}

function render() {
  const down = SIDES.filter(([side]) => !states[side]).map(([, title]) => title);
  $('status').textContent = down.length ? 'Nedostupná: ' + down.join(', ') + '. Zkouším znovu…' : '';
  renderGoals();
  renderCompare();
  renderInfluence();
  renderEconomy();
  renderLife();
  renderCrisis();
  renderSkills();
  renderPack();
}

async function load(side) {
  try {
    const response = await fetch('api.php?view=development&branch=' + side, {cache: 'no-store', signal: AbortSignal.timeout(4000)});
    return response.ok ? await response.json() : null;
  } catch { return null; }
}

async function refresh() {
  const [ai, control] = await Promise.all(SIDES.map(([side]) => load(side)));
  states.ai = ai;
  states.control = control;
  render();
}

$('week').addEventListener('change', event => { chosenWeek = event.target.value; renderCompare(); });
refresh();
setInterval(refresh, 5000);
