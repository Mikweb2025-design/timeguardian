#!/usr/bin/env python3
"""Erzeugt img/app.svg (monochrom), app.png, icon-192.png, icon-512.png, icon-maskable-512.png, apple-touch-icon.png.
Motiv: QR-Code mit Uhr-Badge. Aufruf: python3 tests/frontend/tools/make_icons.py"""
import random
from pathlib import Path
from PIL import Image, ImageDraw

OUT = Path(__file__).resolve().parents[3] / "img"
S = 1024
BG1, BG2 = (14, 34, 70), (30, 76, 140)
ACC, WHITE, INK = (64, 220, 196), (246, 250, 255), (10, 24, 48)
N = 21
RND_SEED = 11


def pattern():
    rnd = random.Random(RND_SEED)
    keep = set()
    for j in range(N):
        for i in range(N):
            if (i < 8 and j < 8) or (i >= N - 8 and j < 8) or (i < 8 and j >= N - 8):
                continue
            if i >= N - 9 and j >= N - 9:
                continue
            if rnd.random() < 0.5:
                keep.add((i, j))
    return keep


def draw(scale):
    im = Image.new("RGB", (S, S))
    px = im.load()
    for y in range(S):
        for x in range(S):
            t = (x + y) / (2 * S - 2)
            px[x, y] = tuple(int(a + (b - a) * t) for a, b in zip(BG1, BG2))
    d = ImageDraw.Draw(im)
    box = int(700 * scale)
    m = box / N
    ox = oy = (S - box) / 2

    def cell(i, j):
        d.rounded_rectangle([ox + i * m + 1, oy + j * m + 1, ox + (i + 1) * m - 1, oy + (j + 1) * m - 1], radius=m * .2, fill=WHITE)

    def finder(i, j):
        d.rounded_rectangle([ox + i * m, oy + j * m, ox + (i + 7) * m, oy + (j + 7) * m], radius=m * 1.1, fill=WHITE)
        d.rounded_rectangle([ox + (i + 1) * m, oy + (j + 1) * m, ox + (i + 6) * m, oy + (j + 6) * m], radius=m * .7, fill=BG1)
        d.rounded_rectangle([ox + (i + 2) * m, oy + (j + 2) * m, ox + (i + 5) * m, oy + (j + 5) * m], radius=m * .5, fill=ACC)

    finder(0, 0); finder(N - 7, 0); finder(0, N - 7)
    for (i, j) in pattern():
        cell(i, j)
    cx, cy, R = ox + (N - 4.5) * m, oy + (N - 4.5) * m, 5.0 * m
    d.ellipse([cx - R - 12, cy - R - 12, cx + R + 12, cy + R + 12], fill=BG1)
    d.ellipse([cx - R, cy - R, cx + R, cy + R], fill=ACC)
    w = max(10, int(m * .55))
    d.line([cx, cy, cx, cy - R * .62], fill=INK, width=w, joint="curve")
    d.line([cx, cy, cx + R * .5, cy + R * .2], fill=INK, width=w, joint="curve")
    d.ellipse([cx - w * .8, cy - w * .8, cx + w * .8, cy + w * .8], fill=INK)
    return im


def svg_mono():
    """Monochrome 24x24 symbol: QR-Quadrate + Uhr (schwarz auf transparent, Nextcloud färbt per CSS-Filter)."""
    rects = []
    def sq(x, y, s, r=.6):
        rects.append(f'<rect x="{x}" y="{y}" width="{s}" height="{s}" rx="{r}"/>')
    # drei Finder (Rahmen mit Punkt)
    for (x, y) in ((2, 2), (2, 14), (14, 2)):
        rects.append(f'<path fill-rule="evenodd" d="M{x} {y}h8v8h-8zM{x+1.6} {y+1.6}v4.8h4.8v-4.8z"/>')
        sq(x + 3, y + 3, 2, .3)
    sq(11, 11, 2, .3)  # Datenpunkt
    clock = ('<path fill-rule="evenodd" d="M18.5 12.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zm0 1.6a3.9 3.9 0 1 1 0 7.8 3.9 3.9 0 0 1 0-7.8z"/>'
             '<path d="M18 15.3h1.2v2.9l1.9 1.1-.6 1-2.5-1.5z"/>')
    return ('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#000">'
            + "".join(rects) + clock + '</svg>\n')


if __name__ == "__main__":
    OUT.mkdir(exist_ok=True)
    full, safe = draw(1.0), draw(0.76)
    for n in (192, 512):
        full.resize((n, n), Image.LANCZOS).save(OUT / f"icon-{n}.png", optimize=True)
    safe.resize((512, 512), Image.LANCZOS).save(OUT / "icon-maskable-512.png", optimize=True)
    full.resize((180, 180), Image.LANCZOS).save(OUT / "apple-touch-icon.png", optimize=True)
    full.resize((64, 64), Image.LANCZOS).save(OUT / "app.png", optimize=True)
    (OUT / "app.svg").write_text(svg_mono(), encoding="utf-8")
    print("icons ok")
