#!/usr/bin/env python3
"""APPREX LINE ステップ用バナー（1040x1040・リッチメッセージ標準）を生成。"""
import os
from PIL import Image, ImageDraw, ImageFont

S = 1040
FONT = "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc"
JP = 0
OUT = "/home/user/-/dist/banners"
os.makedirs(OUT, exist_ok=True)


def f(sz):
    return ImageFont.truetype(FONT, sz, index=JP)


def vgrad(c1, c2):
    img = Image.new("RGB", (S, S), c1)
    d = ImageDraw.Draw(img)
    for y in range(S):
        t = y / (S - 1)
        col = tuple(int(round(c1[i] * (1 - t) + c2[i] * t)) for i in range(3))
        d.line([(0, y), (S, y)], fill=col)
    return img


def center(d, cx, y, s, fnt, fill, stroke=0, sfill=None):
    bb = d.textbbox((0, 0), s, font=fnt, stroke_width=stroke)
    w = bb[2] - bb[0]
    d.text((cx - w / 2 - bb[0], y), s, font=fnt, fill=fill,
           stroke_width=stroke, stroke_fill=sfill)


def text_w(d, s, fnt):
    bb = d.textbbox((0, 0), s, font=fnt)
    return bb[2] - bb[0]


def pill(d, cx, y, label, fnt, bg, fg):
    pad_x, pad_y = 54, 26
    tw = text_w(d, label, fnt)
    w = tw + pad_x * 2
    h = 0
    bb = d.textbbox((0, 0), label, font=fnt)
    h = (bb[3] - bb[1]) + pad_y * 2
    x0 = cx - w / 2
    d.rounded_rectangle([x0, y, x0 + w, y + h], radius=h / 2, fill=bg)
    center(d, cx, y + pad_y - bb[1], label, fnt, fg)
    return h


def banner(name, c1, c2, eyebrow, headline, sub, cta, eyebrow_bg=None):
    img = vgrad(c1, c2)
    d = ImageDraw.Draw(img)
    cx = S / 2

    # 装飾の大きな円（右上・半透明風＝明色オーバーレイ）
    ov = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    od = ImageDraw.Draw(ov)
    od.ellipse([S * 0.55, -S * 0.25, S * 1.25, S * 0.45], fill=(255, 255, 255, 28))
    od.ellipse([-S * 0.3, S * 0.7, S * 0.35, S * 1.35], fill=(255, 255, 255, 22))
    img = Image.alpha_composite(img.convert("RGBA"), ov).convert("RGB")
    d = ImageDraw.Draw(img)

    # eyebrow（小さなタグ）
    fe = f(40)
    eb = eyebrow_bg if eyebrow_bg else (255, 255, 255)
    efg = c2 if eyebrow_bg else c2
    pill(d, cx, 150, eyebrow, fe, eb, efg)

    # 見出し（複数行）
    fh = f(96)
    y = 320
    for line in headline:
        center(d, cx, y, line, fh, (255, 255, 255), stroke=2, sfill=(255, 255, 255))
        y += 120

    # サブ
    fs = f(46)
    center(d, cx, y + 24, sub, fs, (255, 255, 255))

    # CTA
    fc = f(50)
    pill(d, cx, 780, cta, fc, (255, 255, 255), c2)

    # フッター
    ff = f(30)
    center(d, cx, 960, "APPREX（アプリックス）｜site.aiscompany.jp", ff, (255, 255, 255))

    path = os.path.join(OUT, name + ".png")
    img.save(path, "PNG")
    print("saved", path)


BLUE = (59, 130, 246); BLUE_D = (29, 78, 216)
INDIGO = (99, 102, 241); INDIGO_D = (67, 56, 202)
GREEN = (16, 185, 129); GREEN_D = (5, 122, 85)
ORANGE = (245, 158, 11); RED_D = (220, 38, 38)
SKY = (14, 165, 233); SKY_D = (2, 110, 170)
TEAL = (13, 148, 136); TEAL_D = (15, 90, 90)

banner("01_welcome", BLUE, BLUE_D,
       "ノーコードアプリ開発",
       ["プログラミング不要", "誰でもアプリ開発"],
       "初期費用0円・月額19,800円〜・最短2週間",
       "30日間 無料体験 ▶")

banner("02_cases", INDIGO, INDIGO_D,
       "導入事例",
       ["9業種で成果。", "あなたの業種でも。"],
       "飲食・小売・サロン・士業・教室 ほか",
       "事例を見る ▶")

banner("03_price", GREEN, TEAL_D,
       "料金",
       ["初期費用0円", "月額制で始める"],
       "開発費0円／DL課金なし／全プラン1年契約",
       "1分で見積もり ▶")

banner("04_campaign", ORANGE, RED_D,
       "今月限定・先着5名",
       ["初期費用 0円", "今月末まで"],
       "通常30万円 → 0円キャンペーン",
       "今すぐ申し込む ▶")

banner("05_meet", GREEN, GREEN_D,
       "無料オンライン相談",
       ["Google Meetで", "30分 無料相談"],
       "希望の枠を選ぶだけ・URL自動発行",
       "予約する ▶")

banner("06_hp", SKY, SKY_D,
       "ホームページ制作",
       ["HPも月額制", "9,800円〜"],
       "初期費用0円・AI活用でスピード公開",
       "詳しく見る ▶")

print("done")
