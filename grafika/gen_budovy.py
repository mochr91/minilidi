#!/usr/bin/env python3
"""Pixel-art budovy Minilidí (dávka 1: kostel, mlyn, hospoda, statek, pivovar, kovarna ...).

Spuštění z kořene projektu:  python -I grafika/gen_budovy.py [klíč ...]
Bez argumentů nakreslí všechny budovy z tohoto souboru do prototyp/public/sprites/buildings/.
"""
import math
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from pixlib import *  # noqa: F401,F403


# --- kostel ------------------------------------------------------------------------------------------------------------

def kostel():
    c = canvas(46, 87, 2, 2)
    yard(c, DIRT[1])
    # loď: kamenná, břidlicová střecha, vpravo štít s oknem
    walls(c, .5, .45, 1.86, 1.45, 0, 22, STONE, 'stone')
    gable(c, .5, .45, 1.86, 1.45, 22, 13, SLATE, 'i', over=.2, gable_pal=STONE, kind='slate')
    for i in (1.0, 1.32, 1.64):
        window_arch(c, i, 1.45, 7, 9, .2)
    for i in (.95, 1.45):
        c.box(i, 1.45, i + .1, 1.57, 0, 14, STONE, top=STONE[0])
    # růžice ve štítu
    rx, ry = c.p(2.0, .95, 30)
    c.rect(rx - 1, ry - 1, rx + 1, ry + 1, rgba('#7fa3b5'))
    c.dot(rx, ry, GOLD)
    # věž vlevo vpředu
    walls(c, .1, .72, .86, 1.6, 0, 50, STONE, 'stone')
    c.line(c.p(.1, 1.6, 36), c.p(.86, 1.6, 36), STONE[0])
    c.line(c.p(.86, .72, 36), c.p(.86, 1.6, 36), STONE[1])
    arch_l(c, .32, 1.6, 0, .34, 9, DOORC, STONE[0])
    window_arch(c, .38, 1.6, 20, 5, .2)
    for i in (.22, .5):
        window_arch(c, i, 1.6, 40, 7, .16, DOORC)
    door_r(c, .86, 1.0, 40, .26, 7)
    x, y = c.p(.5, 1.6, 30)
    c.rect(x - 1, y - 1, x + 1, y + 1, GOLD)
    c.dot(x, y, DOORC)
    apex = pyramid(c, .1, .72, .86, 1.6, 50, 22, SLATE, over=.12, kind='slate')
    c.line(apex, (apex[0], apex[1] - 6), GOLD)
    c.line((apex[0] - 2, apex[1] - 4), (apex[0] + 2, apex[1] - 4), GOLD)
    c.dot(apex[0] + 1, apex[1] - 6, GOLD)
    # kamenné hroby před kostelem
    for (i, j) in ((1.15, 1.85), (1.5, 1.9), (1.8, 1.8)):
        x, y = c.p(i, j, 0)
        c.rect(x, y - 4, x + 2, y, STONE[0])
        c.rect(x + 2, y - 4, x + 3, y, STONE[2])
        c.dot(x, y - 5, STONE[0])
        c.dot(x + 1, y - 5, STONE[0])
    return done(c)


# --- mlýn --------------------------------------------------------------------------------------------------------------

def mlyn(f=0):
    c = canvas(36, 41, 2, 1)
    yard(c)
    # náhon pod kolem
    c.ground(WATER[1], 0, 1.78, -.1, 2.0, 1.15)
    c.ground(WATER[0], 0, 1.84, .05, 1.92, .95)
    walls(c, .55, .15, 1.72, .85, 0, 6, STONE, 'stone')
    walls(c, .55, .15, 1.72, .85, 6, 17, WOOD, 'wood')
    c.line(c.p(.55, .85, 6), c.p(1.72, .85, 6), DARKWOOD[0])
    gable(c, .55, .15, 1.72, .85, 17, 8, SHINGLE, 'i', over=.18, gable_pal=WOOD, kind='shingle')
    door(c, .8, .85, 0, .36, 9)
    window_left(c, 1.25, .85, 8, 4)
    window_left(c, 1.45, .85, 8, 4)
    sx, sy = c.p(.5, 1.0, 0)
    sack(c, sx, sy)
    sack(c, sx + 4, sy + 1)
    # vodní kolo
    wheel_iso(c, 'i', 1.86, .5, 9, 9, WOOD[2], 8, f * math.pi / 16, rim=WOOD[0], paddles=WOOD[1])
    return done(c)


