# Minilidi

**Fáze: hratelný prototyp.** Běží simulace dvou vesnic (s místní AI a jen s pravidly), mapa, kronika, víra, rozvoj (ekonomika, životní cyklus, krize, cíle) a týdenní srovnání. Koncept a technologie se stále ladí. Rozdělení práce mezi agenty, stav běhu, rozhraní a výsledky ověření jsou v [KOORDINACE.md](KOORDINACE.md). Níže jsou původní návrhové podklady a popis hry.

[Herní koncept](KONCEPT.md) · [Počasí a roční období](POCASI-A-OBDOBI.md) · [Katalog činností a jejich stav](UKOLY-1400-1600.md)

Hru zobrazuje prohlížeč. Místní PHP server přijímá příkazy, samostatný proces posouvá simulaci a každá vesnice ukládá vlastní stav do SQLite. Model počítá Ollama, pokud je povolená místní AI. Spouštěč používá PHP z Laragonu a databáze SQLite, takže samotná hra nepotřebuje vlastní instalátor ani MySQL.

## Návrhová tabule

Spusť [SPUSTIT-TABULI.cmd](SPUSTIT-TABULI.cmd) a spouštěč otevře [místní tabuli](http://127.0.0.1:8091/) v prohlížeči. Využije existující PHP z Laragonu, poslouchá pouze na tomto počítači a funguje bez Ollamy. Po zavření okna „Minilidi - mistni server“ přestane být stránka dostupná; návrhy zůstanou uložené.

Návrhy a úkoly upravuj na tabuli. Uložené karty zůstávají dostupné i po zastavení serveru a dalším spuštění.

## Hratelný prototyp

Dvojklikem otevři [Minilidi.exe](Minilidi.exe) ve složce hry. **Spustit** zapne simulaci a otevře hru v prohlížeči. **Zastavit** i zavření okna ukončí web a simulaci. **Restartovat** hru zastaví a znovu spustí z uloženého stavu obou vesnic. **Otevřít hru** otevře stránku běžící simulace. Další dvojklik na exe obnoví minimalizované ovládací okno.

Soubor exe používá existující PHP z Laragonu a skripty ve složce `prototyp`. Můžeš si na plochu dát zástupce, soubor exe nech vedle složky `prototyp`.

Volba **Používat místní AI a GPU** zapíná samostatný proces Ollamy pro Minilidi. Při změně volby za běhu spouštěč krátce restartuje hru a naváže z uloženého stavu. Po vypnutí volby se proces Ollamy pro Minilidi ukončí a vesnice pokračují podle pravidel. Výchozí model je `gemma4:e4b`. Pokud model neodpoví, zvolí reakci hra. Model lze nastavit proměnnou `MINILIDI_MODEL`. Chyby PHP se zapisují do `prototyp/data/provoz-chyby.log`.

Klikni na postavu na mapě nebo pod ní a vyber vnuknutí. V kronice sleduj reakci i skutečné dokončení úkolu. V kronice můžeš filtrovat historii podle postavy. Herní den má 20 skutečných minut a třicetidenní měsíc 10 hodin. Společný ciferník ukazuje denní dobu, polohu v roce a počasí. Po delším uspání nebo vypnutí hra naváže z posledního uloženého času.

Při přímém spuštění `prototyp/spustit.php` přes PHP s rozšířeními pdo_sqlite a curl lze použít i místní LM Studio nebo llama.cpp s rozhraním OpenAI. V konzoli nastav `MINILIDI_AI=openai`, `MINILIDI_MODEL` na identifikátor modelu a případně `MINILIDI_AI_PORT`. Výchozí port tohoto rozhraní je 1234.

### Porovnání vesnic

Mapa zobrazuje vlevo vesnici s místní AI a vpravo vesnici řízenou pravidly. [Přehled](http://127.0.0.1:8093/prehled.html) má společný graf s výběrem ukazatele, období a vesnic. Pravá vesnice při prvním spuštění převezme stav levé a od té chvíle si ukládá vlastní zásoby, vztahy, úkoly i kroniku. Obě používají společný herní čas a stejné podmínky prací. Hráčovy zásahy se předávají do obou, pokud jsou pro danou postavu dostupné. Grafy počítají srovnání od společného začátku.

Kámen po těžbě čeká u lomu. Trakař odveze až 4 jednotky do vesnických zásob. Nový dům stojí 12 dřeva a 6 kamene. Pokud má vesnice volný dům a dost zásob, může se přistěhovat nový obyvatel.


### Dávkový plán a víra

Každých 6 herních hodin (5 skutečných minut) se připraví hlavní a případný druhý úkol pro postavy od 12 let. Přímé vnuknutí může dávku předběhnout. Dostupnost, spotřebu, únavu, vztahy a důsledky řídí PHP v obou vesnicích. Pokud model chybí nebo návrh pro postavu neprojde, práce se vybere podle skóre hry. Při nízké zábavě či náladě má přednost návštěva, při krajní únavě odpočinek. Počasí a zásoby mohou volbu změnit při zahájení úkolu. Jeden dotaz na model zahrnuje nejvýše osm postav. Přehled rozhodování je v záložce **Schéma**.

V záložce **Víra** jsou hnutí, jejich členové, zázraky, průměrná víra, strach, důvěra a poslední volání modelu. Tlačítko **Pozastavit AI** uloží pauzu i přes restart. Obě vesnice během pauzy pokračují podle pravidel. Zázrak se provádí za přízeň, jejíž stav si eviduje každá větev. Další zázrak lze použít po šesti herních hodinách.

### Rozvoj vesnice

Cíl hry je přežít a rozrůst vesnici. Cíle (chléb, dřevo, hospodářství, domy, počet obyvatel, zásoby, přežité zimy) a skóre jsou na stránce **Rozvoj**, spolu s týdenním srovnáním AI a pravidel (probíhající týden i uložené týdny).

- **Ekonomika:** pole dává obilí, mlýn z něj dělá mouku a pec chléb (dvakrát sytější než kaše). Dřevo řeže pila na prkna, dílna z nich vyrábí nářadí (rychlejší práce). Statek zvedá výnos zvířat a studna omezuje nemoci i škody při požáru. Seno se kosí v létě a v zimě krmí dobytek. Budovy se staví v pevném pořadí (mlýn, pila, pec, studna, dílna, statek, zahrada, hospoda, kurník, chlív, ohrada, sklep, udírna, milíř, chmelnice, sladovna, pivovar, kovárna, důl, stáje, hřbitov, kostel, vinice, lis, špýchar, mlékárna, rybník, koželužna, tkalcovna, fara) podle dostupného materiálu a pracnosti v člověkohodinách (od 20 h u kurníku po 500 h u kostela): víc lidí staví rychleji, zkušený stavitel je rychlejší a kronika zapíše, kdo stavěl. Kurník osadí slepice, které žerou obilí a snášejí vejce; vejce sbírá péče o zvířata, rychle se kazí a jedí se jako první. Chlív a ohrada přidávají jídlo ze zvířat, stáje zrychlují svoz kamene, hřbitov zmírňuje smutek a kostel posiluje obřady. Domy (až čtyři postavené, vedle šesti pevných) mají šest míst, domky čtyři: rodina se čtyřmi lidmi a prarodiči, nebo šestičlenná. Když se v domě zúží, prarodiče nebo mladý pár se odstěhují do volného domu či k příbuzným, případně vesnice postaví domek. Pár se nejdřív zasnoubí a ožení v kapli nebo v kostele (jen když má kde bydlet); děti se rodí jen v manželství, občas dvojčata nebo mrtvé dítě, a při porodu může matka zemřít. Zahrada dává zeleninu a luštěniny (jaro sázení, léto okopávání, podzim sklizeň), sklep zpomalí kažení a umožní nakládat zelí, byliny se sbírají a suší a léčí nemocné; v zimě bez pestré stravy klesá nálada a přibývá nemocí. Příprava na zimu (jídlo na 60 dní, topivo na 45, seno na 150) je vidět na stránce Rozvoj. Pokácené dřevo leží u lesa a svážejí ho lidé, vůz (vyrobí ho dílna, když se zboží hromadí) veze víc a pomáhá i s kamenem, milíř vypálí dřevěné uhlí, v zimě se loví drobná zvěř (zajíc, králík, bažant) a udírna ji uchová. Pole dává i ječmen (čtvrtinu úrody po postavení sladovny), chmelnice se na jaře váže a na podzim česá, sladovna z ječmene dělá slad (říjen až duben) a pivovar z něj od sv. Michala do sv. Jiří vaří pivo, které zraje ve sklepě a pije se večer v hospodě (bez piva má hospoda po postavení pivovaru jen 40 % účinku); mlýn nechává slepicím obilí na zimu. Důl u lomu dává železnou rudu, kovárna z ní a z dřevěného uhlí taví železo a kuje železné nářadí (práce je až o 8 % rychlejší). Patnáctého každý měsíc přijíždí obchodník a smění přebytek za sůl, železo, látku a měděný kotel pro pivovar; na sv. Michala a sv. Martina vesnice odvádí vrchnosti úrok v obilí, vejcích a slepici. Vinice rodí až po dvou letech (v dubnu hrozí mráz), hrozny se lisují do sudu a víno po šedesáti dnech kvašení zpestří nedělní večer v hospodě i obřad v kostele. Špýchar zpomalí kažení obilí a mouky, mlékárna dělá ze mléka krav sýr (část každého jídla i zboží pro obchodníka), rybník dává na podzim kapry, koželužna vydělává kožky na useň a šije boty (v zimě menší pokles nálady a méně nemocí), tkalcovna tká z lnu z pole látku (obchodník ji už nemusí dovážet) a fara učí děti (za lekci roste jejich zkušenost). Mapa ukazuje vesnici v jednom izometrickém pohledu (otáčení je vypnuté) a je o tři políčka širší na východě a jihu.
- **Grafika (vyměnitelné obrázky):** mapa kreslí z kódu, ale každou budovu, dům, strom a rekvizitu lze nahradit pixel art PNG ve složce `prototyp/public/sprites/` (viz `grafika/SPRITY.md`). Soubor stačí přepsat, hra ho vymění sama do několika sekund; přehled stavu je na `sprity.html`. Skill a agent pro tvorbu obrázků jsou v `.claude/skills/pixel-art-sprity` a `.claude/agents/pixel-artist.md`, ukázkový generátor je `grafika/gen_sprites.py` (budovy fáze F už mají obrázky).
- **Život:** lidé stárnou o rok za herní rok, páry (manželé, nově i svatby) mívají děti zhruba jednou za dva roky, těhotenství trvá devět měsíců a těžkou práci omezuje. Při startu je těhotná Barbora Doležalová. Smrt je výjimečná: stáří (od 60 let), vzácná nemoc a velmi vzácný úraz při těžké práci, výjimečně porod.
- **Krize:** požár, povodeň a špatná úroda přicházejí vzácně a stejně v obou větvích. Při požáru a povodni zasahují všichni schopní lidé a výsledek záleží na rychlosti obrany.
- **Dovednosti:** zkušenost roste prací v jednotlivých oborech, ovlivňuje například výnos a rychlost stavby. Mladší se učí rychleji, zkušenější člen rodiny pomáhá a část zkušeností přechází na děti.
- **Balíček činností:** po úpravě souboru [cinnosti.json](prototyp/balicky/cinnosti.json) ho ověř příkazem `php prototyp/balicky/over.php` z kořene projektu. Běžící worker kontroluje soubor každých pět skutečných minut. Stav načtení ukazuje Rozvoj v sekci Činnosti.
- **Týdenní souhrny** se ukládají do tabulky `weekly` každé větve (7 herních dní = 2 h 20 min).
- **Zátěž:** sada `prototyp/tests/zatez.php` měří simulaci pro různé počty obyvatel. Naměřené časy a podmínky běhu jsou v [handoffu](KOORDINACE.md).

| Nastavení | Výchozí hodnota |
|---|---|
| Kontext Ollamy · MINILIDI_NUM_CTX | 4096, nastavitelné do 8192 |
| Prodleva uvolnění modelu Ollamy · AI_KEEP_ALIVE | 5 sekund od odpovědi |
| Čas dávky · MINILIDI_BATCH_TIMEOUT_MS | 60000 ms |
| Čas vnuknutí · MINILIDI_FAST_TIMEOUT_MS | 12000 ms |
| MINILIDI_MIN_FREE_VRAM_MIB | vypnuto; po nastavení se volná VRAM zkontroluje před voláním Ollamy |
| MINILIDI_NO_AI | při hodnotě 1 rozhodují pravidla po celou dobu běhu |
| Zápis SQLite WAL · kontrolní bod | po každé překročené herní hodině |

Testy modelu běží nad SQLite v paměti. S PHP z Laragonu s rozšířeními pdo_sqlite a curl spusť v prvním terminálu ze složky prototyp příkaz **php -S 127.0.0.1:18096 tests/model-stub.php**. Ve druhém spusť **php tests/overeni.php 18096 18097**, kde druhý port musí být volný.
