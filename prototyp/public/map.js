'use strict';

function createVillageMap(root) {
  const canvas = root.querySelector('[data-field=map]');
  const paint = canvas.getContext('2d');
  paint.imageSmoothingEnabled = false;
  const terrain = document.createElement('canvas');
  terrain.width = canvas.width;
  terrain.height = canvas.height;
  const ground = terrain.getContext('2d');

  const COLS = 29;
  const ROWS = 25;
  const HALF_W = 23;
  const HALF_H = 11;
  const project = (x, y) => [Math.round(600 + (x - y) * HALF_W), Math.round(52 + (x + y) * HALF_H)];
  const foot = (x, y) => project(x + .5, y + .5);
  const plots = [[14,5],[13,7]];
  const homes = { Kovářovi:[7.5,6.7],Doležalovi:[12,6.2],Královi:[7.9,11.5],Novákovi:[14.7,6],Veselí:[13.7,8],Hruškovi:[6.7,10.9] };
  const places = { yard: [7.2, 8.2], market: [10.3, 8.7], forest: [3.9, 8.5], field: [12.9, 12.4], river: [15.6, 10.4], pasture: [6.3, 16.2], quarry: [24.6, 11.5], well: [10.5,8.8], building: [14.5,5.8], chapel: [9,4], barn: [12,10] };
  const houses = [
    [7, 6, 'tile', 'home'], [12, 5, 'tile', 'home'], [7, 11, 'thatch', 'home'],
    [6, 8, 'thatch', 'barn'], [12, 10, 'thatch', 'barn'], [10, 6, 'tile', 'workshop'],
    [6, 10, 'tile', 'home'], [17, 9, 'thatch', 'barn'], [10, 10, 'tile', 'home'], [9, 4, 'slate', 'chapel'],
  ];
  const scene = [];
  const noise = (x, y) => ((x * 19349663 ^ y * 83492791) >>> 0) % 997;

  function shape(ctx, color, points) {
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(...points[0]);
    for (const point of points.slice(1)) ctx.lineTo(...point);
    ctx.closePath();
    ctx.fill();
  }

  function rect(ctx, color, x, y, w, h) {
    ctx.fillStyle = color;
    ctx.fillRect(x, y, w, h);
  }

  function landmark(x, y, draw) {
    const [sx, sy] = foot(x, y);
    scene.push({ x: sx, y: sy, draw });
  }

  function tile(x, y, color) {
    const [a, b] = project(x, y);
    shape(ground, color, [[a,b],[a+HALF_W,b+HALF_H],[a,b+2*HALF_H],[a-HALF_W,b+HALF_H]]);
  }

  const riverBank = y => y < 5 ? 16 : y < 12 ? 17 : y < 16 ? 16 : y < 20 ? 15 : 14;
  const inBox = (x,y,left,top,right,bottom) => x>=left && x<=right && y>=top && y<=bottom;

  function cropTile(a, b, ripe) {
    for (let row = -1; row <= 1; row++) {
      const ox = row * 7;
      const oy = -row * 3;
      shape(ground, '#75543a', [[a+ox-8,b+oy-4],[a+ox-5,b+oy-5],[a+ox+9,b+oy+3],[a+ox+6,b+oy+4]]);
      for (let i = 0; i < 3; i++) {
        rect(ground, ripe ? '#c6a650' : '#758441', a+ox-7+i*6, b+oy-5+i*3, 3, 3);
      }
    }
  }

  function drawGround(snowy=false) {
    const grass = snowy?['#d0dbd4','#c0cdc4','#b7c6bc','#d9e0d5','#c9d1c7']:['#788e55','#83995d','#819458','#8a9c60','#778b52'];
    const water = ['#4e7882','#547f86','#5b8790','#5f8b91'];
    rect(ground,snowy?'#9aa9a0':'#68794f',0,0,canvas.width,canvas.height);
    for (let y = 0; y < ROWS; y++) {
      for (let x = 0; x < COLS; x++) {
        const n = noise(x,y);
        const bank = riverBank(y);
        const wet = x === bank || x === bank+1;
        const bridge = wet && y === 8;
        const field = inBox(x,y,11,12,15,15) || inBox(x,y,4,13,7,15) || inBox(x,y,17,14,19,16);
        const road = (y===8 && x>=2 && x<=25) || (x===25 && y>=8 && y<=11) || (x===9 && y>=3 && y<=17) ||
          (y===6 && x>=7 && x<=14) || (y===11 && x>=7 && x<=10);
        const stoneGround = inBox(x,y,23,9,27,13);
        const square = inBox(x,y,9,8,11,9);
        const color = snowy&&!wet&&!road&&!square&&!stoneGround ? grass[n%5] : bridge ? '#956e45' : wet ? water[n%4] : square ? '#beab80' :
          road ? ['#bca270','#b99c68'][n%2] : stoneGround ? ['#99978b','#88877c'][n%2] : field ? ['#9a7047','#a57a50'][n%2] : grass[n%5];
        tile(x,y,color);
        const [a,b] = foot(x,y);
        if (bridge) {
          for (let i = -3; i <= 3; i++) rect(ground,'#cba574',a+i*5-3,b+Math.abs(i)*2-4,7,2);
        } else if (wet) {
          rect(ground,'#85adb0',a-9+n%5,b-3,8,1);
          rect(ground,'#67989c',a+3,b+4,7,1);
        } else if (stoneGround) {
          rect(ground,'#b7b2a4',a-7+n%5,b-3,5,3);
          rect(ground,'#6f7066',a+4,b+3,4,2);
        } else if (snowy && !road && !square) {
          rect(ground,'#e8eee7',a-8+n%7,b-2,5,2);
        } else if (field) {
          cropTile(a,b,x>8);
        } else if (road || square) {
          rect(ground,'#d2b98a',a-7+n%5,b-2,3,2);
          rect(ground,'#a08761',a+5,b+3,3,1);
        } else {
          rect(ground,'#a2ac69',a-8+n%7,b-2,4,2);
          rect(ground,'#667d49',a+6,b+3,3,2);
          if (n%13===0) rect(ground,'#d9cd87',a-5,b+4,2,2);
        }
      }
    }
  }

  function house(ctx, x, y, roof, kind) {
    const roofColors = {
      tile: ['#b46c4c','#914d3b','#704035'],
      thatch: ['#c6a46a','#ab8655','#826543'],
      slate: ['#777d80','#5e6568','#434d50'],
    };
    const [lit, mid, dark] = roofColors[roof];
    const tall = kind === 'chapel' ? 11 : 0;
    const shadow = [[x-29,y-2],[x-8,y-11],[x+31,y+3],[x+11,y+13]];
    shape(ctx,'#626644',shadow);
    shape(ctx,'#dbc69a',[[x-23,y-4],[x,y+8],[x,y-18-tall],[x-23,y-30-tall]]);
    shape(ctx,'#ae946a',[[x,y+8],[x+24,y-4],[x+24,y-31-tall],[x,y-18-tall]]);
    shape(ctx,'#756344',[[x-23,y-7],[x,y+5],[x+24,y-7],[x+24,y-4],[x,y+8],[x-23,y-4]]);
    shape(ctx,'#634f38',[[x-21,y-9],[x-18,y-8],[x-18,y-25-tall],[x-21,y-26-tall]]);
    shape(ctx,'#634f38',[[x-4,y+4],[x-1,y+5],[x-1,y-17-tall],[x-4,y-19-tall]]);
    shape(ctx,'#6e573c',[[x-14,y+1],[x-7,y+5],[x-7,y-9],[x-14,y-12]]);
    rect(ctx,'#efe2a5',x+8,y-15,7,6);
    rect(ctx,'#675744',x+11,y-15,1,6);
    shape(ctx,lit,[[x-28,y-28-tall],[x-12,y-46-tall],[x+12,y-58-tall],[x-4,y-39-tall]]);
    shape(ctx,mid,[[x-12,y-46-tall],[x+12,y-58-tall],[x+29,y-34-tall],[x+5,y-22-tall]]);
    shape(ctx,dark,[[x-28,y-28-tall],[x-12,y-46-tall],[x+5,y-22-tall],[x+1,y-17-tall]]);
    for (let i=0;i<4;i++) {
      const dx = i*5;
      shape(ctx,dark,[[x-6+dx,y-40-tall-dx/2],[x-5+dx,y-40-tall-dx/2],[x+7+dx,y-24-tall-dx/2],[x+6+dx,y-24-tall-dx/2]]);
    }
    if (kind === 'chapel') {
      rect(ctx,'#514d3d',x+8,y-76,3,14);
      rect(ctx,'#514d3d',x+4,y-73,11,3);
    } else if (kind !== 'barn') {
      rect(ctx,'#784c42',x+12,y-52,6,14);
      rect(ctx,'#a66f57',x+11,y-54,8,3);
    }
    if (kind === 'workshop') {
      rect(ctx,'#70503a',x+24,y-20,2,14);
      rect(ctx,'#493f37',x+24,y-19,9,6);
    }
  }

  function quarry(ctx,x,y) {
    shape(ctx,'#6b6a60',[[x-41,y+2],[x-33,y-27],[x-14,y-45],[x+16,y-50],[x+38,y-36],[x+44,y-10],[x+16,y+11],[x-14,y+12]]);
    shape(ctx,'#a6a49a',[[x-33,y-27],[x-14,y-45],[x+16,y-50],[x+38,y-36],[x+15,y-32],[x-12,y-35]]);
    shape(ctx,'#85847c',[[x-36,y-14],[x-13,y-27],[x+17,y-27],[x+39,y-19],[x+27,y-7],[x-9,y-11]]);
    shape(ctx,'#a9a497',[[x-41,y+2],[x-11,y-11],[x+28,y-7],[x+44,y-10],[x+16,y+11],[x-14,y+12]]);
    rect(ctx,'#5d4935',x-10,y-30,22,24);
    rect(ctx,'#302e2a',x-6,y-26,14,20);
    rect(ctx,'#ba9670',x-11,y-31,24,4);
    rect(ctx,'#94734f',x-11,y-28,4,23);
    rect(ctx,'#94734f',x+9,y-28,4,23);
    shape(ctx,'#66645f',[[x-29,y-5],[x-21,y-12],[x-15,y-7],[x-17,y],[x-25,y+1]]);
    shape(ctx,'#77736b',[[x+18,y+4],[x+24,y-4],[x+35,y-5],[x+39,y+1],[x+31,y+7]]);
    rect(ctx,'#d4c8aa',x-24,y-8,4,2);
  }

  function tree(ctx,x,y,variant) {
    const height = 31 + variant%3*5;
    const a = variant%2 ? '#3c6040' : '#315342';
    const b = variant%2 ? '#67834a' : '#4f7448';
    shape(ctx,'#64764a',[[x-12,y],[x+2,y-5],[x+17,y+1],[x+2,y+6]]);
    rect(ctx,'#644b35',x-2,y-15,5,15);
    shape(ctx,a,[[x-13,y-9],[x-14,y-19],[x-7,y-height+4],[x+1,y-height],[x+11,y-height+7],[x+16,y-15],[x+11,y-8],[x-2,y-5]]);
    shape(ctx,b,[[x-12,y-15],[x-8,y-height+7],[x,y-height+2],[x+7,y-height+8],[x+4,y-16],[x-4,y-11]]);
    rect(ctx,'#779454',x-5,y-height+10,5,3);
    rect(ctx,a,x+4,y-17,5,4);
  }

  function stall(ctx,x,y,awning) {
    shape(ctx,'#776447',[[x-13,y-1],[x,y-7],[x+14,y],[x,y+6]]);
    rect(ctx,'#654d32',x-11,y-24,2,24);
    rect(ctx,'#654d32',x+9,y-24,2,24);
    rect(ctx,'#92734a',x-10,y-9,20,7);
    shape(ctx,awning,[[x-14,y-18],[x-9,y-27],[x+12,y-27],[x+15,y-18]]);
    rect(ctx,'#e0c48b',x-6,y-26,4,8);
    rect(ctx,'#e0c48b',x+4,y-26,4,8);
    rect(ctx,'#ab844c',x-7,y-11,6,3);
    rect(ctx,'#8f573e',x+3,y-11,5,3);
  }

  function well(ctx,x,y) {
    shape(ctx,'#7b7770',[[x-10,y-4],[x,y-9],[x+11,y-4],[x+10,y+2],[x,y+6],[x-9,y+2]]);
    shape(ctx,'#383c38',[[x-7,y-4],[x,y-8],[x+8,y-4],[x,y]]);
    rect(ctx,'#604b36',x-8,y-21,2,17);
    rect(ctx,'#604b36',x+6,y-21,2,17);
    shape(ctx,'#9a623f',[[x-12,y-19],[x-6,y-28],[x+5,y-28],[x+12,y-19]]);
  }

  function fence(ctx,x,y) {
    rect(ctx,'#6f5635',x-9,y-10,2,11);
    rect(ctx,'#6f5635',x+8,y-3,2,11);
    shape(ctx,'#a48456',[[x-8,y-7],[x+9,y],[x+9,y+3],[x-8,y-4]]);
  }

  function livestock(ctx,x,y) {
    rect(ctx,'#697447',x-8,y-1,15,4);
    rect(ctx,'#c2b298',x-8,y-9,12,7);
    rect(ctx,'#7f6855',x+3,y-8,5,5);
    rect(ctx,'#695745',x-6,y-3,2,4);
    rect(ctx,'#695745',x+1,y-3,2,4);
    rect(ctx,'#f4ddaf',x-6,y-9,5,2);
  }

  drawGround();
  for (const [x,y,roof,kind] of houses) landmark(x,y,(ctx,a,b)=>house(ctx,a,b,roof,kind));
  for (let y=1;y<ROWS-1;y++) {
    for (let x=1;x<COLS-1;x++) {
      const n = noise(x,y);
      const forest = (x<5 && y!==8 && y<13) || (y<4 && x<15 && x!==9) || (x>18 && y<7) || (x<4 && y>15) || (x>20 && y>16) || (x<11 && y>19);
      const orchard = x>=5 && x<=7 && y>=3 && y<=4 && (x+y)%2===0;
      if ((forest && n%5<2) || orchard) landmark(x,y,(ctx,a,b)=>tree(ctx,a,b,n));
    }
  }
  landmark(25,10,quarry);
  landmark(10.4,8.2,well);
  landmark(11.3,8.8,(ctx,a,b)=>stall(ctx,a,b,'#a85746'));
  landmark(9.8,9.3,(ctx,a,b)=>stall(ctx,a,b,'#777f57'));
  for (let x=4;x<=7;x++) landmark(x,16.2,fence);
  landmark(5,16.8,livestock);
  landmark(6.2,17.2,livestock);
  landmark(16,10,(ctx,a,b)=>{
    shape(ctx,'#b78c58',[[a-12,b-3],[a-3,b-8],[a+21,b+3],[a+12,b+8]]);
    rect(ctx,'#705139',a+11,b+4,3,7);
  });

  const ink = '#322b27';
  const baseSprite = [
    '...oooo...', '..ohhhho..', '..ohHhho..', '..osssso..',
    '..oseeso..', '..osnsso..', '...osso...', '..oCCCCo..',
    '.oLlCClLo.', '.osCCCCso.', '.osbbbbso.', '..oCCdCo..',
    '..oCddCo..', '..oPPPoo..', '..oP.oPo..', '..oo.ooo..',
  ];
  const looks = {
    karel: {
      colors:{h:'#5c4030',H:'#a4794e',C:'#88613e',L:'#b2814f',l:'#c99a65',d:'#b8a36a',P:'#514436',b:'#594034'},
      rows:{1:'..oHHHHo..',2:'.ohhhhho..',5:'..osnhho..',6:'...ohho...'},
    },
    karolina: {
      colors:{h:'#533325',H:'#986344',C:'#7d4e69',L:'#a67288',l:'#b78498',d:'#ead1a5',P:'#624456',b:'#68474f'},
      rows:{1:'..ohhHho..',2:'.ohHhhhho.',5:'..osnsho..',6:'...osho...',8:'.oLlddlLo.',9:'.osCddCso.',10:'.osddddso.'},
    },
    tomas: {
      colors:{h:'#8a603c',H:'#bd8f59',C:'#976f40',L:'#c29459',l:'#d0a875',d:'#8b513b',P:'#625242',b:'#5d4737'},
      rows:{0:'..........',1:'...oooo...',2:'..ohHhho..',7:'..oCCdCo..'},
    },
    marek: {
      colors:{h:'#634331',H:'#a77753',C:'#4f7161',L:'#789581',l:'#9bac8a',d:'#baa16f',P:'#474b43',b:'#574636'},
      rows:{1:'..oHHHho..',2:'..ohhhho..',8:'.oLlCdlLo.',9:'.osCCdCso.'},
    },
    tereza: {
      colors:{h:'#6b4636',H:'#a8795a',C:'#446b89',L:'#6993a9',l:'#8bafbd',d:'#d5be8f',P:'#424e68',b:'#69544b'},
      rows:{1:'..ohhHho..',2:'.ohHhhhho.',5:'..osnsho..',6:'...osho...',8:'.oLlddlLo.',11:'..oCddCo..'},
    },

    anna: {
      colors: { h:'#945b3f',H:'#d1a06f',C:'#9e4839',L:'#c56954',l:'#d58569',d:'#e4c693',P:'#665044',b:'#6c4936' },
      rows: { 1:'..oHHHHo..',2:'.oHHhHHo..',8:'.oLlddlLo.',9:'.osCddCso.',10:'.osddddso.',11:'..oddddo..' },
    },
    matyas: {
      colors: { h:'#60452f',H:'#a77749',C:'#326582',L:'#528eaa',l:'#72a4b8',d:'#c09d55',P:'#4f4d58',b:'#805e3d' },
      rows: { 0:'..........',1:'...oooo...',2:'..oHhhho..',8:'.oLlCdlLo.',9:'.osCCdCso.',10:'.osbbdbso.' },
    },
    jan: {
      colors: { h:'#59402e',H:'#9c7147',C:'#7d5438',L:'#aa754e',l:'#c28d60',d:'#694430',P:'#50473d',b:'#46332b' },
      rows: { 1:'..oHHHHo..',2:'.ohhhhho..',5:'..osnhho..',6:'...ohho...',8:'.oLlCClLo.',11:'..oCddCo..' },
    },
    bara: {
      colors: { h:'#624334',H:'#a97652',C:'#45694b',L:'#74945e',l:'#9fb176',d:'#c0bda0',P:'#4e5545',b:'#635240' },
      rows: { 1:'..oddddo..',2:'.oddHdddo.',5:'..osnsdo..',6:'...osdo...',8:'.oLldClLo.',11:'..oCddCo..' },
    },
    katerina: {
      colors: { h:'#a89c89',H:'#ddd1b9',C:'#705176',L:'#99739c',l:'#ae88ad',d:'#e0ceb2',P:'#4b424b',b:'#56403b' },
      rows: { 1:'..oddddo..',2:'.oddHhddo.',4:'..oseeno..',5:'..osnsdo..',6:'...oddo...',8:'.oLlddlLo.',9:'.osddddso.' },
    },
  };
  function personSprite(person) {
    const scale=person.age<12?2:3;
    const look = looks[person.id];
    const palette = {
      o:ink,s:'#dfa777',e:'#3e2c24',n:'#ba7857',
      h:'#614737',H:'#a57956',C:person.color,L:person.color,l:'#d4a273',d:'#d8b68b',P:'#51453a',b:'#654931',
      ...look?.colors,
    };
    const rows = baseSprite.map((row,index)=>look?.rows[index]??row);
    const sprite = document.createElement('canvas');
    sprite.width = 10*scale;
    sprite.height = rows.length*scale;
    const ctx = sprite.getContext('2d');
    rows.forEach((row,y)=>[...row].forEach((code,x)=>{
      if (palette[code]) rect(ctx,palette[code],x*scale,y*scale,scale,scale);
    }));
    return sprite;
  }

  function wheelbarrow(ctx,x,y) {
    rect(ctx,'#3d352b',x-12,y-8,7,8);
    rect(ctx,'#a1855b',x-10,y-6,3,4);
    shape(ctx,'#856343',[[x-14,y-14],[x+4,y-14],[x+8,y-7],[x-10,y-7]]);
    rect(ctx,'#ba8b5e',x-14,y-14,19,3);
    rect(ctx,'#77746c',x-10,y-18,7,5);
    rect(ctx,'#a7a39a',x-3,y-17,7,5);
    rect(ctx,'#69513a',x+4,y-10,12,2);
  }
  function workProp(ctx,x,y,place,time) {
    const shift=Math.floor(time/200)%2;
    if (place==='river') {
      shape(ctx,'#513e2c',[[x+11,y-14],[x+24,y-25],[x+25,y-24],[x+12,y-12]]);
      rect(ctx,'#b1a181',x+25,y-24,1,18);
      rect(ctx,'#e3d1a8',x+24,y-6,3,2);
    } else if (place==='field' || place==='forest' || place==='quarry' || place==='building') {
      const top=y-27-shift*2;
      shape(ctx,'#694f36',[[x+9,y-10],[x+19,top],[x+21,top+1],[x+11,y-9]]);
      rect(ctx,'#aaa395',x+16,top-2,9,4);
    } else if (place==='pasture') {
      rect(ctx,'#74593f',x+12,y-8,9,7);
      rect(ctx,'#d6b47d',x+12,y-9,9,2);
    }
  }
  function wagonPosition(minute,task) {
    const road=[[24.6,11.5],[25.4,8.5],[17.6,8.5],[10.8,8.5]];
    const progress=Math.min(2.999,Math.max(0,(minute-task.start)/(task.end-task.start)*3));
    const segment=Math.floor(progress), t=progress-segment;
    return road[segment].map((value,axis)=>value+(road[segment+1][axis]-value)*t);
  }

  const portraits = new Map();
  let sprites = [];
  let selectedSprite = '';
  let needsPaint = true;
  let homeCount = 0;
  let snowTheme = false;
  let nightTheme = false;
  let wetTheme = false;

  function setMap(people, selected, minute=0, built=0, weather={}, calendar={}) {
    const snow=weather.ground==='snow';
    const night=calendar.hour<calendar.sunrise || calendar.hour>=calendar.sunset;
    const wet=weather.precip==='rain'||weather.precip==='sleet';
    if(snow!==snowTheme){snowTheme=snow;drawGround(snow);needsPaint=true;}
    if(night!==nightTheme||wet!==wetTheme){nightTheme=night;wetTheme=wet;needsPaint=true;}
    if(built!==homeCount)needsPaint=true;
    homeCount=built;
    if (selectedSprite !== selected) needsPaint = true;
    selectedSprite = selected;
    const previous = new Map(sprites.map(sprite=>[sprite.id,sprite]));
    const byPlace = new Map();
    const present = Object.values(people).filter(person=>person.place!=='away');
    if (present.length !== sprites.length) needsPaint = true;
    sprites = present.map(person=>{
      const task=person.task && person.task.start<=minute ? person.task : null;
      const cart=task?.action==='haul_stone';
      const plot=plots[task?.plot];
      const other=task?.other?people[task.other]:null;
      const location=cart?wagonPosition(minute,task):plot&&task.action==='build_house'?[plot[0]+.5,plot[1]+.8]:
        person.place==='home'?homes[other?.family??person.family]:places[person.place];
      const key = cart?person.id:person.place==='home'?(other?.family??person.family):person.place;
      const slot = byPlace.get(key)??0;
      byPlace.set(key,slot+1);
      const [tx,ty] = project((location??places.market)[0],(location??places.market)[1]);
      const column=slot%5, row=Math.floor(slot/5);
      const offset=[0,-30,31,-53,53][column];
      const x=tx+offset+row*12;
      const y=ty+Math.floor(column/2)*11+row*23;
      const old = previous.get(person.id);
      const sleeping = person.activity==='Spí';
      const prop=task||person.place===person.work?person.place:'';
      if (!old || old.tx!==x || old.ty!==y || old.sleeping!==sleeping || old.cart!==cart || old.prop!==prop) needsPaint=true;
      if (!portraits.has(person.id)) portraits.set(person.id,personSprite(person));
      return { id:person.id, art:portraits.get(person.id), sleeping,cart,prop, x:old?.x??x, y:old?.y??y, tx:x, ty:y, moving:false };
    });
  }

  function drawPerson(ctx,sprite,time) {
    const x=Math.round(sprite.x);
    const y=Math.round(sprite.y);
    const bob=sprite.moving ? Math.floor(time/125)%2*2 : 0;
    const selected=sprite.id===selectedSprite;
    shape(ctx,selected?'#f8dc91':'#455238',[[x-15,y],[x,y-5],[x+15,y],[x,y+5]]);
    if(sprite.cart)wheelbarrow(ctx,x+21,y);
    else workProp(ctx,x,y,sprite.prop,time);
    ctx.drawImage(sprite.art,x-sprite.art.width/2,y-sprite.art.height-bob);
    if (selected) {
      const top=y-sprite.art.height-bob;
      shape(ctx,'#f8dc91',[[x-5,top-9],[x+5,top-9],[x,top-3]]);
    }
    if (sprite.sleeping) {
      rect(ctx,'#fff0ba',x+12,y-47,8,2);
      rect(ctx,'#fff0ba',x+15,y-45,3,4);
      rect(ctx,'#fff0ba',x+11,y-41,8,2);
    }
  }

  function renderMap(time) {
    let moving = false;
    for (const sprite of sprites) {
      sprite.moving = Math.abs(sprite.tx-sprite.x)+Math.abs(sprite.ty-sprite.y)>.7;
      if (sprite.moving) {
        sprite.x += (sprite.tx-sprite.x)*.055;
        sprite.y += (sprite.ty-sprite.y)*.055;
        moving = true;
      }
    }
    if (moving || needsPaint) {
      paint.setTransform(view.zoom,0,0,view.zoom,view.x,view.y);
      paint.drawImage(terrain,0,0);
      const newHomes=plots.slice(0,homeCount).map(([px,py])=>{
        const [x,y]=foot(px,py);
        return {x,y,draw:(ctx,a,b)=>house(ctx,a,b,'tile','home')};
      });
      const layers = [...scene,...newHomes,...sprites].sort((a,b)=>a.y-b.y);
      for (const item of layers) {
        if (item.art) drawPerson(paint,item,time);
        else item.draw(paint,item.x,item.y);
      }
      paint.setTransform(1,0,0,1,0,0);
      if(nightTheme)rect(paint,'rgba(25,39,64,.25)',0,0,canvas.width,canvas.height);
      if(wetTheme){
        paint.strokeStyle='rgba(185,211,214,.35)';paint.lineWidth=1.3;paint.beginPath();
        for(let i=0;i<85;i++){const x=(i*97+31)%canvas.width,y=(i*53+7)%canvas.height;paint.moveTo(x,y);paint.lineTo(x-7,y+12);}
        paint.stroke();
      }
    }
    needsPaint = moving;
    requestAnimationFrame(renderMap);
  }
  requestAnimationFrame(renderMap);

  const view = { zoom:1, x:0, y:0 };
  const zoomOut = root.querySelector('[data-field=map-zoom-out]');
  const zoomIn = root.querySelector('[data-field=map-zoom-in]');
  const resetView = root.querySelector('[data-field=map-reset]');
  const zoomValue = root.querySelector('[data-field=map-zoom-level]');

  function moveView(x,y) {
    view.x = Math.max(canvas.width*(1-view.zoom),Math.min(0,x));
    view.y = Math.max(canvas.height*(1-view.zoom),Math.min(0,y));
    needsPaint = true;
  }

  function zoomTo(zoom,x=canvas.width/2,y=canvas.height/2) {
    const next = Math.max(1,Math.min(3.5,zoom));
    const ratio = next/view.zoom;
    view.zoom = next;
    moveView(x-(x-view.x)*ratio,y-(y-view.y)*ratio);
    zoomValue.textContent=Math.round(next*100)+' %';
    zoomOut.disabled=next===1;
    zoomIn.disabled=next===3.5;
    resetView.disabled=next===1;
  }

  function canvasPoint(event) {
    const box = canvas.getBoundingClientRect();
    return [(event.clientX-box.left)*canvas.width/box.width,(event.clientY-box.top)*canvas.height/box.height];
  }

  function findSprite(event) {
    const [screenX,screenY] = canvasPoint(event);
    const x = (screenX-view.x)/view.zoom;
    const y = (screenY-view.y)/view.zoom;
    return [...sprites].sort((a,b)=>b.y-a.y).find(sprite=>
      Math.abs(sprite.x-x)<=sprite.art.width/2+2 && y>=sprite.y-sprite.art.height-4 && y<=sprite.y+6);
  }

  zoomOut.addEventListener('click',()=>zoomTo(view.zoom/1.25));
  zoomIn.addEventListener('click',()=>zoomTo(view.zoom*1.25));
  resetView.addEventListener('click',()=>zoomTo(1));
  canvas.addEventListener('wheel',event=>{
    event.preventDefault();
    if (event.deltaY) zoomTo(view.zoom*(event.deltaY<0?1.16:1/1.16),...canvasPoint(event));
  },{passive:false});
  canvas.addEventListener('keydown',event=>{
    const arrows = { ArrowLeft:[45,0], ArrowRight:[-45,0], ArrowUp:[0,45], ArrowDown:[0,-45] };
    if (arrows[event.key] && view.zoom>1) {
      event.preventDefault();
      const [x,y]=arrows[event.key];
      moveView(view.x+x,view.y+y);
    } else if (['+','=','-','0'].includes(event.key)) {
      event.preventDefault();
      zoomTo(event.key==='0'?1:event.key==='-'?view.zoom/1.25:view.zoom*1.25);
    }
  });

  let drag = null;
  let dragged = false;
  canvas.addEventListener('pointerdown',event=>{
    if (event.button!==0 || !event.isPrimary) return;
    dragged=false;
    if (view.zoom<=1) return;
    drag={id:event.pointerId, origin:canvasPoint(event), x:view.x, y:view.y};
    canvas.setPointerCapture(event.pointerId);
  });
  canvas.addEventListener('pointermove',event=>{
    if (drag?.id===event.pointerId) {
      const [x,y]=canvasPoint(event);
      const dx=x-drag.origin[0], dy=y-drag.origin[1];
      if (Math.hypot(dx,dy)>5) dragged=true;
      if (dragged) moveView(drag.x+dx,drag.y+dy);
    }
    canvas.style.cursor=dragged && drag?'grabbing':findSprite(event)?'pointer':view.zoom>1?'grab':'default';
  });
  for (const name of ['pointerup','pointercancel','lostpointercapture']) {
    canvas.addEventListener(name,event=>{
      if (drag?.id===event.pointerId) drag=null;
    });
  }
  canvas.addEventListener('click',event=>{
    if (dragged) { dragged=false; return; }
    const sprite=findSprite(event);
    if(sprite)canvas.dispatchEvent(new CustomEvent('villager',{detail:sprite.id}));
  });
  return setMap;
}
