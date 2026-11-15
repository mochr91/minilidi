"""Společná knihovna generátorů pixel-art obrázků Minilidí (Pillow), navazuje na gen_sprites.py.

Obrázky se kreslí bod po bodu v plné velikosti souboru (měřítko 2 dělá hra). Izometrie 2:1, políčko = 10×5 bodů obrázku,
spodní střed obrázku je přední roh půdorysu. Výstup jde do `prototyp/public/sprites/<druh>/`; je-li nastavena proměnná
prostředí SPRITES_OUT, bere se jako kořen složky sprites (s podsložkami buildings, houses, trees, props).
Animované obrázky se ukládají jen jako pás `<klíč>@N.png` (hra bere druh/klíč bez ohledu na @N, takže statický soubor se nesmí
ukládat vedle něj).
"""
import os
import sys
import math
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from gen_sprites import (Canvas, rgba, STONE, PLASTER, WOOD, DARKWOOD, THATCH, TILE, SLATE, GRASS, WATER, GOLD,  # noqa: E402,F401
                         OUTLINE, HW, HH, Z, sack, barrel, door, window_left)
from contextlib import contextmanager  # noqa: E402
from PIL import Image, ImageDraw  # noqa: E402

ROOT = Path(os.environ.get('SPRITES_OUT') or HERE.parent / 'prototyp' / 'public' / 'sprites')

# Další palety (světlo, střed, stín)
DIRT = [rgba('#b8a074'), rgba('#9a8258'), rgba('#7a6644')]
SOIL = [rgba('#8a6a48'), rgba('#6f533a'), rgba('#523c2a')]
LEAF = [rgba('#9cc060'), rgba('#6c9444'), rgba('#4d7335')]
PINE = [rgba('#4f8046'), rgba('#386137'), rgba('#26462b')]
IRON = [rgba('#9a9fa4'), rgba('#6a6f74'), rgba('#42464a')]
SHINGLE = [rgba('#a98258'), rgba('#85623f'), rgba('#5f442c')]
COPPER = [rgba('#e0a05a'), rgba('#c27a3c'), rgba('#8f5628')]
GLOW = [rgba('#ffe28a'), rgba('#f59a35'), rgba('#c24c1e')]
SMOKE = [rgba('#efebe3'), rgba('#c9c4ba'), rgba('#a8a399')]
SNOW = [rgba('#fbfdff'), rgba('#dce6ee'), rgba('#b5c4d2')]
CLAY = [rgba('#d6a67a'), rgba('#b9855b'), rgba('#8f6342')]
DOORC = rgba('#3b2a1e')
WINC = rgba('#f1dc9b')
WINC_R = rgba('#c9b072')
BLACK = rgba('#1b1512')
RED = [rgba('#d65a45'), rgba('#a83e32')]
WHITE = rgba('#f4f0e4')


def lerp(a, b, t):
    return (a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t)


def ipt(p):
    return (int(round(p[0])), int(round(p[1])))


def dot(c, x, y, col):
    c.dot(int(x), int(y), col)


def hline(c, x0, x1, y, col):
    for x in range(int(x0), int(x1) + 1):
        c.dot(x, y, col)


def vline(c, x, y0, y1, col):
    for y in range(int(y0), int(y1) + 1):
        c.dot(x, y, col)


def canvas(width, height, w, d):
    return Canvas(width, height, w, d)


def yard(c, inner=None, margin=.1, grass=None):
    """Podklad bez obrysu: kousek trávy a hlinitý dvorek v půdorysu. Vše, co se kreslí potom, je vrstva s obrysem."""
    c.ground(grass or GRASS[1], margin)
    if inner is not False:
        c.ground(inner or DIRT[1], 0, .1, .1, c.w - .1, c.d - .1)
    # kotva: spodní řádek obrázku nesmí být prázdný
    for dx in (-1, 0, 1):
        c.dot(c.fx + dx, c.img.height - 1, (inner or DIRT[1]) if inner is not False else (grass or GRASS[1]))
    c.base = c.img
    c.img = Image.new('RGBA', c.img.size, (0, 0, 0, 0))
    c.px = ImageDraw.Draw(c.img)


