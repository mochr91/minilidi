'use strict';

function createVillageMap(root) {
  const canvas = root.querySelector('[data-field=map]');
  const paint = canvas.getContext('2d');
  paint.imageSmoothingEnabled = false;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const terrain = document.createElement('canvas');
  terrain.width = canvas.width;
  terrain.height = canvas.height;
  const ground = terrain.getContext('2d');

  // Dlaždice jsou od X0 do X1 a od Y0 do Y1. Původní vesnice (0 až 29 × 0 až 25) zůstala na svých souřadnicích,
  // mapa se rozrostla na všechny strany (sever a západ: lesy, východ: kostel a hřbitov, jih: chov).
  const X0 = -2, X1 = 34, Y0 = -2, Y1 = 30;
  const COLS = X1 - X0;
  const ROWS = Y1 - Y0;
  const HALF_W = 23;
  const HALF_H = 11;
  const ORIGIN_X = 761;
  const ORIGIN_Y = 96;
  const centerX = (X0 + X1) / 2;
  const centerY = (Y0 + Y1) / 2;
  const screenCenterX = ORIGIN_X + (centerX - centerY) * HALF_W;
  const screenCenterY = ORIGIN_Y + (centerX + centerY) * HALF_H;
  // Otáčení mapy je zatím vypnuté (jediný pohled); kód zůstává, zapne se přepsáním na true a vrácením tlačítek.
  const ROTATION_ENABLED = false;
  let rotation = 0;
  function quarterTurn(x, y, turns) {
    switch (turns) {
      case 1: return [-y, x];
      case 2: return [-x, -y];
      case 3: return [y, -x];
      default: return [x, y];
    }
  }
  function project(x, y) {
    const [u, v] = quarterTurn(x - centerX, y - centerY, rotation);
    return [Math.round(screenCenterX + (u - v) * HALF_W), Math.round(screenCenterY + (u + v) * HALF_H)];
  }
  const foot = (x, y) => project(x + .5, y + .5);
  const tileCorners = (x, y) => [project(x,y),project(x+1,y),project(x+1,y+1),project(x,y+1)];
  // --- Rozložení vesnice ---
  // Každá budova stojí na obdélníku dlaždic [x, y, šířka, hloubka]; domy a ostatní budovy se kreslí uvnitř něj.
  // Osa x jde doprava dolů, osa y doleva dolů. Řeka teče zhruba podél x = 16 až 17 (viz riverBank).
  const FOOT = {
    mlyn:[15,9,2,1], pec:[8,6,1,1], pila:[2,13,2,1], dilna:[6,11,1,1], studna:[10,9,1,1], statek:[8,12,2,1],
    hospoda:[12,6,2,1], kurnik:[8,17,2,2], chliv:[11,17,2,2], ohrada:[3,18,4,3], staje:[7,23,3,2],
    hrbitov:[23,2,3,3], kostel:[20,3,2,2], zahrada:[0,15,3,2], sklep:[0,18,2,2], udirna:[0,21,2,2], milir:[0,2,2,2],
    chmelnice:[3,22,3,3], sladovna:[0,23,2,2], pivovar:[17,22,2,2],
    kovarna:[20,22,2,2], dul:[22,12,2,2], vinice:[21,17,3,3], lis:[23,21,2,2],
    spychar:[19,10,2,2], mlekarna:[7,26,2,2], rybnik:[27,18,4,3], kozeluzna:[16,26,2,2], tkalcovna:[12,25,2,2], fara:[24,5,2,2],
  };
  const centerOf = key => { const [x,y,w,d]=FOOT[key]; return [x+w/2,y+d/2]; };
  const buildingSpots = Object.fromEntries(Object.keys(FOOT).map(key=>[key,centerOf(key)]));
  const buildingKinds = { pec:['tile','home'], dilna:['tile','workshop'] };
  const HOME_TILES = { Kovářovi:[2,6], Doležalovi:[4,6], Hruškovi:[6,6], Novákovi:[15,6], Královi:[2,11], Veselí:[4,11],
    Hrubí:[6,13], Sedláčkovi:[4,13], Mlynářovi:[12,12] };
  const homes = Object.fromEntries(Object.entries(HOME_TILES).map(([family,[x,y]])=>[family,[x+.5,y+1.4]]));
  const plots = [[14,11],[12,12],[8,3],[13,3]];
  // Postavené domy a domky (dum1 až dum4) jsou na stavebních parcelách; lidé bydlí v domě `home`.
  plots.forEach(([x,y],i)=>{ homes['dum'+(i+1)]=[x+.5,y+1.4]; });
  const places = { yard:[5,9.3], market:[10.5,9.8], forest:[1.2,9.3], field:[13,14.6], river:[15.6,12.1], pasture:[7.4,19.5],
    quarry:[24.6,11.5], well:[10.5,10.3], building:[13,4.6], chapel:[11.5,4.9], barn:[8.5,13.6], mill:[15.5,10.5],
    oven:[8.5,7.5], sawmill:[3,14.5], workshop:[6.5,12.5], tavern:[13,7.5],
    hops:[4.5,21.2], maltings:[1,25.3], brewery:[18,24.6],
    forge:[21,24.6], mine:[23,14.4], vineyard:[22.5,20.3], press:[24,23.6],
    granary:[21,12.4], dairy:[8,28.3], pond:[29,21.5], tannery:[17,28.3], weaver:[13,27.3], parsonage:[25,7.3] };
  const ROADS = [[2,8,25,8],[10,3,10,28],[21,5,21,8],[25,8,25,11],[10,28,24,28]];
  const BRIDGE_ROWS = [8,28];
  const FIELDS = [[11,13,15,15],[4,15,8,16],[18,14,20,16]];
  const onRoad = (x,y) => ROADS.some(([x0,y0,x1,y1])=>x>=x0 && x<=x1 && y>=y0 && y<=y1);
  const inSquare = (x,y) => x>=8 && x<=12 && y>=9 && y<=10;
  const onField = (x,y) => FIELDS.some(([x0,y0,x1,y1])=>x>=x0 && x<=x1 && y>=y0 && y<=y1);
  const HOUSE_SCALE = .74;
  const PERSON_SCALE = .8;
  // Dlaždice, kam nepatří stromy: budovy s okolím, domy, plochy pro nové domy, kaple, rybářská chýše.
  const reserved = new Set();
  const reserve = (x0,y0,x1,y1) => { for (let y=y0-1;y<=y1+1;y++) for (let x=x0-1;x<=x1+1;x++) reserved.add(x+','+y); };
  for (const [x,y,w,d] of Object.values(FOOT)) reserve(x,y,x+w-1,y+d-1);
  for (const [x,y] of [...Object.values(HOME_TILES),...plots,[11,3],[16,11]]) reserve(x,y,x,y);
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
    scene.push({ tileX:x, tileY:y, draw });
  }

  function tile(x, y, color) {
    shape(ground, color, tileCorners(x,y));
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
    for (let y = Y0; y < Y1; y++) {
      for (let x = X0; x < X1; x++) {
        const n = noise(x,y);
        const bank = riverBank(y);
        const wet = x === bank || x === bank+1;
        const bridge = wet && BRIDGE_ROWS.includes(y);
        const field = onField(x,y);
        const road = onRoad(x,y);
        const stoneGround = inBox(x,y,23,9,27,13);
        const square = inSquare(x,y);
        const color = snowy&&!wet&&!road&&!square&&!stoneGround ? grass[n%5] : bridge ? '#956e45' : wet ? water[n%4] : square ? '#beab80' :
          road ? ['#bca270','#b99c68'][n%2] : stoneGround ? ['#99978b','#88877c'][n%2] : field ? ['#9a7047','#a57a50'][n%2] : grass[n%5];
        tile(x,y,color);
        const [a,b] = foot(x,y);
        if (bridge) {
          for (let i = -3; i <= 3; i++) {
            const [plankX,plankY]=project(x+.5+i/9,y+.5);
            rect(ground,'#cba574',plankX-3,plankY-2,7,2);
          }
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
    if (trySprite(ctx,'props','quarry',x,y,animationTime,12)) return;
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
    if (trySprite(ctx,'trees','tree_'+variant%3,x,y,animationTime) || trySprite(ctx,'trees','tree',x,y,animationTime)) return;
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
    if (trySprite(ctx,'props',awning==='#a85746'?'stall_red':'stall_green',x,y,animationTime,6)) return;
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



  // --- Vyměnitelné obrázky (pixel art) ---
  // Složka public/sprites/ obsahuje PNG podle druhu: buildings/<klíč>.png, houses/<název>.png, trees/<název>.png, props/<název>.png.
  // Server (sprites.php) vrací seznam souborů s časem změny; mapa je každé 4 s znovu zkontroluje, takže stačí soubor přepsat
  // a hra obrázek vymění sama bez restartu a bez načtení stránky. Chybějící obrázek nahradí původní kresba z kódu.
  //  - Varianty: <název>_snow.png se použije za sněhu, <název>@N.png je vodorovný pás N snímků (animace, výchozí 4 snímky/s).
  //  - Kotva: spodní střed obrázku leží na předním rohu základny budovy (u domů, stromů a rekvizit na jejich patě).
  //  - Výchozí měřítko je 2 (jeden bod obrázku = 2×2 body mapy); upravit lze v sprites/manifest.json ("defaults" a "sprites").
  const SPRITE_URL = 'sprites/';
  const SPRITE_DEFAULTS = { scale:2, fps:4 };
  const spriteIndex = new Map();      // 'kind/name' -> { file, mtime, entry }
  let spriteManifest = { defaults:{}, sprites:{} };
  let spriteManifestRaw = '{}';
  function spriteMeta(id) {
    return { ...SPRITE_DEFAULTS, ...(spriteManifest.defaults??{}), ...(spriteManifest.sprites?.[id]??{}) };
  }
  function loadSpriteFile(id,file,mtime,frames) {
    const slot = spriteIndex.get(id) ?? {};
    if (slot.mtime===mtime && slot.file===file) return;
    const img = new Image();
    img.onload = () => {
      const meta = spriteMeta(id);
      const count = Math.max(1,frames);
      const fw = Math.round(img.naturalWidth/count), fh = img.naturalHeight;
      slot.entry = { img, frames:count, fw, fh, scale:meta.scale, fps:meta.fps,
        ax:meta.anchor?.[0]??fw/2, ay:meta.anchor?.[1]??fh, ox:meta.offset?.[0]??0, oy:meta.offset?.[1]??0 };
      needsPaint = true;
    };
    img.onerror = () => { slot.entry = null; };
    slot.file = file; slot.mtime = mtime; slot.entry = null;
    spriteIndex.set(id,slot);
    img.src = SPRITE_URL+file+'?v='+mtime;
  }
  async function refreshSprites() {
    try {
      const response = await fetch('sprites.php',{cache:'no-store'});
      if (!response.ok) return;
      const data = await response.json();
      const manifestKey = JSON.stringify(data.manifest??{});
      const manifestChanged = manifestKey!==spriteManifestRaw;
      if (manifestChanged) { spriteManifestRaw = manifestKey; spriteManifest = { defaults:{}, sprites:{}, ...(data.manifest??{}) }; }
      const seen = new Set();
      for (const [file,mtime] of Object.entries(data.files??{})) {
        const match = /^(buildings|houses|trees|props)\/([a-z0-9_]+?)(?:@(\d+))?\.png$/.exec(file);
        if (!match) continue;
        const id = match[1]+'/'+match[2];
        seen.add(id);
        if (manifestChanged) spriteIndex.delete(id);
        loadSpriteFile(id,file,mtime,Number(match[3]??1));
      }
      for (const id of [...spriteIndex.keys()]) if (!seen.has(id)) { spriteIndex.delete(id); needsPaint = true; }
    } catch (error) { /* bez serveru sprite zůstávají jak jsou */ }
  }
  function spriteEntry(kind,name) {
    const winter = snowTheme ? spriteIndex.get(kind+'/'+name+'_snow')?.entry : null;
    return winter ?? spriteIndex.get(kind+'/'+name)?.entry ?? null;
  }
  // Nakreslí obrázek; (x, y) je bod, na kterém stojí spodní střed (kotva).
  function drawSpriteEntry(ctx,entry,x,y,time) {
    const frame = entry.frames>1 && !reducedMotion.matches ? Math.floor(time*entry.fps/1000)%entry.frames : 0;
    ctx.drawImage(entry.img,frame*entry.fw,0,entry.fw,entry.fh,
      Math.round(x-entry.ax*entry.scale+entry.ox),Math.round(y-entry.ay*entry.scale+entry.oy),entry.fw*entry.scale,entry.fh*entry.scale);
  }
  // Přední roh základny budovy podle jejího půdorysu; střed půdorysu je bod (x, y).
  const frontCorner = key => { const [,,w,d]=FOOT[key]; return (w+d)*HALF_H/2; };
  function trySprite(ctx,kind,name,x,y,time,lift=0) {
    const entry = spriteEntry(kind,name);
    if (!entry) return false;
    drawSpriteEntry(ctx,entry,x,y+lift,time);
    return true;
  }
  refreshSprites();
  setInterval(refreshSprites,4000);
  drawGround();
  const STATIC_HOMES = ['Kovářovi','Doležalovi','Hruškovi','Novákovi','Královi','Veselí'];
  const homeRoofs = { Kovářovi:'tile', Doležalovi:'tile', Hruškovi:'thatch', Novákovi:'thatch', Královi:'tile', Veselí:'thatch' };
  for (const family of STATIC_HOMES) {
    const [x,y]=HOME_TILES[family];
    landmark(x,y,(ctx,a,b)=>{ if (!trySprite(ctx,'houses','home_'+homeRoofs[family],a,b,animationTime,HALF_H)) scaled(ctx,a,b,HOUSE_SCALE,(c,p,q)=>house(c,p,q,homeRoofs[family],'home')); });
  }
  landmark(11,3,(ctx,a,b)=>{ if (!trySprite(ctx,'houses','chapel',a,b,animationTime,HALF_H)) scaled(ctx,a,b,.8,(c,p,q)=>house(c,p,q,'slate','chapel')); });
  for (let y=Y0+1;y<Y1-1;y++) {
    for (let x=X0+1;x<X1-1;x++) {
      const n = noise(x,y);
      const outside = x<2 || y<1 || x>31 || y>28;
      const wet = x===riverBank(y) || x===riverBank(y)+1;
      // Paseky: kostel s hřbitovem na východě, dvůr s chovem na jihu; řeka, silnice a lom zůstávají bez stromů.
      const cleared = reserved.has(x+','+y) || onRoad(x,y) || inSquare(x,y) || onField(x,y) || wet ||
        inBox(x,y,23,9,27,13) || inBox(x,y,19,2,26,7) || inBox(x,y,2,16,14,24);
      const orchard = x>=3 && x<=5 && y>=2 && y<=3 && (x+y)%2===0;
      const grove = outside ? n%5<3 : n%29===0;
      if (!cleared && (grove || orchard)) landmark(x,y,(ctx,a,b)=>tree(ctx,a,b,n));
    }
  }
  landmark(25,10,quarry);
  landmark(8.2,10,(ctx,a,b)=>scaled(ctx,a,b,.85,(c,p,q)=>stall(c,p,q,'#a85746')));
  landmark(12,10,(ctx,a,b)=>scaled(ctx,a,b,.85,(c,p,q)=>stall(c,p,q,'#777f57')));
  landmark(16,11,fishingHut);

  function fishingHut(ctx,x,y) {
    if (trySprite(ctx,'props','fishing_hut',x,y,animationTime,5)) return;
    const dock = [[16.5,11.23],[18.15,11.23],[18.15,11.77],[16.5,11.77]].map(p=>project(...p));
    shape(ctx,'#654b36',dock.map(([a,b])=>[a,b+4]));
    shape(ctx,'#b78c58',dock);
    for (let i=0;i<9;i++) {
      const u=16.55+i*.18;
      line(ctx,'#79563b',1,project(u,11.25),project(u,11.75));
    }
    for (const v of [11.23,11.77]) {
      const [a,b]=project(18.07,v);
      rect(ctx,'#654b36',a-1,b-4,3,12);
    }
    shape(ctx,'#99744d',[[x-17,y-20],[x,y-12],[x,y+5],[x-17,y-3]]);
    shape(ctx,'#6d513b',[[x,y-12],[x+16,y-20],[x+16,y-3],[x,y+5]]);
    for (let i=0;i<3;i++) line(ctx,'#664e39',1,[x-17,y-17+i*5],[x,y-9+i*5]);
    shape(ctx,'#3d382c',[[x-10,y-13],[x-4,y-10],[x-4,y+3],[x-10,y]]);
    rect(ctx,'#d9b367',x+5,y-12,6,5);
    shape(ctx,'#435a52',[[x-21,y-18],[x-10,y-34],[x+9,y-42],[x+22,y-24],[x+1,y-13]]);
    shape(ctx,'#6d8170',[[x-21,y-18],[x-10,y-34],[x+9,y-42],[x-2,y-26]]);
    line(ctx,'#a8a27f',2,[x+11,y-13],[x+17,y-7]);
  }

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
  function personSprite(person, step=0) {
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
      if (!palette[code]) return;
      const footLift = y>=14 && step && (x<5 ? step===1 : step===2) ? scale : 0;
      rect(ctx,palette[code],x*scale,y*scale-footLift,scale,scale);
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
    const swing=Math.sin(time/180);
    if (place==='river') {
      const tip=y-25+Math.round(swing*4);
      shape(ctx,'#513e2c',[[x+11,y-14],[x+24,tip],[x+25,tip+1],[x+12,y-12]]);
      line(ctx,'#b1a181',1,[x+25,tip],[x+27,y-5]);
      rect(ctx,'#e3d1a8',x+26,y-5,3,2);
    } else if (place==='field' || place==='forest' || place==='quarry' || place==='building') {
      const top=y-29+Math.round(swing*7);
      shape(ctx,'#694f36',[[x+9,y-10],[x+19,top],[x+21,top+1],[x+11,y-9]]);
      rect(ctx,'#aaa395',x+16,top-2,9,4);
    } else if (place==='pasture') {
      rect(ctx,'#74593f',x+12,y-8,9,7);
      rect(ctx,'#d6b47d',x+12,y-9,9,2);
    }
  }
  function line(ctx,color,width,from,to) {
    ctx.strokeStyle=color;
    ctx.lineWidth=width;
    ctx.beginPath();
    ctx.moveTo(...from);
    ctx.lineTo(...to);
    ctx.stroke();
  }


  function chimneySmoke(ctx,x,y,time) {
    const driftX=windDirection()[0];
    for(let i=0;i<5;i++) {
      const age=(time/1900+i/5)%1;
      const drift=age*(12+windSpeed*.8)*driftX/23;
      const size=3+Math.floor(age*8);
      rect(ctx,`rgba(211,208,186,${(1-age)*.43})`,Math.round(x+drift),Math.round(y-age*31),size,size-1);
    }
  }


  // --- Nové budovy (kurník, chlív, ohrada, stáje, hřbitov, kostel) ---
  // iso(x,y,i,j,z): bod posunutý o i dlaždic doprava dolů, j dlaždic doleva dolů a z bodů nahoru od středu podstavy.
  const iso=(x,y,i,j,z=0)=>[Math.round(x+(i-j)*HALF_W),Math.round(y+(i+j)*HALF_H-z)];
  function isoBox(ctx,x,y,ha,hb,h,left,right,top,z0=0) {
    const A=iso(x,y,-ha,-hb,z0),B=iso(x,y,ha,-hb,z0),C=iso(x,y,ha,hb,z0),D=iso(x,y,-ha,hb,z0);
    const up=p=>[p[0],p[1]-h];
    shape(ctx,left,[D,C,up(C),up(D)]);
    shape(ctx,right,[C,B,up(B),up(C)]);
    if (top) shape(ctx,top,[up(A),up(B),up(C),up(D)]);
  }
  function ridgeRoof(ctx,x,y,ha,hb,z,rise,over,front,gable,back) {
    const oa=ha+over,ob=hb+over;
    const A=iso(x,y,-oa,-ob,z),B=iso(x,y,oa,-ob,z),C=iso(x,y,oa,ob,z),D=iso(x,y,-oa,ob,z);
    const R1=iso(x,y,-oa,0,z+rise),R2=iso(x,y,oa,0,z+rise);
    shape(ctx,back,[A,B,R2,R1]);
    shape(ctx,gable,[C,B,R2]);
    shape(ctx,front,[D,C,R2,R1]);
    line(ctx,gable,2,R1,R2);
  }
  function wallL(ctx,color,x,y,hb,i0,i1,z0,z1) {
    shape(ctx,color,[iso(x,y,i0,hb,z0),iso(x,y,i1,hb,z0),iso(x,y,i1,hb,z1),iso(x,y,i0,hb,z1)]);
  }
  function wallR(ctx,color,x,y,ha,j0,j1,z0,z1) {
    shape(ctx,color,[iso(x,y,ha,j0,z0),iso(x,y,ha,j1,z0),iso(x,y,ha,j1,z1),iso(x,y,ha,j0,z1)]);
  }
  function groundPatch(ctx,color,x,y,i0,j0,i1,j1) {
    shape(ctx,color,[iso(x,y,i0,j0),iso(x,y,i1,j0),iso(x,y,i1,j1),iso(x,y,i0,j1)]);
  }
  function fenceRun(ctx,x,y,a,b,height=9,rail='#a48456',post='#6f5635') {
    const n=Math.max(1,Math.round(Math.hypot(b[0]-a[0],b[1]-a[1])/.45));
    const p0=iso(x,y,a[0],a[1]),p1=iso(x,y,b[0],b[1]);
    line(ctx,rail,2,[p0[0],p0[1]-height+2],[p1[0],p1[1]-height+2]);
    line(ctx,rail,2,[p0[0],p0[1]-4],[p1[0],p1[1]-4]);
    for (let k=0;k<=n;k++) {
      const p=iso(x,y,a[0]+(b[0]-a[0])*k/n,a[1]+(b[1]-a[1])*k/n);
      rect(ctx,post,p[0]-1,p[1]-height,2,height+1);
    }
  }
  function stoneWall(ctx,x,y,a,b,height=8) {
    const p0=iso(x,y,a[0],a[1]),p1=iso(x,y,b[0],b[1]);
    shape(ctx,'#a9a497',[p0,p1,[p1[0],p1[1]-height],[p0[0],p0[1]-height]]);
    line(ctx,'#cfcabb',2,[p0[0],p0[1]-height],[p1[0],p1[1]-height]);
    line(ctx,'#7d7a6f',1,[p0[0],p0[1]-1],[p1[0],p1[1]-1]);
  }
  function hen(ctx,x,y,flip=1) {
    const s=flip;
    rect(ctx,'#d9cfb5',x-4*s-(s<0?2:0),y-8,2,4);
    rect(ctx,'#f1ead6',x-3,y-6,7,4);
    rect(ctx,'#f1ead6',x+(s>0?3:-5),y-9,3,3);
    rect(ctx,'#c9482f',x+(s>0?4:-4),y-10,2,1);
    rect(ctx,'#e0a63a',x+(s>0?6:-7),y-8,2,1);
    rect(ctx,'#c98a2f',x-1,y-2,1,2);
    rect(ctx,'#c98a2f',x+2,y-2,1,2);
  }
  function pig(ctx,x,y,flip=1) {
    const f=v=>flip>0?v:-v;
    rect(ctx,'#e7a3a0',x-7,y-7,14,6);
    rect(ctx,'#cf8886',x-7,y-3,14,2);
    rect(ctx,'#e7a3a0',flip>0?x+6:x-11,y-8,5,5);
    rect(ctx,'#d98a88',flip>0?x+10:x-13,y-6,3,3);
    rect(ctx,'#cf8886',flip>0?x+6:x-9,y-10,3,2);
    rect(ctx,'#b86f6d',x-6,y-1,2,3);
    rect(ctx,'#b86f6d',x+4,y-1,2,3);
    rect(ctx,'#cf8886',x+f(-9)-(flip>0?0:1),y-7,2,1);
  }
  function cow(ctx,x,y,flip=1) {
    const hx=flip>0?x+9:x-15;
    rect(ctx,'#f3efe4',x-9,y-12,18,8);
    rect(ctx,'#4a3b32',x-6,y-12,5,4);
    rect(ctx,'#4a3b32',x+2,y-9,5,4);
    rect(ctx,'#ddd6c6',x-9,y-6,18,2);
    rect(ctx,'#3d332c',x-8,y-4,2,6);
    rect(ctx,'#3d332c',x-3,y-4,2,6);
    rect(ctx,'#3d332c',x+3,y-4,2,6);
    rect(ctx,'#3d332c',x+7,y-4,2,6);
    rect(ctx,'#f3efe4',hx,y-14,6,7);
    rect(ctx,'#d9a79a',flip>0?hx+4:hx-1,y-9,3,3);
    rect(ctx,'#e9dcb5',hx+(flip>0?1:4),y-16,1,2);
    rect(ctx,'#4a3b32',hx+(flip>0?0:5),y-14,2,3);
    rect(ctx,'#3d332c',flip>0?x-10:x+9,y-12,1,7);
    rect(ctx,'#e6a8a0',x+1,y-4,3,2);
  }
  function horse(ctx,x,y,flip=1) {
    const s=flip>0?1:-1;
    rect(ctx,'#8b5a36',x-10,y-17,20,8);
    rect(ctx,'#6f4527',x-10,y-10,20,2);
    for (const dx of [-9,-5,3,7]) rect(ctx,'#6f4527',x+dx,y-9,2,9);
    shape(ctx,'#8b5a36',s>0?[[x+7,y-17],[x+12,y-27],[x+16,y-26],[x+13,y-15]]:[[x-7,y-17],[x-12,y-27],[x-16,y-26],[x-13,y-15]]);
    rect(ctx,'#8b5a36',s>0?x+13:x-21,y-28,8,4);
    rect(ctx,'#5d3b24',s>0?x+19:x-22,y-26,3,3);
    rect(ctx,'#2f241d',s>0?x+10:x-13,y-28,3,11);
    rect(ctx,'#2f241d',s>0?x-12:x+10,y-17,2,10);
    rect(ctx,'#2f241d',s>0?x+15:x-17,y-28,1,1);
  }
  function hayBale(ctx,x,y) {
    shape(ctx,'#d7b95e',[[x-9,y],[x,y-5],[x+9,y],[x,y+5]]);
    rect(ctx,'#e6cb75',x-9,y-8,18,8);
    rect(ctx,'#b79a43',x-9,y-4,18,1);
    rect(ctx,'#b79a43',x-3,y-8,1,8);
  }
  function trough(ctx,x,y,len=14) {
    rect(ctx,'#5d4630',x-len/2,y-5,len,5);
    rect(ctx,'#4f6b73',x-len/2+2,y-5,len-4,2);
    rect(ctx,'#7a5c3b',x-len/2,y-6,len,1);
  }
  function henhouse(ctx,x,y) {
    groundPatch(ctx,'#a99467',x,y,-.55,.35,.95,1.05);
    for (const [i,j] of [[-.3,-.18],[.3,-.18],[-.3,.2],[.3,.2]]) {
      const p=iso(x,y,i,j);
      rect(ctx,'#5f4a30',p[0]-1,p[1]-7,3,8);
    }
    isoBox(ctx,x,y,.4,.3,13,'#cfa96f','#a07c4c','#d9b77f',6);
    wallL(ctx,'#3b2f26',x,y,.3,.06,.2,7,13);
    wallR(ctx,'#e0c58f',x,y,.4,-.05,.15,8,12);
    ridgeRoof(ctx,x,y,.4,.3,19,9,.1,'#9b5a3f','#7e4733','#6c3d2c');
    shape(ctx,'#8a6a43',[iso(x,y,.06,.3,7),iso(x,y,.2,.3,7),iso(x,y,.26,.9),iso(x,y,.12,.9)]);
    for (const t of [.25,.5,.75]) line(ctx,'#5f4a30',1,iso(x,y,.1+t*.1,.3+t*.6,7-t*7),iso(x,y,.22+t*.1,.3+t*.6,7-t*7));
    hen(ctx,...iso(x,y,.55,.65),-1);
    hen(ctx,...iso(x,y,-.25,.9),1);
    fenceRun(ctx,x,y,[-.55,1.05],[.95,1.05],8);
    fenceRun(ctx,x,y,[.95,1.05],[.95,.35],8);
  }
  function pigsty(ctx,x,y) {
    groundPatch(ctx,'#7c6246',x,y,-.65,.3,.95,1.2);
    groundPatch(ctx,'#5f7378',x,y,.1,.55,.55,.85);
    isoBox(ctx,x,y,.5,.32,14,'#b79a68','#8f7549','#c8ad7a');
    wallL(ctx,'#2f261f',x,y,.32,-.25,.05,0,9);
    ridgeRoof(ctx,x,y,.5,.32,14,9,.1,'#c6a46a','#826543','#ab8655');
    trough(ctx,...iso(x,y,.8,.4),12);
    pig(ctx,...iso(x,y,-.1,.85),1);
    pig(ctx,...iso(x,y,.5,1.0),-1);
    fenceRun(ctx,x,y,[-.65,1.2],[.95,1.2],8);
    fenceRun(ctx,x,y,[.95,1.2],[.95,.3],8);
    fenceRun(ctx,x,y,[-.65,.3],[-.65,1.2],8);
  }
  function cowPen(ctx,x,y) {
    groundPatch(ctx,'#728a4e',x,y,-1.7,-1.2,1.7,1.2);
    groundPatch(ctx,'#85704c',x,y,.55,-.95,1.45,-.25);
    for (let k=0;k<9;k++) {
      const p=iso(x,y,-1.5+(k*37%30)/10,-1+(k*53%20)/10);
      rect(ctx,k%2?'#97ad62':'#5f7b44',p[0],p[1]-1,3,2);
    }
    fenceRun(ctx,x,y,[-1.7,-1.2],[1.7,-1.2],10);
    fenceRun(ctx,x,y,[-1.7,-1.2],[-1.7,1.2],10);
    hayBale(ctx,...iso(x,y,-1.25,-.7));
    trough(ctx,...iso(x,y,1.0,-.55),16);
    cow(ctx,...iso(x,y,-.5,.15),1);
    cow(ctx,...iso(x,y,.35,-.25),-1);
    cow(ctx,...iso(x,y,.85,.55),1);
    fenceRun(ctx,x,y,[-1.7,1.2],[-.35,1.2],10);
    fenceRun(ctx,x,y,[.35,1.2],[1.7,1.2],10);
    fenceRun(ctx,x,y,[1.7,1.2],[1.7,-1.2],10);
  }
  function stable(ctx,x,y) {
    groundPatch(ctx,'#a58f62',x,y,-1.2,.5,1.4,1.25);
    isoBox(ctx,x,y,.95,.5,24,'#8d6b46','#6e5338','#9b7a50');
    for (let i=-.85;i<.9;i+=.22) line(ctx,'#6b5034',1,iso(x,y,i,.5,1),iso(x,y,i,.5,23));
    wallL(ctx,'#2c241e',x,y,.5,-.1,.55,0,17);
    line(ctx,'#c0a06b',2,iso(x,y,-.1,.5,17),iso(x,y,.55,.5,17));
    line(ctx,'#c0a06b',2,iso(x,y,-.1,.5,0),iso(x,y,-.1,.5,17));
    line(ctx,'#c0a06b',2,iso(x,y,.55,.5,0),iso(x,y,.55,.5,17));
    wallR(ctx,'#2c241e',x,y,.95,-.12,.12,9,19);
    wallL(ctx,'#d7b95e',x,y,.5,-.75,-.5,13,20);
    ridgeRoof(ctx,x,y,.95,.5,24,15,.12,'#a9814f','#7a5b36','#6b4f2f');
    rect(ctx,'#8b5a36',...iso(x,y,.2,.5,17).map((v,k)=>k?v-1:v-5),9,9);
    rect(ctx,'#2f241d',...iso(x,y,.2,.5,17).map((v,k)=>k?v+1:v-3),2,7);
    hayBale(ctx,...iso(x,y,1.35,.55));
    horse(ctx,...iso(x,y,-.35,.95),1);
    trough(ctx,...iso(x,y,.85,1.0),12);
    fenceRun(ctx,x,y,[-1.2,1.25],[1.4,1.25],9);
    fenceRun(ctx,x,y,[1.4,1.25],[1.4,.5],9);
  }
  function gravestone(ctx,x,y,kind) {
    shape(ctx,'#7a6a4c',[[x-5,y],[x,y-2],[x+5,y],[x,y+2]]);
    if (kind===1) {
      rect(ctx,'#6f5538',x-1,y-13,2,13);
      rect(ctx,'#6f5538',x-4,y-10,8,2);
    } else if (kind===2) {
      rect(ctx,'#bdb9ab',x-1,y-12,3,12);
      rect(ctx,'#bdb9ab',x-4,y-9,9,3);
      rect(ctx,'#8a877c',x+1,y-12,1,12);
    } else {
      rect(ctx,'#a7a498',x-3,y-10,7,10);
      rect(ctx,'#a7a498',x-2,y-11,5,1);
      rect(ctx,'#8a877c',x+2,y-10,2,10);
    }
  }
  function graveyard(ctx,x,y) {
    groundPatch(ctx,'#6f8a5a',x,y,-1.5,-1.1,1.5,1.1);
    groundPatch(ctx,'#b8aa8a',x,y,-.16,-1.1,.16,1.1);
    stoneWall(ctx,x,y,[-1.5,-1.1],[1.5,-1.1],9);
    stoneWall(ctx,x,y,[-1.5,-1.1],[-1.5,1.1],9);
    tree(ctx,...iso(x,y,-1.15,-.85),3);
    const graves=[[-1.05,-.6,0],[-.55,-.6,2],[.55,-.6,1],[1.05,-.6,0],[-1.05,.05,1],[-.55,.05,0],[.55,.05,0],[1.05,.05,2],[-.85,.65,2],[.85,.65,1]];
    for (const [i,j,kind] of graves.sort((a,b)=>a[0]+a[1]-b[0]-b[1])) gravestone(ctx,...iso(x,y,i,j),kind);
    stoneWall(ctx,x,y,[-1.5,1.1],[-.28,1.1],9);
    stoneWall(ctx,x,y,[.28,1.1],[1.5,1.1],9);
    stoneWall(ctx,x,y,[1.5,1.1],[1.5,-1.1],9);
    for (const i of [-.3,.3]) {
      const p=iso(x,y,i,1.1);
      rect(ctx,'#9b978a',p[0]-3,p[1]-17,6,18);
      rect(ctx,'#cfcabb',p[0]-4,p[1]-19,8,3);
    }
    const top=iso(x,y,0,1.1,17);
    rect(ctx,'#3f3b35',top[0]-1,top[1]-9,2,10);
    rect(ctx,'#3f3b35',top[0]-4,top[1]-6,8,2);
  }
  // --- Budovy vesnice: kreslí se do svého obdélníku dlaždic (FOOT) ---
  function scaled(ctx,x,y,s,draw) {
    ctx.save();
    ctx.translate(x,y);
    ctx.scale(s,s);
    draw(ctx,0,0);
    ctx.restore();
  }
  function shadowOf(ctx,x,y,i0,j0,i1,j1) {
    shape(ctx,'rgba(54,62,40,.28)',[iso(x,y,i0,j0),iso(x,y,i1,j0),iso(x,y,i1,j1),iso(x,y,i0,j1)]);
  }
  function barrel(ctx,x,y) {
    rect(ctx,'#8a6238',x-4,y-9,9,9);
    rect(ctx,'#a67848',x-4,y-9,3,9);
    rect(ctx,'#4a3a28',x-4,y-7,9,1);
    rect(ctx,'#4a3a28',x-4,y-3,9,1);
    rect(ctx,'#c9a56a',x-3,y-10,7,2);
  }
  function sack(ctx,x,y) {
    shape(ctx,'#d9cba5',[[x-4,y],[x-5,y-7],[x-2,y-10],[x+3,y-10],[x+5,y-7],[x+4,y]]);
    rect(ctx,'#a89868',x-2,y-10,5,2);
  }
  function logPile(ctx,x,y,rows=3) {
    for (let r=0;r<rows;r++) for (let k=0;k<rows-r;k++) {
      const cx=x-(rows-r-1)*3+k*6, cy=y-r*5;
      rect(ctx,'#7b5a3a',cx-3,cy-5,7,5);
      rect(ctx,'#d9b982',cx-1,cy-4,3,3);
    }
  }
  function plankStack(ctx,x,y) {
    for (let k=0;k<4;k++) rect(ctx,k%2?'#c9a56a':'#b5905a',x-9,y-4-k*4,18,4);
    rect(ctx,'#6b5a45',x-9,y-2,18,1);
  }

  function ringShape(ctx,color,outer,inner) {
    ctx.fillStyle=color;
    ctx.beginPath();
    outer.forEach((p,k)=>k?ctx.lineTo(...p):ctx.moveTo(...p));
    ctx.closePath();
    inner.forEach((p,k)=>k?ctx.lineTo(...p):ctx.moveTo(...p));
    ctx.closePath();
    ctx.fill('evenodd');
  }
  // Vodní mlýn: kamenný sokl, srubová horní část, na straně k řece velké vodní kolo se žlabem nad ním.
  function watermill(ctx,x,y,time) {
    const ha=.72,hb=.4,h=24;
    shadowOf(ctx,x,y,-1,-.6,1.3,.75);
    isoBox(ctx,x,y,ha,hb,h,'#dbc69a','#b59a6e','#e6d3a8');
    wallL(ctx,'#8a867a',x,y,hb,-ha,ha,0,7);
    wallR(ctx,'#76726a',x,y,ha,-hb,hb,0,7);
    for (const i of [-.6,-.15,.3,.7]) line(ctx,'#5f4a33',2,iso(x,y,i,hb,7),iso(x,y,i,hb,h));
    line(ctx,'#5f4a33',2,iso(x,y,-ha,hb,14),iso(x,y,ha,hb,14));
    wallL(ctx,'#5c4129',x,y,hb,-.62,-.36,0,14);
    wallL(ctx,'#efe2a5',x,y,hb,.02,.2,9,16);
    wallL(ctx,'#2c241e',x,y,hb,.42,.6,9,17);
    ridgeRoof(ctx,x,y,ha,hb,h,12,.07,'#a9814f','#7a5b36','#6b4f2f');
    // vyhazovací trám se zdviží
    const hoist=iso(x,y,.1,hb+.05,h-3);
    line(ctx,'#4a3a28',2,hoist,[hoist[0]+12,hoist[1]+6]);
    line(ctx,'#b1a181',1,[hoist[0]+12,hoist[1]+6],[hoist[0]+12,hoist[1]+13]);
    sack(ctx,...iso(x,y,-.95,.6));
    sack(ctx,...iso(x,y,-.8,.7));
    // vodní kolo na pravém čele (strana k řece)
    const wi=ha+.12, zc=17, rj=.62, rz=17;
    const ring=(k,phase=0,n=28)=>Array.from({length:n},(_,s)=>{
      const a=phase+s*2*Math.PI/n;
      return iso(x,y,wi,Math.cos(a)*rj*k,zc+Math.sin(a)*rz*k);
    });
    shape(ctx,'rgba(70,110,120,.55)',[iso(x,y,wi-.1,-.7,0),iso(x,y,wi+.45,-.7,0),iso(x,y,wi+.45,.7,0),iso(x,y,wi-.1,.7,0)]);
    line(ctx,'#47372b',3,iso(x,y,ha-.1,0,zc),iso(x,y,wi+.18,0,zc));
    ringShape(ctx,'#6b5338',ring(1.08),ring(.9));
    ringShape(ctx,'#9b774a',ring(.9),ring(.84));
    const turn=frozenRiver?0:time/520;
    for (let s=0;s<12;s++) {
      const a=turn+s*Math.PI/6;
      const p0=iso(x,y,wi,Math.cos(a)*rj*.86,zc+Math.sin(a)*rz*.86);
      const p1=iso(x,y,wi,Math.cos(a)*rj*1.1,zc+Math.sin(a)*rz*1.1);
      line(ctx,'#65492f',2,iso(x,y,wi,0,zc),p0);
      line(ctx,s%2?'#d0a86a':'#9b774a',3,p0,p1);
    }
    rect(ctx,'#d0ad72',...iso(x,y,wi,0,zc).map(v=>v-2),4,4);
    // žlab nad kolem a padající voda
    const from=iso(x,y,wi,-1.5,zc+rz+11), to=iso(x,y,wi,-.05,zc+rz+4);
    line(ctx,'#7a5c3b',5,from,to);
    line(ctx,'#72a3ac',2,[from[0],from[1]-2],[to[0],to[1]-2]);
    for (const j of [-1.4,-.8]) {
      const top=iso(x,y,wi,j,zc+rz+8-(j+1.5)*5), bottom=iso(x,y,wi,j,0);
      line(ctx,'#654d32',2,top,bottom);
    }
    if (!frozenRiver) {
      for (let s=0;s<3;s++) {
        const drop=((time/160+s*3)%9)|0;
        rect(ctx,'#abd1cb',to[0]-1+s,to[1]+drop*2,2,2);
      }
      for (let s=0;s<4;s++) {
        const lift=((time/110+s*2)%6)|0;
        rect(ctx,'#e3f1ee',...iso(x,y,wi+.1,-.3+s*.2,0).map((v,k)=>k?v-lift:v),3,2);
      }
    }
  }

  // Hospoda: dlouhý hrázděný dům přes dvě dlaždice, červená taška, komín, štít se džbánem, sudy.
  function tavernHouse(ctx,x,y) {
    const open=sprites.some(sprite=>sprite.place==='tavern');
    const ha=.9,hb=.4,h=21;
    const glow=open?'#ffc46d':'#ad8652';
    shadowOf(ctx,x,y,-1.1,-.55,1.35,.8);
    isoBox(ctx,x,y,ha,hb,h,'#dbc69a','#ae946a','#e6d3a8');
    wallL(ctx,'#7a6746',x,y,hb,-ha,ha,0,4);
    wallR(ctx,'#6b5a3d',x,y,ha,-hb,hb,0,4);
    for (const i of [-.88,-.3,.3,.88]) line(ctx,'#5f4a33',2,iso(x,y,i,hb,4),iso(x,y,i,hb,h));
    line(ctx,'#5f4a33',2,iso(x,y,-ha,hb,13),iso(x,y,ha,hb,13));
    wallL(ctx,'#5c4129',x,y,hb,-.74,-.46,0,13);
    rect(ctx,'#d9b367',...iso(x,y,-.52,hb,6).map((v,k)=>k?v:v-1),2,2);
    for (const i of [-.05,.55]) wallL(ctx,glow,x,y,hb,i-.1,i+.1,8,15);
    wallR(ctx,glow,x,y,ha,-.12,.12,8,15);
    ridgeRoof(ctx,x,y,ha,hb,h,15,.12,'#b46c4c','#914d3b','#704035');
    const chim=iso(x,y,.5,-.1,h+9);
    rect(ctx,'#784c42',chim[0]-3,chim[1]-13,7,17);
    rect(ctx,'#a66f57',chim[0]-4,chim[1]-15,9,3);
    const bracket=iso(x,y,ha+.02,hb,17);
    line(ctx,'#604732',2,bracket,[bracket[0]+12,bracket[1]+4]);
    rect(ctx,'#68412d',bracket[0]+7,bracket[1]+4,14,12);
    rect(ctx,'#c29158',bracket[0]+8,bracket[1]+5,12,10);
    rect(ctx,'#f1d7a0',bracket[0]+11,bracket[1]+7,5,6);
    rect(ctx,'#f1d7a0',bracket[0]+16,bracket[1]+8,2,3);
    barrel(ctx,...iso(x,y,-1.12,.62));
    barrel(ctx,...iso(x,y,-.92,.74));
  }

  // Statek: dlouhé hospodářské stavení se slaměnou střechou, vraty a kupkou sena.
  function farmHouse(ctx,x,y) {
    const ha=.9,hb=.4,h=17;
    shadowOf(ctx,x,y,-1.1,-.55,1.4,.8);
    isoBox(ctx,x,y,ha,hb,h,'#d6c496','#a98f65','#e0cfa4');
    wallL(ctx,'#76684a',x,y,hb,-ha,ha,0,3);
    for (const i of [-.88,-.2,.88]) line(ctx,'#5f4a33',2,iso(x,y,i,hb,3),iso(x,y,i,hb,h));
    wallL(ctx,'#6b4f33',x,y,hb,-.18,.66,0,13);
    line(ctx,'#4a3a28',1,iso(x,y,-.18,hb,0),iso(x,y,.66,hb,13));
    line(ctx,'#4a3a28',1,iso(x,y,-.18,hb,13),iso(x,y,.66,hb,0));
    line(ctx,'#4a3a28',2,iso(x,y,.24,hb,0),iso(x,y,.24,hb,13));
    wallL(ctx,'#5c4129',x,y,hb,-.7,-.5,0,11);
    wallL(ctx,'#efe2a5',x,y,hb,-.6,-.52,5,9);
    wallR(ctx,'#2c241e',x,y,ha,-.1,.1,10,16);
    ridgeRoof(ctx,x,y,ha,hb,h,15,.14,'#c6a46a','#826543','#ab8655');
    const chim=iso(x,y,-.45,-.05,h+9);
    rect(ctx,'#784c42',chim[0]-3,chim[1]-12,6,15);
    hayBale(ctx,...iso(x,y,1.15,.55));
    hayBale(ctx,...iso(x,y,1.35,.35));
  }

  // Pila: otevřená bouda na sloupech, kláda na kozách s pilou, hromada polen a prken.
  function sawmillShed(ctx,x,y) {
    const ha=.85,hb=.4;
    shadowOf(ctx,x,y,-1.05,-.55,1.2,.75);
    groundPatch(ctx,'#cdb88a',x,y,-.8,-.3,.8,.45);
    for (const [i,j] of [[-.8,-.38],[.8,-.38],[-.8,.38],[.8,.38]]) {
      const p=iso(x,y,i,j);
      rect(ctx,'#5d4630',p[0]-1,p[1]-19,3,20);
    }
    // kláda na kozách
    const a=iso(x,y,-.45,.1), b=iso(x,y,.45,.1);
    line(ctx,'#6b5034',2,[a[0],a[1]-2],[a[0]-3,a[1]+5]);
    line(ctx,'#6b5034',2,[b[0],b[1]-2],[b[0]+3,b[1]+5]);
    shape(ctx,'#9a7448',[[a[0]-2,a[1]-8],[b[0]+2,b[1]-8],[b[0]+2,b[1]-3],[a[0]-2,a[1]-3]]);
    rect(ctx,'#d9b982',b[0]+1,b[1]-8,2,5);
    line(ctx,'#a7a39a',2,[a[0]+10,a[1]-24],[a[0]+10,a[1]-6]);
    line(ctx,'#6b5a45',1,[a[0]+8,a[1]-24],[a[0]+12,a[1]-24]);
    ridgeRoof(ctx,x,y,ha,hb,19,9,.16,'#a9814f','#7a5b36','#6b4f2f');
    logPile(ctx,...iso(x,y,-1.1,.75),3);
    plankStack(ctx,...iso(x,y,1.2,.65));
  }

  // Střecha s hřebenem ve směru osy y (štít na straně +y je vidět zleva zepředu).
  function ridgeRoofY(ctx,x,y,ha,hb,z,rise,over,front,gable,back) {
    const oa=ha+over,ob=hb+over;
    const A=iso(x,y,-oa,-ob,z),B=iso(x,y,oa,-ob,z),C=iso(x,y,oa,ob,z),D=iso(x,y,-oa,ob,z);
    const R1=iso(x,y,0,-ob,z+rise),R2=iso(x,y,0,ob,z+rise);
    shape(ctx,back,[A,D,R2,R1]);
    shape(ctx,gable,[D,C,R2]);
    shape(ctx,front,[C,B,R1,R2]);
    line(ctx,gable,2,R1,R2);
  }
  // Kostel: loď s věží na průčelí (strana +y) a jehlanem; celé se vejde do čtverce 2 × 2 dlaždice.
  function church(ctx,x,y) {
    const stoneL='#d2ccbb',stoneR='#aaa493',plinth='#8e887a';
    const slate=['#6c7478','#58606a','#434b4e'];
    shadowOf(ctx,x,y,-1.05,-1.05,1.1,1.1);
    const [nx,ny]=iso(x,y,0,-.2);
    isoBox(ctx,nx,ny,.44,.78,28,stoneL,stoneR,null);
    wallR(ctx,plinth,nx,ny,.44,-.78,.78,0,4);
    for (const j of [-.55,-.22,.11]) {
      wallR(ctx,'#2f3a46',nx,ny,.44,j-.07,j+.07,10,23);
      wallR(ctx,'#4a5d70',nx,ny,.44,j-.07,j+.07,19,23);
      line(ctx,'#e8e2d0',1,iso(nx,ny,.44,j,10),iso(nx,ny,.44,j,23));
    }
    ridgeRoofY(ctx,nx,ny,.44,.78,28,19,.08,slate[0],slate[1],slate[2]);
    const [tx,ty]=iso(x,y,0,.6);
    isoBox(ctx,tx,ty,.5,.4,52,stoneL,stoneR,null);
    wallL(ctx,plinth,tx,ty,.4,-.5,.5,0,5);
    wallL(ctx,'#6a4a30',tx,ty,.4,-.17,.17,0,17);
    wallL(ctx,'#3a2a1d',tx,ty,.4,-.012,.012,0,17);
    shape(ctx,stoneL,[iso(tx,ty,-.2,.4,17),iso(tx,ty,.2,.4,17),iso(tx,ty,0,.4,23)]);
    wallL(ctx,'#2f3a46',tx,ty,.4,-.09,.09,29,40);
    wallR(ctx,'#2f3a46',tx,ty,.5,-.09,.09,29,40);
    wallL(ctx,'#2f3a46',tx,ty,.4,-.07,.07,43,49);
    const base=[iso(tx,ty,-.58,-.48,52),iso(tx,ty,.58,-.48,52),iso(tx,ty,.58,.48,52),iso(tx,ty,-.58,.48,52)];
    const apex=iso(tx,ty,0,0,90);
    shape(ctx,slate[2],[base[0],base[1],apex]);
    shape(ctx,slate[1],[base[2],base[1],apex]);
    shape(ctx,slate[0],[base[3],base[2],apex]);
    line(ctx,'#3f4649',1,base[2],apex);
    rect(ctx,'#4b4a42',apex[0]-1,apex[1]-13,2,14);
    rect(ctx,'#4b4a42',apex[0]-4,apex[1]-10,8,2);
  }
  // Zahrada: záhony podle stavu sezóny (holá zem, sazenice, dozrálá zelenina), plot kolem.
  function gardenPlot(ctx,x,y) {
    const g=extra.garden??{};
    const stage=(g.crop??0)>=1 ? 3 : (g.sown??0)>0 ? ((g.care??0)>.5 ? 2 : 1) : 0;
    groundPatch(ctx,'#8e7a52',x,y,-1.45,-.95,1.45,.95);
    fenceRun(ctx,x,y,[-1.45,-.95],[1.45,-.95],6);
    fenceRun(ctx,x,y,[-1.45,-.95],[-1.45,.95],6);
    for (let r=0;r<3;r++) for (let c=0;c<2;c++) {
      const i0=-1.25+r*.85, j0=-.8+c*.8;
      groundPatch(ctx,'#5a4630',x,y,i0,j0,i0+.7,j0+.62);
      if (stage===0) continue;
      for (let k=0;k<4;k++) {
        const p=iso(x,y,i0+.1+k*.16,j0+.12+((k+r+c)%2)*.26);
        const h=stage===3?5:stage===2?4:2;
        const color=stage===3?['#79a843','#d8923a','#a8c25a','#b6483c'][(k+r+c)%4]:(stage===2?'#5f9a3c':'#8cb35a');
        rect(ctx,color,p[0]-2,p[1]-h,4,h);
        if (stage>=2) rect(ctx,'#3e7a2d',p[0]-1,p[1]-h-1,2,1);
      }
    }
    fenceRun(ctx,x,y,[-1.45,.95],[1.45,.95],6);
    fenceRun(ctx,x,y,[1.45,.95],[1.45,-.95],6);
  }
  // Sklep: zemní násep s kamenným portálem a dveřmi, vedle dvě bečky.
  function cellar(ctx,x,y) {
    groundPatch(ctx,'#a99467',x,y,-.95,-.6,.95,.95);
    const [cx,cy]=iso(x,y,0,-.1);
    ctx.fillStyle='#6f8a4e';
    ctx.beginPath(); ctx.ellipse(cx,cy,36,12,0,0,Math.PI*2); ctx.fill();
    ctx.beginPath(); ctx.moveTo(cx-36,cy); ctx.bezierCurveTo(cx-32,cy-40,cx+32,cy-40,cx+36,cy); ctx.closePath(); ctx.fill();
    ctx.fillStyle='#86a35f';
    ctx.beginPath(); ctx.moveTo(cx-26,cy-6); ctx.bezierCurveTo(cx-20,cy-30,cx+20,cy-30,cx+26,cy-6); ctx.closePath(); ctx.fill();
    const [bx,by]=iso(x,y,0,.32);
    isoBox(ctx,bx,by,.42,.16,18,'#a9a497','#8f8b7f','#c3beae');
    wallL(ctx,'#2c241e',bx,by,.16,-.17,.17,0,13);
    line(ctx,'#6f5538',2,iso(bx,by,-.2,.16,13),iso(bx,by,.2,.16,13));
    for (const [i,j] of [[.7,.55],[.45,.75]]) {
      const p=iso(x,y,i,j);
      rect(ctx,'#7a5c3b',p[0]-4,p[1]-9,8,9);
      rect(ctx,'#4f3b27',p[0]-4,p[1]-6,8,1);
      rect(ctx,'#4f3b27',p[0]-4,p[1]-3,8,1);
      rect(ctx,'#a48456',p[0]-4,p[1]-10,8,1);
    }
  }
  // Udírna: malá dřevěná bouda s komínem, ze kterého jde kouř, a zavěšeným masem u dveří.
  function smokehouse(ctx,x,y,time) {
    groundPatch(ctx,'#a58f62',x,y,-.8,-.5,.9,.8);
    isoBox(ctx,x,y,.5,.4,20,'#8d6b46','#6e5338','#9b7a50');
    wallL(ctx,'#2c241e',x,y,.4,-.18,.18,0,13);
    rect(ctx,'#a8503a',...iso(x,y,.28,.4,12).map((v,k)=>k?v:v-1),3,6);
    rect(ctx,'#a8503a',...iso(x,y,.38,.4,12).map((v,k)=>k?v:v-1),3,5);
    ridgeRoof(ctx,x,y,.5,.4,20,10,.1,'#7a5b36','#5b4429','#6b4f2f');
    const c=iso(x,y,-.2,-.1,32);
    rect(ctx,'#5a4636',c[0]-2,c[1]-8,5,12);
    chimneySmoke(ctx,c[0],c[1]-8,time);
    fenceRun(ctx,x,y,[-.8,.8],[.9,.8],6);
  }
  // Milíř: nízká hranice pokrytá hlínou. Když hoří, z vrcholu stoupá kouř a u paty doutná žár.
  function kilnMound(ctx,x,y,time) {
    const burning=extra.kiln?.state==='burning';
    groundPatch(ctx,burning?'#4b3b2d':'#6a5a43',x,y,-1.0,-.9,1.0,.9);
    const [cx,cy]=iso(x,y,0,0);
    ctx.fillStyle=burning?'#42352a':'#6f5a43';
    ctx.beginPath(); ctx.moveTo(cx-36,cy); ctx.bezierCurveTo(cx-32,cy-44,cx+32,cy-44,cx+36,cy); ctx.closePath(); ctx.fill();
    ctx.fillStyle=burning?'#54453a':'#86704f';
    ctx.beginPath(); ctx.moveTo(cx-26,cy-6); ctx.bezierCurveTo(cx-20,cy-32,cx+20,cy-32,cx+26,cy-6); ctx.closePath(); ctx.fill();
    rect(ctx,'#2b2119',cx-3,cy-30,6,4);
    if (burning) {
      chimneySmoke(ctx,cx,cy-30,time);
      for (const dx of [-20,-8,6,18]) rect(ctx,Math.floor(time/300+dx)%2?'#d9552b':'#f1b042',cx+dx,cy-3,3,2);
    }
    for (let k=0;k<3;k++) rect(ctx,'#7a5a3a',cx+40,cy-3-k*3,12,3);
  }
  // Hromada pokáceného dřeva u lesa (1 až 3 řady kmenů podle množství).
  function woodPile(ctx,x,y,amount) {
    const rows=Math.min(3,Math.ceil(amount/12));
    groundPatch(ctx,'#6a5a43',x,y,-.5,-.4,.5,.4);
    for (let r=0;r<rows;r++) for (let k=0;k<4-r;k++) {
      rect(ctx,r%2?'#8a6a43':'#7a5a3a',x-22+k*11+r*5,y-4-r*5,12,5);
      rect(ctx,'#c9a46a',x-22+k*11+r*5+(r%2?0:10),y-4-r*5,2,5);
    }
  }
  // Chmelnice: tři řady vysokých tyčí s drátem; chmel podle péče vyroste a na podzim má zlatozelené šišky.
  function hopYard(ctx,x,y) {
    const h=extra.hopYard??{};
    const crop=(h.crop??0)>=1, stage=crop?3:(h.care??0)>.5?2:(h.care??0)>0?1:0;
    groundPatch(ctx,'#6d5a3c',x,y,-1.5,-1.2,1.5,1.2);
    const top=34;
    for (const j of [-.8,0,.8]) {
      const a=iso(x,y,-1.3,j), b=iso(x,y,1.3,j);
      line(ctx,'#6f5635',1,[a[0],a[1]-top],[b[0],b[1]-top]);
      for (const i of [-1.3,-.65,0,.65,1.3]) {
        const p=iso(x,y,i,j);
        rect(ctx,'#6f5635',p[0]-1,p[1]-top,2,top+1);
        if (stage===0) continue;
        const vine=stage===1?10:stage===2?top-4:top-2;
        const color=stage===3?'#7aa046':stage===2?'#4f8a38':'#7fb05a';
        rect(ctx,color,p[0]-2,p[1]-vine,4,vine);
        if (stage>=2) rect(ctx,'#3e7a2d',p[0]-3,p[1]-vine+4,2,5);
        if (stage===3) for (const dz of [8,16,24]) rect(ctx,'#c9cf6a',p[0]+1,p[1]-dz,3,4);
      }
    }
  }
  // Sladovna: nízký kamenný dům se sušárnou, na hřebeni větrací kuželová čepice, před dveřmi pytle ječmene.
  function maltHouse(ctx,x,y,time) {
    const ha=.55,hb=.4,h=15;
    groundPatch(ctx,'#a99467',x,y,-.8,-.6,1.1,.95);
    isoBox(ctx,x,y,ha,hb,h,'#b4ad9c','#8f8979','#c4bdad');
    wallL(ctx,'#2c241e',x,y,hb,.05,.3,0,10);
    for (const i of [-.3]) wallL(ctx,'#e0c58f',x,y,hb,i-.07,i+.07,6,11);
    ridgeRoof(ctx,x,y,ha,hb,h,10,.1,'#b97a4f','#8f5a3b','#74472f');
    // Sušárna: čtyřboká věžička s kuželovou střechou (oast).
    const t=iso(x,y,-.15,-.05,h+6);
    isoBox(ctx,t[0],t[1],.18,.18,10,'#a99f8c','#8c8372','#bbb2a0');
    shape(ctx,'#8f5a3b',[[t[0]-12,t[1]-10],[t[0]+12,t[1]-10],[t[0],t[1]-26]]);
    rect(ctx,'#d9d4c8',t[0]-1,t[1]-30,3,4);
    chimneySmoke(ctx,t[0],t[1]-26,time);
    for (const [i,j] of [[.75,.6],[.95,.45]]) {
      const p=iso(x,y,i,j);
      rect(ctx,'#b9a679',p[0]-4,p[1]-8,9,8);
      rect(ctx,'#8f7d57',p[0]-4,p[1]-9,9,2);
    }
  }
  // Pivovar: široký dům s kamennou podezdívkou, vysokým komínem, při vaření stoupá pára; u zdi sudy.
  function brewery(ctx,x,y,time) {
    const ha=.85,hb=.5,h=19;
    const brewing=!!extra.brewProject?.paid;
    shadowOf(ctx,x,y,-1.05,-.65,1.3,.85);
    isoBox(ctx,x,y,ha,hb,h,'#cdb78a','#a38d63','#dcc89d');
    wallL(ctx,'#8c867a',x,y,hb,-ha,ha,0,5);
    wallR(ctx,'#7a756a',x,y,ha,-hb,hb,0,5);
    for (const i of [-.8,0,.8]) line(ctx,'#5f4a33',2,iso(x,y,i,hb,5),iso(x,y,i,hb,h));
    wallL(ctx,'#4f3a26',x,y,hb,-.62,-.3,0,12);
    wallL(ctx,brewing?'#f0c070':'#7b5a3a',x,y,hb,.25,.5,7,14);
    wallR(ctx,'#2c241e',x,y,ha,-.1,.1,0,10);
    ridgeRoof(ctx,x,y,ha,hb,h,13,.12,'#b46c4c','#914d3b','#704035');
    const c=iso(x,y,.55,-.15,h+8);
    rect(ctx,'#8a5a47',c[0]-3,c[1]-22,8,26);
    rect(ctx,'#b0795f',c[0]-4,c[1]-24,10,3);
    if (brewing) chimneySmoke(ctx,c[0],c[1]-24,time);
    barrel(ctx,...iso(x,y,-1.1,.6));
    barrel(ctx,...iso(x,y,-.9,.75));
    barrel(ctx,...iso(x,y,1.15,.7));
  }
  // Kovárna: kamenný dům s velkým komínem a žhnoucí výhní, před ním kovadlina.
  function smithy(ctx,x,y,time) {
    const ha=.6,hb=.45,h=16;
    shadowOf(ctx,x,y,-.8,-.6,1.0,.8);
    isoBox(ctx,x,y,ha,hb,h,'#9b958a','#7d776c','#aba598');
    wallL(ctx,'#2c241e',x,y,hb,-.45,-.1,0,11);
    wallL(ctx,Math.floor(time/260)%2?'#f08a3a':'#e36a2c',x,y,hb,-.4,-.15,1,7);
    ridgeRoof(ctx,x,y,ha,hb,h,10,.1,'#5d5750','#48433d','#3d3934');
    const c=iso(x,y,.3,-.1,h+6);
    rect(ctx,'#6b655c',c[0]-4,c[1]-20,9,24);
    rect(ctx,'#8a8377',c[0]-5,c[1]-22,11,3);
    chimneySmoke(ctx,c[0],c[1]-22,time);
    const a=iso(x,y,.85,.6);
    rect(ctx,'#3b3936',a[0]-5,a[1]-7,10,3);
    rect(ctx,'#55524d',a[0]-2,a[1]-4,4,4);
  }
  // Důl: štola ve svahu s dřevěnou výdřevou, vozík s rudou a hromádka rudy.
  function mineEntrance(ctx,x,y) {
    groundPatch(ctx,'#8a8578',x,y,-.9,-.7,.9,.9);
    const [cx,cy]=iso(x,y,0,-.1);
    ctx.fillStyle='#7f7a6e';
    ctx.beginPath(); ctx.moveTo(cx-38,cy+2); ctx.bezierCurveTo(cx-30,cy-46,cx+30,cy-46,cx+38,cy+2); ctx.closePath(); ctx.fill();
    ctx.fillStyle='#96907f';
    ctx.beginPath(); ctx.moveTo(cx-24,cy-8); ctx.bezierCurveTo(cx-18,cy-34,cx+18,cy-34,cx+24,cy-8); ctx.closePath(); ctx.fill();
    rect(ctx,'#1d1814',cx-10,cy-20,20,22);
    rect(ctx,'#7a5a3a',cx-13,cy-22,3,24);
    rect(ctx,'#7a5a3a',cx+10,cy-22,3,24);
    rect(ctx,'#8a6a43',cx-14,cy-25,28,4);
    const w=iso(x,y,.55,.6);
    rect(ctx,'#5f4a30',w[0]-8,w[1]-9,16,8);
    rect(ctx,'#8a4f3a',w[0]-7,w[1]-12,14,4);
    rect(ctx,'#2c241e',w[0]-6,w[1]-1,4,3);
    rect(ctx,'#2c241e',w[0]+3,w[1]-1,4,3);
    for (const [dx,dy] of [[-30,10],[-24,13],[-27,7]]) rect(ctx,'#8a4f3a',cx+dx,cy+dy,6,4);
  }
  // Vinice: řady keřů u kůlů; s úrodou visí modré hrozny.
  function vineRows(ctx,x,y) {
    const v=extra.vineyard??{};
    const crop=(v.crop??0)>=1, grown=(v.care??0)>.3||crop;
    groundPatch(ctx,'#7d6a45',x,y,-1.5,-1.4,1.5,1.4);
    for (const j of [-1.05,-.35,.35,1.05]) {
      for (const i of [-1.2,-.6,0,.6,1.2]) {
        const p=iso(x,y,i,j);
        rect(ctx,'#6f5635',p[0],p[1]-13,2,14);
        rect(ctx,grown?'#4f8a38':'#7a8f55',p[0]-4,p[1]-11,10,grown?7:4);
        if (grown) rect(ctx,'#3e7a2d',p[0]-5,p[1]-8,3,3);
        if (crop) { rect(ctx,'#5a3d7a',p[0]-2,p[1]-5,3,4); rect(ctx,'#5a3d7a',p[0]+3,p[1]-6,3,4); }
      }
    }
  }
  // Lis: malý dům s otevřeným přístřeškem, dřevěný lis a sudy.
  function pressHouse(ctx,x,y) {
    const ha=.5,hb=.4,h=14;
    groundPatch(ctx,'#a99467',x,y,-.8,-.6,1.0,.9);
    isoBox(ctx,x,y,ha,hb,h,'#d3bf92','#a8926a','#dfcda2');
    wallL(ctx,'#4f3a26',x,y,hb,-.3,0,0,10);
    ridgeRoof(ctx,x,y,ha,hb,h,9,.1,'#a8503a','#8a3f2f','#6f3327');
    const p=iso(x,y,.8,.5);
    rect(ctx,'#6f5635',p[0]-7,p[1]-14,2,14);
    rect(ctx,'#6f5635',p[0]+5,p[1]-14,2,14);
    rect(ctx,'#8a6a43',p[0]-8,p[1]-16,16,3);
    rect(ctx,'#7a5c3b',p[0]-5,p[1]-7,10,7);
    rect(ctx,'#5a3d7a',p[0]-4,p[1]-9,8,2);
    barrel(ctx,...iso(x,y,-.85,.65));
    barrel(ctx,...iso(x,y,-.65,.8));
  }
  const SPRITE_OFFSET = { kurnik:[-.2,-.38], chliv:[-.15,-.44], staje:[-.1,-.37], mlyn:[.2,0] };
  // Náhradní kresby nových budov, dokud pro ně není obrázek ve složce sprites/.
  function shedFallback(roof,kind,scale) {
    return (ctx,x,y)=>scaled(ctx,x,y,scale,(c,a,b)=>house(c,a,b,roof,kind));
  }
  function pondFallback(ctx,x,y) {
    const [x0,y0,w,d]=FOOT.rybnik;
    const pad=.12;
    shape(ctx,'#6d7a52',[project(x0-pad,y0-pad),project(x0+w+pad,y0-pad),project(x0+w+pad,y0+d+pad),project(x0-pad,y0+d+pad)]);
    shape(ctx,snowTheme?'#c5d9de':'#4b7b86',[project(x0+.15,y0+.15),project(x0+w-.15,y0+.15),project(x0+w-.15,y0+d-.15),project(x0+.15,y0+d-.15)]);
    for (let i=0;i<5;i++) {
      const [a,b]=project(x0+.7+i*.6,y0+.5+(i%3)*.7);
      line(ctx,'#a9d0d2',1,[a-5,b],[a+4,b]);
    }
    for (const [u,v] of [[x0+.05,y0+d-.4],[x0+w-.3,y0+.2]]) {
      const [a,b]=project(u,v);
      rect(ctx,'#5d6a3d',a-1,b-12,2,12);
      rect(ctx,'#7a5a3d',a-2,b-14,4,4);
    }
  }
  const NEW_BUILDINGS = {
    spychar:shedFallback('tile','barn',1.15), mlekarna:shedFallback('thatch','barn',1.05), kozeluzna:shedFallback('slate','workshop',1.1),
    tkalcovna:shedFallback('thatch','workshop',1.1), fara:shedFallback('tile','home',1.25), rybnik:pondFallback,
  };
  function buildingSprite(ctx,x,y,key,time) {
    if (FOOT[key] && trySprite(ctx,'buildings',key,x,y,time,frontCorner(key))) return;
    if (NEW_BUILDINGS[key]) { NEW_BUILDINGS[key](ctx,x,y,time); return; }
    const offset=SPRITE_OFFSET[key];
    if (offset) [x,y]=iso(x,y,offset[0],offset[1]);
    const custom={ mlyn:watermill, hospoda:tavernHouse, statek:farmHouse, pila:sawmillShed, kurnik:henhouse, chliv:pigsty,
      ohrada:cowPen, staje:stable, hrbitov:graveyard, kostel:church, zahrada:gardenPlot, sklep:cellar, udirna:smokehouse, milir:kilnMound, chmelnice:hopYard, sladovna:maltHouse, pivovar:brewery,
      kovarna:smithy, dul:mineEntrance, vinice:vineRows, lis:pressHouse }[key];
    if (custom) { custom(ctx,x,y,time); return; }
    if (key==='studna') { scaled(ctx,x,y,.9,(c,a,b)=>well(c,a,b)); return; }
    const [roof,kind]=buildingKinds[key];
    scaled(ctx,x,y,HOUSE_SCALE,(c,a,b)=>{ house(c,a,b,roof,kind); decorBuilding(c,a,b,key,time); });
  }
  function decorBuilding(ctx,x,y,key,time) {
    if (key==='pec') {
      rect(ctx,'#8a6a55',x+13,y-66,9,26);
      rect(ctx,'#4a3d34',x+11,y-68,13,3);
      rect(ctx,'#e0a45a',x-16,y-7,6,5);
      chimneySmoke(ctx,x+16,y-69,time);
    } else if (key==='dilna') {
      rect(ctx,'#4a4742',x-33,y-5,10,5);
      rect(ctx,'#6d675d',x-31,y-9,6,4);
    }
  }
  // Podlaha pod budovou: udusaná zem v rozměru jejích dlaždic (nepoužije se u staveb s vlastním podkladem).
  const YARDS = { mlyn:1, pec:1, pila:0, dilna:1, statek:1, hospoda:1, kostel:1 };
  function drawYards(ctx) {
    for (const key of Object.keys(extra.buildings??{})) {
      if (!FOOT[key] || !YARDS[key]) continue;
      const [x0,y0,w,d]=FOOT[key];
      const pad=.12;
      shape(ctx,snowTheme?'#cfd3c9':'#b5a47a',[project(x0-pad,y0-pad),project(x0+w+pad,y0-pad),project(x0+w+pad,y0+d+pad),project(x0-pad,y0+d+pad)]);
    }
  }

  const scaffoldScale = { kostel:2.2, ohrada:2, staje:1.8, hrbitov:1.6, hospoda:1.5, mlyn:1.5, statek:1.5, pila:1.5, chliv:1.2, kurnik:1.2, zahrada:1.5, sklep:1.2, udirna:1.2, milir:1.4, chmelnice:1.5, sladovna:1.3, pivovar:1.6, kovarna:1.4, dul:1.3, vinice:1.5, lis:1.2, spychar:1.4, mlekarna:1.2, rybnik:1.3, kozeluzna:1.3, tkalcovna:1.3, fara:1.6 };
  function scaffold(ctx,x,y,progress,key) {
    const s=scaffoldScale[key]??.8;
    const w=Math.round(20*s), h=Math.round(36*s);
    ctx.strokeStyle='#6a5233';
    ctx.lineWidth=2;
    ctx.strokeRect(x-w,y-h-6,w*2,h);
    ctx.beginPath();ctx.moveTo(x-w,y-h/2-6);ctx.lineTo(x+w,y-h/2-6);ctx.moveTo(x,y-h-6);ctx.lineTo(x,y-6);ctx.stroke();
    rect(ctx,'#b79a63',x-w,y-6-Math.round(h*Math.min(1,progress)),w*2,Math.round(h*Math.min(1,progress)));
  }
  function flames(ctx,x,y,time) {
    const flick=Math.floor(time/110)%3;
    for (let i=0;i<5;i++) {
      const h=22+((i+flick)%3)*8;
      shape(ctx,'#d9552b',[[x-20+i*9,y],[x-16+i*9,y-h],[x-11+i*9,y]]);
      shape(ctx,'#f1b042',[[x-18+i*9,y],[x-15+i*9,y-h*.6],[x-12+i*9,y]]);
    }
    rect(ctx,'rgba(70,64,60,.55)',x-14,y-62-flick*3,26,18);
  }
  function drawFlood(ctx) {
    for (let y=Y0;y<Y1;y++) {
      const bank=riverBank(y);
      for (let x=bank-2;x<=bank+4;x++) {
        shape(ctx,'rgba(76,130,170,.38)',tileCorners(x,y));
      }
    }
  }

  function buildingLayers(time) {
    const items=[];
    for (const key of Object.keys(extra.buildings??{})) {
      if (!FOOT[key]) continue;
      const [x,y]=project(...buildingSpots[key]);
      items.push({x,y,draw:(ctx,a,b)=>buildingSprite(ctx,a,b,key,time)});
    }
    if ((extra.forestWood??0)>=1) {
      const [x,y]=project(.2,9.0);
      items.push({x,y,draw:(ctx,a,b)=>woodPile(ctx,a,b,extra.forestWood)});
    }
    const site=extra.project;
    if (site && FOOT[site.key]) {
      const [x,y]=project(...buildingSpots[site.key]);
      items.push({x,y,draw:(ctx,a,b)=>scaffold(ctx,a,b,(site.progress??0)/Math.max(1,site.work??1),site.key)});
    }
    const house=extra.houseProject;
    if (house && plots[house.index]) {
      const [x,y]=project(plots[house.index][0]+.5,plots[house.index][1]+.5);
      items.push({x,y,draw:(ctx,a,b)=>scaffold(ctx,a,b,(house.progress??0)/Math.max(1,house.work??1),'dum')});
    }
    if (extra.crisis?.type==='fire') {
      const [x,y]=project(10.5,9.6);
      items.push({x,y:y+2,draw:(ctx,a,b)=>flames(ctx,a,b,time)});
    }
    return items;
  }


  function wagonPosition(minute,task) {
    const road=[[24.6,11.5],[25.4,8.5],[17.6,8.5],[11.2,9.6]];
    const progress=Math.min(2.999,Math.max(0,(minute-task.start)/(task.end-task.start)*3));
    const segment=Math.floor(progress), t=progress-segment;
    return road[segment].map((value,axis)=>value+(road[segment+1][axis]-value)*t);
  }

  function sceneLayer({tileX,tileY,draw}) {
    const [x,y]=foot(tileX,tileY);
    return {x,y,draw};
  }
  let scenery=scene.map(sceneLayer);
  const portraits = new Map();
  let sprites = [];
  let selectedSprite = '';
  let needsPaint = true;
  let homeCount = 0;
  let extra = {};
  let extraKey = '';
  let snowTheme = false;
  let nightTheme = false;
  let wetTheme = false;
  let frozenRiver = false;
  let windSpeed = 0;

  function riverCurrent(ctx,time) {
    if (frozenRiver) {
      for(let y=Y0;y<Y1;y++) {
        if (BRIDGE_ROWS.includes(y)) continue;
        for(let x=riverBank(y);x<=riverBank(y)+1;x++) {
          shape(ctx,'#b3cbd0',tileCorners(x,y));
          line(ctx,'#d6e0df',1,project(x+.18,y+.3),project(x+.74,y+.64));
          const glint=(time/1800+y*.37+x*.61)%1;
          if (glint<.4) {
            const a=.2+glint;
            line(ctx,'#edf7f6',2,project(x+a,y+.54),project(x+a+.2,y+.54));
          }
        }
      }
      return;
    }
    for(let segment=Y0;segment<Y1;segment++) {
      if (BRIDGE_ROWS.includes(segment)) continue;
      for(let lane=0;lane<2;lane++) {
        const phase=(time/950+segment*.37+lane*.53)%1;
        const x=riverBank(segment)+.54+lane*.91;
        const y=segment+.06+phase*.43;
        line(ctx,lane===0?'rgba(201,235,228,.87)':'rgba(160,207,206,.73)',lane===0?3:2,
          project(x,y),project(x+.12,y+.45));
      }
    }
  }

  function windDirection() {
    const [a,b]=project(0,0),[x,y]=project(1,0);
    return [x-a,y-b];
  }

  function windStreaks(ctx,time) {
    if (windSpeed<5) return;
    const [dx,dy]=windDirection();
    const count=Math.min(19,4+Math.floor(windSpeed/2));
    for(let i=0;i<count;i++) {
      const age=(time*(.00022+windSpeed*.000021)+i*.618)%1;
      const [x,y]=project(2+noise(i,10)%21+age*5,2+noise(i,25)%21);
      const length=.55+windSpeed*.025+(i%3)*.18;
      const opacity=Math.sin(age*Math.PI)*Math.min(.69,.22+windSpeed*.012);
      ctx.strokeStyle=`rgba(251,244,217,${opacity})`;
      ctx.lineWidth=2;
      ctx.beginPath();
      ctx.moveTo(x,y);
      ctx.quadraticCurveTo(x+dx*length*.56,y+dy*length*.56-7,x+dx*length,y+dy*length);
      ctx.stroke();
    }
  }

  function setMap(people, selected, minute=0, built=0, weather={}, calendar={}, development={}) {
    const nextKey=JSON.stringify([Object.keys(development.buildings??{}),development.project?.key,
      Math.round((development.project?.progress??0)/Math.max(1,development.project?.work??1)*40),development.crisis?.type,Math.round((development.houseProject?.progress??0)/Math.max(1,development.houseProject?.work??1)*40),
      (development.garden?.crop??0)>=1?3:(development.garden?.sown??0)>0?((development.garden?.care??0)>.5?2:1):0,development.kiln?.state,Math.min(3,Math.ceil((development.forestWood??0)/12)),
      (development.hopYard?.crop??0)>=1?3:(development.hopYard?.care??0)>.5?2:(development.hopYard?.care??0)>0?1:0,!!development.brewProject?.paid,JSON.stringify(development.houseKinds??{}),(development.vineyard?.crop??0)>=1?2:(development.vineyard?.care??0)>.3?1:0]);
    if (nextKey!==extraKey) { extraKey=nextKey; needsPaint=true; }
    extra=development;
    const snow=weather.ground==='snow';
    const night=calendar.hour<calendar.sunrise || calendar.hour>=calendar.sunset;
    const wet=weather.precip==='rain'||weather.precip==='sleet';
    const frozen=weather.frozen===true;
    const wind=Number.isFinite(weather.wind)?Math.max(0,weather.wind):0;
    if (frozen!==frozenRiver || wind!==windSpeed) needsPaint=true;
    frozenRiver=frozen;
    windSpeed=wind;
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
      const siteKey=task?.action==='construct' ? development.project?.key : null;
      const projectSpot=siteKey && FOOT[siteKey] ? [buildingSpots[siteKey][0],FOOT[siteKey][1]+FOOT[siteKey][3]+.35] : null;
      const location=cart?wagonPosition(minute,task):projectSpot?projectSpot:
        plot&&task.action==='build_house'?[plot[0]+.5,plot[1]+1.3]:
        person.place==='home'?homes[other?(other.home??other.family):(person.home??person.family)]:places[person.place];
      const key = cart?person.id:person.place==='home'?(other?(other.home??other.family):(person.home??person.family)):person.place;
      const slot = byPlace.get(key)??0;
      byPlace.set(key,slot+1);
      const position=location??places.market;
      const [tx,ty] = project(...position);
      const column=slot%5, row=Math.floor(slot/5);
      const offset=[0,-30,31,-53,53][column]*PERSON_SCALE;
      const offsetX=offset+row*12*PERSON_SCALE;
      const offsetY=Math.floor(column/2)*11*PERSON_SCALE+row*23*PERSON_SCALE;
      const x=tx+offsetX;
      const y=ty+offsetY;
      const old = previous.get(person.id);
      const sleeping = person.activity?.startsWith('Spí') ?? false;
      const prop=task||person.place===person.work?person.place:'';
      if (!old || old.tx!==x || old.ty!==y || old.sleeping!==sleeping || old.cart!==cart || old.prop!==prop) needsPaint=true;
      const portraitKey=[person.age<12,person.color].join('|');
      if (portraits.get(person.id)?.key!==portraitKey) {
        portraits.set(person.id,{key:portraitKey,frames:[0,1,2].map(step=>personSprite(person,step))});
      }
      const frames=portraits.get(person.id).frames;
      return { id:person.id, place:person.place, art:frames[0], frames, sleeping,cart,prop, position,offsetX,offsetY,phase:noise(x,y),
        x:old?.x??x, y:old?.y??y, tx:x, ty:y, moving:false };
    });
  }

  function drawPerson(ctx,sprite,time) {
    const [px,py]=spritePoint(sprite,time);
    ctx.save();
    ctx.translate(px,py);
    ctx.scale(PERSON_SCALE,PERSON_SCALE);
    const x=0,y=0;
    const step=sprite.moving ? 1+Math.floor(time/145)%2 : 0;
    const bob=step ? step-1 : sprite.sleeping ? Math.round(Math.sin(time/420)) : 0;
    const selected=sprite.id===selectedSprite;
    shape(ctx,selected?'#f8dc91':'#455238',[[x-15,y],[x,y-5],[x+15,y],[x,y+5]]);
    if(sprite.cart)wheelbarrow(ctx,x+21,y);
    else if(!sprite.sleeping && !sprite.moving) workProp(ctx,x,y,sprite.prop,time+sprite.phase);
    ctx.drawImage(sprite.frames[step],x-sprite.art.width/2,y-sprite.art.height-bob);
    if (selected) {
      const top=y-sprite.art.height-bob;
      shape(ctx,'#f8dc91',[[x-5,top-9],[x+5,top-9],[x,top-3]]);
    }
    if (sprite.sleeping) {
      for (let i=0;i<2;i++) {
        const phase=(time/1800+sprite.phase/997+i*.5)%1;
        const left=x+11+Math.round(phase*8);
        const top=y-sprite.art.height-5-Math.round(phase*14);
        const ink=`rgba(255,240,186,${.95-phase*.65})`;
        rect(ctx,ink,left,top,7,2);
        line(ctx,ink,2,[left+6,top+1],[left,top+7]);
        rect(ctx,ink,left,top+7,7,2);
      }
    }
    ctx.restore();
  }

  function spritePoint(sprite,time) {
    const active=!sprite.moving && !sprite.sleeping && !sprite.cart && !reducedMotion.matches;
    const angle=(time+sprite.phase*7)/760;
    const dx=active ? Math.sin(angle)*(sprite.prop==='river'?2:5) : 0;
    const dy=active ? Math.sin(angle*2)*2 : 0;
    return [Math.round(sprite.x+dx),Math.round(sprite.y+dy)];
  }

  let lastFrame=-Infinity;
  let animationTime=0;
  function renderMap(time) {
    requestAnimationFrame(renderMap);
    if (!needsPaint && time-lastFrame<39) return;
    const elapsed=Math.min(90,time-lastFrame);
    lastFrame=time;
    animationTime=reducedMotion.matches?0:time;
    for (const sprite of sprites) {
      sprite.moving = Math.abs(sprite.tx-sprite.x)+Math.abs(sprite.ty-sprite.y)>.7;
      const blend=reducedMotion.matches || !sprite.moving ? 1 : 1-Math.exp(-elapsed/470);
      sprite.x += (sprite.tx-sprite.x)*blend;
      sprite.y += (sprite.ty-sprite.y)*blend;
      if (reducedMotion.matches) sprite.moving=false;
    }
    if (!reducedMotion.matches || needsPaint) {
      paint.setTransform(view.zoom,0,0,view.zoom,view.x,view.y);
      paint.drawImage(terrain,0,0);
      if (extra.crisis?.type==='flood') drawFlood(paint);
      riverCurrent(paint,animationTime);
      drawYards(paint);
      const newHomes=plots.slice(0,homeCount).map(([px,py],i)=>{
        const [x,y]=foot(px,py);
        const cottage=extra.houseKinds?.[i]==='domek';
        return {x,y,draw:(ctx,a,b)=>{ if (!trySprite(ctx,'houses',cottage?'cottage':'home_new',a,b,animationTime,HALF_H)) scaled(ctx,a,b,cottage?HOUSE_SCALE*.78:HOUSE_SCALE,(c,p,q)=>house(c,p,q,cottage?'thatch':'tile','home')); }};
      });
      const layers = [...scenery,...newHomes,...buildingLayers(animationTime),...sprites].sort((a,b)=>a.y-b.y);
      for (const item of layers) {
        if (item.art) drawPerson(paint,item,animationTime);
        else item.draw(paint,item.x,item.y);
      }
      windStreaks(paint,animationTime);
      paint.setTransform(1,0,0,1,0,0);
      if(nightTheme)rect(paint,'rgba(25,39,64,.25)',0,0,canvas.width,canvas.height);
      if(wetTheme){
        paint.strokeStyle='rgba(185,211,214,.35)';paint.lineWidth=1.3;paint.beginPath();
        for(let i=0;i<85;i++){
          const x=(i*97+31+Math.floor(animationTime/19))%canvas.width;
          const y=(i*53+7+Math.floor(animationTime/5))%canvas.height;
          paint.moveTo(x,y);
          paint.lineTo(x-7,y+12);
        }
        paint.stroke();
      }
    }
    needsPaint = false;
  }
  requestAnimationFrame(renderMap);
  reducedMotion.addEventListener('change',()=>{needsPaint=true;});

  const view = { zoom:1, x:0, y:0 };
  const zoomOut = root.querySelector('[data-field=map-zoom-out]');
  const zoomIn = root.querySelector('[data-field=map-zoom-in]');
  const resetView = root.querySelector('[data-field=map-reset]');
  const rotateLeft = root.querySelector('[data-field=map-rotate-left]');
  const rotateRight = root.querySelector('[data-field=map-rotate-right]');
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
    resetView.disabled=next===1 && rotation===0;
  }

  function rotateMap(turns) {
    const x=canvas.width/2;
    const y=canvas.height/2;
    const dx=((x-view.x)/view.zoom-screenCenterX)/HALF_W;
    const dy=((y-view.y)/view.zoom-screenCenterY)/HALF_H;
    const [u,v]=quarterTurn((dy+dx)/2,(dy-dx)/2,turns);
    rotation=(rotation+turns)%4;
    drawGround(snowTheme);
    scenery=scene.map(sceneLayer);
    for (const sprite of sprites) {
      const [px,py]=project(...sprite.position);
      sprite.x=sprite.tx=px+sprite.offsetX;
      sprite.y=sprite.ty=py+sprite.offsetY;
    }
    moveView(x-(screenCenterX+(u-v)*HALF_W)*view.zoom,y-(screenCenterY+(u+v)*HALF_H)*view.zoom);
    resetView.disabled=view.zoom===1 && rotation===0;
  }

  function resetMap() {
    if (rotation) rotateMap(4-rotation);
    zoomTo(1);
  }

  function canvasPoint(event) {
    const box = canvas.getBoundingClientRect();
    return [(event.clientX-box.left)*canvas.width/box.width,(event.clientY-box.top)*canvas.height/box.height];
  }

  function findSprite(event) {
    const [screenX,screenY] = canvasPoint(event);
    const x = (screenX-view.x)/view.zoom;
    const y = (screenY-view.y)/view.zoom;
    return [...sprites].sort((a,b)=>b.y-a.y).find(sprite=>{
      const [px,py]=spritePoint(sprite,animationTime);
      return Math.abs(px-x)<=sprite.art.width*PERSON_SCALE/2+2 && y>=py-sprite.art.height*PERSON_SCALE-4 && y<=py+6;
    });
  }

  zoomOut.addEventListener('click',()=>zoomTo(view.zoom/1.25));
  zoomIn.addEventListener('click',()=>zoomTo(view.zoom*1.25));
  resetView.addEventListener('click',resetMap);
  rotateLeft?.addEventListener('click',()=>rotateMap(3));
  rotateRight?.addEventListener('click',()=>rotateMap(1));
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
    } else if (['+','=','-','0',...(ROTATION_ENABLED?['q','e']:[])].includes(event.key.toLowerCase())) {
      event.preventDefault();
      switch (event.key.toLowerCase()) {
        case 'q': rotateMap(3); break;
        case 'e': rotateMap(1); break;
        case '0': resetMap(); break;
        default: zoomTo(event.key==='-'?view.zoom/1.25:view.zoom*1.25);
      }
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
