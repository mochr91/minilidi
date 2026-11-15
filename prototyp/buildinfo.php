<?php
declare(strict_types=1);

// Popis budovy pro okénko po kliknutí na mapě: co to je, co dělá, jak na tom budova teď je a kdo ji stavěl.
// `buildingInfo($world,$key)` vrací pole pro api.php?view=building&key=... (klíč budovy nebo `home:<id domu>`).

const BUILDING_DOES = [
    'mlyn'=>['Semele obilí na mouku (úkol „Semlít obilí“, šest obilí na dávku).','Dává slepicím rezervu obilí, aby se všechno nesemlelo na chléb.'],
    'pila'=>['Řeže dřevo na prkna (ze šesti dřeva čtyři prkna).','Prkna jsou potřeba na stavby, nářadí, vozy a sudy.'],
    'pec'=>['Z mouky peče chléb, který zasytí dvakrát víc než kaše.','Pečení je jedna z hlavních prací dne.'],
    'studna'=>['Voda pro vesnici: sousedé si nosí vodu a pomáhají si.','Zmírní škody při požáru a zdravotní následky povodně.'],
    'dilna'=>['Vyrábí nářadí z prken a kamene, vozy a sudy.','Bez dílny nejde postavit kostel, kovárna ani další velké stavby.'],
    'statek'=>['Hospodářské zázemí: obilí se v něm kazí pomaleji.','Základ pro chov: kurník, chlív, ohrada a stáje.'],
    'zahrada'=>['Na jaře se sází záhony, v létě okopávají, na podzim se sklízí zelenina a luštěniny.','Úroda dává pestrou stravu, kterou vesnice v zimě potřebuje.'],
    'hospoda'=>['Večer od 18 do 22 hodin se tu scházejí sousedé, roste zábava a nálada.','S pivovarem se pije pivo, v neděli i víno.'],
    'kurnik'=>['Slepice (6 až 12) snášejí vejce, která se sbírají při péči o zvířata a rychle se kazí.','Bez opraveného plotu chodí liška.'],
    'chliv'=>['Prase dává maso a kožky pro koželužnu.','Z chlíva je také hnůj na pole.'],
    'ohrada'=>['Krávy: mléko (s mlékárnou se z něj dělá sýr), kožky a hnůj.','Seno z letní seče krmí dobytek v zimě.'],
    'sklep'=>['V chladu se zelenina, vejce a pivo kazí pomaleji.','Nakládá se v něm zelí a zraje víno.'],
    'udirna'=>['Uzí zvěřinu a ryby, takže vydrží celou zimu.','Uzené maso tvoří část zimních jídel.'],
    'milir'=>['Pálí dřevěné uhlí: 40 dřeva dá 10 uhlí za šest dní, pálení vyžaduje dohled.','Uhlí topí třikrát výhřevněji než dřevo a potřebuje ho kovárna.'],
    'chmelnice'=>['Chmel se na jaře váže na tyče a na podzim česá (kolem 20 za sezónu).','Je základ pro pivo.'],
    'sladovna'=>['Z ječmene dělá slad (od října do dubna).','Slad je druhá surovina pro pivo.'],
    'pivovar'=>['Vaří pivo od sv. Michala do sv. Jiří: várka je 100 dávek a zraje ve sklepě.','Pivo pije hospoda a prodává se obchodníkovi.'],
    'kovarna'=>['Z rudy a uhlí taví železo a kuje železné nářadí (o 8 % výkonnější).','Železo se prodává i kupuje od obchodníka.'],
    'dul'=>['Těží železnou rudu: těžká práce s rizikem úrazu.','Ruda jde do kovárny.'],
    'staje'=>['Koně: vůz s koněm veze víc kamene a dřeva.','Pomáhají s dopravou materiálu na stavby.'],
    'hrbitov'=>['Místo pohřbů, smrt blízkých vesničany zasáhne méně.','Má význam pro víru a klid vesnice.'],
    'kostel'=>['Obřady posilují víru, náladu a klid, v kostele se konají svatby.','V neděli se při obřadu používá víno. Je podmínkou fary a vinice.'],
    'vinice'=>['Réva na svahu: první úroda až po dvou letech, v dubnu hrozí pozdní mráz.','Hrozny se česají na podzim a dávají víno.'],
    'lis'=>['Lisuje hrozny na víno (30 hroznů dá 20 vína) a víno kvasí 60 dní ve sklepě.','Sudy dělá dílna.'],
    'vcelin'=>['Úly (3 až 8): péče od dubna do září, med a vosk se vytáčí v srpnu, na podzim se úly zazimují.','Včely zvednou úrodu zahrady, chmelnice a vinice až o 10 %. Med zkracuje nemoc, ze vosku jsou svíčky pro školu.'],
    'spychar'=>['Obilí a mouka v něm vydrží mnohem déle, což se hodí na zimu i na úrok pánovi.','Myši se k zásobám nedostanou.'],
    'mlekarna'=>['Mléko od krav se sýří na sýr (část každého jídla), který vydrží celou zimu.','Přebytek sýra se prodává.'],
    'rybnik'=>['Kapří rybník: násada roste od jara do podzimu, v říjnu a listopadu se vylovuje.','Kapři jsou jídlo a vždy se nechá násada na další rok.'],
    'kozeluzna'=>['Z kožek dělá useň a z ní boty.','Boty v zimě zlepšují zdraví a náladu (potřeba zhruba 0,3 páru na osobu).'],
    'tkalcovna'=>['Z lnu z pole se příde a tká látka na teplé oblečení.','Obchodník látku s tkalcovnou nevozí, protože si ji vesnice dělá sama.'],
    'fara'=>['Kněz učí děti od 6 do 13 let číst, počítat a modlit se (lekce denně mimo neděli).','V zimě se bez svíček ve včelíně neučí. Děti rychleji rostou do řemesel.'],
    'cihelna'=>['Kope hlínu (březen až září) a pálí cihly a tašky (květen až září, dřevem nebo přebytkem uhlí).','Tašky se používají na přestavbu doškových střech, které při požáru hoří nejhůř.'],
    'lazen'=>['Sobotní koupel (od 12 do 19 hodin) zahřeje, zvedne náladu a spotřebuje dřevo.','Čistota snižuje šanci zimní nemoci. Při nedostatku dřeva lázeň zavře.'],
];