# --- hospoda -----------------------------------------------------------------------------------------------------------

def hospoda():
    c = canvas(36, 41, 2, 1)
    yard(c)
    walls(c, .42, .1, 1.75, .9, 0, 13, PLASTER, 'timber')
    gable(c, .42, .1, 1.75, .9, 13, 11, TILE, 'i', over=.2, gable_pal=PLASTER, kind='tile')
    chimney(c, 1.35, .22, 20, 26)
    door(c, .95, .9, 0, .4, 9)
    window_left(c, .55, .9, 5, 4)
    window_left(c, 1.4, .9, 5, 4)
    window_r(c, 1.75, .35, 5, .3, 4)
    # vývěsní štít na konzole
    x, y = c.p(.42, .95, 12)
    c.line((x, y), (x - 6, y), DARKWOOD[1])
    c.line((x - 6, y), (x - 6, y + 1), DARKWOOD[1])
    c.rect(x - 8, y + 1, x - 4, y + 5, GOLD)
    c.dot(x - 6, y + 3, RED[1])
    # lavice a sud
    bx, by = c.p(.7, 1.15, 0)
    c.rect(bx, by - 3, bx + 6, by - 3, WOOD[0])
    c.line((bx, by - 2), (bx, by), WOOD[2])
    c.line((bx + 6, by - 2), (bx + 6, by), WOOD[2])
    barrel(c, *c.p(1.4, 1.12, 0))
    return done(c)


# --- statek ------------------------------------------------------------------------------------------------------------

def statek():
    c = canvas(36, 41, 2, 1)
    yard(c)
    # obytná část (vlevo) a stodola (vpravo)
    walls(c, .42, .1, 1.1, .9, 0, 9, PLASTER, 'plaster')
    gable(c, .42, .1, 1.1, .9, 9, 7, TILE, 'i', over=.15, gable_pal=PLASTER, kind='tile')
    door(c, .6, .9, 0, .3, 8)
    window_left(c, .85, .9, 4, 3)
    walls(c, 1.1, .1, 1.78, .9, 0, 11, WOOD, 'wood')
    gable(c, 1.1, .1, 1.78, .9, 11, 9, THATCH, 'i', over=.2, gable_pal=WOOD, kind='thatch')
    # velká vrata stodoly
    c.poly([c.p(1.28, .9, 0), c.p(1.62, .9, 0), c.p(1.62, .9, 9), c.p(1.28, .9, 9)], DARKWOOD[1])
    c.line(c.p(1.28, .9, 0), c.p(1.62, .9, 9), DARKWOOD[2])
    c.line(c.p(1.62, .9, 0), c.p(1.28, .9, 9), DARKWOOD[2])
    chimney(c, .62, .22, 12, 20)
    # kupka sena a koryto
    hx, hy = c.p(.5, 1.12, 0)
    hill(c, hx + 4, hy, 5, 4, THATCH)
    return done(c)


# --- pivovar -----------------------------------------------------------------------------------------------------------

