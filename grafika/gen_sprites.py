#!/usr/bin/env python3
"""Generátor pixel-art obrázků budov Minilidí (Pillow).

Spuštění z kořene projektu:
    python grafika/gen_sprites.py            # všech šest budov fáze F do prototyp/public/sprites/buildings/
    python grafika/gen_sprites.py kozeluzna  # jen vybrané

Obrázky mají měřítko 2 (1 bod souboru = 2×2 body mapy). Půdorys se kreslí izometricky 2:1 (políčko = 10×5 bodů souboru),
spodní střed obrázku je přední roh základny budovy. Jiný obrázek stačí přepsat do téže složky, hra ho sama vymění.
Skript je ukázka stylu (tmavý obrys, tři tóny na materiál, hrubé stínování): další sprity viz .claude/skills/pixel-art-sprity.
"""
import os
import sys
import random
from pathlib import Path
from PIL import Image, ImageDraw

HW, HH = 10, 5                       # polovina šířky a výšky políčka v bodech obrázku (políčko mapy je 46×22 bodů, obrázek 2× menší a budova stojí uvnitř)
Z = .85                              # výšky stěn a střech se zkracují, aby budova nebyla vyšší než okolní domy
OUT = Path(os.environ.get('SPRITES_OUT') or Path(__file__).resolve().parent.parent / 'prototyp' / 'public' / 'sprites' / 'buildings')
OUTLINE = (43, 33, 29, 255)

def rgba(hex_color, alpha=255):
    h = hex_color.lstrip('#')
    return (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), alpha)

# Palety: světlo, střed, stín (světlo zleva nahoře: levá stěna střední, pravá stěna tmavá, střecha světlá).
STONE = [rgba('#b9b4a4'), rgba('#9a9585'), rgba('#6f6b60')]
PLASTER = [rgba('#f0e4c4'), rgba('#d9c9a0'), rgba('#a89874')]
WOOD = [rgba('#b78a56'), rgba('#92683f'), rgba('#6a4a2e')]
DARKWOOD = [rgba('#7b5a3a'), rgba('#5d4129'), rgba('#43301f')]
THATCH = [rgba('#e0c27a'), rgba('#c39f55'), rgba('#92733d')]
TILE = [rgba('#d0795a'), rgba('#b35d44'), rgba('#85402f')]
SLATE = [rgba('#8d969b'), rgba('#6c767c'), rgba('#4b555b')]
GRASS = [rgba('#9bb060'), rgba('#7e9650'), rgba('#5f7a40')]
WATER = [rgba('#86c0c4'), rgba('#5b9aa5'), rgba('#3f7685')]
GOLD = rgba('#f3d98b')

