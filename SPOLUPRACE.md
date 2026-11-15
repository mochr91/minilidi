# Jak spolupracujeme na Minilidech

Úkoly zadávej na [místní tabuli](http://127.0.0.1:8091/). Pohled „Plán práce“ ukazuje nahoře aktivní úkoly po blocích (co jde dělat najednou), pod nimi věci k promyšlení, které rozhoduješ ty, a až na konci nápady na potom. Každý úkol má řešitele CLAUDE, CODEX nebo JÁ (změníš ho výběrem přímo na kartě), větu „Hotovo, když“ a tlačítka Začít, Hotovo a Kopírovat zadání. Tlačítko „Kopírovat blok“ nebo „CLAUDE“ a „CODEX“ nad tabulí vloží do schránky celé zadání, které předáš agentovi. Agent si ho umí načíst i sám z odkazu na tabuli. Rozpracované změny, požadavky na druhého agenta a výsledky testů evidujeme v [KOORDINACE.md](KOORDINACE.md). V místních pracovních kopiích oba používáme živý soubor v E:\AI2\Minilidi.

## Když chceš začít hned

Otevři E:\AI2\Minilidi v Codexu i v Claude Code. Jeden agent může upravovat kód, zatímco druhý čte soubory a připravuje kontrolu. Oba mohou upravovat soubory současně, pokud mají podle handoffu každý jinou část. Pro změnu společného souboru se nejdřív domluví v handoffu.

Příklad zadání pro Claude Code: „Přečti KOORDINACE.md a zkontroluj kód plánování činností. Vysvětli, kdy má být výsledek v obou vesnicích stejný, a navrhni tři testy. Zjištění připiš do handoffu. Kód simulace teď ponech.“

## Git ve třech pojmech

- **Commit** je pojmenovaný snímek změn. V Codexu můžeš říct „ukaž mi, co se změnilo“ a po kontrole „ulož to jako commit“.
- **Větev** je řada snímků pro jednu změnu. Hlavní větev se jmenuje main.
- **Worktree** je samostatná složka s jednou větví. Dva agenti mohou upravovat vlastní pracovní kopie a potom si změny ukázat a sloučit.

Pro práci ve dvou kopiích otevři E:\AI2\Minilidi-worktrees\codex (větev codex/prace) v Codexu a E:\AI2\Minilidi-worktrees\claude (větev claude/prace) v Claude Code. Aktuální stav větví a to, co v hlavní složce zatím není zapsané do Gitu, je v bodě 3 [KOORDINACE.md](KOORDINACE.md); před novou prací se větve musí srovnat s hlavní složkou. Hlavní složka E:\AI2\Minilidi zůstává místem, odkud běží hra a tabule. Až je úkol ověřený, požádej agenta, aby ukázal rozdíly a navrhl sloučení do main. Živé databáze, logy a obsah místní tabule jsou mimo Git.

## Ovládání z prohlížeče

Místní tabule slouží k evidenci. Úkol v ní spustíš tak, že jeho text předáš vybranému agentovi. Claude Code Remote Control umožňuje z webu pokračovat v místní relaci, dokud běží na zapnutém počítači. Pokud chceš úkoly spouštět z webu i při vypnutém počítači, praktická cesta je soukromý GitHub repozitář s Issues nebo Projects a zvlášť připojené Codex Cloud a Claude Code na webu. Prohlížeč pak slouží i ke kontrole změn v pull requestech.

Používej přihlášení k předplatnému v obou nástrojích. Přiděl jeden konkrétní úkol jednomu agentovi a druhému dej ke kontrole jen výsledek. Pro práci v rámci paušálu sleduj Usage v obou účtech a při vyčerpání limitu počkej na jeho obnovení.

[Codex Cloud](https://learn.chatgpt.com/docs/cloud) · [Claude Code na webu](https://code.claude.com/docs/en/claude-code-on-the-web) · [Claude Remote Control](https://code.claude.com/docs/en/remote-control)
