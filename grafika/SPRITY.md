# Pixel art Minilidí: specifikace obrázků

Mapa vesnice (`prototyp/public/map.js`) kreslí vše z kódu. Každou budovu, dům, strom a rekvizitu lze nahradit PNG obrázkem
ve složce `prototyp/public/sprites/`. Stačí soubor přepsat nebo přidat: server (`sprites.php`) hlásí seznam souborů a mapa
si ho každé 4 s načte znovu, takže se obrázek ve hře vymění sám, bez restartu a bez obnovení stránky.
Chybějící obrázek nahradí původní kresba z kódu, takže se dá měnit po kusech.

Styl: pixel art ve stylu Graveyard Keeper a Stardew Valley, tedy teplé zemité barvy, tmavý obrys, měkké ruční stínování,
čitelná silueta, drobné detaily (záplaty, hrany, zrnitost dřeva, mech na střeše).

## Pevná technická pravidla

| Co | Hodnota |
|---|---|
| Formát | PNG s průhledným pozadím (RGBA), bez polopropustných bodů kromě volitelného měkkého stínu na zemi |
| Měřítko | 1 bod obrázku = 2×2 body mapy (`scale: 2`); výchozí, jde změnit v `sprites/manifest.json` |
| Políčko mapy | izometrické 2:1, 46×22 bodů mapy = **23×11 bodů obrázku** |
| Půdorys | `w` políček doprava dolů a `d` políček doleva dolů (viz tabulka); diamant půdorysu je široký `(w+d)×23` a vysoký `(w+d)×11` bodů mapy |
| Kotva | **spodní střed obrázku = přední (dolní) roh půdorysu** budovy; u domů, stromů a rekvizit je to pata |
| Světlo | zleva shora: levá stěna střední, pravá stěna tmavá, střecha nejsvětlejší |
| Obrys | 1 bod, tmavě hnědý (`#2b211d`), jen po vnější siluetě |
| Paleta | 3 až 4 tóny na materiál (světlo, střed, stín, volitelně odlesk); celkem do 32 barev na obrázek |
| Vyhlazování | žádné (ani při tvorbě, ani v exportu), hrany bodově ostré |

Budova má být o něco menší než její diamant půdorysu (střecha smí přesahovat o 1 až 2 políčka obrázku), aby nepřekrývala sousedy.
Výška: ke štítu stačí 25 až 40 bodů obrázku u domu, 50 až 70 u kostela; vyšší věže jen u kostela a fary.

## Názvy souborů a složky

`prototyp/public/sprites/<druh>/<název>.png`, názvy jen malými písmeny, číslicemi a podtržítkem.

