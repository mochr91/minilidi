#!/usr/bin/env python3
"""Pixel-art domy, stromy a rekvizity Minilidí.

Spuštění z kořene projektu:  python -I grafika/gen_ostatni.py [klíč ...]
Domy jdou do sprites/houses, stromy do sprites/trees, rekvizity do sprites/props.
"""
import math
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from pixlib import *  # noqa: F401,F403

NEWWOOD = [rgba('#dcb478'), rgba('#c0925a'), rgba('#96693f')]
NEWTILE = [rgba('#e48f68'), rgba('#c8704f'), rgba('#9c4f3a')]


def flower_box(c, i, j, z, n=3):
    x, y = c.p(i, j + .05, z)
    c.rect(x, y, x + n * 2, y + 1, WOOD[2])
    for k in range(n):
        c.dot(x + k * 2, y - 1, RED[0] if k % 2 == 0 else rgba('#f0d060'))


# --- domy --------------------------------------------------------------------------------------------------------------

def home_tile():
    c = canvas(40, 40, 2, 2)
    i0, j0, i1, j1 = .4, .4, 1.9, 1.9
    yard(c, GRASS[0], .0)
    walls(c, i0, j0, i1, j1, 0, 15, PLASTER, 'timber')
    gable(c, i0, j0, i1, j1, 15, 12, TILE, 'i', over=.2, gable_pal=PLASTER, kind='tile',
          hook=lambda: chimney(c, 1.4, .55, 15, 27, .22))
    door(c, .62, j1, 0, .4, 10)
    window_left(c, 1.15, j1, 6, 5)
    window_left(c, 1.5, j1, 6, 5)
    window_r(c, i1, .9, 6, .35, 5)
    flower_box(c, 1.15, j1, 4)
    return done(c)


def home_thatch():
    c = canvas(40, 40, 2, 2)
    i0, j0, i1, j1 = .45, .45, 1.9, 1.9
    yard(c, GRASS[0], .0)
    walls(c, i0, j0, i1, j1, 0, 13, WOOD, 'wood')
    gable(c, i0, j0, i1, j1, 13, 14, THATCH, 'j', over=.22, gable_pal=WOOD, kind='thatch',
          hook=lambda: chimney(c, .6, .75, 13, 26, .22))
    door(c, .75, j1, 0, .4, 10)
    window_left(c, 1.35, j1, 6, 4)
    window_r(c, i1, 1.0, 6, .35, 4)
    # okénko ve štítu
    x, y = c.p(1.17, j1 + .22, 21)
    c.rect(x - 1, y, x + 1, y + 2, DOORC)
    flower_box(c, 1.35, j1, 3)
    return done(c)


def home_new():
    c = canvas(40, 40, 2, 2)
    i0, j0, i1, j1 = .4, .4, 1.9, 1.9
    yard(c, GRASS[0], .0)
    walls(c, i0, j0, i1, j1, 0, 15, NEWWOOD, 'wood')
    gable(c, i0, j0, i1, j1, 15, 12, NEWTILE, 'i', over=.2, gable_pal=NEWWOOD, kind='tile',
          hook=lambda: chimney(c, 1.4, .55, 15, 31, .22, [rgba('#cdc7b6'), rgba('#aaa494'), rgba('#7d7869')]))
    door(c, .62, j1, 0, .4, 10)
    window_left(c, 1.15, j1, 6, 5)
    window_left(c, 1.5, j1, 6, 5)
    window_r(c, i1, .9, 6, .35, 5)
    # čerstvě řezané dříví
    log_pile(c, .0, 1.95, 2, 3, NEWWOOD)
    return done(c)


def cottage():
    c = canvas(34, 34, 2, 2)
    i0, j0, i1, j1 = .55, .55, 1.9, 1.9
    yard(c, GRASS[0], .0)
    walls(c, i0, j0, i1, j1, 0, 11, PLASTER, 'plaster')
    for i in (.55, 1.9):
        c.line(c.p(i, j1, 0), c.p(i, j1, 11), WOOD[2])
    c.line(c.p(i0, j1, 5), c.p(i1, j1, 5), WOOD[2])
    gable(c, i0, j0, i1, j1, 11, 11, THATCH, 'i', over=.22, gable_pal=PLASTER, kind='thatch')
    door(c, .75, j1, 0, .34, 8)
    window_left(c, 1.35, j1, 4, 4)
    window_r(c, i1, 1.0, 4, .3, 4)
    return done(c)