// Které zásoby se u budovy ukazují v řádku „Teď“.
const BUILDING_STOCKS = [
    'mlyn'=>['grain','flour'],'pec'=>['flour','food'],'pila'=>['wood','planks'],'dilna'=>['tools','planks'],'statek'=>['grain','hay'],
    'zahrada'=>['veg','legumes'],'sklep'=>['veg','kraut','beer'],'udirna'=>['game','meatSmoked'],'milir'=>['forestWood','charcoal'],
    'chmelnice'=>['hops'],'sladovna'=>['barley','malt'],'pivovar'=>['malt','hops','beer'],'hospoda'=>['beer','wine'],
    'kovarna'=>['ore','iron','ironTools'],'dul'=>['ore'],'kurnik'=>['eggs'],'ohrada'=>['milk','hay'],'chliv'=>['hides'],
    'vinice'=>['grapes','wine'],'lis'=>['grapes','barrels','wine'],'spychar'=>['grain','flour'],'mlekarna'=>['milk','cheese'],
    'kozeluzna'=>['hides','leather','boots'],'tkalcovna'=>['flax','cloth'],'vcelin'=>['honey','wax','candles'],'cihelna'=>['clay','bricks'],
];

function buildingStockLine(array $world,array $keys): ?string {
    $parts=[];
    foreach ($keys as $key) {
        if (!isset($world[$key]) || !is_numeric($world[$key])) continue;
        $parts[]=(RESOURCES[$key]??$key).' '.fmtAmount((float)$world[$key]);
    }
    return $parts ? implode(' · ',$parts) : null;
}

