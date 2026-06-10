#!/usr/bin/env python3
"""APPREX LINE リッチメニュー画像（2500x1686・6分割）を生成。"""
from PIL import Image, ImageDraw, ImageFont

W, H = 2500, 1686
COLS, ROWS = 3, 2
GAP = 8

# ブランドカラー
BLUE   = (59, 130, 246)
ORANGE = (245, 158, 11)
GREEN  = (16, 185, 129)
INDIGO = (99, 102, 241)
SKY    = (14, 165, 233)
SLATE  = (51, 65, 85)
INK    = (31, 41, 55)
MUTED  = (107, 114, 128)
LINEC  = (226, 232, 240)

FONT_PATH = "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc"
# JP face index in the Noto Sans CJK collection
JP_INDEX = 0


def tint(c, p):
    """色cを白とpの割合で混ぜる（p=色の比率）。"""
    return tuple(int(round(ch * p + 255 * (1 - p))) for ch in c)


def font(size):
    return ImageFont.truetype(FONT_PATH, size, index=JP_INDEX)


def ctext(d, cx, y, s, fnt, fill, anchor_mm=False, stroke=0, stroke_fill=None):
    """中央寄せテキスト。"""
    bb = d.textbbox((0, 0), s, font=fnt, stroke_width=stroke)
    w = bb[2] - bb[0]
    h = bb[3] - bb[1]
    x = cx - w / 2 - bb[0]
    yy = (y - h / 2 - bb[1]) if anchor_mm else y
    d.text((x, yy), s, font=fnt, fill=fill, stroke_width=stroke, stroke_fill=stroke_fill)


# ---- アイコン（白で描画。circ=(cx,cy,r) 円の中に収める） ----
def icon_rocket(d, cx, cy, r):
    w = r * 0.7
    # 本体
    d.rounded_rectangle([cx - w * 0.42, cy - r * 0.75, cx + w * 0.42, cy + r * 0.35],
                        radius=w * 0.42, fill="white")
    # ノーズ
    d.polygon([(cx, cy - r * 1.05), (cx - w * 0.42, cy - r * 0.55),
               (cx + w * 0.42, cy - r * 0.55)], fill="white")
    # 窓
    d.ellipse([cx - w * 0.2, cy - r * 0.45, cx + w * 0.2, cy - r * 0.05], fill=BLUE)
    # フィン
    d.polygon([(cx - w * 0.42, cy - r * 0.05), (cx - w * 0.85, cy + r * 0.45),
               (cx - w * 0.42, cy + r * 0.3)], fill="white")
    d.polygon([(cx + w * 0.42, cy - r * 0.05), (cx + w * 0.85, cy + r * 0.45),
               (cx + w * 0.42, cy + r * 0.3)], fill="white")
    # 炎
    d.polygon([(cx - w * 0.22, cy + r * 0.35), (cx + w * 0.22, cy + r * 0.35),
               (cx, cy + r * 0.95)], fill="white")


def icon_calc(d, cx, cy, r):
    w, h = r * 1.0, r * 1.25
    x0, y0 = cx - w / 2, cy - h / 2
    d.rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=r * 0.16, fill="white")
    # 画面
    d.rounded_rectangle([x0 + w * 0.16, y0 + h * 0.1, x0 + w * 0.84, y0 + h * 0.3],
                        radius=r * 0.06, fill=ORANGE)
    # ボタン 3x3
    for i in range(3):
        for j in range(3):
            bx = x0 + w * (0.22 + j * 0.28)
            by = y0 + h * (0.45 + i * 0.16)
            d.ellipse([bx - w * 0.07, by - w * 0.07, bx + w * 0.07, by + w * 0.07], fill=ORANGE)


