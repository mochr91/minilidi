'use strict';
const categories={concept:'Koncept a cíl hry',gameplay:'Herní pravidla a smyčka',world:'Svět a postavy',ai:'AI a chování',ui:'Obrazovky a ovládání',tech:'Technika a ukládání',parking:'Nápady na později'};
const statuses={todo:'K zpracování',doing:'Rozpracováno',blocked:'Čeká',done:'Hotovo',idea:'Nápad',discuss:'K diskusi',agreed:'Dohodnuto',later:'Odloženo'};
const priorities={none:'Bez priority',high:'Vysoká',medium:'Střední',low:'Nízká'};
const kinds={task:'Úkol pro agenta',think:'K promyšlení',idea:'Nápad na potom',decision:'Rozhodnutí'};
const assignees={claude:'CLAUDE',codex:'CODEX',user:'JÁ',none:'Bez řešitele'};
const $=id=>document.getElementById(id);
const form=$('form'), dialog=$('editor');
let items=[], view='plan', editing=null;
function element(tag,cls,content){
  const node=document.createElement(tag);
  if(cls)node.className=cls;
  if(content!==undefined)node.textContent=content;
  return node;
}
function options(select,values){for(const [id,label] of Object.entries(values))select.add(new Option(label,id));}
options(form.elements.category,categories);
options(form.elements.status,statuses);
options(form.elements.priority,priorities);
options(form.elements.kind,kinds);
options(form.elements.assignee,assignees);
options($('priority-filter'),{all:'Všechny',...priorities});
options($('assignee-filter'),{all:'Všichni',...assignees});
function folded(value){return value.toLocaleLowerCase('cs').normalize('NFD').replace(/\p{M}/gu,'');}
const byOrder=(a,b)=>(a.block||'').localeCompare(b.block||'','cs')||(a.order-b.order)||a.title.localeCompare(b.title,'cs');
const isOpenTask=x=>x.kind==='task'&&x.status!=='done';
const isOpenThink=x=>x.kind==='think'&&x.status!=='agreed'&&x.status!=='done';
function promptFor(list){
  const lines=['Pracuješ na hře Minilidi (E:\\AI2\\Minilidi).','1. Přečti AGENTS.md a KOORDINACE.md (vlastnictví souborů, rozhraní, pravidla, testy).','2. Dělej jen to, co patří tobě podle bodu 2 handoffu; změnu cizího souboru zapiš jako žádost.',''];
  for(const x of list){
    lines.push('ÚKOL: '+x.title+(x.block?' ['+x.block+']':'')+' · řešitel: '+assignees[x.assignee]+' · id: '+x.id,'ZADÁNÍ: '+x.body);
    if(x.accept)lines.push('HOTOVO, KDYŽ: '+x.accept);
    if(x.result)lines.push('DOSAVADNÍ VÝSLEDEK: '+x.result);
    lines.push('');
  }
  lines.push('3. Po dokončení: spusť testy (KOORDINACE bod 7), zapiš výsledek do KOORDINACE.md (bod 5) a přepni kartu: POST http://127.0.0.1:8091/api.php s {"id":"<id>","patch":{"status":"done","result":"co se změnilo a jak je to ověřené"}}.');
  return lines.join('\n');
}
async function copyText(text,label){
  try{await navigator.clipboard.writeText(text);}
  catch{const area=element('textarea');area.value=text;document.body.append(area);area.select();document.execCommand('copy');area.remove();}
  flash('Zkopírováno: '+label);
}
let flashTimer=0;
function flash(text){$('message').textContent=text;clearTimeout(flashTimer);flashTimer=setTimeout(render,2500);}
async function api(note){
  const response=await fetch('api.php',note?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(note)}:{cache:'no-store'});
  const value=await response.json();
  if(!response.ok)throw new Error(value.error||'Požadavek se nezdařil.');
  return value;
}
async function patch(id,fields){
  try{items=await api({id,patch:fields});render();}
  catch(e){flash('Uložení se nezdařilo. '+e.message);}
}
function badge(text,cls){return element('span','tag'+(cls?' '+cls:''),text);}
function card(item,compact){
  const node=element('article','card kind-'+item.kind+(item.status==='done'?' is-done':''));
  const top=element('div','card-top');
  if(item.kind==='task'||item.kind==='think'){
    const select=element('select','assignee a-'+item.assignee);
    for(const [id,label] of Object.entries(assignees))select.add(new Option(label,id));
    select.value=item.assignee;
    select.setAttribute('aria-label','Řešitel: '+item.title);
    select.addEventListener('change',()=>patch(item.id,{assignee:select.value}));
    top.append(select);
  }else top.append(badge(kinds[item.kind]));
  top.append(badge(statuses[item.status],'s-'+item.status));
  if(item.priority!=='none')top.append(badge(priorities[item.priority],'p-'+item.priority));
  node.append(top,element('h3','',item.title));
  if(item.body&&!compact)node.append(element('p','body',item.body));
  else if(item.body)node.append(element('p','body short',item.body.length>170?item.body.slice(0,167)+'…':item.body));
  if(item.accept&&!compact){const a=element('p','accept');a.append(element('b','','Hotovo, když: '),document.createTextNode(item.accept));node.append(a);}
  if(item.result){const r=element('p','result');r.append(element('b','',item.kind==='think'||item.kind==='decision'?'Rozhodnutí: ':'Výsledek: '),document.createTextNode(item.result));node.append(r);}
  const footer=element('div','card-footer');
  if(item.block&&compact)footer.append(badge(item.block));
  if(item.kind==='idea'||item.kind==='decision')footer.append(badge(categories[item.category]));
  const action=(label,fn,cls)=>{const b=element('button',cls||'edit',label);b.type='button';b.addEventListener('click',fn);b.setAttribute('aria-label',label+': '+item.title);footer.append(b);return b;};
  if(item.kind==='task'&&item.status!=='done'){
    if(item.status!=='doing')action('Začít',()=>patch(item.id,{status:'doing'}),'edit go');
    action('Hotovo',()=>patch(item.id,{status:'done'}),'edit ok');
    action('Kopírovat zadání',()=>copyText(promptFor([item]),item.title),'edit');
  }
  if(item.kind==='task'&&item.status==='done')action('Znovu otevřít',()=>patch(item.id,{status:'todo'}),'edit');
  if(item.kind==='think')action('Rozhodnuto',()=>openEditor({...item,kind:'decision',status:'agreed'}),'edit ok');
  if(item.kind==='idea')action('Převést na úkol',()=>openEditor({...item,kind:'task',status:'todo'}),'edit');
  action('Upravit',()=>openEditor(item),'edit');
  node.append(footer);
  return node;
}
function section(title,hint,list,open,build){
  const wrap=element(open?'section':'details','block-section');
  if(!open&&wrap.tagName==='DETAILS'){const s=element('summary','',title);s.append(element('span','count',String(list.length)));wrap.append(s);}
  else{const h=element('h2','',title);h.append(element('span','count',String(list.length)));wrap.append(h);}
  if(hint)wrap.append(element('p','hint',hint));
  if(!list.length)wrap.append(element('p','empty','Zatím tu nic není.'));
  else build(wrap,list);
  return wrap;
}
function grid(list,compact){const g=element('div','cards');for(const x of list)g.append(card(x,compact));return g;}
function tally(list){
  const counts={};for(const x of list)counts[x.assignee]=(counts[x.assignee]||0)+1;
  return Object.entries(counts).map(([k,v])=>assignees[k]+' '+v).join(' · ');
}
function renderPlan(visible,board){
  const active=visible.filter(isOpenTask).sort(byOrder);
  board.append(section('Aktivní úkoly','Co mohou agenti programovat. Řešitele změníš přímo na kartě. Bloky jsou úkoly, které dává smysl dělat najednou.',active,true,(wrap,list)=>{
    const blocks=new Map();
    for(const x of list){const k=x.block||'Bez bloku';if(!blocks.has(k))blocks.set(k,[]);blocks.get(k).push(x);}
    for(const [name,group] of blocks){
      const head=element('div','block-head');
      head.append(element('h3','',name),element('span','tally',tally(group)));
      const copy=element('button','edit','Kopírovat blok');copy.type='button';
      copy.addEventListener('click',()=>copyText(promptFor(group),name));
      head.append(copy);
      wrap.append(head,grid(group,false));
    }
  }));
  const think=visible.filter(isOpenThink).sort(byOrder);
  board.append(section('K promyšlení (rozhoduješ ty)','Rozhodnutí, která agent neudělá za tebe. U každého je otázka a doporučení. Po rozhodnutí klikni na „Rozhodnuto“.',think,true,(wrap,list)=>wrap.append(grid(list,false))));
  board.append(section('Nápady na potom','Zatím se nedělají. Až budeš chtít, použij „Převést na úkol“.',visible.filter(x=>x.kind==='idea'),false,(wrap,list)=>wrap.append(grid(list,true))));
  board.append(section('Dohodnuto','Rozhodnutí, ze kterých se vychází.',visible.filter(x=>x.kind==='decision'||isOpenThink(x)===false&&x.kind==='think'),false,(wrap,list)=>wrap.append(grid(list,true))));
  const done=visible.filter(x=>x.kind==='task'&&x.status==='done').sort((a,b)=>(b.updated||'').localeCompare(a.updated||''));
  board.append(section('Hotovo','Dokončené úkoly, nejnovější nahoře.',done,false,(wrap,list)=>wrap.append(grid(list,true))));
}
function renderAssignee(visible,board){
  const lanes=element('div','lanes');
  for(const [key,label] of Object.entries(assignees)){
    const list=visible.filter(x=>x.assignee===key&&(isOpenTask(x)||isOpenThink(x))).sort(byOrder);
    if(key==='none'&&!list.length)continue;
    const lane=element('section','lane'), title=element('h2','',label);
    title.append(element('span','count',String(list.length)));lane.append(title);
    if(!list.length)lane.append(element('p','empty','Nic otevřeného.'));
    for(const x of list)lane.append(card(x,false));
    lanes.append(lane);
  }
  board.append(lanes);
}
function renderCategory(visible,board){
  const lanes=element('div','lanes three');
  for(const [key,label] of Object.entries(categories)){
    const list=visible.filter(x=>x.category===key).sort(byOrder);
    if(!list.length)continue;
    const lane=element('section','lane'), title=element('h2','',label);
    title.append(element('span','count',String(list.length)));lane.append(title);
    for(const x of list)lane.append(card(x,true));
    lanes.append(lane);
  }
  board.append(lanes);
}
function render(){
  const q=folded($('query').value.trim()), p=$('priority-filter').value, a=$('assignee-filter').value;
  const visible=items.filter(x=>(p==='all'||p===x.priority)&&(a==='all'||a===x.assignee)&&folded(x.title+' '+x.body+' '+x.block).includes(q));
  const open=items.filter(isOpenTask);
  $('message').textContent='Aktivní úkoly: '+open.length+(open.length?' ('+tally(open)+')':'')+' · K promyšlení: '+items.filter(isOpenThink).length+' · Nápady: '+items.filter(x=>x.kind==='idea').length+(visible.length!==items.length?' · Zobrazeno karet: '+visible.length:'');
  const board=$('board');board.replaceChildren();
  const openStates=new Set([...document.querySelectorAll('details.block-section[open] summary')].map(s=>s.firstChild?.textContent));
  if(view==='plan')renderPlan(visible,board);
  else if(view==='assignee')renderAssignee(visible,board);
  else renderCategory(visible,board);
  for(const d of board.querySelectorAll('details.block-section'))if(openStates.has(d.querySelector('summary').firstChild?.textContent))d.open=true;
  if(!board.childElementCount||!visible.length)board.append(element('p','empty','Žádná karta neodpovídá filtru.'));
  const blocks=[...new Set(items.map(x=>x.block).filter(Boolean))].sort();
  $('blocks').replaceChildren(...blocks.map(b=>{const o=document.createElement('option');o.value=b;return o;}));
}
function openEditor(item){
  editing=item?.id??null;
  $('editor-title').textContent=editing?'Upravit kartu':'Nová karta';
  $('error').hidden=true;
  const defaults={title:'',body:'',accept:'',result:'',block:'',order:0,kind:'task',assignee:'none',category:'concept',status:'todo',priority:'none'};
  for(const [k,v] of Object.entries({...defaults,...(item??{})})){
    if(form.elements[k])form.elements[k].value=v;
  }
  dialog.showModal();
}
$('add').addEventListener('click',()=>openEditor());
$('cancel').addEventListener('click',()=>dialog.close());
for(const key of ['plan','assignee','category'])$('by-'+key).addEventListener('click',()=>{
  view=key;
  for(const k of ['plan','assignee','category'])$('by-'+k).setAttribute('aria-pressed',String(k===key));
  render();
});
for(const who of ['claude','codex'])$('copy-'+who).addEventListener('click',()=>{
  const list=items.filter(x=>x.assignee===who&&isOpenTask(x)).sort(byOrder);
  if(!list.length)flash('Pro '+assignees[who]+' není otevřený úkol.');
  else copyText(promptFor(list),'všechny úkoly pro '+assignees[who]);
});
$('query').addEventListener('input',render);
$('priority-filter').addEventListener('change',render);
$('assignee-filter').addEventListener('change',render);
dialog.addEventListener('cancel',e=>{if($('save').disabled)e.preventDefault();});
form.addEventListener('submit',async e=>{
  e.preventDefault();
  const note=Object.fromEntries(new FormData(form));
  if(editing)note.id=editing;
  $('save').disabled=$('cancel').disabled=true;
  $('error').hidden=true;
  try{items=await api(note);dialog.close();render();}
  catch(e){$('error').textContent='Uložení se nezdařilo. '+e.message;$('error').hidden=false;}
  finally{$('save').disabled=$('cancel').disabled=false;}
});
api().then(data=>{items=data;$('add').disabled=false;render();})
.catch(()=>{$('message').textContent='Tabuli se nepodařilo načíst. Otevři ji přes místní server a zkus stránku obnovit.';});