// Zvláštní řádky pro budovy, které mají vlastní stav.
function buildingSpecialFacts(array $world,string $key): array {
    $facts=[];
    $day=fn(float $minute)=>intdiv((int)$minute,1440);
    switch ($key) {
        case 'zahrada':
            $g=$world['garden']??[];
            $facts[]='Zasetí '.round(100*($g['sown']??0)).' % · péče '.round(100*($g['care']??0)).' %'.(($g['crop']??0)>=1?' · k sklizni '.fmtAmount((float)$g['crop']):'');
            break;
        case 'chmelnice':
            $g=$world['hopYard']??[];
            $facts[]='Péče '.round(100*($g['care']??0)).' %'.(($g['crop']??0)>=1?' · k česání '.fmtAmount((float)$g['crop']):'');
            break;
        case 'vinice':
            $g=$world['vineyard']??[];
            $facts[]='Péče '.round(100*($g['care']??0)).' %'.(($g['crop']??0)>=1?' · k česání '.fmtAmount((float)$g['crop']):'').(!empty($g['frost'])?' · letos zmrzla':'');
            break;
        case 'lis':
            $b=$world['wineBatch']??['state'=>'idle'];
            $facts[]=($b['state']??'idle')==='fermenting' ? 'Kvasí víno: hotové za '.max(0,(int)ceil(($b['end']-$world['minute'])/1440)).' dní' : 'Lis je volný';
            break;
        case 'milir':
            $k=$world['kiln']??['state'=>'idle'];
            $facts[]=($k['state']??'idle')==='burning' ? 'Milíř hoří, uhlí bude hotové za '.max(0,(int)ceil(($k['end']-$world['minute'])/1440)).' dní' : 'Milíř je zhasnutý';
            break;
        case 'pivovar':
            $facts[]='Pivo celkem '.fmtAmount(beerStock($world)).' dávek · uvařeno várek '.(int)($world['beerLog']['brews']??0);
            break;
        case 'rybnik':
            $p=$world['pond']??['fish'=>0];
            $facts[]='Kapři v rybníku: '.fmtAmount((float)($p['fish']??0));
            break;
        case 'fara':
            $facts[]='Lekcí odučeno: '.(int)($world['school']['lessons']??0);
            break;
        case 'vcelin':
            $a=$world['apiary']??[];
            $facts[]='Úlů '.(int)($world['hives']??0).' z '.hivesCap($world).' · péče '.round(100*($a['care']??0)).' %'.(($a['crop']??0)>=1?' · k vytočení '.fmtAmount((float)$a['crop']):'');
            $facts[]='Opylení: úroda zahrady a vinic +'.round(100*(pollination($world)-1)).' %';
            break;
        case 'cihelna':
            $map=roofMap($world);
            $tile=count(array_filter($map,fn($r)=>$r==='tile'));
            $facts[]='Střechy z tašek: '.$tile.' z '.count($map).' domů · riziko požáru ×'.round(fireRiskFactor($world),2);
            break;
        case 'lazen':
            $facts[]='Hygiena vesnice '.round(100*($world['hygiene']??0)).' % · koupelí od začátku '.(int)($world['counters']['baths']??0);
            break;
        case 'spychar':
            $facts[]='Kažení obilí denně 0,07 % místo 0,15 %';
            break;
    }
    return $facts;
}

