# Jak spolupracujeme na Minilidech

Úkoly zadávej na [místní tabuli](http://127.0.0.1:8091/). Na kartu napiš jednoho řešitele, konkrétní výsledek a jak poznáš, že funguje. Text karty předej do Codexu nebo Claude Code. Rozpracované změny, požadavky na druhého agenta a výsledky testů evidujeme v [KOORDINACE.md](KOORDINACE.md). V místních pracovních kopiích oba používáme živý soubor v E:\AI2\Minilidi.

## Když chceš začít hned

Otevři E:\AI2\Minilidi v Codexu i v Claude Code. Jeden agent může upravovat kód, zatímco druhý čte soubory a připravuje kontrolu. Oba mohou upravovat soubory současně, pokud mají podle handoffu každý jinou část. Pro změnu společného souboru se nejdřív domluví v handoffu.

Příklad zadání pro Claude Code: „Přečti KOORDINACE.md a zkontroluj kód plánování činností. Vysvětli, kdy má být výsledek v obou vesnicích stejný, a navrhni tři testy. Zjištění připiš do handoffu. Kód simulace teď ponech.“

## Git ve třech pojmech

- **Commit** je pojmenovaný snímek změn. V Codexu můžeš říct „ukaž mi, co se změnilo“ a po kontrole „ulož to jako commit“.
- **Větev** je řada snímků pro jednu změnu. Hlavní větev se jmenuje main.
- **Worktree** je samostatná složka s jednou větví. Dva agenti mohou upravovat vlastní pracovní kopie a potom si změny ukázat a sloučit.

Pro práci ve dvou kopiích otevři E:\AI2\Minilidi-worktrees\codex v Codexu a E:\AI2\Minilidi-worktrees\claude v Claude Code. Hlavní složka E:\AI2\Minilidi zůstává místem, odkud běží hra a tabule. Až je úkol ověřený, požádej agenta, aby ukázal rozdíly a navrhl sloučení do main. Živé databáze, logy a obsah místní tabule jsou mimo Git.

## Ovládání z prohlížeče

Místní tabule slouží k evidenci. Úkol v ní spustíš tak, že jeho text předáš vybranému agentovi. Claude Code Remote Control umožňuje z webu pokračovat v místní relaci, dokud běží na zapnutém počítači. Pokud chceš úkoly spouštět z webu i při vypnutém počítači, praktická cesta je soukromý GitHub repozitář s Issues nebo Projects a zvlášť připojené Codex Cloud a Claude Code na webu. Prohlížeč pak slouží i ke kontrole změn v pull requestech.

Používej přihlášení k předplatnému v obou nástrojích. Přiděl jeden konkrétní úkol jednomu agentovi a druhému dej ke kontrole jen výsledek. Pro práci v rámci paušálu sleduj Usage v obou účtech a při vyčerpání limitu počkej na jeho obnovení.

[Codex Cloud](https://learn.chatgpt.com/docs/cloud) · [Claude Code na webu](https://code.claude.com/docs/en/claude-code-on-the-web) · [Claude Remote Control](https://code.claude.com/docs/en/remote-control)
