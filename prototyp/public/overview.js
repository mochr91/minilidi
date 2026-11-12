'use strict';

const get = id => document.getElementById(id);
const branch=new URLSearchParams(location.search).get('branch')==='control'?'control':'ai';
document.body.dataset.branch=branch;
let otherState=null;
const rounded = n => Math.round(n);
const number = new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:1});
const stockSeries = [['food','Jídlo','#8a5931'],['wood','Dřevo','#3c6848'],['stone','Kámen','#55577f']];
const growthSeries=[['population','Obyvatelé','#366247'],['houses','Nové domy','#8a5931']];
const needSeries = [['satiation','Jídlo','#8a5931'],['energy','Energie','#326576'],['fun','Zábava','#845077']];
const firstName = p => p.name.split(' ')[0];
let state = null;

function element(tag,text) {
  const node=document.createElement(tag);
  if (text!==undefined) node.textContent=text;
  return node;
}
const timeLabel=minute=>MinilidiTime.label(minute,state?.calendar?.originDay??0);

function currentNeeds(people) {
  const avg=key=>people.reduce((sum,p)=>sum+p[key],0)/Math.max(1,people.length);
  return {satiation:avg('food'),energy:avg('energy'),fun:avg('fun')};
}

function chart(canvas,legend,points,series,fixedMaximum,comparisonMaximum=0) {
  const ctx=canvas.getContext('2d');
  const width=canvas.width, height=canvas.height;
  const left=43, right=width-20, top=12, bottom=height-34;
  const first=points[0].minute, last=points.at(-1).minute;
  const observed=Math.max(1,comparisonMaximum,...points.flatMap(point=>series.map(([key])=>point[key])));
  const step=fixedMaximum?25:Math.max(1,Math.ceil(observed/4));
  const max=step*4;
  const x=minute=>last===first?(left+right)/2:left+(minute-first)/(last-first)*(right-left);
  const y=value=>bottom-value/max*(bottom-top);
  ctx.clearRect(0,0,width,height);
  ctx.font='12px Segoe UI, sans-serif';
  ctx.textAlign='right';
  ctx.fillStyle='#625e4f';
  ctx.strokeStyle='#d6cbb3';
  ctx.lineWidth=1;
  for(let i=0;i<=4;i++){
    const value=i*step;
    ctx.fillText(number.format(value),left-7,y(value)+4);
    ctx.beginPath();ctx.moveTo(left,y(value));ctx.lineTo(right,y(value));ctx.stroke();
  }
  ctx.textAlign='left';ctx.fillText(timeLabel(first),left,height-9);
  if(last!==first){ctx.textAlign='right';ctx.fillText(timeLabel(last),right,height-9);}
  legend.replaceChildren();
  for(const [key,label,color] of series){
    ctx.strokeStyle=color;ctx.fillStyle=color;ctx.lineWidth=2.5;
    ctx.beginPath();
    points.forEach((point,index)=>{const a=x(point.minute),b=y(point[key]);if(index===0)ctx.moveTo(a,b);else ctx.lineTo(a,b);});
    ctx.stroke();
    ctx.beginPath();ctx.arc(x(last),y(points.at(-1)[key]),4,0,Math.PI*2);ctx.fill();
    const row=element('li'), swatch=element('i');swatch.style.backgroundColor=color;swatch.setAttribute('aria-hidden','true');
    row.append(swatch,element('span',label+' '+number.format(points.at(-1)[key])));legend.append(row);
  }
}

function arrow(ctx,a,b,color,width,directed) {
  const angle=Math.atan2(b.y-a.y,b.x-a.x);
  const dx=Math.cos(angle),dy=Math.sin(angle);
  const x=b.x-dx*24,y=b.y-dy*24;
  ctx.strokeStyle=color;ctx.lineWidth=width;ctx.beginPath();
  ctx.moveTo(a.x+dx*23,a.y+dy*23);ctx.lineTo(x,y);ctx.stroke();
  if(directed){
    ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(x,y);
    ctx.lineTo(x-dx*10-dy*4,y-dy*10+dx*4);ctx.lineTo(x-dx*10+dy*4,y-dy*10-dx*4);ctx.fill();
  }
}