function buildingOrderState(array $world,string $key): string {
    $project=$world['project']??null;
    if ($project!==null && $project['key']===$key) {
        $pct=round(100*$project['progress']/max(1,$project['work']));
        return 'Staví se: '.fmtAmount((float)$project['progress']).' z '.$project['work'].' člověkohodin ('.$pct.' %)'.(empty($project['paid'])?', čeká na materiál':'').'.';
    }
    $def=BUILDINGS[$key];
    $missing=array_values(array_filter($def['needs'],fn($n)=>!hasBuilding($world,$n)));
    if ($missing) return 'Nestojí. Nejdřív musí stát: '.implode(', ',array_map(fn($n)=>BUILDINGS[$n]['label'],$missing)).'.';
    $before=[];
    foreach (BUILD_ORDER as $k) { if ($k===$key) break; if (!hasBuilding($world,$k)) $before[]=BUILDINGS[$k]['label']; }
    return $before ? 'Nestojí. Na řadě je až po: '.implode(', ',$before).'.' : 'Nestojí. Je na řadě jako další stavba.';
}

function buildingInfo(array $world,string $key): ?array {
    if (str_starts_with($key,'home:')) return homeInfo($world,substr($key,5));
    if (!isset(BUILDINGS[$key])) return null;
    $def=BUILDINGS[$key];
    $built=hasBuilding($world,$key);
    $cost=[];
    foreach ($def['cost'] as $res=>$amount) $cost[]=['label'=>RESOURCES[$res]??$res,'amount'=>$amount];
    $status=[];
    $facts=[];
    if ($built) {
        $since=(int)$world['buildings'][$key];
        $status[]=$since>0 ? 'Stojí od dne '.(intdiv($since,1440)+1).' hry.' : 'Stojí od začátku hry.';
        $log=$world['buildLog'][$key]??null;
        if ($log && !empty($log['builders'])) {
            $status[]='Stavěli: '.implode(', ',array_map(fn($b)=>$b['name'].' ('.fmtAmount((float)$b['hours']).' h)',array_slice($log['builders'],0,4))).'.';
        }
        $line=buildingStockLine($world,BUILDING_STOCKS[$key]??[]);
        if ($line) $facts[]=$line;
        $facts=array_merge($facts,buildingSpecialFacts($world,$key));
    } else {
        $status[]=buildingOrderState($world,$key);
    }
    return ['key'=>$key,'kind'=>'building','label'=>$def['label'],'built'=>$built,'what'=>$def['text'],'does'=>BUILDING_DOES[$key]??[],
        'status'=>$status,'facts'=>$facts,'cost'=>$cost,'work'=>$def['work'],
        'needs'=>array_map(fn($n)=>BUILDINGS[$n]['label'],$def['needs'])];
}

function homeInfo(array $world,string $id): ?array {
    $houses=houseList($world);
    if (!isset($houses[$id])) return null;
    $house=$houses[$id];
    $roof=roofMap($world)[$id]??'tile';
    $residents=[];
    foreach (residentsOf($world,$id) as $rid) $residents[]=$world['people'][$rid]['name'].' ('.$world['people'][$rid]['age'].')';
    $kind=$house['kind']==='domek' ? 'Domek pro čtyři lidi' : 'Dům pro šest lidí';
    $status=[];
    $status[]=$house['built']>0 ? 'Postaven ve dni '.(intdiv((int)$house['built'],1440)+1).' hry.' : 'Dům stojí od začátku hry.';
    $status[]='Střecha: '.($roof==='tile'?'tašky':'došky').($roof==='thatch'?' (dá se přestavět v cihelně)':'').'.';
    $facts=[$residents ? 'Bydlí ('.count($residents).' z '.$house['capacity'].'): '.implode(', ',$residents) : 'Dům je prázdný (kapacita '.$house['capacity'].').'];
    return ['key'=>'home:'.$id,'kind'=>'home','label'=>houseLabel($id,$houses),'built'=>true,
        'what'=>$kind.'. Tady rodina spí, jí a odpočívá.','does'=>['Dává místo k bydlení, lidé v něm tráví noc.','Když se v domě zúží, mladí nebo prarodiče se stěhují jinam, případně se staví další dům.'],
        'status'=>$status,'facts'=>$facts,'cost'=>[],'work'=>0,'needs'=>[]];
}