def chapel():
    c = canvas(36, 56, 2, 2)
    i0, j0, i1, j1 = .5, .5, 1.8, 1.8
    yard(c, GRASS[0], .0)
    walls(c, i0, j0, i1, j1, 0, 15, STONE, 'stone')
    gable(c, i0, j0, i1, j1, 15, 12, SLATE, 'j', over=.15, gable_pal=STONE, kind='slate')
    arch_l(c, 1.0, j1 + .18, 0, .42, 9, DOORC, STONE[0])
    window_arch(c, .62, j1, 6, 6, .18)
    window_arch(c, 1.5, j1, 6, 6, .18)
    window_r(c, i1, 1.0, 5, .3, 6)
    # zvonička na hřebeni
    walls(c, .98, .98, 1.42, 1.42, 26, 34, WOOD, 'wood')
    window_arch(c, 1.1, 1.42, 27, 4, .18, DOORC)
    apex = pyramid(c, .98, .98, 1.42, 1.42, 34, 9, SLATE, over=.08, kind='slate')
    c.line(apex, (apex[0], apex[1] - 5), GOLD)
    c.line((apex[0] - 2, apex[1] - 3), (apex[0] + 2, apex[1] - 3), GOLD)
    return done(c)


# --- stromy ------------------------------------------------------------------------------------------------------------