def icon_video(d, cx, cy, r):
    w, h = r * 1.15, r * 0.85
    x0, y0 = cx - w * 0.62, cy - h / 2
    d.rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=r * 0.16, fill="white")
    # レンズ（右の三角）
    d.polygon([(x0 + w + r * 0.08, cy - h * 0.42), (x0 + w + r * 0.5, cy - h * 0.16),
               (x0 + w + r * 0.5, cy + h * 0.16), (x0 + w + r * 0.08, cy + h * 0.42)],
              fill="white")
    # 再生マーク
    d.polygon([(cx - w * 0.16, cy - h * 0.22), (cx - w * 0.16, cy + h * 0.22),
               (cx + w * 0.12, cy)], fill=GREEN)


def icon_cases(d, cx, cy, r):
    s = r * 0.62
    g = r * 0.16
    for i in range(2):
        for j in range(2):
            x0 = cx - s - g / 2 + j * (s + g)
            y0 = cy - s - g / 2 + i * (s + g)
            d.rounded_rectangle([x0, y0, x0 + s, y0 + s], radius=r * 0.12, fill="white")


def icon_blog(d, cx, cy, r):
    w, h = r * 1.0, r * 1.25
    x0, y0 = cx - w / 2, cy - h / 2
    d.rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=r * 0.12, fill="white")
    for i in range(4):
        yy = y0 + h * (0.2 + i * 0.2)
        ln = 0.62 if i == 0 else 0.78
        col = INDIGO if i == 0 else SKY
        d.rounded_rectangle([x0 + w * 0.14, yy, x0 + w * (0.14 + ln), yy + h * 0.08],
                            radius=h * 0.04, fill=col)


def icon_chat(d, cx, cy, r):
    w, h = r * 1.35, r * 1.0
    x0, y0 = cx - w / 2, cy - h / 2 - r * 0.1
    d.rounded_rectangle([x0, y0, x0 + w, y0 + h], radius=r * 0.28, fill="white")
    d.polygon([(cx - w * 0.18, y0 + h), (cx + w * 0.06, y0 + h),
               (cx - w * 0.26, y0 + h + r * 0.34)], fill="white")
    for k in range(3):
        bx = cx - w * 0.22 + k * (w * 0.22)
        d.ellipse([bx - r * 0.1, cy - r * 0.1 - r * 0.1, bx + r * 0.1, cy + r * 0.1 - r * 0.1],
                  fill=SLATE)


CELLS = [
    ("30日 無料体験", "FREE TRIAL", BLUE,   icon_rocket),
    ("かんたん見積もり", "ESTIMATE", ORANGE, icon_calc),
    ("オンライン相談", "GOOGLE MEET", GREEN, icon_video),
    ("導入事例",       "CASES",     INDIGO, icon_cases),
    ("ブログ／記事",   "BLOG",      SKY,    icon_blog),
    ("お問い合わせ",   "CONTACT",   SLATE,  icon_chat),
]

img = Image.new("RGB", (W, H), LINEC)
d = ImageDraw.Draw(img)

cw = (W - GAP * (COLS - 1)) / COLS
ch = (H - GAP * (ROWS - 1)) / ROWS

f_label = font(96)
f_sub = font(40)

for idx, (label, sub, color, drawicon) in enumerate(CELLS):
    r = idx // COLS
    c = idx % COLS
    x0 = c * (cw + GAP)
    y0 = r * (ch + GAP)
    cx = x0 + cw / 2
    # 背景（淡いブランド色）
    d.rectangle([x0, y0, x0 + cw, y0 + ch], fill=tint(color, 0.12))
    # アイコン円
    icy = y0 + ch * 0.34
    icr = ch * 0.20
    d.ellipse([cx - icr, icy - icr, cx + icr, icy + icr], fill=color)
    drawicon(d, cx, icy, icr * 0.82)
    # ラベル
    ctext(d, cx, y0 + ch * 0.66, label, f_label, INK, anchor_mm=True,
          stroke=2, stroke_fill=INK)
    # サブラベル（英字）
    ctext(d, cx, y0 + ch * 0.80, sub, f_sub, MUTED, anchor_mm=True)

out = "/home/user/-/dist/apprex-richmenu.png"
img.save(out, "PNG")
print("saved", out, img.size)
