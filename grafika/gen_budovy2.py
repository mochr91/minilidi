#!/usr/bin/env python3
"""Pixel-art budovy Minilidí (dávka 2: menší stavby, hospodářství, výroba).

Spuštění z kořene projektu:  python -I grafika/gen_budovy2.py [klíč ...]
Bez argumentů nakreslí všechny budovy z tohoto souboru do prototyp/public/sprites/buildings/.
"""
import math
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from pixlib import *  # noqa: F401,F403


def cylinder(c, x, y, rx, ry, h, pal, top_pal=None, inner=None):
    """Válec ve screen souřadnicích: (x, y) je střed spodní elipsy."""
    for dx in range(-rx, rx + 1):
        bottom = y + int(round(ry * math.sqrt(max(0.0, 1 - (dx / rx) ** 2))))
        col = pal[0] if dx < -rx * .35 else (pal[2] if dx > rx * .45 else pal[1])
        for yy in range(bottom - h, bottom + 1):
            c.dot(x + dx, yy, col)
    ty = y - h
    for dy in range(-ry, ry + 1):
        half = int(round(rx * math.sqrt(max(0.0, 1 - (dy / ry) ** 2))))
        for dx in range(-half, half + 1):
            c.dot(x + dx, ty + dy, (top_pal or pal)[0])
    if inner:
        for dy in range(-ry + 1, ry):
            half = int(round((rx - 2) * math.sqrt(max(0.0, 1 - (dy / (ry - 1)) ** 2))))
            for dx in range(-half, half + 1):
                c.dot(x + dx, ty + dy, inner)


def plank_stack(c, i0, j0, i1, j1, z1):
    c.box(i0, j0, i1, j1, 0, z1, WOOD, top=WOOD[0])
    c.planks(i0, i1, j1, 0, z1, WOOD[2], 2)
    c.planks_right(i1, j0, j1, 0, z1, DARKWOOD[1], 2)


# --- pec ---------------------------------------------------------------------------------------------------------------

def pec(f=0):
    c = canvas(26, 42, 1, 1)
    yard(c)
    # komín za pecí (kreslí se první, kopule ho zakryje dole)
    walls(c, .55, .12, .78, .35, 6, 19, STONE, 'stone')
    c.poly([c.p(.55, .12, 19), c.p(.78, .12, 19), c.p(.78, .35, 19), c.p(.55, .35, 19)], STONE[2])
    cx, cy = c.p(.66, .24, 19)
    smoke(c, cx, cy - 1, f, 3)
    # kamenná podnož a hliněná kopule
    walls(c, .12, .12, .88, .88, 0, 9, STONE, 'stone')
    c.poly([c.p(.12, .12, 9), c.p(.88, .12, 9), c.p(.88, .88, 9), c.p(.12, .88, 9)], STONE[0])
    x, y = c.p(.5, .5, 9)
    hill(c, x, y + 1, 8, 7, CLAY)
    # struktura hlíny: tmavší spáry a světlý odlesk
    for yy in range(y - 8, y + 2):
        for xx in range(x - 9, x + 10):
            if c.img.getpixel((xx, yy))[:4] == CLAY[1] and (xx * 3 + yy * 5) % 9 == 0:
                c.dot(xx, yy, CLAY[2])
    for (dx, dy) in ((-3, -6), (-4, -5), (-5, -4), (-2, -6), (-4, -4)):
        c.dot(x + dx, y + dy, CLAY[0])
    c.line((x - 7, y), (x + 7, y), STONE[2])
    # ústí pece s žárem
    arch_l(c, .36, .88, 1, .36, 5, BLACK, STONE[0])
    gx, gy = c.p(.42, .88, 1)
    c.rect(gx, gy - 2, gx + 3, gy, GLOW[2])
    c.rect(gx + 1, gy - 2, gx + 2, gy - 1, GLOW[1] if f % 2 else GLOW[0])
    # dříví vpravo
    log_pile(c, .9, .98, 2, 3)
    return done(c)


# --- pila --------------------------------------------------------------------------------------------------------------

