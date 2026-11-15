# Seznam obrázků k nakreslení

Pravidla (měřítko 2, kotva, obrys, paleta, názvy) jsou v `SPRITY.md`. Tento seznam je pracovní: po dokončení obrázku
přepiš `[ ]` na `[x]`. Soubory se ukládají do `prototyp/public/sprites/`, stav je vidět na `http://127.0.0.1:8093/sprity.html`.
Rozměry jsou doporučené v bodech obrázku (hra je zvětší 2×); výšku smí obrázek mít větší, pokud to budova potřebuje
(kvůli kouři, věži apod.). Všechny obrázky jsou PNG s průhledným pozadím, izometrie 2:1, spodní střed = přední roh půdorysu.

Všechny položky níže generuje `python -I grafika/gen_vse.py` (po částech `gen_budovy.py`, `gen_budovy2.py`, `gen_ostatni.py`;
společné kreslicí funkce jsou v `pixlib.py`). Kontrola: `python -I grafika/over_sprity.py prototyp/public/sprites/*/*.png`.

**Animace:** hra bere obrázek podle `druh/klíč` bez ohledu na `@N`, takže vedle pásu `<klíč>@4.png` nesmí ležet statický `<klíč>.png`
(hra by je střídavě přenačítala). Animované budovy jsou proto uložené jen jako pás.

## 1. Budovy (všech 30 hotovo)

| Hotovo | Soubor | Půdorys | Rozměr | Co je nakresleno |
|---|---|---|---|---|
| [x] | `buildings/mlyn@4.png` | 2×1 | 36×41 | vodní mlýn s točícím se kolem u náhonu (4 snímky) |
| [x] | `buildings/pec@4.png` | 1×1 | 26×42 | chlebová pec s kopulí, komínem a kouřem (4 snímky) |
| [x] | `buildings/pila.png` | 2×1 | 36×41 | otevřená pila s klátou, pilovým rámem a prkny |
| [x] | `buildings/dilna.png` | 1×1 | 26×29 | dílna s nářadím u zdi a stolem |
| [x] | `buildings/studna.png` | 1×1 | 26×32 | kamenná studna se stříškou a věděrkem |
| [x] | `buildings/statek.png` | 2×1 | 36×41 | obytná část a stodola, seno |
| [x] | `buildings/hospoda.png` | 2×1 | 36×41 | hostinec s cedulí, lavicí a sudem |
| [x] | `buildings/kurnik.png` | 2×2 | 46×52 | kurník s ohrádkou a slepicemi |
| [x] | `buildings/chliv.png` | 2×2 | 46×52 | chlév s ohrádkou, blátem a prasaty |
| [x] | `buildings/ohrada.png` | 4×3 | 76×65 | ohrada s ploty, napajedlem, stodůlkou a kravami |
| [x] | `buildings/staje.png` | 3×2 | 56×64 | stáje s koňmi a vozem se senem |
| [x] | `buildings/hrbitov.png` | 3×3 | 66×60 | hřbitov se zdí, bránou, kříži, náhrobky a hrobkou |
| [x] | `buildings/kostel.png` | 2×2 | 46×87 | kamenný kostel s věží (nejvyšší budova) |
| [x] | `buildings/zahrada.png` | 3×2 | 56×55 | zeleninová zahrada se záhony, plotem a strašákem |
| [x] | `buildings/sklep.png` | 2×2 | 46×52 | zemní sklep s dveřmi do svahu |
| [x] | `buildings/udirna@4.png` | 2×2 | 46×64 | udírna s komínem a kouřem (4 snímky) |
| [x] | `buildings/milir@4.png` | 2×2 | 46×58 | milíř, hromada dřeva zakrytá drnem, kouř (4 snímky) |
| [x] | `buildings/chmelnice.png` | 3×3 | 66×60 | chmelnice s vysokými tyčemi, drátem a popínavým chmelem |
| [x] | `buildings/sladovna.png` | 2×2 | 46×52 | sladovna s humnem, věžičkou a komínem |
| [x] | `buildings/pivovar@4.png` | 2×2 | 46×66 | pivovar s komínem, měděným kotlem a sudy (4 snímky, kouř a pára) |
| [x] | `buildings/kovarna@4.png` | 2×2 | 46×52 | kovárna s výhní, kovadlinou a jiskrami (4 snímky) |
| [x] | `buildings/dul.png` | 2×2 | 46×52 | vchod do štoly s podpěrami, kolejemi a vozíkem |
| [x] | `buildings/vinice.png` | 3×3 | 66×60 | řady vinné révy na kůlech s hrozny |
| [x] | `buildings/lis.png` | 2×2 | 46×52 | lisovna se šroubovým lisem a sudy |
| [ ] | `buildings/vcelin.png` | 2×2 | 46×40 | včelín (fáze G): řada slaměných košů na dřevěných stolcích, nízký plůtek, včely; zatím náhradní kresba z kódu |
| [ ] | `buildings/cihelna.png` | 2×2 | 46×52 | cihelna (fáze G): pec na pálení cihel s komínem a kouřem, hromada hlíny a sušící se cihly na prknech |
| [ ] | `buildings/lazen.png` | 2×2 | 46×52 | lázeň (fáze G): nízká roubená lázeň s komínem a párou, necky před vchodem |

