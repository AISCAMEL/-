# -*- coding: utf-8 -*-
"""在庫共有 → 商談自動起票 → 成約 → 手数料配分の流れ。"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle, FancyArrowPatch

plt.rcParams["font.family"] = "IPAGothic"
INK = "#1a1a2e"; GREEN = "#16a085"; PURPLE = "#6b4fbb"; BLUE = "#2e86de"; ORANGE = "#e67e22"; RED = "#c0392b"


def box(ax, x, y, w, h, fc, text, tc="white", fs=9.5, ec=None, z=3):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.2,rounding_size=2",
                 fc=fc, ec=ec or fc, lw=1.2, zorder=z))
    ax.text(x + w / 2, y + h / 2, text, ha="center", va="center", color=tc, fontsize=fs, zorder=z + 1)


def arrow(ax, x1, y1, x2, y2, color="#555", lw=2.0, style="-|>"):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle=style, mutation_scale=16,
                 lw=lw, color=color, zorder=5))


fig, ax = plt.subplots(figsize=(13, 8))
ax.set_xlim(0, 130); ax.set_ylim(0, 80); ax.axis("off")
ax.add_patch(Rectangle((0, 0), 130, 80, fc="#faf9fc", zorder=0))
ax.text(65, 76, "在庫共有 → 商談自動起票 → 手数料配分の流れ", ha="center", va="center",
        fontsize=19, fontweight="bold", color=INK)

# レーン
for ly, lab, c in [(60, "在庫・公開", BLUE), (40, "商談化（自動）", PURPLE), (20, "成約・手数料", GREEN)]:
    ax.add_patch(Rectangle((2, ly - 7.5), 126, 15, fc="white", ec="#e7e2ef", lw=1, zorder=1))
    ax.text(4.5, ly + 5.5, lab, ha="left", va="center", fontsize=10, fontweight="bold", color=c, zorder=2)

# 在庫・公開レーン
box(ax, 14, 56.5, 22, 8, BLUE, "保有店A が在庫を登録\n「掲載（共有）」ON")
box(ax, 44, 56.5, 22, 8, BLUE, "カーメル在庫ページ\n（公開・ログイン分け）")
box(ax, 74, 56.5, 24, 8, "#5aa0e8", "加盟店ネットワークで\n他店も閲覧（小売価格のみ）")
arrow(ax, 36, 60.5, 44, 60.5, BLUE); arrow(ax, 66, 60.5, 74, 60.5, BLUE)

# 商談化レーン（2経路）
box(ax, 14, 36.5, 26, 8, PURPLE, "販売店B が「取り寄せ・\n商談を依頼」")
box(ax, 60, 36.5, 30, 8, ORANGE, "お客様が在庫詳細から\n「問い合わせ」")
box(ax, 99, 36.5, 27, 8, PURPLE, "商談(carmel_deal)を\n自動起票・重複は再利用")
arrow(ax, 86, 53.5, 27, 44.5, "#9aa", 1.6, "-|>")     # 共有→依頼
arrow(ax, 40, 40.5, 99, 40.5, PURPLE)
arrow(ax, 90, 38.5, 99, 39.5, ORANGE)

# 自動セット注記
box(ax, 99, 27, 27, 7, "#efeafb", "store_id=販売店B /\nsource_store_id=保有店A を自動セット", tc=PURPLE, fs=8.2, ec="#ddd2f5")
arrow(ax, 112, 36.5, 112, 34, PURPLE, 1.6)

# 成約・手数料レーン
box(ax, 14, 16.5, 26, 8, GREEN, "通常フロー進行\n書類→契約→納車→成約")
box(ax, 50, 16.5, 30, 8, GREEN, "成約ステータスで\n手数料を自動計算\n（販売価格 × 料率5%）")
box(ax, 90, 16.5, 26, 8, RED, "[carmel_hq_commissions]\n本部が精算（済/未）")
arrow(ax, 112, 27, 112, 24.5, GREEN, 1.6)
arrow(ax, 40, 20.5, 50, 20.5, GREEN); arrow(ax, 80, 20.5, 90, 20.5, GREEN)
arrow(ax, 27, 36.5, 27, 24.5, GREEN, 1.6)

# フッター注記
ax.text(65, 6.5,
        "通知：取り寄せ/問い合わせ→保有店A＋本部へ自動通知。原価は本部/自店のみ表示（他店は小売価格のみ）。手数料は source_store_id≠store_id の成約に適用。",
        ha="center", va="center", fontsize=8.6, color="#555")

plt.tight_layout()
fig.savefig("docs/カーメル_在庫共有_手数料フロー.png", dpi=140, bbox_inches="tight", facecolor="#faf9fc")
print("saved docs/カーメル_在庫共有_手数料フロー.png")
