(() => {
  const modes = document.querySelector('[data-modes]');
  if (modes) {
    const common = [
      ['Načíst stav vesnice','Hra zohlední denní dobu, počasí, věk, jídlo, energii, vztahy, zásoby a stav rozdělané práce.'],
      ['Povolit možné činnosti','Hra sestaví nabídku pro každou postavu. Činnosti, které nesplňují podmínky věku, času, roční doby, místa či provozu, se nenabízejí.'],
      null,
      ['Prověřit a přidělit','Při přidělení může hra upřednostnit obživu, zásobování nebo krizovou práci. Při zahájení se znovu zohlední únava, naléhavá potřeba a krize.'],
      ['Odehrát a zaznamenat','Úkol může změnit zásoby, potřeby a vztahy. Výsledek jde do kroniky. Záznamy se používají i při denním shrnutí paměti postav.']
    ];
    const choices = {
      ai:['Navrhnout plán přes AI','Místní model vybírá z povolených možností ve skupinách nejvýše osmi osob. Při nečitelném výsledku může následovat jeden zkrácený pokus (nejvýše 15 sekund), celá dávka má rozpočet 80 sekund. Chybějící nebo neplatnou volbu doplní pravidla.'],
      control:['Vybrat podle pravidel','Herní pravidla ohodnotí povolené úkoly podle potřeb postavy a situace vesnice. Tato větev model nevolá. Stejný výběr slouží AI větvi jako náhrada a jako základ pro audit.']
    };
    const flow = document.getElementById('flow'), detail = document.getElementById('flow-detail');
    let mode = 'ai', selected = 2;
    function show(index) {
      selected = index;
      const [label, text] = common[index] ?? choices[mode];
      flow.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
      const h = document.createElement('h3'), p = document.createElement('p');
      h.textContent = label; p.textContent = text; detail.replaceChildren(h,p);
    }
    function render() {
      flow.replaceChildren(...common.map((entry,i) => {
        const li = document.createElement('li'), b = document.createElement('button'), num = document.createElement('small');
        num.textContent = '0' + (i + 1); b.type = 'button'; b.append(num,(entry ?? choices[mode])[0]); b.addEventListener('click', () => show(i));
        b.setAttribute('aria-controls','flow-detail'); li.append(b); return li;
      }));
      show(selected);
    }
    modes.addEventListener('click', event => {
      const b = event.target.closest('[data-mode]');
      if (!b) return;
      mode = b.dataset.mode;
      modes.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button === b)));
      selected = 2; render();
    });
    render();
  }

  const dev = document.querySelector('.dev-page');
  if (dev) {
    dev.classList.add('dash-main');
    const nav = document.createElement('nav');
    nav.className = 'dash-sections'; nav.setAttribute('aria-label','Sekce rozvoje');
    const sections = [...dev.querySelectorAll(':scope > section[aria-labelledby]')];
    const labels = ['Cíle','Srovnání','Vliv AI','Stavby a zásoby','Obyvatelé','Krize','Činnosti','Dovednosti'];
    const links = sections.map((section,i) => {
      const a = document.createElement('a');
      a.textContent = labels[i]; a.href = '#' + section.getAttribute('aria-labelledby'); nav.append(a);
      return a;
    });
    dev.querySelector('.dash-hero').after(nav);
    const show = () => {
      const requested = location.hash || '#' + (dev.dataset.defaultSection || '');
      const index = Math.max(0, links.findIndex(a => a.hash === requested));
      sections.forEach((section,i) => section.hidden = i !== index);
      links.forEach((a,i) => i === index ? a.setAttribute('aria-current','true') : a.removeAttribute('aria-current'));
    };
    window.addEventListener('hashchange',show); show();
    dev.querySelectorAll('.dev-table').forEach(table => {
      const wrap = document.createElement('div');
      wrap.className = 'dash-table-scroll'; wrap.tabIndex = 0;
      table.before(wrap); wrap.append(table);
    });
    document.querySelectorAll('.dev-panel .dev-note').forEach(p => {
      if (p.textContent.startsWith('Obilí se melou')) p.textContent = 'Mlýn mele obilí na mouku, ze které pec peče chléb. Pila řeže dřevo na prkna a dílna vyrábí nářadí. Studna pomáhá se zdravím a při požáru, statek zvyšuje výnos ze zvířat a hospoda je večerním místem setkávání. Pokácené dřevo leží u lesa a vozí se vozem, který vyrobí dílna, když se u lomu nebo lesa hromadí zboží. Milíř za šest dní vypálí dřevěné uhlí, drobná zvěř se loví v zimě a udírna ji uchová. Zahrada dává zeleninu a luštěniny (sází se na jaře, sklízí na podzim) a sklep s kyselým zelím a bylinami drží zimní zásoby, bez pestré stravy v zimě klesá nálada a přibývá nemocí. Kurník chová slepice, které žerou obilí a snášejí vejce ke sběru (vejce se rychle kazí, proto se jedí první), chlív a ohrada přidávají jídlo ze zvířat, chmelnice, sladovna a pivovar (z ječmene z pole, chmele a dřeva) vaří od sv. Michala do sv. Jiří pivo, které zraje ve sklepě a pije se v hospodě, stáje zrychlují svoz kamene, hřbitov zmírňuje smutek a kostel posiluje obřady. Stavby se počítají v člověkohodinách.';
    });
  }

  document.querySelectorAll('details[data-frame]').forEach(details => {
    function open() {
      if (!details.open || details.querySelector('iframe')) return;
      const iframe = document.createElement('iframe');
      iframe.title = 'Vztahy · ' + (details.dataset.frame === 'ai' ? 'vesnice s AI' : 'vesnice bez AI');
      iframe.src = 'prehled-panel.html?mode=relations&branch=' + details.dataset.frame;
      details.append(iframe);
    }
    details.addEventListener('toggle',open); open();
  });
})();