const $ = id => document.getElementById(id);
const make = (tag, text, className) => {
  const node = document.createElement(tag);
  if (text) node.textContent = text;
  if (className) node.className = className;
  return node;
};
let sending = false;
let lastState = null;

function list(items, empty) {
  if (!items.length) return make('p', empty, 'muted');
  const ul = make('ul');
  ul.append(...items);
  return ul;
}

function village(state) {
  const panel = $(state.branch).querySelector('.faith-content');
  const people = Object.values(state.people);
  const numbers = make('div', '', 'faith-numbers');
  for (const [key, label] of [['faith','Víra'],['fear','Strach'],['trust','Důvěra']]) {
    const avg = people.reduce((sum, person) => sum + person[key], 0) / Math.max(1, people.length);
    const cell = make('div', label);
    cell.append(make('strong', String(Math.round(avg))));
    numbers.append(cell);
  }
  const groups = state.religions.map(group => {
    const item = make('li');
    item.append(make('strong', group.name), make('p',
      state.doctrines[group.doctrine] + ' · ' + group.relationToPlayer + ' · síla ' + group.strength +
      ' · ' + group.members.length + ' členů'), make('p', 'Vede ' + state.people[group.leader]?.name +
      '. Členové: ' + group.members.map(id => state.people[id]?.name).filter(Boolean).join(', ')));
    return item;
  });
  const events = state.faithEvents.slice().reverse().map(event => {
    const item = make('li');
    const hour = Math.floor(event.minute % 1440 / 60);
    const minute = String(event.minute % 60).padStart(2, '0');
    const day = Math.floor(event.minute / 1440) - state.calendar.originDay + 1;
    item.append(make('strong', event.title), make('p', day + '. den, ' + hour + ':' + minute + ' · ' + event.source + ' · ' + event.detail));
    return item;
  });
  panel.replaceChildren(numbers, make('h3', 'Hnutí'), list(groups, 'Ve vsi zatím nevzniklo žádné hnutí.'),
    make('h3', 'Znamení a obřady'), list(events, 'Kronika víry zatím nemá žádné události.'));
}

function render(ai, control) {
  lastState = ai;
  village(ai);
  village(control);
  const ready = Boolean(ai.directorVersion && control.directorVersion);
  const status = s => Math.floor(s.grace) + ' / 100' + (s.miracleAfter > s.minute ?
    ' · zbývá ' + Math.ceil(s.miracleAfter - s.minute) + ' herních minut do dalšího zázraku' : '');
  $('grace').textContent = ready ? 'Přízeň s AI ' + status(ai) + ' · bez AI ' + status(control) :
    'Nové zásahy budou dostupné po restartu simulace.';
  for (const [key, miracle] of Object.entries(ai.miracles)) {
    let button = $('miracle-' + key);
    if (!button) {
      button = make('button', miracle.label + ' · ' + miracle.cost);
      button.id = 'miracle-' + key;
      button.type = 'button';
      button.onclick = () => send('miracle', key);
      $('miracles').append(button);
    }
    button.disabled = !ready || sending || ![ai,control].some(s => s.running && s.grace >= miracle.cost && s.minute >= s.miracleAfter);
  }
  $('pause').textContent = ai.aiPaused ? 'Zapnout AI' : 'Pozastavit AI';
  $('pause').disabled = !ready || !ai.running || ai.aiForcedOff || sending;
  $('pause').onclick = () => send('ai_pause', ai.aiPaused ? '0' : '1');
  const rows = ai.modelMetrics;
  const calls = rows.filter(row => row.load_ms != null);
  const avg = calls.length ? Math.round(calls.reduce((sum,row) => sum + Number(row.load_ms),0) / calls.length) : null;
  const active = !ready ? 'Čeká na restart.' : ai.aiForcedOff ? 'AI vypnul spouštěč.' : ai.aiPaused ? 'AI pozastavena.' : 'AI připravena.';
  $('metrics').textContent = active + (rows.length ? ' V posledních ' + rows.length + ' voláních použila pravidla ' +
    rows.filter(row => Number(row.fallback)).length + '×.' + (avg === null ? '' : ' Průměr načtení ' + avg + ' ms.') : '');
  $('calls').replaceChildren(...rows.map(row => make('li', (row.type === 'plan' ? 'Plán' : 'Vnuknutí') +
    ' · ' + Math.round(row.elapsed_ms) + ' ms' + (row.tokens == null ? '' : ' · ' + row.tokens + ' tokenů') + ' · ' + row.detail)));
}

async function get(branch) {
  const response = await fetch('api.php?view=faith&branch=' + branch, {cache:'no-store'});
  if (!response.ok) throw new Error('Simulace momentálně neodpovídá.');
  return response.json();
}

async function refresh() {
  try { render(...await Promise.all([get('ai'),get('control')])); }
  catch (error) { $('feedback').textContent = error.message; }
}

async function send(kind, value) {
  if (!lastState || sending) return;
  sending = true;
  try {
    const response = await fetch('api.php', {method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({kind,value})});
    const data = await response.json();
    if (!response.ok) throw new Error(data.error);
    $('feedback').textContent = kind === 'ai_pause' ? (data.paused ? 'AI pozastavena.' : 'AI zapnuta.') :
      ['ai','control'].map(branch => (branch === 'ai' ? 'S AI' : 'Bez AI') +
        ' · ' + (data.branches[branch] || 'zázrak čeká na provedení')).join(' | ');
  } catch (error) { $('feedback').textContent = error.message; }
  finally { sending = false; await refresh(); }
}
refresh();
setInterval(refresh, 3000);
