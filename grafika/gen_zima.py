#!/usr/bin/env python3
"""Zimní varianty budov a domů (`<klíč>_snow.png`, u animací `<klíč>_snow@N.png`).

Spuštění z kořene projektu:  python -I grafika/gen_zima.py [klíč ...]
Vezme hotové obrázky ze sprites/buildings, sprites/houses a sprites/props a přebarví střechy (taška, došky, břidlice, šindel),
trávu, hliněný dvorek a vodu na sníh a led. Stěny, okna a dveře zůstanou. Po změně letního obrázku stačí skript spustit znovu.
"""
import re
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from pixlib import (ROOT, Image, limit_colors, rgba, TILE, THATCH, SLATE, SHINGLE, GRASS, DIRT, WATER, SNOW, CLAY,  # noqa: E402
                    OUTLINE)

NEWTILE = [rgba('#e48f68'), rgba('#c8704f'), rgba('#9c4f3a')]
ICE = [rgba('#d4eaf0'), rgba('#aecfdb'), rgba('#86aebf')]
SNOW_SOIL = [rgba('#eef2f4'), rgba('#d3dde4'), rgba('#aab9c6')]

# Letní barva -> zimní barva (střecha: světlá plocha, tón, stín; trávník; dvorek; voda)
MAP = {}
for pal in (TILE, NEWTILE, THATCH, SLATE, SHINGLE):
    MAP[pal[0]] = SNOW[0]
    MAP[pal[1]] = SNOW[1]
    MAP[pal[2]] = SNOW[2]
for a, b in zip(GRASS, SNOW_SOIL):
    MAP[a] = b
for a, b in zip(DIRT, SNOW_SOIL):
    MAP[a] = b
for a, b in zip(WATER, ICE):
    MAP[a] = b

# Obrázky, které se nezasněžují (rostliny, bez střech): ostatní ano
SKIP = {'chmelnice', 'vinice', 'zahrada', 'quarry', 'stall_red', 'stall_green'}


def snowify(img):
    out = img.copy()
    px = out.load()
    for y in range(img.height):
        for x in range(img.width):
            p = px[x, y]
            if p[3] and p in MAP:
                px[x, y] = MAP[p]
    # sníh na hřebenech: každý horní okraj střechy (sněhový bod pod obrysem) zesvětlí
    src = out.copy()
    s = src.load()
    for y in range(1, img.height):
        for x in range(img.width):
            if s[x, y][3] and s[x, y] in (SNOW[1], SNOW[2]) and s[x, y - 1][:3] == OUTLINE[:3]:
                px[x, y] = SNOW[0]
    return out


def make(kind, name):
    folder = ROOT / kind
    animated = list(folder.glob(name + '@*.png'))
    source = animated[0] if animated else folder / (name + '.png')
    if not source.exists():
        print('chybí', source)
        return
    m = re.search(r'@(\d+)\.png$', source.name)
    out = limit_colors(snowify(Image.open(source).convert('RGBA')))
    target = folder / ('%s_snow%s.png' % (name, '@' + m.group(1) if m else ''))
    for old in folder.glob(name + '_snow*.png'):
        old.unlink()
    out.save(target)
    print('%-34s %s %d barev' % (kind + '/' + target.name, out.size, len({p for p in out.getdata() if p[3]})))


def keys():
    result = []
    for kind in ('buildings', 'houses'):
        for f in sorted((ROOT / kind).glob('*.png')):
            name = re.sub(r'(_snow)?(@\d+)?\.png$', '', f.name)
            if '_snow' in f.name or name in SKIP or (kind, name) in result:
                continue
            result.append((kind, name))
    result.append(('props', 'fishing_hut'))
    return result


if __name__ == '__main__':
    wanted = sys.argv[1:]
    for kind, name in keys():
        if not wanted or name in wanted:
            make(kind, name)