def pila():
    c = canvas(36, 41, 2, 1)
    yard(c, DIRT[0])
    # zadní stěna, kláda na kozách, pilový rám a prkna
    c.box(.6, .1, 1.75, .16, 0, 10, DARKWOOD)
    c.box(.65, .35, 1.4, .6, 3, 6, WOOD, top=WOOD[0])
    for i in (.7, 1.3):
        c.box(i, .32, i + .1, .62, 0, 3, DARKWOOD)
    sx, sy = c.p(.95, .5, 6)
    c.rect(sx, sy - 11, sx + 1, sy, DARKWOOD[1])
    c.rect(sx + 5, sy - 11, sx + 6, sy, DARKWOOD[1])
    c.rect(sx, sy - 12, sx + 6, sy - 11, DARKWOOD[0])
    c.line((sx + 3, sy - 10), (sx + 3, sy - 1), IRON[0])
    plank_stack(c, 1.45, .35, 1.75, .8, 6)
    for (i, j) in ((.6, .1), (1.75, .1)):
        post(c, i, j, 0, 12, WOOD, 1)
    for (i, j) in ((.6, .9), (1.75, .9)):
        post(c, i, j, 0, 12, WOOD, 1)
    gable(c, .55, .1, 1.78, .9, 12, 7, SHINGLE, 'i', over=.2, gable_pal=DARKWOOD, kind='shingle')
    log_pile(c, .45, 1.1, 3, 4)
    log_pile(c, 1.3, 1.2, 2, 3, DARKWOOD)
    return done(c)


# --- dílna -------------------------------------------------------------------------------------------------------------

def dilna():
    c = canvas(26, 29, 1, 1)
    yard(c)
    walls(c, .25, .2, .85, .8, 0, 9, WOOD, 'wood')
    gable(c, .25, .2, .85, .8, 9, 7, SHINGLE, 'i', over=.14, gable_pal=WOOD, kind='shingle')
    door(c, .38, .8, 0, .28, 8)
    window_left(c, .7, .8, 4, 3)
    # nářadí na stěně
    x, y = c.p(.85, .5, 5)
    c.line((x, y - 3), (x, y + 2), IRON[0])
    c.rect(x - 1, y - 4, x + 1, y - 3, IRON[1])
    # pracovní stůl a poleno
    bx, by = c.p(.05, 1.0, 0)
    c.rect(bx, by - 4, bx + 7, by - 3, WOOD[0])
    c.line((bx + 1, by - 2), (bx + 1, by), WOOD[2])
    c.line((bx + 6, by - 2), (bx + 6, by), WOOD[2])
    c.rect(bx + 2, by - 6, bx + 4, by - 5, IRON[0])
    log_pile(c, .95, .98, 2, 2)
    return done(c)


# --- studna ------------------------------------------------------------------------------------------------------------

def studna():
    c = canvas(26, 32, 1, 1)
    yard(c)
    x, y = c.p(.5, .5, 0)
    cylinder(c, x, y + 1, 6, 3, 6, STONE, STONE, WATER[2])
    for k in range(3):
        c.dot(x - 5 + k * 4, y + 3 - (k % 2), STONE[2])
    post(c, .24, .5, 4, 20, WOOD, 1)
    post(c, .78, .5, 4, 20, WOOD, 1)
    gable(c, .15, .3, .87, .7, 20, 5, SHINGLE, 'i', over=.1, gable_pal=WOOD, kind='shingle', rake=True)
    bx, by = c.p(.51, .5, 14)
    c.line((bx, by), (bx, by + 5), WOOD[0])
    c.rect(bx - 1, by + 5, bx + 1, by + 7, WOOD[1])
    c.dot(bx, by + 5, DARKWOOD[2])
    return done(c)


# --- kurník ------------------------------------------------------------------------------------------------------------

def kurnik():
    c = canvas(46, 52, 2, 2)
    yard(c, DIRT[0])
    fence(c, (.12, .3), (.12, 1.88), 4)
    fence(c, (.12, .3), (1.0, .3), 4)
    walls(c, 1.0, .25, 1.78, .98, 0, 9, WOOD, 'wood')
    gable(c, 1.0, .25, 1.78, .98, 9, 7, SHINGLE, 'i', over=.18, gable_pal=WOOD, kind='shingle')
    door_r(c, 1.78, .5, 0, .24, 6)
    c.poly([c.p(1.15, .98, 1), c.p(1.35, .98, 1), c.p(1.35, .98, 5), c.p(1.15, .98, 5)], DOORC)
    c.line(c.p(1.25, .98, 1), c.p(1.3, 1.3, 0), WOOD[0])
    c.line(c.p(1.35, .98, 1), c.p(1.4, 1.3, 0), WOOD[2])
    # okno a bidýlko
    window_left(c, 1.5, .98, 4, 3)
    chicken(c, *c.p(.5, 1.2, 0))
    chicken(c, *c.p(.9, 1.55, 0), col=rgba('#b57a4a'))
    chicken(c, *c.p(.3, 1.65, 0), col=rgba('#e3d4b0'))
    chicken(c, *c.p(1.3, 1.65, 0))
    sx, sy = c.p(.7, .55, 0)
    c.rect(sx, sy - 2, sx + 5, sy, WOOD[1])
    c.rect(sx + 1, sy - 3, sx + 4, sy - 2, THATCH[0])
    fence(c, (.12, 1.88), (1.88, 1.88), 4)
    fence(c, (1.88, .3), (1.88, 1.88), 4)
    return done(c)