@contextmanager
def under(c):
    """Kreslení do podkladu (bez obrysu): záhony, pastvina, voda apod."""
    layer, draw = c.img, c.px
    c.img, c.px = c.base, ImageDraw.Draw(c.base)
    try:
        yield
    finally:
        c.base = c.img
        c.img, c.px = layer, draw


def done(c):
    """Obrys kolem vrstvy staveb a složení s podkladem."""
    layer = c.finish()
    base = getattr(c, 'base', None)
    if base is None:
        return layer
    out = base.copy()
    out.alpha_composite(layer)
    return out


# --- stěny -----------------------------------------------------------------------------------------------------------

def walls(c, i0, j0, i1, j1, z0, z1, pal, kind='wood', beam=None):
    """Kvádr se strukturou: wood (srubová prkna), stone (kámen se spárami), plaster (omítka) a timber (hrázděná omítka)."""
    c.box(i0, j0, i1, j1, z0, z1, pal)
    if kind == 'wood':
        c.planks(i0, i1, j1, z0, z1, pal[2], 3)
        c.planks_right(i1, j0, j1, z0, z1, DARKWOOD[2], 3)
    elif kind == 'stone':
        c.stones('L', i0, i1, j1, z0, z1, pal[0], pal[2])
        c.stones('R', j0, j1, i1, z0, z1, pal[0], pal[1])
    elif kind == 'timber':
        beam = beam or WOOD[2]
        n = max(2, int(round((i1 - i0) / .5)))
        for k in range(n + 1):
            i = i0 + (i1 - i0) * k / n
            c.line(c.p(i, j1, z0), c.p(i, j1, z1), beam)
        c.line(c.p(i0, j1, (z0 + z1) // 2), c.p(i1, j1, (z0 + z1) // 2), beam)
        c.line(c.p(i0, j1, z1 - 1), c.p(i1, j1, z1 - 1), beam)
        m = max(2, int(round((j1 - j0) / .5)))
        for k in range(m + 1):
            j = j0 + (j1 - j0) * k / m
            c.line(c.p(i1, j, z0), c.p(i1, j, z1), DARKWOOD[1])
        c.line(c.p(i1, j0, (z0 + z1) // 2), c.p(i1, j1, (z0 + z1) // 2), DARKWOOD[1])
        c.line(c.p(i1, j0, z1 - 1), c.p(i1, j1, z1 - 1), DARKWOOD[1])
        # šikmý ztužující trám
        c.line(c.p(i0, j1, z0 + 1), c.p(i0 + (i1 - i0) / n, j1, (z0 + z1) // 2), beam)


def door_r(c, i, j, z, w=.4, h=9, col=DOORC):
    c.poly([c.p(i, j, z), c.p(i, j + w, z), c.p(i, j + w, z + h), c.p(i, j, z + h)], col)


def window_r(c, i, j, z, w=.3, h=4):
    c.poly([c.p(i, j, z), c.p(i, j + w, z), c.p(i, j + w, z + h), c.p(i, j, z + h)], WINC_R)
    c.line(c.p(i, j, z + h // 2), c.p(i, j + w, z + h // 2), DARKWOOD[1])


def arch_l(c, i, j, z, w, h, col=DOORC, frame=None):
    """Dveře s kamenným špicatým obloukem na přední levé stěně."""
    if frame:
        c.poly([c.p(i - .06, j, z), c.p(i + w + .06, j, z), c.p(i + w + .06, j, z + h + 1), c.p(i + w / 2, j, z + h + 4), c.p(i - .06, j, z + h + 1)], frame)
    c.poly([c.p(i, j, z), c.p(i + w, j, z), c.p(i + w, j, z + h), c.p(i + w / 2, j, z + h + 3), c.p(i, j, z + h)], col)


def window_arch(c, i, j, z, h=7, w=.22, col=rgba('#7fa3b5')):
    c.poly([c.p(i, j, z), c.p(i + w, j, z), c.p(i + w, j, z + h), c.p(i + w / 2, j, z + h + 2), c.p(i, j, z + h)], col)
    c.line(c.p(i + w / 2, j, z), c.p(i + w / 2, j, z + h), STONE[2])


# --- střechy -----------------------------------------------------------------------------------------------------------

def _rows(c, a, b, r0, r1, pal, kind, step, base_on_dark=False):
    """Řady šindele, tašek nebo došků rovnoběžně s okapem (a→r0 a b→r1 jsou spádnice)."""
    length = max(abs(a[1] - r0[1]), abs(a[0] - r0[0]))
    n = max(1, int(length // step))
    for k in range(1, n + 1):
        t = k / (n + 1)
        p, q = ipt(lerp(a, r0, t)), ipt(lerp(b, r1, t))
        steps = max(abs(q[0] - p[0]), abs(q[1] - p[1]), 1)
        for s in range(steps + 1):
            x = p[0] + (q[0] - p[0]) * s / steps
            y = p[1] + (q[1] - p[1]) * s / steps
            x, y = int(round(x)), int(round(y))
            if kind == 'thatch':
                c.dot(x, y, pal[1] if (s + k) % 5 else pal[2])
            elif kind in ('tile', 'slate', 'shingle'):
                c.dot(x, y, pal[1])
                if (s + (k % 2) * 2) % 4 == 0:
                    c.dot(x, y + 1, pal[2] if base_on_dark is False else pal[1])


def _plane(c, quad, col, pal, kind, step, texture=True, dark=False):
    c.poly(quad, col)
    if texture:
        _rows(c, quad[0], quad[1], quad[3], quad[2], pal, kind, step, base_on_dark=dark)


def gable(c, i0, j0, i1, j1, z, rise, pal, along='i', over=.2, gable_pal=None, step=2, kind='tile', rake=True, hook=None):
    """Sedlová střecha. along='i': hřeben rovnoběžně s osou i (vidět přední svah a pravý štít), 'j': hřeben podél j."""
    o = over
    if along == 'i':
        jm = (j0 + j1) / 2
        r0, r1 = c.p(i0 - o, jm, z + rise), c.p(i1 + o, jm, z + rise)
        a0, a1 = c.p(i0 - o, j0 - o, z), c.p(i1 + o, j0 - o, z)
        f0, f1 = c.p(i0 - o, j1 + o, z), c.p(i1 + o, j1 + o, z)
        c.poly([a0, a1, r1, r0], pal[1])
        c.poly([a1, f1, r1], (gable_pal or pal)[2])
        if hook:
            hook()
        _plane(c, [f0, f1, r1, r0], pal[0], pal, kind, step)
        c.line(f0, f1, pal[2])
        c.line(r0, r1, pal[0])
        if rake:
            c.line(a1, r1, pal[0])
            c.line(f1, r1, pal[2])
        return r0, r1
    im = (i0 + i1) / 2
    r0, r1 = c.p(im, j0 - o, z + rise), c.p(im, j1 + o, z + rise)
    b0, b1 = c.p(i0 - o, j0 - o, z), c.p(i0 - o, j1 + o, z)
    e0, e1 = c.p(i1 + o, j0 - o, z), c.p(i1 + o, j1 + o, z)
    c.poly([b0, b1, r1, r0], pal[1])
    c.poly([b1, e1, r1], (gable_pal or pal)[1])
    if hook:
        hook()
    _plane(c, [e1, e0, r0, r1], pal[2], pal, kind, step, dark=True)
    c.line(e1, e0, pal[2])
    c.line(r0, r1, pal[0])
    if rake:
        c.line(b1, r1, pal[0])
        c.line(e1, r1, pal[1])
    return r0, r1


def hip(c, i0, j0, i1, j1, z, rise, pal, along='i', over=.2, inset=None, step=2, kind='tile'):
    """Valbová střecha (inset = o kolik se hřeben zkracuje na koncích; polovina kratší strany dá jehlan)."""
    o = over
    ii0, ii1, jj0, jj1 = i0 - o, i1 + o, j0 - o, j1 + o
    wi, wj = ii1 - ii0, jj1 - jj0
    if along == 'i':
        inset = min(wi, wj) / 2 if inset is None else inset
        jm = (jj0 + jj1) / 2
        r0, r1 = c.p(ii0 + inset, jm, z + rise), c.p(ii1 - inset, jm, z + rise)
        c.poly([c.p(ii0, jj0, z), c.p(ii1, jj0, z), r1, r0], pal[1])
        _plane(c, [c.p(ii0, jj1, z), c.p(ii1, jj1, z), r1, r0], pal[0], pal, kind, step)
        _plane(c, [c.p(ii1, jj1, z), c.p(ii1, jj0, z), r1, r1], pal[2], pal, kind, step, dark=True)
        c.line(c.p(ii1, jj0, z), r1, pal[1])
        c.line(c.p(ii1, jj1, z), r1, pal[2])
        c.line(c.p(ii0, jj1, z), c.p(ii1, jj1, z), pal[2])
    else:
        inset = min(wi, wj) / 2 if inset is None else inset
        im = (ii0 + ii1) / 2
        r0, r1 = c.p(im, jj0 + inset, z + rise), c.p(im, jj1 - inset, z + rise)
        c.poly([c.p(ii0, jj0, z), c.p(ii0, jj1, z), r1, r0], pal[1])
        _plane(c, [c.p(ii0, jj1, z), c.p(ii1, jj1, z), r1, r1], pal[0], pal, kind, step)
        _plane(c, [c.p(ii1, jj1, z), c.p(ii1, jj0, z), r0, r1], pal[2], pal, kind, step, dark=True)
        c.line(c.p(ii0, jj1, z), r1, pal[1])
        c.line(c.p(ii1, jj1, z), r1, pal[2])
        c.line(c.p(ii1, jj1, z), c.p(ii1, jj0, z), pal[2])
    c.line(r0, r1, pal[0])
    return r0, r1


def pyramid(c, i0, j0, i1, j1, z, rise, pal, over=.1, kind='shingle', step=2):
    """Jehlanová střecha (věž, věžička): dvě viditelné strany."""
    o = over
    ii0, ii1, jj0, jj1 = i0 - o, i1 + o, j0 - o, j1 + o
    apex = c.p((ii0 + ii1) / 2, (jj0 + jj1) / 2, z + rise)
    left = [c.p(ii0, jj1, z), c.p(ii1, jj1, z), apex, apex]
    right = [c.p(ii1, jj1, z), c.p(ii1, jj0, z), apex, apex]
    _plane(c, left, pal[0], pal, kind, step)
    _plane(c, right, pal[2], pal, kind, step, dark=True)
    c.line(c.p(ii1, jj1, z), apex, pal[1])
    return apex


def lean_roof(c, i0, j0, i1, j1, z_hi, z_lo, pal, over=.12, kind='shingle', step=2, high='back'):
    """Pultová střecha: vyšší strana vzadu (j0) a nižší vpředu (j1), vidět přední plocha a pravý bok."""
    o = over
    f0, f1 = c.p(i0 - o, j1 + o, z_lo), c.p(i1 + o, j1 + o, z_lo)
    b0, b1 = c.p(i0 - o, j0 - o, z_hi), c.p(i1 + o, j0 - o, z_hi)
    _plane(c, [f0, f1, b1, b0], pal[0], pal, kind, step)
    c.poly([c.p(i1 + o, j0 - o, z_hi), c.p(i1 + o, j1 + o, z_lo), c.p(i1 + o, j1 + o, z_lo - 2), c.p(i1 + o, j0 - o, z_hi - 2)], pal[2])
    c.line(f0, f1, pal[2])
    c.line(b0, b1, pal[0])


# --- drobnosti ---------------------------------------------------------------------------------------------------------

def chimney(c, i, j, z0, z1, size=.22, pal=STONE):
    c.box(i, j, i + size, j + size, z0, z1, pal, top=pal[2])
    x, y = c.p(i + size / 2, j + size / 2, z1)
    c.dot(x, y, BLACK)


def smoke(c, x, y, f=0, n=4, spread=1):
    """Sloupec kouře: puffy rostou nahoru a rozevírají se; snímek f posouvá o bod (smyčka po 4 snímcích)."""
    for k in range(n):
        dy = 4 * k + f
        size = 2 if k < 2 else 3
        dx = ((k + f // 2) % 3 - 1) * spread
        col = SMOKE[0] if k % 2 == 0 else SMOKE[1]
        if k == n - 1:
            col = SMOKE[2]
        c.rect(int(x + dx), int(y - dy - size), int(x + dx + size - 1), int(y - dy - 1), col)


def box_px(c, x, y, w, h, pal, top=True):
    """Bedna nebo balík ve screen souřadnicích: (x, y) je levý dolní roh, w a h v bodech."""
    c.rect(x, y - h, x + w, y, pal[1])
    c.rect(x + w - 1, y - h, x + w, y, pal[2])
    if top:
        c.rect(x, y - h, x + w - 1, y - h, pal[0])


def crate(c, i, j, s=4, pal=WOOD):
    x, y = c.p(i, j, 0)
    box_px(c, x, y, s, s, pal)
    c.line((x, y - s // 2), (x + s - 1, y - s // 2), pal[2])


def log_pile(c, i, j, rows=3, width=3, col=None):
    x, y = c.p(i, j, 0)
    col = col or WOOD
    for r in range(rows):
        for k in range(width - r % 2):
            xx = x + k * 3 + (r % 2) * 1
            yy = y - r * 2
            c.rect(xx, yy - 1, xx + 2, yy, col[1] if (k + r) % 2 else col[0])
            c.dot(xx + 2, yy - 1, col[2])


def fence(c, a, b, h=4, pal=WOOD, rails=2, step=.34, z=0):
    """Plot z kůlů a latí od bodu a=(i, j) k b=(i, j) po zemi."""
    n = max(1, int(round(max(abs(b[0] - a[0]), abs(b[1] - a[1])) / step)))
    pts = [c.p(a[0] + (b[0] - a[0]) * k / n, a[1] + (b[1] - a[1]) * k / n, z) for k in range(n + 1)]
    for r in range(1, rails + 1):
        zr = h * r / (rails + 0.5) + .5
        q0 = c.p(a[0], a[1], z + zr)
        q1 = c.p(b[0], b[1], z + zr)
        c.line(q0, q1, pal[0] if r % 2 else pal[1])
    for x, y in pts:
        c.line((x, y), (x, y - h - 1), pal[2])
        c.dot(x, y - h - 1, pal[0])


def stone_wall(c, a, b, h=5, thick=.12, pal=STONE):
    """Nízká kamenná zídka podél osy i (a→b se mění i) nebo j."""
    (ai, aj), (bi, bj) = a, b
    if abs(bi - ai) >= abs(bj - aj):
        j0, j1 = aj - thick / 2, aj + thick / 2
        c.box(ai, j0, bi, j1, 0, h, pal, top=pal[0])
        c.stones('L', ai, bi, j1, 0, h, pal[0], pal[2])
    else:
        i0, i1 = ai - thick / 2, ai + thick / 2
        c.box(i0, aj, i1, bj, 0, h, pal, top=pal[0])
        c.stones('R', aj, bj, i1, 0, h, pal[0], pal[1])


def post(c, i, j, z0, z1, pal=DARKWOOD, w=1):
    """Svislý sloupek (2 body široký) na půdorysném bodě (i, j) od výšky z0 do z1."""
    x, y0 = c.p(i, j, z0)
    _, y1 = c.p(i, j, z1)
    c.rect(x, y1, x + w, y0, pal[1])
    c.line((x + w, y1), (x + w, y0), pal[2])
    c.line((x, y1), (x, y0), pal[0])


def tuft(c, x, y, col=None):
    col = col or LEAF[1]
    c.dot(x, y, col)
    c.dot(x - 1, y - 1, col)
    c.dot(x + 1, y - 1, LEAF[0])


def hill(c, x, y, rx, ry, pal, top=None):
    """Zemní kopec: půlelipsa se světlým temenem (screen souřadnice středu paty)."""
    for yy in range(-ry, 1):
        t = (yy / ry) if ry else 0
        half = int(round(rx * math.sqrt(max(0.0, 1 - t * t))))
        for xx in range(-half, half + 1):
            shade = pal[1]
            if xx > half * .45:
                shade = pal[2]
            elif xx < -half * .25 and yy < -ry * .25:
                shade = pal[0]
            c.dot(x + xx, y + yy, shade)


def wheel(c, cx, cy, r, col, spokes=6, phase=0.0, rim=None):
    """Kolo ve screen souřadnicích (kruh o poloměru r bodů) s paprsky; phase v radiánech."""
    n = max(12, r * 4)
    pts = [(cx + r * math.cos(2 * math.pi * k / n), cy + r * math.sin(2 * math.pi * k / n)) for k in range(n)]
    for k in range(n):
        c.line(ipt(pts[k]), ipt(pts[(k + 1) % n]), rim or col)
    for s in range(spokes):
        th = phase + 2 * math.pi * s / spokes
        c.line((int(cx), int(cy)), ipt((cx + (r - 1) * math.cos(th), cy + (r - 1) * math.sin(th))), col)


def wheel_iso(c, plane, fixed, a, zc, r, col, spokes=8, phase=0.0, rim=None, paddles=None):
    """Kolo ve svislé rovině stěny: plane='i' (pravá stěna, rovina i=fixed, osa a je j) nebo 'j' (levá stěna, osa a je i)."""
    n = max(16, r * 4)

    def at(u, v):
        return c.p(fixed, a + u, zc + v) if plane == 'i' else c.p(a + u, fixed, zc + v)

    def ring(rad, k):
        th = 2 * math.pi * k / n
        return at(rad / 10.0 * math.cos(th), rad / Z * math.sin(th))
    for k in range(n):
        c.line(ring(r, k), ring(r, k + 1), rim or col)
    for s in range(spokes):
        th = phase + 2 * math.pi * s / spokes
        c.line(at(0, 0), at((r - 1) / 10.0 * math.cos(th), (r - 1) / Z * math.sin(th)), col)
        if paddles:
            q = at((r + 1) / 10.0 * math.cos(th), (r + 1) / Z * math.sin(th))
            c.dot(q[0], q[1], paddles)
    x, y = at(0, 0)
    c.dot(x, y, DARKWOOD[2])


# --- zvířata (screen souřadnice, (x, y) = bod pod tělem) ---------------------------------------------------------------

def cow(c, x, y, spots=True, brown=False, face=1):
    body = rgba('#8b5a3a') if brown else WHITE
    shade = rgba('#6a432b') if brown else rgba('#c9c3b4')
    c.rect(x - 3, y - 5, x + 3, y - 2, body)
    c.rect(x + 2, y - 5, x + 3, y - 2, shade)
    c.rect(x - 3, y - 2, x + 3, y - 2, shade)
    c.dot(x - 3, y - 1, rgba('#2b211d'))
    c.dot(x - 1, y - 1, rgba('#2b211d'))
    c.dot(x + 2, y - 1, rgba('#2b211d'))
    if spots:
        c.dot(x - 2, y - 4, rgba('#3a2b24'))
        c.dot(x - 1, y - 5, rgba('#3a2b24'))
        c.dot(x + 1, y - 3, rgba('#3a2b24'))
    hx = x - 4 if face < 0 else x + 4
    c.rect(min(hx, hx + face), y - 5, max(hx, hx + face), y - 3, body)
    c.dot(hx + face * 2, y - 3, rgba('#d9a7a0'))
    c.dot(hx, y - 6, rgba('#d8c79a'))


def chicken(c, x, y, col=WHITE):
    c.rect(x, y - 2, x + 2, y - 1, col)
    c.dot(x + 3, y - 3, col)
    c.dot(x + 3, y - 4, RED[0])
    c.dot(x + 4, y - 3, rgba('#e8a830'))
    c.dot(x - 1, y - 2, rgba('#c9c3b4'))
    c.dot(x + 1, y, rgba('#e8a830'))


def pig(c, x, y):
    c.rect(x - 3, y - 4, x + 3, y - 1, rgba('#e8a8a0'))
    c.rect(x + 2, y - 4, x + 3, y - 1, rgba('#c78680'))
    c.rect(x + 4, y - 4, x + 5, y - 2, rgba('#e8a8a0'))
    c.dot(x + 6, y - 3, rgba('#c78680'))
    c.dot(x + 4, y - 5, rgba('#c78680'))
    c.dot(x - 3, y, rgba('#7a5a52'))
    c.dot(x + 2, y, rgba('#7a5a52'))
    c.dot(x - 4, y - 3, rgba('#c78680'))


def horse(c, x, y, col=None):
    col = col or rgba('#8a5a38')
    dk = rgba('#4a3022')
    c.rect(x - 4, y - 6, x + 3, y - 3, col)
    c.rect(x + 2, y - 6, x + 3, y - 3, dk)
    for lx in (x - 4, x - 2, x + 1, x + 3):
        c.line((lx, y - 2), (lx, y), dk)
    c.rect(x + 4, y - 9, x + 5, y - 5, col)
    c.rect(x + 5, y - 9, x + 7, y - 8, col)
    c.dot(x + 4, y - 10, dk)
    c.line((x + 3, y - 9), (x + 3, y - 6), dk)
    c.line((x - 5, y - 6), (x - 6, y - 3), dk)


# --- výstup ------------------------------------------------------------------------------------------------------------

def limit_colors(img, n=32):
    """Sloučí nejméně používané barvy s nejbližší častější, dokud jich není nejvýš n (alfa zůstane 0 nebo 255)."""
    pixels = list(img.getdata())
    counts = {}
    for p in pixels:
        if p[3]:
            counts[p] = counts.get(p, 0) + 1
    if len(counts) <= n:
        return img
    keep = sorted(counts, key=lambda k: -counts[k])
    remap = {}
    while len(keep) > n:
        rare = keep.pop()
        best = min(keep, key=lambda k: sum((k[x] - rare[x]) ** 2 for x in range(3)))
        remap[rare] = best
    out = img.copy()
    px = out.load()
    for y in range(img.height):
        for x in range(img.width):
            v = px[x, y]
            if v[3] and v in remap:
                px[x, y] = remap[v]
    return out


def check(img, label):
    w, h = img.size
    warns = []
    data = img.load()
    # oříznutí: na okraji leží jiná barva než obrys (obrys v krajní řadě je v pořádku)
    def clipped(p):
        return p[3] and p[:3] != OUTLINE[:3]
    if any(clipped(data[x, 0]) for x in range(w)):
        warns.append('oříznuto nahoře')
    if any(clipped(data[0, y]) for y in range(h - 24)):
        warns.append('oříznuto vlevo')
    if any(clipped(data[w - 1, y]) for y in range(h - 24)):
        warns.append('oříznuto vpravo')
    if not any(data[x, h - 1][3] for x in range(w)):
        warns.append('prázdný spodní řádek')
    return warns


def strip(frames):
    w, h = frames[0].size
    out = Image.new('RGBA', (w * len(frames), h), (0, 0, 0, 0))
    for k, f in enumerate(frames):
        out.paste(f, (k * w, 0))
    return out


def save(kind, name, builder, frames=1):
    """Vykreslí (frames snímků, builder(f) nebo builder()) a uloží PNG; vrací seznam varování."""
    if frames > 1:
        imgs = [limit_colors(builder(f)) for f in range(frames)]
        warns = sum((check(i, name) for i in imgs), [])
        img = strip(imgs)
        fname = '%s@%d.png' % (name, frames)
        stale = ROOT / kind / (name + '.png')
        if stale.exists():
            stale.unlink()
    else:
        img = limit_colors(builder())
        warns = check(img, name)
        fname = name + '.png'
        for old in (ROOT / kind).glob(name + '@*.png'):
            old.unlink()
    out = ROOT / kind
    out.mkdir(parents=True, exist_ok=True)
    img.save(out / fname)
    colors = len({p for p in img.getdata() if p[3]})
    print('%-26s %-10s %3d barev %s' % (kind + '/' + fname, img.size, colors, ('! ' + ', '.join(warns)) if warns else ''))
    return warns
