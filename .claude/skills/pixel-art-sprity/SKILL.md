---
name: pixel-art-sprity
description: Tvorba pixel art obrázků (budovy, domy, stromy, rekvizity) pro mapu Minilidí ve stylu Graveyard Keeper a Stardew Valley, uložených do prototyp/public/sprites/ tak, aby je hra sama vyměnila za původní kresbu z kódu. Použij při pokynu "nakresli / vytvoř / přepracuj obrázek budovy, domu, stromu" nebo "udělej grafiku vesnice".
---

# Pixel art sprity Minilidí

Výstupem jsou soubory PNG (ne popis); seznam úkolů a rozměrů je v `grafika/SEZNAM.md`, kontrola souborů `python -I grafika/over_sprity.py <soubory>`, hotový prompt pro agenta je na konci `grafika/SPRITY.md`.

Před prací přečti `grafika/SPRITY.md` (pevná technická pravidla, názvy souborů, kotva, manifest) a podívej se na ukázky ve
`grafika/gen_sprites.py`. Nic z tohoto souboru nenahrazuje specifikaci: je to jen postup.

## Postup

1. Zjisti klíč a půdorys (tabulka v `grafika/SPRITY.md`); pokud klíč není v hře, nejdřív ho přidá vývojář do `FOOT` v `map.js`.
2. Rozhodni rozměr obrázku v bodech (měřítko 2): šířka zhruba `(w+d)*11,5`+přesah střechy, výška podle budovy; spodní střed je přední roh půdorysu.
3. Kresli skriptem (Python + Pillow, ukázka `gen_sprites.py`, funkce `Canvas`, `box`, `gable_roof`, `finish` s obrysem) nebo ručně v pixel editoru.
   Nový skript ulož do `grafika/`, výstup piš přes proměnnou prostředí `SPRITES_OUT` do kopie projektu, ne rovnou do živé složky.
4. Dodrž styl: tmavý obrys `#2b211d` jen po vnější siluetě, 3 až 4 tóny na materiál, světlo zleva shora, bez vyhlazování,
   bez polopropustných bodů, teplé zemité barvy, čitelná silueta, 2 až 3 charakteristické detaily (okno, komín, rekvizita u vchodu).
   Střecha: světlý svah vlevo, tmavý vpravo; dřevo se vzorem prken, kámen s nepravidelnými spárami.
5. Ověř v prohlížeči (stránka `sprity.html`, mapa hry): kotva, měřítko proti sousedním budovám, překryvy, sníh (`_snow`), animace (`@N`).
6. Zapiš nové klíče do přehledu v `grafika/SPRITY.md`, pokud jsi přidal nový druh.

## Zásady

- Vlastní PNG přepisuje jen soubory ve složce `sprites/`; kód mapy se nemění, pokud to zadání výslovně nechce.
- Živou hru neotevírej ani nerestartuj bez pokynu uživatele; pro náhled použij kopii a vlastní port.
- Jeden obrázek = jedna budova nebo předmět; žádné kreslení do terénu ani lidí, dokud nejsou pro ně souborové druhy.
- Nikdy nemaž ostatní obrázky; po práci vypiš seznam nových nebo změněných souborů.
