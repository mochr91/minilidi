# Handoff pro nové vlákno (Minilidi, stav 15. 11. 2026 11:57)

Vlož tento soubor do nového vlákna a řekni „pokračuj podle HANDOFF-PRO-NOVE-VLAKNO.md“. Stručně: co běží, co je hotové, pravidla, recepty, otevřené věci.

## 1. Co je Minilidi
Simulace vesnice (PHP 8.5 + SQLite + Ollama `gemma4:e4b`) v `E:\AI2\Minilidi\prototyp`. Hra `http://127.0.0.1:8093`, tabule úkolů `http://127.0.0.1:8091`. Dvě větve: `ai` (`data\vesnice.sqlite`) a `control` (pravidla bez AI, `.bez-ai.sqlite`). Další agent (Codex) upravuje živé soubory souběžně: před zápisem soubor znovu načti, porovnej hash/čas, nepřepisuj jeho verze. Společný handoff je `KOORDINACE.md` (vlastnictví souborů, rozhraní bod 4, záznam prací bod 5). Pravidla agentů jsou v `AGENTS.md` (git commit a sloučení řeší uživatel; PHP bez `mb_*`; při změně stavu světa doplnit výchozí hodnoty do `decodeWorld`).

## 2. Stav živé hry (po restartu 15. 11. 11:56, na pokyn uživatele)
- Obě větve běží s kódem fáze A až F, nové PHP procesy od 11:56. Den hry zhruba 117 (AI 19 lidí, bez AI 20), 24 budov v obou (stojí do lisu; vesnice bez AI ještě nemá lis), žádný projekt zrovna neběží.
- Chybový log `prototyp\data\provoz-chyby.log` bez nových záznamů (poslední 12. 11.).
- Restart: jen s výslovným pokynem uživatele. Tlačítko spouštěče: `SendMessage(hwnd 853472 "Restartovat", 0x00F5,0,0)`; ověřit text tlačítka přes `GetWindowText` (hwnd: Spustit 1116460, Zastavit 2099834, Otevřít hru 1968422, spouštěč PowerShell PID 13024). `Add-Type` musí být ve stejném volání PowerShellu jako `SendMessage` (typ se mezi voláními neuchová). Po restartu ověř `api.php?branch=ai|control` a `provoz-chyby.log`.
- Oprava zásobování kamenem (`dev11` ve scratchpadu) je nenasazená, uživatel ji odložil („Zatím ne“). Opíjení pivem a vínem není, otevřená otázka.

## 3. Co je hotovo (fáze rozšíření ekonomiky, podrobnosti `ROZSIRENI-EKONOMIKY.md` a `KOORDINACE.md` bod 5)
A zima a zásoby, B les a vůz a uhlí, C pivo, bydlení (domy po 6 lidech, domky po 4, zasnoubení, svatby v kapli či kostele, dvojčata, mrtvě narozené), D kovárna a důl a obchodník a úrok, E vinice a víno, **F (15. 11.)**: špýchar, mlékárna, rybník, koželužna a ševcovna, tkalcovna, fara a škola (`crafts.php`).
Pevné pořadí staveb `BUILD_ORDER` končí: kostel, vinice, lis, spychar, mlekarna, rybnik, kozeluzna, tkalcovna, fara; každá se staví z vlastních zásob (`BUILD_PREPAY`).
Fáze F: špýchar (kažení obilí .9993 a mouky .997 místo .9985 a .994), mléko → sýr (část jídla), rybník (obsádka 8, výlov říjen až listopad), kožky → useň → boty (v zimě −10 % poklesu nálady a nemocí při 0,3 páru na osobu), len 6 % úrody z pole → látka (obchodník ji s tkalcovnou nevozí), fara: lekce `teach` denně mimo neděli (děti 6 až 13 let +1,4 bodu zkušenosti v každém oboru).
Mapa: o 3 dlaždice širší na východě a delší na jihu (`X0..X1 = -2..34`, `Y0..Y1 = -2..30`, plátno 1614×821, CSS poměr `1614 / 821`), druhý most na řádku 28.

