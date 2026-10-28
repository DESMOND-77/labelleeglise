#!/usr/bin/env python3
"""Génère docs/guide-utilisation.pdf à partir de docs/guide/guide.html.

Aucune dépendance Python : seulement la bibliothèque standard, Chrome (ou
Chromium) pour imprimer en PDF, et `pdftotext` (poppler) pour retrouver la
page de chaque titre. Le sommaire est rempli en deux passes : la première
mesure les pages, la seconde les inscrit.

    python3 docs/guide/build.py
    CHROME=/chemin/vers/chrome python3 docs/guide/build.py   # navigateur explicite
"""
from __future__ import annotations

import html
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
SOURCE = HERE / "guide.html"
TEMP = HERE / ".guide-build.html"
OUTPUT = HERE.parent / "guide-utilisation.pdf"

HEADING = re.compile(r'<h([12]) id="(c[^"]+)"><span class="n">([^<]+)</span>([^<]+)</h\1>')


def find_chrome() -> str:
    candidates = [os.environ.get("CHROME", "")] + [
        shutil.which(n) or ""
        for n in ("google-chrome", "google-chrome-stable", "chromium", "chromium-browser")
    ] + ["/opt/google/chrome/chrome"]
    for c in candidates:
        if c and Path(c).exists():
            return c
    sys.exit("Chrome ou Chromium introuvable (variable CHROME pour l'indiquer).")


def headings(source: str) -> list[tuple[int, str, str, str]]:
    return [
        (int(level), anchor, num.strip(), html.unescape(title.strip()))
        for level, anchor, num, title in HEADING.findall(source)
    ]


def toc_html(entries, pages: dict[str, int]) -> str:
    rows = []
    for level, anchor, num, title in entries:
        page = pages.get(anchor, 0)
        label = html.escape(f"{num} {title}") if level == 1 else html.escape(f"{num} {title}")
        rows.append(
            f'<li class="l{level}"><a href="#{anchor}"><span class="t">{label}</span>'
            f'<span class="pg">{page or "·"}</span></a></li>'
        )
    return "\n".join(rows)


def render(chrome: str, source_html: str, pdf: Path) -> None:
    TEMP.write_text(source_html, encoding="utf-8")
    with tempfile.TemporaryDirectory() as profile:
        subprocess.run(
            [
                chrome, "--headless=new", "--no-sandbox", "--disable-gpu", "--no-pdf-header-footer",
                "--run-all-compositor-stages-before-draw", "--virtual-time-budget=30000",
                f"--user-data-dir={profile}", f"--print-to-pdf={pdf}", TEMP.as_uri(),
            ],
            check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=180,
        )


def page_texts(pdf: Path) -> list[str]:
    out = subprocess.run(["pdftotext", "-layout", str(pdf), "-"], check=True, capture_output=True, text=True).stdout
    return [re.sub(r"\s+", " ", p) for p in out.split("\f")][:-1] or [""]


def locate(entries, pages_text: list[str]) -> dict[str, int]:
    """Page (1-indexée) du premier titre correspondant, en ignorant les pages du sommaire."""
    toc_pages = {i for i, t in enumerate(pages_text) if "Sommaire" in t[:80] or t.count("·") > 6}
    found: dict[str, int] = {}
    start = 0
    for _, anchor, num, title in entries:
        pattern = re.compile(re.escape(num) + r"\s*" + re.escape(title[:40]).replace(r"\ ", r"\s+"))
        for i in range(start, len(pages_text)):
            if i in toc_pages:
                continue
            if pattern.search(pages_text[i]):
                found[anchor] = i + 1
                start = i
                break
    return found


def main() -> None:
    chrome = find_chrome()
    source = SOURCE.read_text(encoding="utf-8")
    entries = headings(source)
    pages: dict[str, int] = {}
    pdf = Path(tempfile.mkstemp(suffix=".pdf")[1])
    try:
        for attempt in range(1, 4):
            filled = source.replace('<ol class="toc" id="toc"></ol>', f'<ol class="toc" id="toc">\n{toc_html(entries, pages)}\n</ol>')
            render(chrome, filled, pdf)
            measured = locate(entries, page_texts(pdf))
            missing = [a for _, a, _, _ in entries if a not in measured]
            print(f"passe {attempt} : {len(measured)}/{len(entries)} titres localisés"
                  + (f", manquants : {missing}" if missing else ""))
            if measured == pages:
                break
            pages = measured
        shutil.copyfile(pdf, OUTPUT)
    finally:
        pdf.unlink(missing_ok=True)
        TEMP.unlink(missing_ok=True)
    total = len(page_texts(OUTPUT))
    print(f"{OUTPUT.relative_to(HERE.parent.parent)} : {total} pages, {OUTPUT.stat().st_size // 1024} Ko")


if __name__ == "__main__":
    main()
