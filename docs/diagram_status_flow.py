# -*- coding: utf-8 -*-
"""案件ステータス遷移図（ローン/買取/リース）。色＝担当（本部/加盟店/自動）。"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle, FancyArrowPatch

plt.rcParams["font.family"] = "IPAGothic"
INK = "#1a1a2e"
AUTO = "#2e86de"   # 自動/システム
HQ = "#6b4fbb"     # 本部
STORE = "#16a085"  # 加盟店
NG = "#c0392b"     # 否決/不成立

W, H, GAP = 14.5, 6.4, 3.2


def node(ax, x, y, label, color):
    ax.add_patch(FancyBboxPatch((x, y), W, H, boxstyle="round,pad=0.2,rounding_size=1.6",
                 fc=color, ec=color, lw=1, zorder=3))
    ax.text(x + W / 2, y + H / 2, label, ha="center", va="center", color="white",
            fontsize=8.6, zorder=4)


def arrow(ax, x1, y1, x2, y2, color="#888"):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle="-|>", mutation_scale=13,
                 lw=1.8, color=color, zorder=2))


def chain(ax, y, title, steps):
    ax.text(2, y + H + 2.2, title, ha="left", va="center", fontsize=12, fontweight="bold", color=INK)
    x = 2
    prev = None
    for label, color in steps:
        node(ax, x, y, label, color)
        if prev is not None:
            arrow(ax, prev, y + H / 2, x, y + H / 2)
        prev = x + W
        x += W + GAP
    return x


fig, ax = plt.subplots(figsize=(19, 9.5))
ax.set_xlim(0, 200); ax.set_ylim(0, 104); ax.axis("off")
ax.add_patch(Rectangle((0, 0), 200, 104, fc="#faf9fc", zorder=0))
ax.text(100, 99, "案件ステータス遷移図（3業務）", ha="center", va="center",
        fontsize=20, fontweight="bold", color=INK)

# 凡例
lx = 50
for c, t in [(AUTO, "自動/システム"), (HQ, "本部"), (STORE, "加盟店"), (NG, "否決/不成立")]:
    ax.add_patch(Rectangle((lx, 92.5), 3, 2.4, fc=c, zorder=3))
    ax.text(lx + 4, 93.7, t, ha="left", va="center", fontsize=9, color=INK)
    lx += 27

# ローン
loan = [("仮申込", AUTO), ("AIスコア済", AUTO), ("信販審査中", HQ), ("審査OK", HQ),
        ("加盟店\nマッチング", HQ), ("書類準備中", STORE), ("契約完了", HQ),
        ("納車準備中", STORE), ("納車済", STORE), ("アフター", STORE), ("クローズ", STORE)]
chain(ax, 74, "ローン販売", loan)
# NG分岐
ax.add_patch(FancyBboxPatch((2 + 3 * (W + GAP), 62), W, H, boxstyle="round,pad=0.2,rounding_size=1.6",
             fc=NG, ec=NG, lw=1, zorder=3))
ax.text(2 + 3 * (W + GAP) + W / 2, 62 + H / 2, "審査NG", ha="center", va="center", color="white", fontsize=8.6, zorder=4)
ax.add_patch(FancyArrowPatch((2 + 2 * (W + GAP) + W, 74 + H / 2 - 1), (2 + 3 * (W + GAP), 62 + H),
             arrowstyle="-|>", mutation_scale=12, lw=1.6, color=NG, zorder=2))

# 買取
bb = [("査定申込", AUTO), ("査定中", STORE), ("査定額提示", STORE), ("成約", STORE),
      ("書類準備中", STORE), ("引取完了", STORE), ("クローズ", STORE)]
chain(ax, 40, "車買取", bb)
ax.add_patch(FancyBboxPatch((2 + 3 * (W + GAP), 28), W, H, boxstyle="round,pad=0.2,rounding_size=1.6",
             fc=NG, ec=NG, lw=1, zorder=3))
ax.text(2 + 3 * (W + GAP) + W / 2, 28 + H / 2, "不成立", ha="center", va="center", color="white", fontsize=8.6, zorder=4)
ax.add_patch(FancyArrowPatch((2 + 2 * (W + GAP) + W, 40 + H / 2 - 1), (2 + 3 * (W + GAP), 28 + H),
             arrowstyle="-|>", mutation_scale=12, lw=1.6, color=NG, zorder=2))

# リース
ls = [("リース申込", AUTO), ("リース審査", HQ), ("契約完了", HQ), ("納車済", STORE),
      ("リース中", STORE), ("満了/完済", STORE), ("クローズ", STORE)]
chain(ax, 8, "自社リース", ls)

# 注記
ax.text(100, 2.5,
        "本部専用遷移：審査OK/NG（信販審査）・契約完了（マネーフォワード契約）。加盟店画面ではこれらは「本部の手続き待ち」と表示。\n"
        "ステータス変更で 通知・在庫連動（販売中→商談中→売約済→納車済）・監査ログ を自動発火。",
        ha="center", va="center", fontsize=8.6, color="#555")

plt.tight_layout()
fig.savefig("docs/カーメル_ステータス遷移図.png", dpi=140, bbox_inches="tight", facecolor="#faf9fc")
print("saved docs/カーメル_ステータス遷移図.png")