function relations(people) {
  const selected=get('relation-person').value;
  const graph=get('relation-graph');
  const ctx=graph.getContext('2d');
  const w=graph.width,h=graph.height;
  ctx.clearRect(0,0,w,h);
  const nodes=people.map((p,i)=>{
    const angle=-Math.PI/2+i*Math.PI*2/people.length;
    return {p,x:w/2+Math.cos(angle)*w*.32,y:h/2+Math.sin(angle)*h*.34};
  });
  for(let i=0;i<nodes.length;i++)for(let j=i+1;j<nodes.length;j++){
    let a=nodes[i],b=nodes[j];
    if(selected && a.p.id!==selected && b.p.id!==selected)continue;
    if(b.p.id===selected)[a,b]=[b,a];
    const forward=a.p.bonds[b.p.id]??0, backward=b.p.bonds[a.p.id]??0;
    const strength=selected?forward:(forward+backward)/2;
    arrow(ctx,a,b,'rgba(65,99,68,'+(.22+.007*strength)+')',1+strength/21,!!selected);
  }
  ctx.textAlign='center';
  for(const {p,x,y} of nodes) {
    ctx.fillStyle=p.color;ctx.beginPath();ctx.arc(x,y,22,0,Math.PI*2);ctx.fill();
    if(p.id===selected){ctx.strokeStyle='#273826';ctx.lineWidth=3;ctx.stroke();}
    ctx.fillStyle='white';ctx.font='bold 17px Segoe UI, sans-serif';ctx.fillText(firstName(p)[0],x,y+6);
    ctx.fillStyle='#27291f';ctx.font='14px Segoe UI, sans-serif';ctx.fillText(firstName(p),x,y+40);
  }
  const table=get('relation-table'), header=element('thead'), heading=element('tr'), body=element('tbody');
  const name=element('th','Postava');name.scope='col';heading.append(name);
  for(const p of people){const cell=element('th',firstName(p));cell.scope='col';heading.append(cell);}
  header.append(heading);
  for(const p of people){
    const row=element('tr');row.dataset.selected=String(p.id===selected);
    const cell=element('th',firstName(p));cell.scope='row';row.append(cell);
    for(const other of people){
      const value=p.bonds[other.id];
      row.append(element('td',typeof value==='number'?rounded(value):'–'));
    }
    body.append(row);
  }
  table.tHead?.remove();
  for(const old of [...table.tBodies])old.remove();
  table.append(header,body);
}

function render() {
  const people=Object.values(state.people);
  const minute=Math.floor(state.minute);
  get('overview-status').hidden=state.running;
  get('overview-status').textContent='Simulace je zastavená.';
  const current={minute,food:state.food,wood:state.wood,stone:state.stone,...currentNeeds(people)};
  const history=state.history.filter(sample=>sample.minute<minute);
  const points=[...history,current];
  const otherPoints=otherState?[...otherState.history,otherState]:[];
  const stockMaximum=Math.max(0,...otherPoints.flatMap(point=>stockSeries.map(([key])=>point[key])));
  chart(get('stock-chart'),get('stock-legend'),points,stockSeries,false,stockMaximum);
  chart(get('need-chart'),get('need-legend'),points,needSeries,true);
  const growthPoints=[...history.filter(p=>Number.isFinite(p.population)&&Number.isFinite(p.houses)),
    {minute,population:people.length,houses:state.homesBuilt.length}];
  chart(get('growth-chart'),get('growth-legend'),growthPoints,growthSeries,false,Math.max(7,Object.keys(otherState?.people??{}).length));
  const select=get('relation-person');
  const known=new Set([...select.options].map(option=>option.value));
  for(const p of people)if(!known.has(p.id))select.add(new Option(p.name,p.id));
  relations(people);
  const tbody=get('player-table').tBodies[0];
  tbody.replaceChildren(...people.map(p=>{
    const row=element('tr'), label=element('th',p.name);label.scope='row';
    row.append(label,...['faith','trust','fear'].map(key=>element('td',rounded(p[key]))));
    return row;
  }));
}
get('relation-person').addEventListener('change',()=>{if(state)relations(Object.values(state.people));});
async function refresh() {
  try {
    const load=side=>fetch('api.php?view=overview&branch='+side,{cache:'no-store',signal:AbortSignal.timeout(4000)});
    const [res,other]=await Promise.all([load(branch),load(branch==='ai'?'control':'ai').catch(()=>null)]);
    if(!res.ok)throw Error('Přehled je nedostupný');
    state=await res.json();
    otherState=other?.ok?await other.json():null;
    render();
  } catch {
    get('overview-status').hidden=false;
    get('overview-status').textContent='Přehled se nepodařilo načíst. Zkouším znovu…';
  }
  setTimeout(refresh,12000);
}
void refresh();
