# Koordinace agentů (Claude + Codex) na Minilidech

Sdílený soubor pro souběžnou práci. Přečti ho na začátku práce a aktualizuj na konci. Nemá žádný přímý kanál mezi agenty: jediná spojka je tato složka a uživatel. Soubor uchovává jen to, co není vidět z kódu: kdo co drží, dohodnutá rozhraní a rozhodnutí.

## Pravidla
1. **Před zápisem soubor znovu načti** a edituj jen své řádky (ne přepis celého souboru). Neprováděj hromadné formátování cizích souborů.
2. **Zámky souborů:** Soubor v tabulce níže patří agentovi, který je uvedený. Cizí soubor neupravuj. Potřebuješ-li změnu, zapiš žádost do sekce „Žádosti“ a nech ji udělat majitele.
3. **Rozhraní jsou smlouva:** Změna formátu stavu světa nebo API (`api.php`) se nejdřív zapíše do sekce „Rozhraní“ a teprve potom se použije.
4. **Zálohuj před větší změnou** do `E:\AI2\complete-backups\` (ZIP nebo složka). Data v `prototyp\data` se nemažou ani nepřepisují.
5. **Běžící procesy** (spouštěč, worker, web na 8093) restartuje jen ten, komu to uživatel výslovně dovolil. Po restartu zapiš do „Stav běhu“.
6. **Testy:** Před předáním spusť `tests\overeni.php` (a `tests\rozvoj.php`, až existuje). Nic neoznačuj za hotové bez ověření.

## Zámky souborů (aktualizuj při převzetí)
| Soubory | Majitel | Poznámka |
|---|---|---|
| `prototyp\world.php`, `jobs.php`, `auto.php`, `ai.php`, `faith.php`, `social.php`, `weather.php`, `family.php`, `worker.php`, `public\api.php` | Claude | simulace a API; plánovaný rozvoj (ekonomika, život, krize, cíle, souhrny) |
| nové: `economy.php`, `life.php`, `crisis.php`, `goals.php`, `summary.php`, `tests\rozvoj.php`, `tests\zatez.php` | Claude | |
| nové stránky `public\rozvoj.html`, `rozvoj.js`, `rozvoj.css` | Claude | |
| `public\village.js`, `village-panel.css`, `village.css`, `index.html`, `vesnice.html`, `prehled*.html`, `overview.*`, `vira.*`, `schema.*`, `comparison.js`, `season-clock.js` | Codex | GUI mapy a kronika |
| `public\map.js` | **sdíleno, žádost nutná** | Claude potřebuje přidat sprity nových budov; viz Žádosti |

## Rozhraní (smlouva mezi simulací a GUI)
Stav světa vrací `api.php?branch=ai|control` jako JSON (celý `world`). Nové klíče, které Claude přidává (vše volitelné, GUI musí chybějící pole snést):
- `stock`: `{grain, flour, planks, hay, tools}` (čísla), vedle stávajících `food`, `wood`, `stone`
- `buildings`: `{mlyn:minuta, pec:minuta, pila:..., dilna:..., studna:..., statek:...}` (kdy byla budova dokončena)
- `project`: `null` nebo `{key, label, progress, work, paid}` (rozestavěná stavba)
- `crisis`: `null` nebo `{type, label, start, end, need, done}`; `harvestFactor` a `harvestUntil`
- `goals`: `{done:{klíč:minuta}, main:{key,label,current,target}}`
- osoby: `sex`, `birthDay`, `spouse`, `pregnant` (`null` nebo `{due,since,revealed}`), `lastBirth`
- `graveyard`: seznam `{id,name,age,cause,minute}`
- `view=development` navíc vrátí `weekly` (týdenní souhrny), `buildingDefs`, `goalDefs`
Dokud majitel GUI nepřidá zobrazení, bude vše vidět na nové stránce `rozvoj.html` (Claude).

## Žádosti
- (Claude → Codex, `map.js`) Přidat vykreslení nových budov podle `state.buildings` (mlýn, pec, pila, dílna, studna, statek) a čtyři parcely pro domy místo dvou. Souřadnice a styl nech na Codexovi (jen nesmí zakrýt silnice), nebo napiš do sekce níže, ať je udělám já.
- (Claude → Codex, `village.js`) Při úmrtí postavy zmizí z `state.people`, `roster` už to řeší. K věku v kartě přidej příznak těhotná (`person.pregnant && person.pregnant.revealed`) a nápis „zemřel/a“ nepotřeba.

## Stav běhu
- (doplní ten, kdo restartuje) čas, PID, co se právě měřilo.

## Rozhodnutí uživatele
- Cíl hry: přežít a rozrůst vesnici. Krize: požár, potopa, špatná úroda, výjimečně. Celý životní cyklus (věk, těhotenství, porody realisticky, smrt vzácně: stáří, nemoc, úraz). Ekonomika: obilí → mlýn → mouka → pec → chléb; dřevo → pila → prkna → dílna; statek, studna, seno. Doba: 1 den = 20 minut zůstává. PC běží nepřetržitě, pauza zatím ne.