Dříve nakreslil `gen_sprites.py`: `spychar`, `mlekarna`, `rybnik`, `kozeluzna`, `tkalcovna`, `fara` (lze přepsat lepšími).

## 2. Domy (5, hotovo)

| Hotovo | Soubor | Půdorys | Rozměr | Co je nakresleno |
|---|---|---|---|---|
| [x] | `houses/home_tile.png` | 1×1 | 40×40 | hrázděný dům s červenou taškovou střechou, komín |
| [x] | `houses/home_thatch.png` | 1×1 | 40×40 | srubový dům se slámovou střechou, komín |
| [x] | `houses/home_new.png` | 1×1 | 40×40 | nově postavený dům (světlejší dřevo a taška), dříví |
| [x] | `houses/cottage.png` | 1×1 | 34×34 | menší omítnutý domek pro dva se slámovou střechou |
| [x] | `houses/chapel.png` | 1×1 | 36×56 | kaple s břidlicovou střechou a zvoničkou s křížkem |

## 3. Stromy (hotovo včetně sněhových)

| Hotovo | Soubor | Rozměr | Co je nakresleno |
|---|---|---|---|
| [x] | `trees/tree_0.png` | 22×34 | smrk, pata kmene dole uprostřed |
| [x] | `trees/tree_1.png` | 24×34 | listnatý strom (světlejší koruna) |
| [x] | `trees/tree_2.png` | 22×40 | vyšší smrk |
| [x] | `trees/tree_0_snow.png`, `tree_1_snow.png`, `tree_2_snow.png` | stejně | stejné stromy se sněhem |

## 4. Rekvizity (4, hotovo)

| Hotovo | Soubor | Rozměr | Co je nakresleno |
|---|---|---|---|
| [x] | `props/quarry.png` | 70×50 | lom: skalní stěna, vchod, kameny, jeřáb, vozík |
| [x] | `props/stall_red.png` | 30×36 | trhový stánek s červeno-bílou plachtou |
| [x] | `props/stall_green.png` | 30×36 | trhový stánek se zeleno-bílou plachtou |
| [x] | `props/fishing_hut.png` | 40×44 | rybářská chýše se sítěmi, molem a člunem |

## 5. Zimní varianty budov (hotovo)

[x] `_snow` varianty mají všechny budovy a domy kromě rostlinných (chmelnice, vinice, zahrada) a rybářská chýše; sněhové stromy
už byly hotové. Vznikají automaticky skriptem `python -I grafika/gen_zima.py` z letních obrázků (sníh na střechách, zasněžený
trávník a dvorek, zamrzlá voda); po změně letního obrázku skript spusť znovu. U animovaných budov je to pás
`<klíč>_snow@4.png`. Zimní verzi lze případně dokreslit ručně a skript pak pro daný klíč nespouštět (přepsal by ji).

## 6. Animace

Hotovo: `mlyn@4` (točící se kolo), `kovarna@4` (žár a jiskry), `udirna@4`, `milir@4`, `pec@4` (kouř z komína), `pivovar@4`
(kouř z komína a z kotle). Vodorovný pás N snímků, 4 snímky za sekundu, každý snímek má stejnou šířku a kotvu.

## 7. Zatím jen z kódu (potřebují nejdřív úpravu `map.js`, nezačínat)

Terén (tráva, cesty, pole, řeka, most, skály), lidé a jejich animace chůze, zvířata (slepice, prase, kráva, kůň), ploty a zdi,
lešení rozestavěných staveb, oheň a povodeň. Až bude pro ně v mapě souborový druh, přibudou do tohoto seznamu.

## K doladění

Obrázky jsou kreslené skriptem (Pillow), ne ručně: dobře drží měřítko, kotvu a paletu, ale detaily (zvířata, nářadí, rybářská chýše,
hřbitov, pec) by zlepšil pixel artist. Na hře je potřeba ověřit hlavně překryv sousedů u velkých budov (ohrada, hřbitov, chmelnice)
a čitelnost při běžném přiblížení.
