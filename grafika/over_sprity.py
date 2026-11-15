"""Kontrola PNG obrázků Minilidí: RGBA, jen alfa 0 nebo 255, nejvýš 32 barev, kotva (neprázdný spodní řádek).
Použití: python -I grafika/over_sprity.py prototyp/public/sprites/buildings/*.png
"""
from PIL import Image
import sys
bad = 0
for path in sys.argv[1:]:
    try:
        im = Image.open(path)
        assert im.mode == 'RGBA', 'není RGBA'
        data = list(im.getdata())
        alphas = {p[3] for p in data}
        assert alphas <= {0, 255}, 'polopropustné body ' + str(sorted(alphas)[:5])
        colors = {p for p in data if p[3]}
        assert len(colors) <= 32, str(len(colors)) + ' barev (nejvýš 32)'
        assert any(im.getpixel((x, im.height - 1))[3] for x in range(im.width)), 'spodní řádek je prázdný (kotva)'
        print('OK  ', path, im.size, len(colors), 'barev')
    except Exception as error:
        bad += 1
        print('CHYBA', path, error)
sys.exit(1 if bad else 0)