def spruce(width, height, tiers, snow=False):
    c = canvas(width, height, 1, 1)
    cx = width // 2
    base = height - 3
    c.rect(cx - 1, base - 4, cx, base + 1, WOOD[1])
    c.rect(cx + 1, base - 4, cx + 1, base + 1, WOOD[2])
    c.dot(cx - 1, base - 3, WOOD[0])
    c.rect(cx - 3, base + 1, cx + 3, base + 1, GRASS[2])
    top_gap = 2
    span = base - 2 - top_gap
    th = max(6, int(span * .5))
    for t in range(tiers):
        frac = t / (tiers - 1) if tiers > 1 else 0
        bottom = int(base - 3 - (span - th) * (1 - frac) * 1.0 + 0)
        bottom = int(top_gap + th + (span - th) * frac)
        apex_y = bottom - th
        half_max = int((width // 2 - 1) * (.55 + .45 * frac))
        for yy in range(apex_y, bottom + 1):
            t2 = (yy - apex_y) / max(1, bottom - apex_y)
            half = int(round(half_max * t2 ** .9))
            edge = half
            if (yy + t) % 3 == 0 and yy < bottom:
                edge = max(0, half - 1)
            for xx in range(-edge, edge + 1):
                col = PINE[1]
                if xx < -edge * .35:
                    col = PINE[0]
                elif xx > edge * .25:
                    col = PINE[2]
                if yy > bottom - 2:
                    col = PINE[2] if xx > -edge * .3 else PINE[1]
                c.dot(cx + xx, yy, col)
            if snow:
                if yy < apex_y + (bottom - apex_y) * .55:
                    for xx in range(-edge, -edge + 2 + int(t2 * 2)):
                        c.dot(cx + xx, yy, SNOW[0])
                    c.dot(cx + edge - 1, yy, SNOW[1])
                if yy == bottom:
                    for xx in range(-edge, edge + 1, 2):
                        c.dot(cx + xx, yy, SNOW[1])
        if snow:
            c.line((cx - half_max // 2, bottom - 1), (cx + half_max // 2, bottom - 1), SNOW[1])
    if snow:
        c.dot(cx, 1 + top_gap, SNOW[0])
        c.dot(cx, 2 + top_gap, SNOW[0])
    return c.finish()


def disc(c, cx, cy, r, col):
    for yy in range(-r, r + 1):
        half = int(round(math.sqrt(max(0, r * r - yy * yy) + .25)))
        for xx in range(-half, half + 1):
            c.dot(cx + xx, cy + yy, col)


def broadleaf(width, height, snow=False):
    c = canvas(width, height, 1, 1)
    cx = width // 2
    base = height - 3
    c.rect(cx - 1, base - 11, cx, base + 1, WOOD[1])
    c.rect(cx + 1, base - 11, cx + 1, base + 1, WOOD[2])
    c.dot(cx - 1, base - 6, WOOD[0])
    c.line((cx, base - 9), (cx - 4, base - 13), WOOD[1])
    c.line((cx, base - 9), (cx + 4, base - 13), WOOD[2])
    c.rect(cx - 3, base + 1, cx + 3, base + 1, GRASS[2])
    blobs = [(cx - 5, base - 17, 6), (cx + 5, base - 17, 6), (cx, base - 21, 8), (cx - 3, base - 13, 6), (cx + 4, base - 13, 6)]
    for (x, y, r) in blobs:
        disc(c, x, y, r, LEAF[2])
    for (x, y, r) in blobs:
        disc(c, x - 1, y - 1, r - 1, LEAF[1])
    for (x, y, r) in blobs[:3]:
        disc(c, x - 2, y - 2, max(2, r - 3), LEAF[0])
    rng = c.rng
    for _ in range(26):
        x, y = cx + rng.randint(-9, 9), base - 24 + rng.randint(0, 14)
        if c.img.getpixel((x, y))[3] and c.img.getpixel((x, y))[:3] != OUTLINE[:3]:
            c.dot(x, y, LEAF[2] if rng.random() < .55 else LEAF[0])
    if snow:
        for yy in range(base - 30, base - 17):
            for xx in range(0, width):
                p = c.img.getpixel((xx, yy))
                if p[3] and yy < base - 24 + (xx * 7 % 5):
                    c.dot(xx, yy, SNOW[0] if (xx + yy) % 3 else SNOW[1])
    return c.finish()


# --- rekvizity ---------------------------------------------------------------------------------------------------------

def polyshade(c, pts, pal):
    c.poly(pts, pal[1])


def quarry():
    c = canvas(70, 50, 2, 2)
    base = 46
    # dlažba a skalní stěna
    c.poly([(35, 49), (3, 37), (35, 25), (67, 37)], DIRT[1])
    c.poly([(35, 45), (10, 36), (35, 27), (60, 36)], DIRT[0])
    wall = [(3, 36), (5, 22), (12, 12), (22, 7), (34, 4), (46, 7), (57, 11), (65, 20), (67, 36), (35, 28)]
    c.poly(wall, STONE[1])
    c.poly([(3, 36), (5, 22), (12, 12), (22, 7), (28, 12), (22, 22), (20, 32), (35, 28)], STONE[0])
    c.poly([(46, 7), (57, 11), (65, 20), (67, 36), (47, 30), (44, 18)], STONE[2])
    for (x0, y0, x1, y1) in ((10, 18, 24, 20), (30, 12, 44, 14), (14, 26, 28, 27), (46, 24, 60, 26), (36, 18, 50, 20)):
        c.line((x0, y0), (x1, y1), STONE[2])
        c.line((x0 + 2, y0 - 1), (x1 - 3, y1 - 1), STONE[0])
    for (x, y) in ((14, 14), (26, 9), (42, 11), (54, 14), (60, 22), (8, 28), (52, 28)):
        c.rect(x, y, x + 2, y, STONE[0])
        c.dot(x + 1, y + 1, STONE[2])
    # vchod do štoly s dřevěnou výztuží
    c.poly([(26, 38), (26, 25), (30, 20), (40, 20), (44, 25), (44, 38)], BLACK)
    c.rect(24, 24, 26, 39, DARKWOOD[1])
    c.rect(44, 24, 46, 39, DARKWOOD[2])
    c.rect(24, 22, 46, 24, DARKWOOD[0])
    c.line((25, 23), (45, 23), DARKWOOD[1])
    c.dot(35, 31, GLOW[0])
    c.dot(35, 32, GLOW[1])
    # koleje a vozík
    c.line((31, 39), (28, 46), IRON[1])
    c.line((39, 39), (42, 46), IRON[1])
    for k in range(3):
        c.line((29 + k, 41 + k * 2), (41 - k, 41 + k * 2), DARKWOOD[1])
    c.rect(28, 40, 40, 44, WOOD[1])
    c.rect(28, 40, 40, 40, WOOD[0])
    c.rect(39, 40, 40, 44, WOOD[2])
    for x in (30, 36):
        c.rect(x, 44, x + 1, 45, IRON[2])
    for x in (30, 33, 36):
        c.dot(x, 39, STONE[0])
    # dřevěný jeřáb
    c.line((55, 36), (55, 22), DARKWOOD[1])
    c.line((54, 36), (54, 22), DARKWOOD[0])
    c.line((55, 22), (62, 24), DARKWOOD[1])
    c.line((55, 28), (60, 25), DARKWOOD[2])
    c.line((62, 24), (62, 29), IRON[1])
    c.rect(60, 29, 64, 31, STONE[1])
    # sloupy opracovaných kvádrů a kameny
    for (x, y, w, h) in ((6, 42, 6, 4), (11, 40, 5, 4), (8, 38, 5, 3)):
        c.rect(x, y, x + w, y + h, STONE[1])
        c.rect(x, y, x + w, y, STONE[0])
        c.rect(x + w, y, x + w, y + h, STONE[2])
    for (x, y) in ((50, 42), (55, 44), (58, 41)):
        c.rect(x, y, x + 2, y + 1, STONE[0])
        c.rect(x + 2, y, x + 2, y + 1, STONE[2])
    return c.finish()


def stall(awning):
    c = canvas(30, 36, 2, 2)
    yard(c, DIRT[0], .0)
    for (i, j) in ((.65, .75), (1.85, .75)):
        post(c, i, j, 0, 20, WOOD, 1)
    # pult se zbožím
    c.box(.7, 1.1, 1.85, 1.6, 0, 7, WOOD, top=WOOD[0])
    c.planks(.7, 1.85, 1.6, 0, 7, WOOD[2], 3)
    for (i, j) in ((.65, 1.7), (1.85, 1.7)):
        post(c, i, j, 0, 15, WOOD, 1)
    # markýza: šikmá plocha s pruhy
    o = .1
    i0, i1, j0, j1 = .6 - o, 1.9 + o, .75, 1.75 + o
    n = 6
    for k in range(n):
        a, b = i0 + (i1 - i0) * k / n, i0 + (i1 - i0) * (k + 1) / n
        quad = [c.p(a, j1, 15), c.p(b, j1, 15), c.p(b, j0, 21), c.p(a, j0, 21)]
        c.poly(quad, awning if k % 2 == 0 else WHITE)
    c.line(c.p(i0, j1, 15), c.p(i1, j1, 15), awning)
    for k in range(n + 1):
        a = i0 + (i1 - i0) * k / n
        x, y = c.p(a, j1, 15)
        c.dot(x, y + 1, awning if k % 2 == 0 else WHITE)
    c.line(c.p(i1, j0, 21), c.p(i1, j1, 15), IRON[1])
    return c


def stall_goods(c, kind):
    x, y = c.p(.85, 1.35, 7)
    if kind == 'red':
        for k in range(4):
            c.rect(x + k * 3, y - 2, x + k * 3 + 1, y - 1, RED[0])
        c.rect(x + 1, y - 4, x + 3, y - 3, rgba('#e8c46a'))
        c.rect(x + 8, y - 4, x + 10, y - 3, CLAY[0])
    else:
        for k in range(4):
            c.rect(x + k * 3, y - 2, x + k * 3 + 1, y - 1, LEAF[0])
            c.dot(x + k * 3, y - 3, LEAF[1])
        c.rect(x + 2, y - 4, x + 3, y - 4, rgba('#d9833a'))
        c.rect(x + 8, y - 4, x + 10, y - 3, RED[0])


def stall_red():
    c = stall(RED[0])
    stall_goods(c, 'red')
    bx, by = c.p(.2, 1.9, 0)
    box_px(c, bx, by, 4, 4, WOOD)
    return done(c)


def stall_green():
    green = rgba('#5f9a52')
    c = stall(green)
    stall_goods(c, 'green')
    bx, by = c.p(.2, 1.9, 0)
    barrel(c, bx, by)
    return done(c)


def fishing_hut():
    c = canvas(40, 44, 2, 2)
    c.ground(GRASS[1], .05)
    c.ground(DIRT[1], 0, 0, 0, 2, .95)
    c.ground(WATER[2], 0, 0, .95, 2, 2)
    c.ground(WATER[1], 0, .08, 1.05, 1.92, 1.92)
    c.ground(WATER[0], 0, .9, 1.25, 1.6, 1.75)
    for (i, j) in ((.3, 1.5), (.2, 1.85), (1.0, 1.9), (1.75, 1.6), (1.45, 1.2)):
        x, y = c.p(i, j)
        c.line((x - 2, y), (x + 1, y), WATER[0])
    for dx in (-1, 0, 1):
        c.dot(c.fx + dx, c.img.height - 1, WATER[1])
    c.base = c.img
    c.img = Image.new('RGBA', c.img.size, (0, 0, 0, 0))
    c.px = ImageDraw.Draw(c.img)
    # chýše na břehu vlevo vzadu
    walls(c, .3, .2, 1.05, .9, 0, 9, WOOD, 'wood')
    gable(c, .3, .2, 1.05, .9, 9, 7, THATCH, 'i', over=.16, gable_pal=WOOD, kind='thatch')
    door(c, .45, .9, 0, .28, 7)
    window_left(c, .8, .9, 4, 3)
    # síť na sušáku vpravo na břehu
    post(c, 1.55, .2, 0, 14, DARKWOOD, 1)
    post(c, 1.55, .8, 0, 14, DARKWOOD, 1)
    c.line(c.p(1.55, .2, 14), c.p(1.55, .8, 14), DARKWOOD[0])
    c.poly([c.p(1.55, .22, 13), c.p(1.55, .78, 13), c.p(1.55, .78, 5), c.p(1.55, .22, 7)], rgba('#8d989d'))
    for k in range(34):
        t, u = (k * 7 % 10) / 10.0, (k * 3 % 7) / 7.0
        a = c.p(1.55, .26 + .5 * t, 12 - 6 * u)
        c.dot(a[0], a[1], rgba('#5c676c') if k % 2 else rgba('#bfc9cc'))
    # molo vpředu od chýše do vody
    c.box(.45, .95, .75, 1.75, 1, 2, WOOD, top=WOOD[0])
    for j in (1.05, 1.2, 1.35, 1.5, 1.65):
        c.line(c.p(.45, j, 2), c.p(.75, j, 2), WOOD[2])
    for (i, j) in ((.45, 1.7), (.75, 1.7)):
        post(c, i, j, 0, 4, DARKWOOD, 1)
    # člun na vodě
    c.box(1.1, 1.35, 1.65, 1.6, 0, 3, WOOD, top=DARKWOOD[1])
    c.poly([c.p(1.65, 1.35, 3), c.p(1.65, 1.6, 3), c.p(1.8, 1.475, 3)], WOOD[0])
    c.line(c.p(1.1, 1.6, 3), c.p(1.65, 1.6, 3), WOOD[0])
    return done(c)


BUILDERS = {
    'home_tile': ('houses', home_tile, 1), 'home_thatch': ('houses', home_thatch, 1), 'home_new': ('houses', home_new, 1),
    'cottage': ('houses', cottage, 1), 'chapel': ('houses', chapel, 1),
    'tree_0': ('trees', lambda: spruce(22, 34, 4), 1), 'tree_1': ('trees', lambda: broadleaf(24, 34), 1),
    'tree_2': ('trees', lambda: spruce(22, 40, 5), 1),
    'tree_0_snow': ('trees', lambda: spruce(22, 34, 4, True), 1), 'tree_1_snow': ('trees', lambda: broadleaf(24, 34, True), 1),
    'tree_2_snow': ('trees', lambda: spruce(22, 40, 5, True), 1),
    'quarry': ('props', quarry, 1), 'stall_red': ('props', stall_red, 1), 'stall_green': ('props', stall_green, 1),
    'fishing_hut': ('props', fishing_hut, 1),
}

if __name__ == '__main__':
    wanted = sys.argv[1:] or list(BUILDERS)
    for key in wanted:
        kind, fn, frames = BUILDERS[key]
        save(kind, key, fn, frames)