# --- chlév -------------------------------------------------------------------------------------------------------------

def chliv():
    c = canvas(46, 52, 2, 2)
    yard(c, DIRT[0])
    with under(c):
        c.ground(SOIL[1], 0, .5, 1.0, 1.75, 1.75)
        c.ground(SOIL[2], 0, .8, 1.2, 1.4, 1.55)
    fence(c, (.12, .25), (.12, 1.88), 4)
    walls(c, .2, .25, 1.25, .95, 0, 9, WOOD, 'wood')
    gable(c, .2, .25, 1.25, .95, 9, 6, THATCH, 'i', over=.2, gable_pal=WOOD, kind='thatch')
    door(c, .45, .95, 0, .4, 7)
    window_left(c, .95, .95, 4, 3)
    pig(c, *c.p(1.0, 1.4, 0))
    pig(c, *c.p(1.5, 1.6, 0))
    # koryto
    tx, ty = c.p(.55, 1.6, 0)
    c.rect(tx, ty - 3, tx + 6, ty - 1, WOOD[1])
    c.rect(tx + 1, ty - 3, tx + 5, ty - 3, THATCH[0])
    fence(c, (.12, 1.88), (1.88, 1.88), 4)
    fence(c, (1.88, .98), (1.88, 1.88), 4)
    return done(c)


# --- ohrada ------------------------------------------------------------------------------------------------------------

def ohrada():
    c = canvas(76, 65, 4, 3)
    yard(c, GRASS[0], .08)
    with under(c):
        c.ground(DIRT[0], 0, 2.4, .3, 3.5, 1.1)
        c.ground(DIRT[1], 0, 1.3, 2.0, 2.3, 2.7)
    fence(c, (.22, .15), (.22, 2.88), 4)
    fence(c, (.22, .15), (3.88, .15), 4)
    # stodůlka
    walls(c, .4, .3, 1.8, 1.15, 0, 11, WOOD, 'wood')
    gable(c, .4, .3, 1.8, 1.15, 11, 8, THATCH, 'i', over=.22, gable_pal=WOOD, kind='thatch')
    c.poly([c.p(.8, 1.15, 0), c.p(1.4, 1.15, 0), c.p(1.4, 1.15, 8), c.p(.8, 1.15, 8)], DARKWOOD[1])
    c.line(c.p(.8, 1.15, 0), c.p(1.4, 1.15, 8), DARKWOOD[2])
    c.line(c.p(1.4, 1.15, 0), c.p(.8, 1.15, 8), DARKWOOD[2])
    # napajedlo
    c.box(2.6, .45, 3.4, .75, 0, 3, WOOD, top=WATER[1])
    c.line(c.p(2.65, .5, 3), c.p(3.35, .5, 3), WATER[0])
    # krávy
    cow(c, *c.p(2.4, 1.5, 0))
    cow(c, *c.p(3.1, 1.9, 0), spots=False, brown=True, face=-1)
    cow(c, *c.p(1.8, 2.1, 0), face=-1)
    cow(c, *c.p(2.9, 1.1, 0), spots=False, brown=True)
    hx, hy = c.p(1.0, 1.6, 0)
    hill(c, hx, hy, 5, 4, THATCH)
    fence(c, (.22, 2.88), (3.88, 2.88), 4)
    fence(c, (3.88, .15), (3.88, 2.88), 4)
    return done(c)


# --- stáje -------------------------------------------------------------------------------------------------------------