class Canvas:
    def __init__(self, width, height, w, d):
        self.img = Image.new('RGBA', (width, height), (0, 0, 0, 0))
        self.px = ImageDraw.Draw(self.img)
        self.w, self.d = w, d
        # přední roh základny je dole uprostřed; zadní roh je o (w+d)*HH výš
        self.fx = width // 2
        self.fy = height - 2
        self.rng = random.Random(7)

    def p(self, i, j, z=0):
        """Souřadnice půdorysu (i doprava dolů, j doleva dolů, políčka) a výšky z (body) -> bod obrázku."""
        bx = self.fx - (self.w - self.d) * HW
        by = self.fy - (self.w + self.d) * HH
        return (round(bx + (i - j) * HW), round(by + (i + j) * HH - z * Z))

    def poly(self, pts, color):
        self.px.polygon(pts, fill=color)

    def line(self, a, b, color):
        self.px.line([a, b], fill=color)

    def dot(self, x, y, color):
        if 0 <= x < self.img.width and 0 <= y < self.img.height:
            self.img.putpixel((int(x), int(y)), color)

    def rect(self, x0, y0, x1, y1, color):
        self.px.rectangle([x0, y0, x1, y1], fill=color)

    def ground(self, color, margin=0.0, i0=0, j0=0, i1=None, j1=None):
        i1 = self.w if i1 is None else i1
        j1 = self.d if j1 is None else j1
        pts = [self.p(i0 - margin, j0 - margin), self.p(i1 + margin, j0 - margin), self.p(i1 + margin, j1 + margin), self.p(i0 - margin, j1 + margin)]
        self.poly(pts, color)

    def left_face(self, i0, i1, j, z0, z1, color):
        self.poly([self.p(i0, j, z0), self.p(i1, j, z0), self.p(i1, j, z1), self.p(i0, j, z1)], color)

    def right_face(self, i, j0, j1, z0, z1, color):
        self.poly([self.p(i, j0, z0), self.p(i, j1, z0), self.p(i, j1, z1), self.p(i, j0, z1)], color)

    def box(self, i0, j0, i1, j1, z0, z1, pal, top=None):
        """Kvádr: levá (přední) stěna je rovina j=j1, pravá je i=i1."""
        self.left_face(i0, i1, j1, z0, z1, pal[1])
        self.right_face(i1, j0, j1, z0, z1, pal[2])
        if top is not None:
            self.poly([self.p(i0, j0, z1), self.p(i1, j0, z1), self.p(i1, j1, z1), self.p(i0, j1, z1)], top)

    def planks(self, i0, i1, j, z0, z1, color, step=3):
        for z in range(z0 + step, z1, step):
            self.line(self.p(i0, j, z), self.p(i1, j, z), color)

    def planks_right(self, i, j0, j1, z0, z1, color, step=3):
        for z in range(z0 + step, z1, step):
            self.line(self.p(i, j0, z), self.p(i, j1, z), color)

    def stones(self, face, a0, a1, fixed, z0, z1, light, dark):
        """Kamenná struktura: náhodné světlé a tmavé spáry po řadách."""
        for z in range(z0 + 2, z1, 3):
            offset = self.rng.random()
            a = a0 + offset * .3
            while a < a1:
                pt = self.p(a, fixed, z) if face == 'L' else self.p(fixed, a, z)
                self.dot(pt[0], pt[1], dark if self.rng.random() < .5 else light)
                a += .28 + self.rng.random() * .3

    def gable_roof(self, i0, j0, i1, j1, z, rise, pal, along='i', over=.18, rows=True):
        """Sedlová střecha: hřeben vede podél osy i (along='i') nebo j. Viditelné jsou přední levá a pravá plocha."""
        if along == 'i':
            jm = (j0 + j1) / 2
            ridge0, ridge1 = self.p(i0 - over, jm, z + rise), self.p(i1 + over, jm, z + rise)
            # přední plocha (od j1 k hřebeni) je vlevo-dole
            front = [self.p(i0 - over, j1 + over, z), self.p(i1 + over, j1 + over, z), ridge1, ridge0]
            back_gable = [self.p(i1 + over, j0 - over, z), self.p(i1 + over, j1 + over, z), ridge1]
            self.poly(front, pal[0])
            self.poly(back_gable, pal[2])
            if rows:
                for k in range(1, rise, 2):
                    t = k / rise
                    a = self.p(i0 - over, j1 + over - (j1 - jm + over) * t, z + rise * t)
                    b = self.p(i1 + over, j1 + over - (j1 - jm + over) * t, z + rise * t)
                    self.line(a, b, pal[1])
        else:
            im = (i0 + i1) / 2
            ridge0, ridge1 = self.p(im, j0 - over, z + rise), self.p(im, j1 + over, z + rise)
            right = [self.p(i1 + over, j0 - over, z), self.p(i1 + over, j1 + over, z), ridge1, ridge0]
            front_gable = [self.p(i0 - over, j1 + over, z), self.p(i1 + over, j1 + over, z), ridge1]
            self.poly(front_gable, pal[1])
            self.poly(right, pal[2])
            if rows:
                for k in range(1, rise, 2):
                    t = k / rise
                    a = self.p(i1 + over - (i1 - im + over) * t, j0 - over, z + rise * t)
                    b = self.p(i1 + over - (i1 - im + over) * t, j1 + over, z + rise * t)
                    self.line(a, b, pal[1])
        # hřeben
        self.line(ridge0, ridge1, pal[0])

    def finish(self):
        """Dvoubodový obrys: tmavý obrys kolem celé siluety (okolní průhledné body)."""
        src = self.img.copy()
        w, h = src.size
        out = src.copy()
        for y in range(h):
            for x in range(w):
                if src.getpixel((x, y))[3] == 0:
                    for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                        nx, ny = x + dx, y + dy
                        if 0 <= nx < w and 0 <= ny < h and src.getpixel((nx, ny))[3] > 0:
                            out.putpixel((x, y), OUTLINE)
                            break
        self.img = out
        return out

# --- jednotlivé budovy -----------------------------------------------------------------------------

