#!/usr/bin/env python3
"""Nakreslí všechny obrázky Minilidí (budovy, domy, stromy, rekvizity) do prototyp/public/sprites/.

Spuštění z kořene projektu:  python -I grafika/gen_vse.py
Stejnou práci dělají jednotlivě gen_budovy.py, gen_budovy2.py a gen_ostatni.py (lze jim předat jen vybrané klíče).
Animované budovy (mlyn, pec, pivovar, kovarna, udirna, milir) se ukládají jako pás `<klíč>@4.png` (4 snímky, 4 snímky za sekundu).
"""
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from pixlib import save  # noqa: E402
import gen_budovy  # noqa: E402
import gen_budovy2  # noqa: E402
import gen_ostatni  # noqa: E402

if __name__ == '__main__':
    for module in (gen_budovy, gen_budovy2, gen_ostatni):
        for key, (kind, fn, frames) in module.BUILDERS.items():
            save(kind, key, fn, frames)