def staje():
    c = canvas(56, 64, 3, 2)
    yard(c)
    walls(c, .4, .22, 2.72, 1.0, 0, 11, WOOD, 'wood')
    c.line(c.p(.4, 1.0, 11), c.p(2.72, 1.0, 11), DARKWOOD[0])
    gable(c, .4, .22, 2.72, 1.0, 11, 10, THATCH, 'i', over=.24, gable_pal=WOOD, kind='thatch')
    for k, i in enumerate((.65, 1.3, 1.95)):
        c.poly([c.p(i, 1.0, 0), c.p(i + .45, 1.0, 0), c.p(i + .45, 1.0, 9), c.p(i, 1.0, 9)], DOORC)
        c.line(c.p(i, 1.0, 4), c.p(i + .45, 1.0, 4), WOOD[0])
        if k != 1:
            hx, hy = c.p(i + .1, 1.0, 5)
            horse(c, hx + 2, hy + 4)
    # vůz s kopou sena před stájemi
    c.box(.5, 1.42, 1.3, 1.82, 4, 7, WOOD, top=WOOD[0])
    c.planks(.5, 1.3, 1.82, 4, 7, WOOD[2], 2)
    for i in (.62, 1.1):
        wheel_iso(c, 'j', 1.83, i, 4, 4, DARKWOOD[1], 6, 0.3, rim=DARKWOOD[0])
    c.line(c.p(1.3, 1.62, 4), c.p(1.9, 1.62, 3), WOOD[0])
    hx, hy = c.p(.9, 1.6, 7)
    hill(c, hx, hy, 6, 3, THATCH)
    # káď s vodou
    barrel(c, *c.p(2.2, 1.55, 0))
    return done(c)


# --- hřbitov -----------------------------------------------------------------------------------------------------------

def hrob_kriz(c, i, j):
    x, y = c.p(i, j, 0)
    c.line((x, y), (x, y - 7), WOOD[1])
    c.line((x - 2, y - 5), (x + 2, y - 5), WOOD[0])
    c.dot(x + 1, y - 7, WOOD[2])


def hrob_kamen(c, i, j, h=5):
    """Náhrobek se zaoblenou hlavou a vyrytým křížkem."""
    x, y = c.p(i, j, 0)
    c.rect(x, y - h, x + 3, y, STONE[0])
    c.rect(x + 3, y - h, x + 3, y, STONE[2])
    c.rect(x + 1, y - h - 1, x + 2, y - h - 1, STONE[0])
    c.dot(x + 1, y - h + 1, STONE[2])
    c.dot(x + 1, y - h + 2, STONE[2])
    c.dot(x, y - h + 2, STONE[2])
    c.dot(x + 2, y - h + 2, STONE[2])
    c.line((x, y + 1), (x + 3, y + 1), STONE[2])


def kere(c, x, y):
    c.rect(x - 3, y - 3, x + 3, y, PINE[1])
    c.rect(x - 2, y - 4, x + 1, y - 4, PINE[0])
    c.rect(x + 2, y - 3, x + 3, y, PINE[2])
    c.dot(x - 2, y - 3, LEAF[1])


def hrbitov():
    c = canvas(66, 60, 3, 3)
    yard(c, GRASS[0], .05)
    left = [(.35, 1.05), (.35, 1.7), (.35, 2.3)]
    right = [(2.15, 1.25), (2.15, 1.9), (2.15, 2.45)]
    with under(c):
        c.ground(DIRT[0], 0, 1.35, 1.0, 1.7, 2.9)
        for (i, j) in left + right:
            c.ground(SOIL[1], 0, i, j, i + .35, j + .5)
            c.ground(SOIL[0], 0, i, j, i + .35, j + .08)
    stone_wall(c, (.1, .1), (2.9, .1), 3, .08)
    stone_wall(c, (.1, .1), (.1, 2.9), 3, .08)
    # hrobka
    walls(c, 1.25, .25, 2.0, 1.0, 0, 11, STONE, 'stone')
    gable(c, 1.25, .25, 2.0, 1.0, 11, 7, SLATE, 'j', over=.12, gable_pal=STONE, kind='slate')
    arch_l(c, 1.45, 1.0, 0, .3, 6, DOORC, STONE[0])
    x, y = c.p(1.62, 1.0, 19)
    c.line((x, y), (x, y - 6), STONE[0])
    c.line((x - 2, y - 4), (x + 2, y - 4), STONE[0])
    # hroby od zadních k předním (podle hloubky)
    plan = [(left[0], 'k'), (right[0], 'c'), (left[1], 'c'), (right[1], 'k'), (left[2], 'k'), (right[2], 'c')]
    for (i, j), kind in plan:
        if kind == 'k':
            hrob_kamen(c, i + .13, j - .02)
        else:
            hrob_kriz(c, i + .17, j - .02)
    kere(c, *c.p(.3, 2.7, 0))
    kere(c, *c.p(2.65, .5, 0))
    # přední zídka s bránou: dva pilíře a železné pruty
    stone_wall(c, (.1, 2.9), (1.2, 2.9), 2, .08)
    stone_wall(c, (1.8, 2.9), (2.9, 2.9), 2, .08)
    for i in (1.2, 1.8):
        c.box(i - .07, 2.84, i + .07, 2.96, 0, 8, STONE, top=STONE[0])
    for k in range(3):
        x, y = c.p(1.35 + k * .15, 2.98, 0)
        c.line((x, y), (x, y - 4), IRON[1])
    c.line(c.p(1.2, 2.98, 6), c.p(1.8, 2.98, 6), IRON[1])
    stone_wall(c, (2.9, .1), (2.9, 2.9), 2, .08)
    return done(c)


