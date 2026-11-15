'use strict';

(() => {
  const list = document.querySelector('[data-village-goals]');
  if (!list) return;

  const descriptions = {
    tavern: ['Hospoda na návsi', 'Večerní setkávání sousedů.', 'Postavit pilu a nasbírat 12 dřeva, 8 kamene a 10 prken.'],
    bread: ['Vlastní chléb', 'Z obilí až k pečenému chlebu.', 'Postavit mlýn, potom chlebovou pec.'],
    timber: ['Dřevo a řemesla', 'Prkna na stavby a nářadí pro řemeslníky.', 'Postavit pilu a potom dílnu.'],
    farm: ['Rozvoj hospodářství', 'Čistá voda a lepší zázemí pro chov.', 'Postavit studnu a statek. Statek potřebuje pilu.'],
    public_buildings: ['Provozní budovy', 'Rozšířit zázemí vesnice.', 'Dokončit postupně 3, 5, 7, 10 a 13 budov.'],
    livestock: ['Chov', 'Slepice, prase, krávy a koně dají víc jídla a rychlejší svoz kamene.', 'Postavit kurník, chlív, ohradu pro krávy a stáje. Potřebují statek, stáje také pilu.'],
    faith_house: ['Hřbitov a kostel', 'Místo pro poslední rozloučení a pro společné obřady.', 'Postavit hřbitov (po hospodě) a potom kostel (potřebuje také dílnu, 500 člověkohodin).'],
    plank_stock: ['Zásoba prken', 'Materiál na další stavby.', 'Na pile zpracovat dřevo a uskladnit 10, potom 25 prken.'],
    tool_stock: ['Vybavení nářadím', 'Dostatek nářadí usnadní práci.', 'V dílně vyrábět z prken a kamene. Uskladnit 5, potom 10 kusů.'],
    homes: ['Nové domy', 'Více místa pro rodiny.', 'Na první dům dřevo a kámen, na další také prkna.'],
    pop: ['Více obyvatel', 'Vesnice roste narozením dětí a příchodem lidí.', 'Zajistit dost bydlení a obživy pro 12, 16, 20 a 30 lidí.'],
    stocks: ['Zásoba jídla', 'Rezerva na horší časy.', 'Nashromáždit potraviny na 15 herních dní.'],
    winters: ['Přežít zimu', 'Udržet vesnici přes období nedostatku.', 'Překonat bez hladovění postupně 1, 2, 3 a 5 zim.'],
  };
  const pending = new Set(['tavern', 'public_buildings', 'plank_stock', 'tool_stock']);
  const branches = [['ai', 'S AI'], ['control', 'Bez AI']];
  const format = new Intl.NumberFormat('cs-CZ', {maximumFractionDigits: 1});
  let lastRender = '';

  function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  }

  function goalProgress(label, goal, available) {
    const state = !available ? 'Nedostupné' : !goal ? 'Zatím není' : goal.complete
      ? 'Splněno' : format.format(goal.current)+' / '+format.format(goal.target);
    const group = element('span');
    group.append(element('b', label), element('small', state));
    if (goal && Number.isFinite(goal.target) && goal.target > 0 && Number.isFinite(goal.current)) {
      const progress = element('progress');
      progress.max = goal.target;
      progress.value = goal.complete ? goal.target : Math.max(0, Math.min(goal.current, goal.target));
      progress.setAttribute('aria-label', label+' · '+goal.label+' · '+state);
      group.append(progress);
    }
    return group;
  }

  function render(states) {
    const catalogs = branches.map(([side]) => {
      const goals = states[side]?.goalsState;
      return Array.isArray(goals) ? new Map(goals.map(goal => [goal.key, goal])) : null;
    });
    const anyData = catalogs.some(Boolean);
    const reported = new Set(catalogs.flatMap(catalog => catalog ? [...catalog.keys()] : []));
    const keys = anyData ? [...new Set([...Object.keys(descriptions).filter(key => reported.has(key) || pending.has(key)), ...reported])] : [];
    const signature = JSON.stringify([catalogs.map(Boolean), keys.map(key => catalogs.map(catalog => catalog?.get(key)))]);
    if (signature === lastRender) return;
    lastRender = signature;
    if (!keys.length) {
      list.replaceChildren(element('p', 'Cíle zatím nejsou dostupné.', 'village-goal-empty'));
      return;
    }
    const scroll = list.scrollTop;
    list.replaceChildren(...keys.map(key => {
      const goals = catalogs.map(catalog => catalog?.get(key));
      const [title, detail, needs] = descriptions[key] ?? [goals.find(Boolean)?.label ?? key, '', 'Dosáhnout dalšího milníku.'];
      const card = element('article', undefined, 'village-goal');
      card.append(element('h3', title));
      if (detail) card.append(element('p', detail, 'village-goal-desc'));
      card.append(element('p', needs, 'village-goal-requirement'));
      if (reported.has(key)) {
        const progress = element('div', undefined, 'village-goal-progress');
        progress.append(...branches.map(([, label], index) => goalProgress(label, goals[index], !!catalogs[index])));
        card.append(progress);
      } else {
        card.append(element('p', 'Čeká na nasazení', 'village-goal-plan'));
      }
      return card;
    }));
    list.scrollTop = scroll;
  }

  document.addEventListener('village-status', event => render(event.detail));
})();