def pivovar(f=0):
    c = canvas(46, 66, 2, 2)
    yard(c)
    walls(c, .22, .3, 1.35, 1.45, 0, 7, STONE, 'stone')
    walls(c, .22, .3, 1.35, 1.45, 7, 16, PLASTER, 'plaster')
    gable(c, .22, .3, 1.35, 1.45, 16, 11, TILE, 'i', over=.2, gable_pal=PLASTER, kind='tile')
    arch_l(c, .55, 1.45, 0, .38, 9, DOORC, STONE[0])
    window_left(c, .95, 1.45, 9, 4)
    window_left(c, .35, 1.45, 9, 4)
    # vysoký komín
    chimney(c, .3, .4, 22, 31, .16, CLAY)
    cx, cy = c.p(.38, .48, 31)
    smoke(c, cx, cy - 1, f, 4)
    # přístavek s měděným kotlem
    walls(c, 1.35, .75, 1.85, 1.55, 0, 9, STONE, 'stone')
    c.poly([c.p(1.35, .75, 9), c.p(1.85, .75, 9), c.p(1.85, 1.55, 9), c.p(1.35, 1.55, 9)], STONE[0])
    kx, ky = c.p(1.6, 1.15, 9)
    hill(c, kx, ky, 6, 6, COPPER)
    c.dot(kx - 1, ky - 7, GLOW[0])
    c.line((kx + 1, ky - 6), (kx + 1, ky - 11), IRON[1])
    px, py = kx + 1, ky - 12
    smoke(c, px, py, f, 3)
    # sudy před pivovarem
    bx, by = c.p(1.1, 1.78, 0)
    barrel(c, bx, by)
    barrel(c, bx + 5, by + 1)
    barrel(c, bx + 2, by - 5)
    return done(c)


# --- kovárna -----------------------------------------------------------------------------------------------------------

def kovarna(f=0):
    c = canvas(46, 52, 2, 2)
    yard(c, DIRT[2])
    walls(c, .22, .3, 1.5, 1.4, 0, 12, STONE, 'stone')
    gable(c, .22, .3, 1.5, 1.4, 12, 9, SLATE, 'i', over=.22, gable_pal=STONE, kind='slate')
    # velký komín s ohništěm
    walls(c, 1.5, .5, 1.85, 1.05, 0, 17, STONE, 'stone')
    c.poly([c.p(1.5, .5, 17), c.p(1.85, .5, 17), c.p(1.85, 1.05, 17), c.p(1.5, 1.05, 17)], STONE[0])
    cx, cy = c.p(1.68, .78, 17)
    c.rect(cx - 2, cy - 4, cx, cy, STONE[1])
    c.rect(cx - 1, cy - 4, cx, cy - 1, BLACK)
    smoke(c, cx, cy - 4, f, 4)
    # otevřený vstup se žárem
    c.poly([c.p(.55, 1.4, 0), c.p(1.25, 1.4, 0), c.p(1.25, 1.4, 10), c.p(.55, 1.4, 10)], DOORC)
    glow = GLOW[(f + 1) % 3]
    gx, gy = c.p(.75, 1.4, 0)
    c.rect(gx + 1, gy - 4, gx + 6, gy - 1, glow)
    c.rect(gx + 2, gy - 3, gx + 5, gy - 2, GLOW[0])
    c.line(c.p(.55, 1.4, 10), c.p(1.25, 1.4, 10), DARKWOOD[0])
    # kovadlina na špalku
    ax, ay = c.p(1.0, 1.85, 0)
    c.rect(ax, ay - 4, ax + 2, ay, DARKWOOD[1])
    c.rect(ax - 2, ay - 6, ax + 4, ay - 5, IRON[1])
    c.rect(ax - 2, ay - 7, ax + 4, ay - 7, IRON[0])
    c.dot(ax + 5, ay - 6, IRON[1])
    # jiskry
    sparks = [(1, -9), (3, -11), (-1, -12), (4, -8)]
    for k, (dx, dy) in enumerate(sparks):
        if (k + f) % 2 == 0:
            c.dot(ax + dx + (f % 2), ay + dy - f, GLOW[0])
    # uhlí a podkovy na stěně
    ux, uy = c.p(.3, 1.8, 0)
    hill(c, ux, uy, 4, 3, DARKWOOD)
    for k in range(3):
        c.dot(c.p(1.3 + k * .08, 1.4, 6)[0], c.p(1.3, 1.4, 6)[1] + k, IRON[0])
    return done(c)


BUILDERS = {
    'kostel': ('buildings', kostel, 1), 'mlyn': ('buildings', mlyn, 4), 'hospoda': ('buildings', hospoda, 1),
    'statek': ('buildings', statek, 1), 'pivovar': ('buildings', pivovar, 4), 'kovarna': ('buildings', kovarna, 4),
}

if __name__ == '__main__':
    wanted = sys.argv[1:] or list(BUILDERS)
    for key in wanted:
        kind, fn, frames = BUILDERS[key]
        save(kind, key, fn, frames)
