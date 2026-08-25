# -*- coding: utf-8 -*-
"""ブランド案：A=茶系+ドット(ログイン) / B=紫統一(ポータル)"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle, Circle
import matplotlib.font_manager as fm

jp = fm.FontProperties(family="IPAGothic")
plt.rcParams["font.family"] = "IPAGothic"

# ロゴ準拠カラー
BROWN = "#3a241c"; BLUE = "#0b4ea2"; YELLOW = "#f6c63c"; ORANGE = "#ef8120"
# 紫
PURPLE = "#5b2a86"; PACC = "#7c3aed"

def rrect(ax, x, y, w, h, fc, ec=None, lw=1.2, rs=2, z=2):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle=f"round,pad=0.3,rounding_size={rs}",
                 fc=fc, ec=ec or fc, lw=lw, zorder=z))

# ========== 案A：茶系＋ドット ログイン ==========
figA, ax = plt.subplots(figsize=(7.2, 9))
ax.set_xlim(0, 100); ax.set_ylim(0, 130); ax.axis("off")
ax.add_patch(Rectangle((0, 0), 100, 130, fc="#faf5ef", zorder=0))
ax.add_patch(Rectangle((0, 95), 100, 35, fc="#f3e9dc", zorder=0))
rrect(ax, 18, 30, 64, 86, "white", "#ece3d6", 1.2, 3, 2)

cx = 50
ax.text(cx, 105, "CarMel", ha="center", va="center", color=BROWN,
        fontsize=30, fontweight="bold", fontstyle="italic", fontproperties=jp, zorder=3)
# 3ドット（ロゴ準拠）
for i, c in enumerate([BLUE, YELLOW, ORANGE]):
    ax.add_patch(Circle((cx-6 + i*6, 99.3), 1.5, fc=c, zorder=4))
ax.text(cx, 95.5, "ネットで安心してクルマ頼める！", ha="center", va="center",
        color="#8a7563", fontsize=9, fontproperties=jp, zorder=3)

ax.text(cx, 88, "ログイン", ha="center", va="center", color=BROWN,
        fontsize=13, fontweight="bold", fontproperties=jp, zorder=3)
ax.add_patch(Rectangle((cx-5, 84.5), 10, 0.9, fc=ORANGE, zorder=3))

def fieldA(y, label, ph):
    ax.text(26, y+5.6, label, ha="left", va="center", color="#5a4a3c",
            fontsize=8.3, fontweight="bold", fontproperties=jp, zorder=3)
    rrect(ax, 26, y, 48, 4.2, "#fff", "#ddd0c0", 1.3, 1, 3)
    ax.text(28, y+2.1, ph, ha="left", va="center", color="#c3b6a6", fontsize=8, fontproperties=jp, zorder=4)

fieldA(72, "メールアドレス または ユーザー名", "you@example.com")
fieldA(61, "パスワード", "••••••••")
ax.add_patch(Rectangle((26, 55.5), 2.2, 2.2, fc="#fff", ec="#bbb", lw=1, zorder=3))
ax.text(29.5, 56.6, "ログイン状態を保持", ha="left", va="center", color="#777", fontsize=8, fontproperties=jp, zorder=3)
rrect(ax, 26, 47, 48, 5.5, ORANGE, ORANGE, 1, 1.4, 3)
ax.text(cx, 49.7, "ログイン", ha="center", va="center", color="#fff", fontsize=11, fontweight="bold", fontproperties=jp, zorder=4)
ax.text(cx, 42, "パスワードをお忘れですか？", ha="center", va="center", color=ORANGE, fontsize=8.3, fontproperties=jp, zorder=3)
ax.text(cx, 36, "申込済みの方はメール／LINEのリンクから設定", ha="center", va="center", color="#a9978650"[:7], fontsize=7, fontproperties=jp, zorder=3)
ax.text(cx, 22, "案A：茶系＋カラフルドット（ロゴ準拠）", ha="center", va="center", color=BROWN, fontsize=11, fontweight="bold", fontproperties=jp)
plt.tight_layout()
figA.savefig("/home/user/-/docs/カーメル_案A_茶系ドット.png", dpi=130, bbox_inches="tight", facecolor="#faf5ef")
print("A saved")

# ========== 案B：紫統一 ポータル ==========
figB, ax = plt.subplots(figsize=(11, 8))
ax.set_xlim(0, 100); ax.set_ylim(0, 75); ax.axis("off")
ax.add_patch(Rectangle((0, 0), 100, 75, fc="#f6f2fb", zorder=0))

# トップバー
rrect(ax, 2, 67, 96, 6.5, PURPLE, PURPLE, 1, 1.2, 2)
ax.text(5, 70.2, "CarMel", ha="left", va="center", color="#fff", fontsize=14, fontweight="bold", fontstyle="italic", fontproperties=jp, zorder=3)
for i, lab in enumerate(["マイページ", "加盟店", "本部"]):
    ax.text(60 + i*13, 70.2, lab, ha="center", va="center", color="#e7dcf7", fontsize=9, fontproperties=jp, zorder=3)
ax.add_patch(Rectangle((54, 67.8), 12, 0.6, fc=YELLOW, zorder=3))  # active tab line

# KPIカード
labels = [("案件総数", "128"), ("成約数", "94"), ("転換率", "73.4%"), ("売上合計", "¥4,820,000")]
for i, (lab, val) in enumerate(labels):
    x = 4 + i*23.5
    rrect(ax, x, 55, 21, 9, "white", "#e6def2", 1.2, 1.5, 2)
    ax.text(x+10.5, 60.5, val, ha="center", va="center", color=PURPLE, fontsize=14, fontweight="bold", fontproperties=jp, zorder=3)
    ax.text(x+10.5, 57, lab, ha="center", va="center", color="#8a7ba0", fontsize=8, fontproperties=jp, zorder=3)

# PHASEステッパー（マイページ例）
ax.text(4, 50.5, "マイページ：進捗", ha="left", va="center", color=PURPLE, fontsize=9, fontweight="bold", fontproperties=jp)
steps = ["仮申込","審査","結果","契約","納車準備","納車","アフター"]
for i, s in enumerate(steps):
    x = 8 + i*12
    done = i < 3; active = i == 3
    col = PACC if active else (PURPLE if done else "#cdbfe0")
    ax.add_patch(Circle((x, 44), 2.0, fc=col, zorder=3))
    ax.text(x, 44, str(i+1), ha="center", va="center", color="#fff", fontsize=8, fontweight="bold", fontproperties=jp, zorder=4)
    ax.text(x, 40, s, ha="center", va="center", color="#6a5a80", fontsize=7, fontproperties=jp, zorder=3)
    if i < 6:
        ax.add_patch(Rectangle((x+2.2, 43.6), 7.6, 0.8, fc=PURPLE if done else "#ddd0ee", zorder=2))

# カンバン3列
cols = [("審査待ち", 3, PURPLE), ("契約準備", 2, PACC), ("納車準備", 2, "#9b6dd6")]
for i, (title, n, c) in enumerate(cols):
    x = 4 + i*31.5
    rrect(ax, x, 4, 29, 30, "#efe8f8", "#efe8f8", 1, 1.5, 1)
    rrect(ax, x, 30, 29, 4, c, c, 1, 1.2, 2)
    ax.text(x+2, 32, title, ha="left", va="center", color="#fff", fontsize=8.5, fontweight="bold", fontproperties=jp, zorder=3)
    for j in range(n):
        yy = 24 - j*7
        rrect(ax, x+2, yy, 25, 5.5, "white", "#e6def2", 1, 1, 3)
        ax.text(x+4, yy+3.5, f"#{100+i*3+j}  山田 様", ha="left", va="center", color="#3a2f4a", fontsize=7.5, fontproperties=jp, zorder=4)
        ax.text(x+4, yy+1.5, "ローン・東京", ha="left", va="center", color=PACC, fontsize=6.5, fontproperties=jp, zorder=4)

ax.text(50, 1.2, "案B：全ポータル 紫統一イメージ（本部ダッシュボード例）", ha="center", va="center", color=PURPLE, fontsize=11, fontweight="bold", fontproperties=jp)
plt.tight_layout()
figB.savefig("/home/user/-/docs/カーメル_案B_紫統一ポータル.png", dpi=130, bbox_inches="tight", facecolor="#f6f2fb")
print("B saved")