def sack(c, x, y):
    c.rect(x, y - 4, x + 3, y, rgba('#d8c79a'))
    c.rect(x + 1, y - 5, x + 2, y - 5, rgba('#bfae80'))
    c.dot(x + 1, y - 2, rgba('#a89874'))

def barrel(c, x, y, pal=WOOD):
    c.rect(x, y - 5, x + 3, y, pal[1])
    c.rect(x + 3, y - 5, x + 4, y, pal[2])
    c.line((x, y - 4), (x + 4, y - 4), DARKWOOD[1])
    c.line((x, y - 1), (x + 4, y - 1), DARKWOOD[1])
    c.line((x, y - 5), (x + 3, y - 5), pal[0])

def door(c, i, j, z, width=.5, height=9):
    """Dveře na přední levé stěně (rovina j) v poloze i."""
    c.poly([c.p(i, j, z), c.p(i + width, j, z), c.p(i + width, j, z + height), c.p(i, j, z + height)], rgba('#3b2a1e'))
    c.line(c.p(i, j, z + height + 1), c.p(i + width, j, z + height + 1), WOOD[0])

def window_left(c, i, j, z, size=4):
    a, b = c.p(i, j, z), c.p(i + .3, j, z + size)
    c.poly([c.p(i, j, z), c.p(i + .3, j, z), c.p(i + .3, j, z + size), c.p(i, j, z + size)], rgba('#f1dc9b'))
    c.line(c.p(i, j, z + size // 2), c.p(i + .3, j, z + size // 2), DARKWOOD[1])

def spychar():
    c = Canvas(48, 56, 2, 2)
    c.ground(GRASS[1], .15)
    c.ground(rgba('#a79a73'), .0, .1, .1, 1.9, 1.9)
    # kamenný sokl a roubená komora, vysoká střecha
    c.box(.2, .2, 1.8, 1.8, 0, 6, STONE)
    c.stones('L', .2, 1.8, 1.8, 0, 6, STONE[0], STONE[2])
    c.box(.2, .2, 1.8, 1.8, 6, 22, WOOD)
    c.planks(.2, 1.8, 1.8, 6, 22, WOOD[2], 3)
    c.planks_right(1.8, .2, 1.8, 6, 22, DARKWOOD[1], 3)
    door(c, .7, 1.8, 6, .7, 10)
    c.line(c.p(.7, 1.8, 11), c.p(1.4, 1.8, 11), GOLD)
    window_left(c, 1.4, 1.8, 15, 3)
    c.gable_roof(.2, .2, 1.8, 1.8, 22, 16, THATCH, along='i', over=.3)
    # větrací okénko ve štítu a pytle před vchodem
    gx, gy = c.p(1.95, 1.0, 26)
    c.rect(gx - 1, gy - 1, gx + 1, gy + 1, rgba('#3b2a1e'))
    sx, sy = c.p(.4, 2.05, 0)
    sack(c, sx, sy)
    sack(c, sx + 4, sy + 1)
    sack(c, sx + 2, sy - 3)
    return c.finish()

def mlekarna():
    c = Canvas(48, 56, 2, 2)
    c.ground(GRASS[1], .15)
    c.ground(rgba('#b3a67f'), .0, .1, .1, 1.9, 1.9)
    # bílé omítnuté stěny s trámy a červenou tašková střecha
    c.box(.2, .2, 1.8, 1.8, 0, 17, PLASTER)
    c.line(c.p(.2, 1.8, 0), c.p(.2, 1.8, 17), WOOD[2])
    c.line(c.p(1.8, 1.8, 0), c.p(1.8, 1.8, 17), WOOD[2])
    c.line(c.p(1.0, 1.8, 0), c.p(1.0, 1.8, 17), WOOD[2])
    c.line(c.p(.2, 1.8, 9), c.p(1.8, 1.8, 9), WOOD[2])
    c.line(c.p(1.8, .2, 9), c.p(1.8, 1.8, 9), WOOD[2])
    c.line(c.p(1.8, 1.0, 0), c.p(1.8, 1.0, 17), WOOD[2])
    door(c, 1.1, 1.8, 0, .5, 10)
    window_left(c, .45, 1.8, 7, 4)
    c.gable_roof(.2, .2, 1.8, 1.8, 17, 13, TILE, along='j', over=.3)
    # komín, konve na mléko a sýry na desce
    kx, ky = c.p(1.5, .7, 30)
    c.rect(kx, ky - 6, kx + 3, ky, STONE[1])
    c.rect(kx + 3, ky - 6, kx + 4, ky, STONE[2])
    for k in range(3):
        mx, my = c.p(.1 + k * .25, 2.1, 0)
        c.rect(mx, my - 6, mx + 3, my, rgba('#b8c0c4'))
        c.rect(mx + 3, my - 6, mx + 4, my, rgba('#838c92'))
        c.rect(mx, my - 8, mx + 3, my - 7, rgba('#e8eef0'))
    wx, wy = c.p(1.5, 2.0, 0)
    c.rect(wx, wy - 3, wx + 7, wy - 2, WOOD[0])
    for k in range(2):
        c.rect(wx + 1 + k * 3, wy - 6, wx + 3 + k * 3, wy - 4, rgba('#f0c75a'))
    return c.finish()

def rybnik():
    c = Canvas(74, 44, 4, 3)
    c.ground(GRASS[1], .12)
    # nízký hliněný břeh a hladina
    c.ground(rgba('#8a7a55'), 0, .0, .0)
    c.ground(WATER[1], 0, .25, .25, 3.75, 2.75)
    c.ground(WATER[0], 0, .6, .45, 3.3, 1.6)
    rng = random.Random(3)
    for _ in range(26):
        x, y = c.p(.6 + rng.random() * 2.8, .5 + rng.random() * 2.0)
        c.line((x - 2, y), (x + 1, y), WATER[0])
    for (i, j) in [(1.4, 1.5), (2.6, 1.0), (2.9, 2.0)]:
        x, y = c.p(i, j)
        c.rect(x - 2, y - 1, x + 2, y, rgba('#6f9b4d'))
        c.dot(x, y - 2, rgba('#f2e3e8'))
    # rákosí na břehu
    for (i, j) in [(.15, 2.6), (.25, .4), (3.7, .3), (3.85, 2.4), (2.2, 2.85)]:
        x, y = c.p(i, j)
        for k in range(3):
            c.line((x + k, y), (x + k - 1 + (k % 2) * 2, y - 8 - k), rgba('#6d8a3d'))
        c.rect(x, y - 11, x + 1, y - 8, rgba('#7a5a3d'))
    # dřevěný molo
    pts = [c.p(.0, 1.35), c.p(1.4, 1.35), c.p(1.4, 1.65), c.p(.0, 1.65)]
    c.poly(pts, WOOD[0])
    for k in range(7):
        a, b = c.p(.1 + k * .2, 1.35), c.p(.1 + k * .2, 1.65)
        c.line(a, b, WOOD[2])
    return c.finish()

def kozeluzna():
    c = Canvas(48, 56, 2, 2)
    c.ground(GRASS[1], .15)
    c.ground(rgba('#a89a74'), 0, .1, .1, 1.9, 1.9)
    # dřevěná stodola s nízkou břidlicovou střechou, kůže na sušácích, kádě na vydělávání
    c.box(.3, .2, 1.8, 1.6, 0, 15, WOOD)
    c.planks(.3, 1.8, 1.6, 0, 15, WOOD[2], 3)
    c.planks_right(1.8, .2, 1.6, 0, 15, DARKWOOD[1], 3)
    door(c, 1.0, 1.6, 0, .6, 10)
    c.gable_roof(.3, .2, 1.8, 1.6, 15, 9, SLATE, along='i', over=.3)
    # sušák s kůžemi vpředu
    for (i, color) in [(.2, rgba('#c79a64')), (.55, rgba('#b4814f')), (.9, rgba('#d3a971'))]:
        x0, y0 = c.p(i, 2.0, 0)
        c.rect(x0, y0 - 11, x0 + 1, y0, DARKWOOD[1])
        c.poly([(x0 + 1, y0 - 11), (x0 + 6, y0 - 11), (x0 + 6, y0 - 4), (x0 + 3, y0 - 2), (x0 + 1, y0 - 5)], color)
    bx, by = c.p(1.5, 2.05, 0)
    barrel(c, bx, by)
    barrel(c, bx + 6, by + 1)
    return c.finish()

def tkalcovna():
    c = Canvas(48, 56, 2, 2)
    c.ground(GRASS[1], .15)
    c.ground(rgba('#b3a67f'), 0, .1, .1, 1.9, 1.9)
    # hrázděná chalupa se slaměnou střechou, otevřený štít se stavem a svazky lnu
    c.box(.2, .2, 1.8, 1.8, 0, 15, PLASTER)
    for i in (.2, .75, 1.3, 1.8):
        c.line(c.p(i, 1.8, 0), c.p(i, 1.8, 15), WOOD[2])
    c.line(c.p(.2, 1.8, 7), c.p(1.8, 1.8, 7), WOOD[2])
    c.line(c.p(.2, 1.8, 0), c.p(1.0, 1.8, 15), WOOD[1])
    for j in (.2, .75, 1.3, 1.8):
        c.line(c.p(1.8, j, 0), c.p(1.8, j, 15), WOOD[2])
    window_left(c, .85, 1.8, 6, 5)
    door(c, 1.35, 1.8, 0, .4, 9)
    c.gable_roof(.2, .2, 1.8, 1.8, 15, 15, THATCH, along='j', over=.3)
    # stav v otevřeném štítu
    gx, gy = c.p(1.0, 1.95, 16)
    c.rect(gx - 4, gy, gx + 4, gy + 5, rgba('#4a3828'))
    for k in range(5):
        c.line((gx - 3 + k * 2, gy), (gx - 3 + k * 2, gy + 5), rgba('#e8d9b0'))
    # svazky lnu
    for k in range(3):
        fx, fy = c.p(.1 + k * .22, 2.1, 0)
        c.rect(fx, fy - 8, fx + 2, fy, rgba('#c9b66a'))
        c.rect(fx, fy - 5, fx + 2, fy - 5, rgba('#8d9a55'))
        c.dot(fx + 1, fy - 9, rgba('#9fb7d6'))
    return c.finish()

def fara():
    c = Canvas(50, 66, 2, 2)
    c.ground(GRASS[1], .15)
    c.ground(rgba('#b3a67f'), 0, .1, .1, 1.9, 1.9)
    # kamenné přízemí, omítnuté patro, červená střecha, zvonková vížka a plot se zahrádkou
    c.box(.2, .2, 1.8, 1.8, 0, 12, STONE)
    c.stones('L', .2, 1.8, 1.8, 0, 12, STONE[0], STONE[2])
    c.stones('R', .2, 1.8, 1.8, 0, 12, STONE[0], STONE[2])
    c.box(.2, .2, 1.8, 1.8, 12, 22, PLASTER)
    door(c, .9, 1.8, 0, .55, 10)
    window_left(c, .35, 1.8, 5, 4)
    window_left(c, 1.4, 1.8, 14, 4)
    window_left(c, .5, 1.8, 14, 4)
    c.gable_roof(.2, .2, 1.8, 1.8, 22, 14, TILE, along='i', over=.3)
    # vížka se zvonem
    tx, ty = c.p(.55, 1.0, 33)
    c.rect(tx - 3, ty - 12, tx + 3, ty, PLASTER[1])
    c.rect(tx + 3, ty - 12, tx + 4, ty, PLASTER[2])
    c.poly([(tx - 4, ty - 12), (tx + 5, ty - 12), (tx + 1, ty - 19)], TILE[1])
    c.rect(tx - 1, ty - 9, tx + 1, ty - 5, rgba('#3b2a1e'))
    c.dot(tx, ty - 7, GOLD)
    c.line((tx + 1, ty - 19), (tx + 1, ty - 23), rgba('#d9c27a'))
    c.line((tx - 1, ty - 21), (tx + 3, ty - 21), rgba('#d9c27a'))
    # záhonek před domem
    for k in range(4):
        gx, gy = c.p(.1 + k * .3, 2.15, 0)
        c.rect(gx, gy - 2, gx + 2, gy, rgba('#7e5a3a'))
        c.rect(gx, gy - 3, gx + 2, gy - 3, rgba('#6f9b4d'))
    return c.finish()

BUILDERS = {'spychar': spychar, 'mlekarna': mlekarna, 'rybnik': rybnik, 'kozeluzna': kozeluzna, 'tkalcovna': tkalcovna, 'fara': fara}

if __name__ == '__main__':
    wanted = sys.argv[1:] or list(BUILDERS)
    OUT.mkdir(parents=True, exist_ok=True)
    for name in wanted:
        image = BUILDERS[name]()
        image.save(OUT / (name + '.png'))
        print(name, image.size)
