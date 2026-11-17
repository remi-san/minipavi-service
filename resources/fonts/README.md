# Minitel font

`Minitel.ttf` comes from [Zigazou/Minitel-Canvas](https://github.com/Zigazou/Minitel-Canvas)
(its `Minitel.ttf`, unchanged), under the CC0 1.0 Universal license (`LICENSE`). `Vdt\HtmlRenderer`
embeds it in the HTML view of a service (`?web=true`).

Its glyphs are 1 em wide and 1.25 em high (ascent 1, descent 0.25): at a font size equal to
the width of a cell, they fill the Minitel's cells (8 × 10 pixels, 0.8 as wide as high). It
has ASCII, the Minitel's G2 characters and accented lowercase letters, but not every accented
capital: the renderer falls back to a monospace font for those.