# --- zahrada -----------------------------------------------------------------------------------------------------------

def zahrada():
    c = canvas(56, 55, 3, 2)
    yard(c, GRASS[0], .08)
    beds = [.3, .95, 1.6, 2.25]
    with under(c):
        for k, i in enumerate(beds):
            c.ground(SOIL[1], 0, i, .3, i + .45, 1.7)
            c.ground(SOIL[2], 0, i + .3, .3, i + .45, 1.7)
        for k, i in enumerate(beds):
            for m in range(5):
                x, y = c.p(i + .12, .4 + m * .3, 0)
                if k % 2 == 0:
                    c.rect(x, y - 2, x + 2, y, LEAF[1])
                    c.rect(x + 1, y - 3, x + 2, y - 2, LEAF[0])
                    c.dot(x, y - 1, LEAF[2])
                else:
                    c.dot(x, y - 1, LEAF[0])
                    c.dot(x + 1, y - 2, LEAF[1])
                    c.dot(x + 2, y - 1, LEAF[0])
                    c.dot(x + 1, y, rgba('#d9833a'))
    fence(c, (.1, .12), (.1, 1.9), 4)
    fence(c, (.1, .12), (2.9, .12), 4)
    # strašák
    x, y = c.p(1.5, 1.0, 0)
    c.line((x, y), (x, y - 13), WOOD[1])
    c.line((x - 4, y - 10), (x + 4, y - 10), WOOD[0])
    c.rect(x - 3, y - 10, x + 3, y - 6, rgba('#6f8aa8'))
    c.rect(x - 1, y - 14, x + 1, y - 12, rgba('#e8c9a0'))
    c.rect(x - 3, y - 15, x + 3, y - 15, rgba('#7b5a3a'))
    c.rect(x - 1, y - 17, x + 1, y - 16, rgba('#7b5a3a'))
    barrel(c, *c.p(2.55, 1.6, 0))
    fence(c, (.1, 1.9), (2.9, 1.9), 4)
    fence(c, (2.9, .12), (2.9, 1.9), 4)
    return done(c)


# --- sklep -------------------------------------------------------------------------------------------------------------

def sklep():
    c = canvas(46, 52, 2, 2)
    yard(c)
    x, y = c.p(1.0, 1.0, 0)
    hill(c, x, y + 1, 18, 16, GRASS)
    for (dx, dy) in ((-8, -9), (4, -12), (9, -5), (-3, -4), (-12, -3), (0, -15)):
        c.dot(x + dx, y + dy, DIRT[1])
        c.dot(x + dx + 1, y + dy, DIRT[1])
    # kamenné čelo s dveřmi
    walls(c, .45, 1.3, 1.6, 1.6, 0, 12, STONE, 'stone')
    c.poly([c.p(.45, 1.3, 12), c.p(1.6, 1.3, 12), c.p(1.6, 1.6, 12), c.p(.45, 1.6, 12)], GRASS[0])
    arch_l(c, .85, 1.6, 0, .4, 8, DOORC, WOOD[0])
    c.line(c.p(.85, 1.6, 3), c.p(1.25, 1.6, 3), IRON[1])
    c.line(c.p(.85, 1.6, 6), c.p(1.25, 1.6, 6), IRON[1])
    # větrací roura a sudy
    vx, vy = c.p(1.35, .9, 10)
    c.rect(vx, vy - 6, vx + 2, vy, IRON[1])
    c.rect(vx - 1, vy - 8, vx + 3, vy - 7, IRON[2])
    barrel(c, *c.p(.2, 1.8, 0))
    barrel(c, *c.p(1.5, 1.85, 0))
    return done(c)


# --- udírna ------------------------------------------------------------------------------------------------------------