- **buildings/** – klíč budovy z hry (půdorys `w×d`):

  | klíč | půdorys | klíč | půdorys | klíč | půdorys |
  |---|---|---|---|---|---|
  | mlyn | 2×1 | pec | 1×1 | pila | 2×1 |
  | dilna | 1×1 | studna | 1×1 | statek | 2×1 |
  | hospoda | 2×1 | kurnik | 2×2 | chliv | 2×2 |
  | ohrada | 4×3 | staje | 3×2 | hrbitov | 3×3 |
  | kostel | 2×2 | zahrada | 3×2 | sklep | 2×2 |
  | udirna | 2×2 | milir | 2×2 | chmelnice | 3×3 |
  | sladovna | 2×2 | pivovar | 2×2 | kovarna | 2×2 |
  | dul | 2×2 | vinice | 3×3 | lis | 2×2 |
  | spychar | 2×2 | mlekarna | 2×2 | rybnik | 4×3 |
  | kozeluzna | 2×2 | tkalcovna | 2×2 | fara | 2×2 |
  | vcelin | 2×2 | cihelna | 2×2 | lazen | 2×2 |

- **houses/** – `home_tile` (dům s taškovou střechou), `home_thatch` (se slámou), `home_new` (nově postavený dům), `cottage` (domek pro dva), `chapel` (kaple); půdorys 1×1.
- **trees/** – `tree_0`, `tree_1`, `tree_2` (varianty podle polohy) nebo jednotně `tree`; pata kmene je kotva.
- **props/** – `quarry` (lom), `stall_red` a `stall_green` (stánky na návsi), `fishing_hut` (rybářská chýše).

Varianty:
- `<název>_snow.png` se použije za sněhu (jinak `<název>.png`).
- `<název>@N.png` je vodorovný pás N stejně širokých snímků (animace, např. `kovarna@4.png`); výchozí rychlost 4 snímky za sekundu.
- Počet snímků se bere z `@N`, šířka jednoho snímku = šířka souboru / N; všechny snímky mají stejnou kotvu.

## manifest.json (volitelné)

```json
{
  "defaults": { "scale": 2, "fps": 4 },
  "sprites": {
    "buildings/kostel": { "scale": 2, "anchor": [30, 70], "offset": [0, 0] },
    "buildings/kovarna": { "fps": 6 }
  }
}
```

`anchor` je bod v obrázku (v jeho bodech) položený na kotvu mapy, výchozí je spodní střed; `offset` posune obrázek v bodech mapy.

## Co zatím zůstává z kódu

Terén (tráva, cesty, pole, řeka, most), lidé, zvířata, ploty, schodiště stavby (lešení), oheň, povodeň, déšť, vítr.
Měnit je lze až po rozšíření mapy o další druhy souborů (viz TODO v `map.js`, funkce `trySprite`).

## Postup tvorby a kontrola

1. Nakresli obrázek ručně nebo skriptem: `python -I grafika/gen_vse.py` vykreslí všechny budovy, domy, stromy a rekvizity
   (jednotlivě `gen_budovy.py`, `gen_budovy2.py`, `gen_ostatni.py` s klíči jako argumenty, kreslicí funkce jsou v `pixlib.py`);
   `gen_sprites.py <klíč>` je původní ukázka stylu pro budovy fáze F. Animovaný obrázek ukládej jen jako pás `<klíč>@N.png`,
   statický `<klíč>.png` vedle něj nesmí ležet (hra bere `druh/klíč` bez ohledu na `@N`).
2. Ulož do správné složky; kontrola v prohlížeči: `http://127.0.0.1:8093/sprity.html` (přehled všech klíčů, chybějící jsou označené) a mapa hry.
3. Zkontroluj: kotva na předním rohu půdorysu, nepřekrývá sousedy, čitelná v běžném přiblížení, obrys vnější, žádné polopropustné body.
4. Přehled v `sprity.html` ukazuje, které klíče už mají obrázek.

## Prompt pro generování PNG souborů (pro AI agenta)

Výstupem nejsou popisy ani náčrty, ale **hotové soubory PNG** uložené na disk. Agent musí mít nástroj pro zápis souborů a spouštění kódu
(nebo vlastní generátor obrázků, který umí uložit PNG). Seznam úkolů je v `grafika/SEZNAM.md`.

> Jsi pixel artist hry Minilidi (vesnice kolem roku 1550, český venkov). Vytvoř **soubory PNG**, ne popis.
>
> Úkol: nakresli obrázky [KLÍČE ZE SEZNAMU, např. kostel, mlyn, home_tile] a ulož je jako PNG do
> `E:\AI2\Minilidi\prototyp\public\sprites\<druh>\<klíč>.png` (druh = buildings, houses, trees nebo props).
> Přečti si nejdřív `grafika/SPRITY.md` a `grafika/SEZNAM.md` (rozměry, půdorysy, kotva, názvy) a ukázky v `grafika/gen_sprites.py`.
>
> Jak: napiš Python skript s Pillow (`python -I`), který nakreslí každý obrázek bod po bodu v plné velikosti souboru
> (`Image.new('RGBA', (šířka, výška), (0,0,0,0))`, kresli `ImageDraw` bez vyhlazování), a ulož ho voláním `image.save(cesta)`.
> Skript uložíš do `grafika/` (např. `gen_<skupina>.py`), soubory piš do cesty z proměnné prostředí `SPRITES_OUT`, když je nastavená.
> Pokud kreslíš jiným nástrojem, výsledek musí být stejně PNG ve stejném rozměru, nikdy JPEG a nikdy obrázek se zvětšeným měřítkem.
>
> Styl: izometrický pohled 2:1 (políčko mapy je 23×11 bodů obrázku), styl Graveyard Keeper a Stardew Valley, teplé zemité barvy,
> tmavý obrys 1 bod (`#2b211d`) jen po vnější siluetě, tři až čtyři tóny na materiál, světlo zleva shora (levá stěna střední,
> pravá tmavá, střecha nejsvětlejší), konkrétní materiály (dřevo s prkny, kámen se spárami, taška nebo došky), 2 až 3 drobné
> detaily (okno, komín, rekvizita u vchodu). Spodní střed obrázku je přední roh půdorysu budovy. Pozadí průhledné, žádné polopropustné body,
> žádný stín mimo budovu, žádné vyhlazování.
>
> Po uložení **ověř soubory kódem**: PNG se otevře, režim je RGBA, rozměr odpovídá seznamu, všechny hodnoty alfa jsou 0 nebo 255,
> počet barev nejvýš 32, spodní řádek obrázku není celý průhledný. Vypiš cesty uložených souborů a rozměry. Nic dalšího neměň,
> kód hry, databázi ani běžící hru neovládej.
>
> Na konci označ hotové položky v `grafika/SEZNAM.md` (`[x]`) a napiš, co zůstává k doladění.

### Ověření souborů (hotový skript pro agenta)

```python
from PIL import Image
import sys
for path in sys.argv[1:]:
    im = Image.open(path)
    assert im.mode == 'RGBA', path + ': není RGBA'
    alphas = {a for *_, a in im.getdata()}
    assert alphas <= {0, 255}, path + ': polopropustné body ' + str(sorted(alphas)[:5])
    colors = {p for p in im.getdata() if p[3]}
    assert len(colors) <= 32, path + ': ' + str(len(colors)) + ' barev'
    bottom = [im.getpixel((x, im.height - 1))[3] for x in range(im.width)]
    assert any(bottom), path + ': spodní řádek je prázdný (kotva)'
    print('OK', path, im.size, len(colors), 'barev')
```

Spuštění: `python -I grafika/over_sprity.py <soubory.png>` (skript je stejný jako blok výše).