# -*- coding: utf-8 -*-
"""ログイン画面 デザインモックアップ"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle
import matplotlib.font_manager as fm

jp = fm.FontProperties(family="IPAGothic")
plt.rcParams["font.family"] = "IPAGothic"

BRAND = "#5b2a86"; ACCENT = "#7c3aed"
fig, ax = plt.subplots(figsize=(7.2, 9))
ax.set_xlim(0, 100); ax.set_ylim(0, 130); ax.axis("off")

# 背景グラデ風
ax.add_patch(Rectangle((0, 0), 100, 130, fc="#f3eefb", zorder=0))
ax.add_patch(Rectangle((0, 95), 100, 35, fc="#ece2fa", zorder=0))

# カード
card = FancyBboxPatch((18, 30), 64, 86, boxstyle="round,pad=1,rounding_size=3",
                      fc="white", ec="#e7eaf0", lw=1.2, zorder=2)
card.set_clip_on(False)
ax.add_patch(card)

cx = 50
# ワードマーク
ax.text(cx, 104, "CarMel", ha="center", va="center", color=BRAND,
        fontsize=30, fontweight="bold", fontstyle="italic", fontproperties=jp, zorder=3)
ax.text(cx, 97.5, "ネットで安心してクルマ頼める！", ha="center", va="center",
        color="#8a7a9c", fontsize=9, fontproperties=jp, zorder=3)

# タイトル＋下線
ax.text(cx, 90, "ログイン", ha="center", va="center", color=BRAND,
        fontsize=13, fontweight="bold", fontproperties=jp, zorder=3)
ax.add_patch(Rectangle((cx-5, 86.5), 10, 0.9, fc=ACCENT, zorder=3))

def field(y, label, placeholder=""):
    ax.text(26, y+5.8, label, ha="left", va="center", color="#3a3f4b",
            fontsize=8.5, fontweight="bold", fontproperties=jp, zorder=3)
    ax.add_patch(FancyBboxPatch((26, y), 48, 4.2, boxstyle="round,pad=0.2,rounding_size=1",
                 fc="#fff", ec="#d9dee7", lw=1.3, zorder=3))
    if placeholder:
        ax.text(28, y+2.1, placeholder, ha="left", va="center", color="#b8bec9",
                fontsize=8, fontproperties=jp, zorder=4)

field(74, "メールアドレス または ユーザー名", "you@example.com")
field(63, "パスワード", "••••••••")

# remember
ax.add_patch(Rectangle((26, 57.5), 2.2, 2.2, fc="#fff", ec="#aab", lw=1, zorder=3))
ax.text(29.5, 58.6, "ログイン状態を保持", ha="left", va="center", color="#666",
        fontsize=8, fontproperties=jp, zorder=3)

# ログインボタン
ax.add_patch(FancyBboxPatch((26, 49), 48, 5.5, boxstyle="round,pad=0.2,rounding_size=1.4",
             fc=ACCENT, ec=ACCENT, zorder=3))
ax.text(cx, 51.7, "ログイン", ha="center", va="center", color="#fff",
        fontsize=11, fontweight="bold", fontproperties=jp, zorder=4)

# リンク
ax.text(cx, 44, "パスワードをお忘れですか？", ha="center", va="center", color=ACCENT,
        fontsize=8.5, fontproperties=jp, zorder=3)
ax.text(cx, 37, "お申込み済みの方は、受付時のメール／LINEのリンクから\nパスワードを設定してください。", ha="center",
        va="center", color="#9298a5", fontsize=7.2, fontproperties=jp, zorder=3, linespacing=1.5)

ax.text(cx, 12, "© 2026 CarMel", ha="center", va="center", color="#b3a8c4",
        fontsize=7.5, fontproperties=jp, zorder=3)

plt.tight_layout()
plt.savefig("/home/user/-/docs/カーメル_ログイン画面モックアップ.png", dpi=130,
            bbox_inches="tight", facecolor="#f3eefb")
print("saved")