def udirna(f=0):
    c = canvas(46, 64, 2, 2)
    yard(c)
    walls(c, .35, .35, 1.5, 1.4, 0, 11, DARKWOOD, 'wood')
    gable(c, .35, .35, 1.5, 1.4, 11, 11, SHINGLE, 'j', over=.2, gable_pal=DARKWOOD, kind='shingle')
    # vstup se zavěšenými uzeninami
    c.poly([c.p(.65, 1.42, 0), c.p(1.2, 1.42, 0), c.p(1.2, 1.42, 9), c.p(.65, 1.42, 9)], BLACK)
    for k in range(4):
        x, y = c.p(.72 + k * .13, 1.42, 9)
        c.line((x, y), (x, y + 4 + k % 2), WOOD[0])
        c.rect(x - 1, y + 4 + k % 2, x, y + 7 + k % 2, RED[1] if k % 2 else CLAY[1])
    c.line(c.p(.65, 1.42, 9), c.p(1.2, 1.42, 9), WOOD[0])
    chimney(c, .8, .55, 20, 29, .24, DARKWOOD)
    cx, cy = c.p(.92, .67, 29)
    smoke(c, cx, cy - 1, f, 4)
    # přístřešek na dříví vpravo
    c.box(1.5, .6, 1.85, 1.35, 0, 5, DARKWOOD)
    lean_roof(c, 1.5, .6, 1.85, 1.35, 8, 5, SHINGLE)
    log_pile(c, 1.55, 1.7, 3, 3)
    return done(c)


# --- milíř -------------------------------------------------------------------------------------------------------------

def milir(f=0):
    c = canvas(46, 58, 2, 2)
    yard(c, DARKWOOD[2], .1)
    x, y = c.p(.95, 1.0, 0)
    hill(c, x, y + 2, 16, 15, SOIL)
    for (dx, dy) in ((-9, -7), (3, -11), (8, -4), (-4, -3), (-12, -2), (0, -13), (6, -8)):
        c.rect(x + dx, y + dy, x + dx + 2, y + dy, GRASS[1])
        c.dot(x + dx + 1, y + dy - 1, GRASS[0])
    # věnec polen u paty
    for k in range(7):
        c.rect(x - 15 + k * 5, y + 3 - (k % 2), x - 12 + k * 5, y + 4 - (k % 2), WOOD[1])
        c.dot(x - 12 + k * 5, y + 3 - (k % 2), WOOD[0])
    # komínek na vrcholu a kouř
    c.rect(x - 1, y - 17, x + 1, y - 15, DARKWOOD[2])
    smoke(c, x, y - 18, f, 5)
    # chatrč uhlíře
    gable(c, 1.3, 1.25, 1.85, 1.85, 0, 11, THATCH, 'j', over=.1, gable_pal=THATCH, kind='thatch')
    dx, dy = c.p(1.57, 1.97, 0)
    c.rect(dx - 1, dy - 5, dx + 1, dy, BLACK)
    log_pile(c, .15, 1.9, 3, 4)
    return done(c)


# --- chmelnice ---------------------------------------------------------------------------------------------------------

