(() => {
  const number = new Intl.NumberFormat('cs-CZ', {maximumFractionDigits: 1});
  const branches = [['ai', 'S AI'], ['control', 'Bez AI']];
  const format = value => Number.isFinite(value) ? number.format(value) : '—';

  function element(tag, className, content) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (content !== undefined) node.textContent = content;
    return node;
  }

  function comparison({title, values, max, heading = 'h3'}) {
    const card = element('div', 'dash-chart-card');
    card.append(element(heading, 'dash-chart-label', title));
    const capacity = max ?? Math.max(1, ...Object.values(values).filter(Number.isFinite));
    for (const [key, label] of branches) {
      const row = element('div', 'dash-chart-pair');
      const track = element('div', 'dash-chart-track');
      track.setAttribute('aria-hidden', 'true');
      if (Number.isFinite(values[key])) {
        const bar = element('span', 'dash-chart-fill');
        bar.dataset.series = key;
        bar.style.width = Math.min(100, Math.max(0, values[key] / capacity * 100)) + '%';
        track.append(bar);
      }
      row.append(element('span', '', label), track, element('strong', '', format(values[key])));
      card.append(row);
    }
    return card;
  }

  function weeklyScores(container, entries) {
    if (!entries.length) {
      container.textContent = 'Graf se zobrazí po prvním dokončeném herním týdnu.';
      return;
    }
    const max = Math.max(1, ...entries.flatMap(entry => Object.values(entry.values)).filter(Number.isFinite));
    container.replaceChildren(...entries.map(entry =>
      comparison({title: 'Týden ' + entry.week, values: entry.values, max, heading: 'p'})
    ));
  }

  function audit(container, entries, batches) {
    const sum = entries.reduce((total, entry) => total + entry.count, 0);
    if (!sum) {
      container.replaceChildren();
      return;
    }
    const legend = element('ul', 'dash-chart-legend');
    entries.forEach(({label, count, key}) => {
      const item = element('li');
      const dot = element('span', 'dash-chart-dot');
      dot.dataset.series = key;
      dot.setAttribute('aria-hidden', 'true');
      item.append(dot, element('span', '', label), element('strong', '', format(count / sum * 100) + ' %'));
      legend.append(item);
    });
    function stack(counts) {
      const total = Object.values(counts).reduce((a, b) => a + b, 0);
      const track = element('div', 'dash-chart-stack');
      track.setAttribute('aria-hidden', 'true');
      if (total) entries.forEach(({key}) => {
        if (!counts[key]) return;
        const segment = element('span', 'dash-chart-fill');
        segment.dataset.series = key;
        segment.style.flex = counts[key] + ' 0 0%';
        track.append(segment);
      });
      return track;
    }
    const charts = [stack(Object.fromEntries(entries.map(entry => [entry.key, entry.count]))), legend];
    if (batches.length) {
      charts.push(element('h3', '', 'Poslední dávky'));
      const list = element('ul', 'dash-chart-batches');
      batches.forEach(batch => {
        const total = Object.values(batch.counts).reduce((a, b) => a + b, 0);
        const item = element('li');
        const track = stack(batch.counts);
        const summary = entries.map(({key, label}) => label + ' ' + (batch.counts[key] ?? 0)).join(', ');
        track.title = summary;
        item.append(element('span', '', batch.label), track, element('strong', '', format(total)));
        item.querySelector('strong').setAttribute('aria-label', 'Celkem ' + total + '. ' + summary);
        list.append(item);
      });
      charts.push(list);
    }
    container.replaceChildren(...charts);
  }

  window.MinilidiCharts = {comparison, weeklyScores, audit};
})();
