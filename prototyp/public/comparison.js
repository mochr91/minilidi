'use strict';
const panes=[...document.querySelectorAll('iframe')];
for (const frame of panes) {
  frame.addEventListener('load',()=>{
    const body=frame.contentDocument?.body;
    if (!body) return;
    const content=body.querySelector('main');
    const resize=()=>{
      const bottom=(content??body).getBoundingClientRect().bottom;
      frame.style.height=(Math.ceil(bottom)+12)+'px';
    };
    new ResizeObserver(resize).observe(body);
    resize();
  });
}
window.addEventListener('message',event=>{
  if (event.origin!==location.origin || event.data?.kind!=='select-villager' || typeof event.data.person!=='string') return;
  const sender=panes.find(frame=>frame.contentWindow===event.source);
  if (sender) for(const frame of panes) if(frame!==sender)frame.contentWindow.postMessage(event.data,location.origin);
});