def chmelnice():
    c = canvas(66, 60, 3, 3)
    yard(c, SOIL[1], .05)
    rows = [.5, 1.3, 2.1, 2.7]
    cols = [.35, .95, 1.55, 2.15, 2.7]
    with under(c):
        for i in rows:
            c.ground(SOIL[2], 0, i - .12, .15, i + .12, 2.85)
    pts = sorted(((i, j) for i in rows for j in cols), key=lambda t: t[0] + t[1])
    tops = {}
    for (i, j) in pts:
        x, y0 = c.p(i, j, 0)
        _, y1 = c.p(i, j, 27)
        tops[(i, j)] = (x, y1)
        c.line((x, y0), (x, y1), DARKWOOD[1])
        # popínavý chmel
        for yy in range(y1 + 2, y0 - 1):
            off = 1 if ((yy + int(i * 7 + j * 3)) // 3) % 2 == 0 else 0
            c.rect(x - 1 + off, yy, x + off, yy, LEAF[1] if (yy % 3) else LEAF[2])
            if yy % 4 == 0:
                c.dot(x - 2 + off, yy, LEAF[0])
        c.dot(x, y1 - 1, DARKWOOD[0])
    # dráty
    for i in rows:
        for a, b in zip(cols, cols[1:]):
            c.line(tops[(i, a)], tops[(i, b)], rgba('#d8d0b0'))
    for a, b in zip(rows, rows[1:]):
        for j in cols:
            c.line(tops[(a, j)], tops[(b, j)], rgba('#b8b090'))
    return done(c)


# --- sladovna ----------------------------------------------------------------------------------------------------------

def sladovna():
    c = canvas(46, 52, 2, 2)
    yard(c)
    walls(c, .35, .3, 1.55, 1.45, 0, 7, STONE, 'stone')
    walls(c, .35, .3, 1.55, 1.45, 7, 15, PLASTER, 'timber')
    gable(c, .35, .3, 1.55, 1.45, 15, 13, TILE, 'j', over=.22, gable_pal=PLASTER, kind='tile')
    arch_l(c, .75, 1.45, 0, .42, 8, DOORC, WOOD[0])
    window_left(c, 1.25, 1.45, 9, 4)
    # nakládací dvířka ve štítu s trámem
    hx, hy = c.p(.95, 1.67, 20)
    c.rect(hx - 2, hy, hx + 2, hy + 4, DOORC)
    c.line((hx, hy - 2), (hx + 5, hy - 2), WOOD[2])
    # větrací věžička na hřebeni
    walls(c, .85, .7, 1.05, .9, 28, 33, WOOD, 'wood')
    pyramid(c, .85, .7, 1.05, .9, 33, 6, SHINGLE, over=.06)
    chimney(c, 1.2, .5, 18, 28, .2, STONE)
    sx, sy = c.p(1.45, 1.75, 0)
    sack(c, sx, sy)
    sack(c, sx + 4, sy + 1)
    sack(c, sx + 2, sy - 4)
    return done(c)


# --- důl ---------------------------------------------------------------------------------------------------------------

def dul():
    c = canvas(46, 52, 2, 2)
    yard(c, STONE[2], .05)
    x, y = c.p(1.0, 1.0, 0)
    hill(c, x, y + 2, 19, 20, STONE)
    for (dx, dy) in ((-10, -8), (6, -12), (11, -4), (-14, -3), (0, -17), (-5, -13)):
        c.rect(x + dx, y + dy, x + dx + 2, y + dy, STONE[2])
    # dřevěný portál
    c.box(.62, 1.3, .76, 1.42, 0, 13, DARKWOOD, top=DARKWOOD[0])
    c.box(1.3, 1.3, 1.44, 1.42, 0, 13, DARKWOOD, top=DARKWOOD[0])
    c.poly([c.p(.76, 1.42, 0), c.p(1.3, 1.42, 0), c.p(1.3, 1.42, 12), c.p(.76, 1.42, 12)], BLACK)
    c.box(.58, 1.3, 1.48, 1.44, 12, 15, DARKWOOD, top=DARKWOOD[0])
    lx, ly = c.p(1.05, 1.44, 12)
    c.line((lx, ly), (lx, ly + 3), IRON[1])
    c.rect(lx - 1, ly + 3, lx + 1, ly + 5, GLOW[0])
    # koleje a vozík
    c.line(c.p(.9, 1.45, 0), c.p(.95, 2.0, 0), IRON[1])
    c.line(c.p(1.15, 1.45, 0), c.p(1.2, 2.0, 0), IRON[1])
    for j in (1.55, 1.7, 1.85):
        a, b = c.p(.85, j, 0), c.p(1.25, j, 0)
        c.line(a, b, DARKWOOD[1])
    c.box(.88, 1.6, 1.22, 1.88, 2, 6, WOOD, top=STONE[1])
    c.planks(.88, 1.22, 1.88, 2, 6, WOOD[2], 2)
    c.dot(c.p(1.0, 1.88, 6)[0], c.p(1.0, 1.88, 6)[1] - 1, STONE[0])
    c.dot(c.p(1.1, 1.88, 6)[0], c.p(1.1, 1.88, 6)[1] - 1, STONE[0])
    for i in (.94, 1.14):
        c.dot(*c.p(i, 1.9, 1), DARKWOOD[2])
    # hromada kamení a krumpáč
    kx, ky = c.p(1.65, 1.6, 0)
    hill(c, kx, ky, 5, 4, STONE)
    px, py = c.p(.35, 1.7, 0)
    c.line((px, py), (px + 3, py - 10), WOOD[1])
    c.line((px, py - 10), (px + 6, py - 10), IRON[1])
    return done(c)


# --- vinice ------------------------------------------------------------------------------------------------------------

def vinice():
    c = canvas(66, 60, 3, 3)
    yard(c, SOIL[0], .05)
    rows = [.45, 1.15, 1.85, 2.55]
    cols = [.3, .8, 1.3, 1.8, 2.3, 2.8]
    with under(c):
        for i in rows:
            c.ground(SOIL[2], 0, i - .1, .1, i + .1, 2.9)
    pts = sorted(((i, j) for i in rows for j in cols), key=lambda t: t[0] + t[1])
    for i in rows:
        for a, b in zip(cols, cols[1:]):
            for z in (6, 10):
                c.line(c.p(i, a, z), c.p(i, b, z), rgba('#cfc7a8'))
    for (i, j) in pts:
        x, y0 = c.p(i, j, 0)
        c.line((x, y0), (x, y0 - 12), DARKWOOD[1])
        c.rect(x - 2, y0 - 10, x + 2, y0 - 2, LEAF[1])
        c.rect(x - 2, y0 - 10, x - 1, y0 - 6, LEAF[0])
        c.rect(x + 1, y0 - 7, x + 2, y0 - 2, LEAF[2])
        c.dot(x - 1, y0 - 4, rgba('#6b3f78'))
        c.dot(x + 1, y0 - 3, rgba('#6b3f78'))
        c.dot(x, y0 - 2, rgba('#8d5a9c'))
    x, y = c.p(2.85, 2.9, 0)
    barrel(c, x - 2, y + 1)
    return done(c)


# --- lis ---------------------------------------------------------------------------------------------------------------

def lis():
    c = canvas(46, 52, 2, 2)
    yard(c)
    walls(c, .25, .3, 1.3, 1.2, 0, 7, WOOD, 'wood')
    walls(c, .25, .3, 1.3, 1.2, 7, 13, PLASTER, 'timber')
    gable(c, .25, .3, 1.3, 1.2, 13, 10, TILE, 'i', over=.2, gable_pal=PLASTER, kind='tile')
    door(c, .45, 1.2, 0, .4, 9)
    window_left(c, .98, 1.2, 8, 4)
    chimney(c, .5, .4, 18, 25, .2, STONE)
    # vinný lis: základna, dva sloupy, příčník a šroub s pákou
    c.box(1.3, 1.3, 1.85, 1.85, 0, 4, DARKWOOD, top=WOOD[0])
    x, y = c.p(1.575, 1.575, 4)
    c.rect(x - 3, y - 3, x + 3, y, rgba('#6b3f78'))
    c.rect(x - 3, y - 4, x + 3, y - 3, rgba('#8d5a9c'))
    post(c, 1.38, 1.38, 4, 20, DARKWOOD, 1)
    post(c, 1.78, 1.38, 4, 20, DARKWOOD, 1)
    c.line((x - 5, y - 19), (x + 5, y - 19), DARKWOOD[0])
    c.line((x - 5, y - 18), (x + 5, y - 18), DARKWOOD[1])
    c.line((x, y - 18), (x, y - 8), IRON[0])
    c.line((x - 1, y - 18), (x - 1, y - 8), IRON[1])
    c.line((x - 6, y - 12), (x + 6, y - 14), WOOD[0])
    barrel(c, *c.p(.25, 1.55, 0))
    barrel(c, *c.p(.65, 1.7, 0))
    bx, by = c.p(1.0, 1.85, 0)
    c.rect(bx, by - 3, bx + 4, by - 1, WOOD[1])
    for k in range(3):
        c.dot(bx + 1 + k, by - 4, rgba('#8d5a9c'))
    return done(c)


BUILDERS = {
    'pec': ('buildings', pec, 4), 'pila': ('buildings', pila, 1), 'dilna': ('buildings', dilna, 1),
    'studna': ('buildings', studna, 1), 'kurnik': ('buildings', kurnik, 1), 'chliv': ('buildings', chliv, 1),
    'ohrada': ('buildings', ohrada, 1), 'staje': ('buildings', staje, 1), 'hrbitov': ('buildings', hrbitov, 1),
    'zahrada': ('buildings', zahrada, 1), 'sklep': ('buildings', sklep, 1), 'udirna': ('buildings', udirna, 4),
    'milir': ('buildings', milir, 4), 'chmelnice': ('buildings', chmelnice, 1), 'sladovna': ('buildings', sladovna, 1),
    'dul': ('buildings', dul, 1), 'vinice': ('buildings', vinice, 1), 'lis': ('buildings', lis, 1),
}

if __name__ == '__main__':
    wanted = sys.argv[1:] or list(BUILDERS)
    for key in wanted:
        kind, fn, frames = BUILDERS[key]
        save(kind, key, fn, frames)
