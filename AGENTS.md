# Minilidi

Před prací otevři sdílený [handoff](KOORDINACE.md). V místní pracovní kopii použij živý soubor E:\AI2\Minilidi\KOORDINACE.md. V cloudové kopii použij verzi z repozitáře a pokyny v zadání.

Drž se vlastníka souborů a domluveného API (body 2 a 4 handoffu). Před zápisem znovu načti soubor, který může souběžně upravovat druhý agent. Úkoly najdeš na tabuli (bod 6 handoffu): zadání pro sebe načteš z `http://127.0.0.1:8091/api.php?format=md&assignee=claude` nebo `...assignee=codex`, po dokončení zapíšeš výsledek, změněné soubory a ověření do bodu 5 handoffu a kartu přepneš na hotovo (`POST api.php` s `{"id":"...","patch":{"status":"done","result":"..."}}`). Karty „K promyšlení“ rozhoduje uživatel, ne agent.

Pro test simulace použij vlastní port a databázi mimo živou složku prototyp\data, příkazy jsou v bodě 7 handoffu. Běžící hru a procesy restartuj jen s výslovným pokynem uživatele. Commit a sloučení do main domlouvá uživatel. Postup pro společnou práci je v [SPOLUPRACE.md](SPOLUPRACE.md).

PHP běží bez rozšíření mbstring, nepoužívej funkce `mb_*`. Při každé změně formátu stavu světa doplň výchozí hodnoty do `decodeWorld`, aby se načetl i starý uložený stav.