## 4. Grafika (hlavní otevřená práce)
- Mapa (`prototyp\public\map.js`) kreslí vše z kódu, ale každou budovu, dům, strom a rekvizitu nahradí PNG ve `prototyp\public\sprites\<buildings|houses|trees|props>\<název>.png`. `sprites.php` hlásí soubory, mapa je každé 4 s znovu načte (přepsaný soubor se vymění sám). Varianty `<název>_snow.png`, animace `<název>@N.png`, volitelný `sprites\manifest.json`. Měřítko 2, kotva = spodní střed na předním rohu půdorysu, políčko 23×11 bodů obrázku.
- Hotové: šest budov fáze F (`grafika\gen_sprites.py`, Python 3.10 + Pillow 12, spouštět `python -I`; výstup do kopie přes proměnnou `SPRITES_OUT`). Ostatní zůstává z kódu: 24 budov, domy (`home_tile`, `home_thatch`, `home_new`, `cottage`, `chapel`), stromy, rekvizity (`quarry`, `stall_red`, `stall_green`, `fishing_hut`). Terén, lidé, zvířata, ploty, lešení, oheň a povodeň zatím sprity nepodporují (rozšíření = další druh souborů a hook ve funkci `trySprite`).
- Podklady: `grafika\SPRITY.md` (pravidla, tabulka klíčů, prompt na konci), `grafika\SEZNAM.md` (seznam úkolů a rozměrů, hotové `[x]`), `grafika\over_sprity.py` (kontrola PNG), skill `.claude\skills\pixel-art-sprity`, agent `.claude\agents\pixel-artist.md`, přehled `http://127.0.0.1:8093/sprity.html`.
- Dvě odštěpená vlákna z této konverzace dělala prompt a seznam grafik („Simulace analýza a vylepšení (fork)“ a „fork 2“). Co přesně změnila, je v souborech výše (`SPRITY.md`, `SEZNAM.md`, `over_sprity.py`, skill a agent byly upraveny po mém zápisu); před navázáním je přečti.
- Poznámka: staré budovy mají měřítko ~0,74 oproti půdorysu, nové obrázky jsou kreslené s `HW,HH = 10,5` a výškou ×0,85, aby se k nim vešly. Při kreslení ostatních zkontroluj měřítko vůči sousedům na mapě.

## 5. Recepty a pasti
- **Vývoj:** zkopíruj živé `prototyp` (bez `data`) do scratchpadu (naposledy `dev17`), edituj tam, spusť sady `scratchpad\run-tests.ps1 -Root <kopie>` (24 sad, trvá několik minut, výstup až na konci), simulace `sim-f.php <kořen> <dny> <výstup>` (od nuly), `upgrade-f.php <kořen> <snímek.sqlite> <dny> <výstup>` (z živého snímku; snímek `vac.php <cíl>` = `VACUUM INTO`). `php -n -d extension_dir=<php>\ext -d extension=pdo_sqlite -d extension=curl`, PHP je `D:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64\php.exe`.
- **Nasazení:** záloha originálů do `E:\AI2\complete-backups\minilidi-<co>-pred-nasazenim-<datum>`, kopírovat jen změněné soubory, před kopií porovnat živé časy proti vzniku kopie (Codex), zvýšit verze skriptů v HTML (`index.html`, `vesnice.html`, `prehled.html`, `rozvoj.html`; aktuálně map.js 33, village.js 37, village-panel.css 17 v index a 14 ve vesnice, rozvoj.js 14, dashboard-overview.js 9), ověřit hashe, PHP lint a `node --check`. Dočasné soubory (`save-shot.php` apod.) nikdy nenasazovat.
- **Vizuální kontrola:** prohlížečové okno je skryté, screenshoty jsou zastaralé. Spusť `php -S 127.0.0.1:<port> -t <kopie>\public` s `MINILIDI_DB=<snímek>` (a kopií `.bez-ai.sqlite`), dočasný `save-shot.php` (POST z `canvas.toDataURL`) a výsledek načti jako PNG; před snímkem vynutit vykreslení screenshotem.
- **Soubory:** nepoužívej `Set-Content -Encoding UTF8` (BOM), edituj nástrojem Edit nebo pomocnou funkcí, která zachová konce řádků (smíšené LF/CRLF). Škálování plátna CSS-em je proti ostrosti pixel artu, proto obrázky kreslit ve 2násobném měřítku.
- **Databáze:** živou DB nikdy nemazat ani neresetovat bez výslovného pokynu; testy jedou nad paměťovou DB nebo kopií.
- **Tabule** (`http://127.0.0.1:8091/api.php`): karta se vytvoří `POST` s `title, kind:task, category:gameplay, status, priority, assignee, block:"G · Rozšíření ekonomiky", order, body, accept`; kategorie `graphics` neexistuje (422). Karty kindu `think` rozhoduje uživatel. Na tabuli jsou hotová karta fáze F a nové: „Pixel art grafika vesnice (jiné vlákno)“, „Sledovat nové budovy fáze F po restartu“, „Sledovat kovárnu, obchodníka, úrok a vinici“, „první pivní sezóna“, „bydlení, svatby a narození“.

## 6. Co dál (návrh, nic není schválené jako úkol)
1. Dokreslit grafiku podle `grafika\SEZNAM.md` (v jiném vlákně, aby se šetřily tokeny).
2. Po restartu sledovat: stavbu šesti nových budov (do tří týdnů hry), boty a látku v zimě, výlov rybníka na podzim, sýr, úrok na sv. Michala a sv. Martina, první víno (třetí rok), hlad (nemá být).
3. Rozhodnutí uživatele: opíjení pivem a vínem, nasazení opravy kamene (`dev11`), další řemesla (včelín, lázeň, kolárna, cihelna), rozšíření spritů o terén, lidi a zvířata.
4. Commit a sloučení větví (`claude/prace`, `codex/prace`, `main`) čeká na uživatele.

## 7. Zálohy
`E:\AI2\complete-backups\`: `minilidi-faze-f-pred-nasazenim-20261115` (originály před fází F), `minilidi-kovarna-vino-pred-nasazenim-20261114`, `minilidi-bydleni-…`, `minilidi-pivo-…` a starší.
