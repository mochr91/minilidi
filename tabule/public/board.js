'use strict';
const categories={concept:'Koncept a cíl hry',gameplay:'Herní pravidla a smyčka',world:'Svět a postavy',ai:'AI a chování',ui:'Obrazovky a ovládání',tech:'Technika a ukládání',parking:'Nápady na později'};
const statuses={idea:'Nápad',discuss:'K diskusi',agreed:'Dohodnuto',later:'Odloženo'};
const priorities={none:'Bez priority',high:'Vysoká',medium:'Střední',low:'Nízká'};
const $=id=>document.getElementById(id);
const form=$('form'), dialog=$('editor');
let items=[], view='category', editing=null;
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
options($('priority-filter'),{all:'Všechny',...priorities});
function folded(value){return value.toLocaleLowerCase('cs').normalize('NFD').replace(/\p{M}/gu,'');}
function render(){
  const q=folded($('query').value.trim()), p=$('priority-filter').value;
  const visible=items.filter(x=>(p==='all'||p===x.priority)&&folded(x.title+' '+x.body).includes(q));
  $('message').textContent=(visible.length===items.length?'Celkem: '+items.length:'Zobrazeno: '+visible.length+' z '+items.length)+' · Pracovní návrhy';
  const board=$('board');board.replaceChildren();
  for(const [key,label] of Object.entries(view==='category'?categories:statuses)){
    const list=visible.filter(x=>x[view]===key);
    if(!list.length&&(q||p!=='all'))continue;
    const lane=element('section','lane'), title=element('h2','',label);
    title.append(element('span','count',String(list.length)));lane.append(title);
    if(!list.length)lane.append(element('p','empty','Zatím tu není žádný návrh.'));
    for(const item of list){
      const card=element('article','card');
      card.append(element('h3','',item.title));
      if(item.body)card.append(element('p','',item.body));
      const footer=element('div','card-footer');
      footer.append(element('span','tag',view==='category'?statuses[item.status]:categories[item.category]));
      if(item.priority!=='none')footer.append(element('span','tag','Priorita: '+priorities[item.priority]));
      const edit=element('button','edit','Upravit');edit.type='button';
      edit.setAttribute('aria-label','Upravit: '+item.title);
      edit.addEventListener('click',()=>openEditor(item));
      footer.append(edit);card.append(footer);lane.append(card);
    }
    board.append(lane);
  }
  if(!board.childElementCount)board.append(element('p','empty','Žádný návrh neodpovídá filtru.'));
}
async function api(note){
  const response=await fetch('api.php',note?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(note)}:{cache:'no-store'});
  const value=await response.json();
  if(!response.ok)throw new Error(value.error||'Požadavek se nezdařil.');
  return value;
}
function openEditor(item){
  editing=item?.id??null;
  $('editor-title').textContent=item?'Upravit nápad':'Nový nápad';
  $('error').hidden=true;
  for(const [k,v] of Object.entries(item??{title:'',body:'',category:'concept',status:'idea',priority:'none'})){
    if(form.elements[k])form.elements[k].value=v;
  }
  dialog.showModal();
}
$('add').addEventListener('click',()=>openEditor());
$('cancel').addEventListener('click',()=>dialog.close());
for(const key of ['category','status'])$('by-'+key).addEventListener('click',()=>{
  view=key;
  for(const k of ['category','status'])$('by-'+k).setAttribute('aria-pressed',String(k===key));
  render();
});
$('query').addEventListener('input',render);
$('priority-filter').addEventListener('change',render);
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
