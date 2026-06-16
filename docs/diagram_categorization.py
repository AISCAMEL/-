# -*- coding: utf-8 -*-
"""カーメル統合管理システム 全体仕分け（分類）図"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch
import matplotlib.font_manager as fm

# 日本語フォント
jp = fm.FontProperties(fname="/usr/share/fonts/opentype/ipafont-gothic/ipagp.ttf") \
    if False else fm.FontProperties(family="IPAGothic")
plt.rcParams["font.family"] = "IPAGothic"
plt.rcParams["axes.unicode_minus"] = False

fig, ax = plt.subplots(figsize=(16, 11))
ax.set_xlim(0, 100)
ax.set_ylim(0, 100)
ax.axis("off")

# 色
C_TITLE = "#1a1a2e"
C_BIZ   = "#2e86de"   # 業務
C_ROLE  = "#8e44ad"   # 権限
C_DATA  = "#16a085"   # データ(CPT)
C_PORT  = "#e67e22"   # ポータル
C_EXT   = "#c0392b"   # 外部連携
C_BG    = "#f4f6fb"

def box(x, y, w, h, text, fc, tc="white", fs=11, bold=True, ec=None):
    p = FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.3,rounding_size=1.2",
                       fc=fc, ec=ec or fc, lw=1.5, zorder=2)
    ax.add_patch(p)
    ax.text(x + w/2, y + h/2, text, ha="center", va="center",
            color=tc, fontsize=fs, fontweight="bold" if bold else "normal",
            fontproperties=jp, zorder=3, linespacing=1.4)

def header(x, y, w, text, color):
    box(x, y, w, 4.2, text, color, fs=13)

# 背景
ax.add_patch(FancyBboxPatch((1, 1), 98, 98, boxstyle="round,pad=0.3",
             fc=C_BG, ec="#d0d5e0", lw=1.5, zorder=0))

# タイトル
ax.text(50, 95.5, "カーメル統合管理システム　全体仕分け図", ha="center", va="center",
        color=C_TITLE, fontsize=20, fontweight="bold", fontproperties=jp)
ax.text(50, 91.8, "WordPress（carmel_deal がマスター）／ 要件定義 v1.3", ha="center",
        va="center", color="#555", fontsize=11, fontproperties=jp)

# ① 業務種別
header(3, 84, 28, "① 業務種別（deal_type）", C_BIZ)
box(3, 78.5, 28, 4.5, "ローン販売  loan", C_BIZ, fs=11)
box(3, 73.5, 28, 4.5, "車買取  buyback", C_BIZ, fs=11)
box(3, 68.5, 28, 4.5, "自社リース  lease", C_BIZ, fs=11)
box(3, 63.0, 28, 4.5, "アフター（車検・保険・整備）", C_BIZ, fs=10)

# ② 権限ロール
header(36, 84, 28, "② 権限ロール（4階層）", C_ROLE)
box(36, 78.5, 28, 4.5, "LEVEL1  本部管理者 hq_admin", C_ROLE, fs=9.5)
box(36, 73.5, 28, 4.5, "LEVEL2  加盟店オーナー store_owner", C_ROLE, fs=9)
box(36, 68.5, 28, 4.5, "LEVEL3  加盟店スタッフ store_staff", C_ROLE, fs=9)
box(36, 63.0, 28, 4.5, "LEVEL4  ユーザー customer", C_ROLE, fs=9.5)

# ③ ポータル
header(69, 84, 28, "③ ポータル（画面）", C_PORT)
box(69, 78.5, 28, 4.5, "/mypage  顧客マイページ", C_PORT, fs=9.5)
box(69, 73.5, 28, 4.5, "/store  加盟店ポータル", C_PORT, fs=9.5)
box(69, 68.5, 28, 4.5, "/hq  本部管理画面", C_PORT, fs=9.5)
box(69, 63.0, 28, 4.5, "/login  統合ログイン", C_PORT, fs=9.5)

# ④ データモデル（CPT）
header(3, 55, 61, "④ データモデル（カスタム投稿タイプ：WPが正）", C_DATA)
cpts = [
    "carmel_deal\n案件", "carmel_store\n加盟店", "carmel_vehicle\n在庫車両",
    "carmel_document\n書類", "carmel_repayment\n返済", "carmel_support_ticket\nサポート",
    "carmel_inspection\n車検", "carmel_insurance\n保険",
]
cw, ch, gap = 14, 7, 1
x0, y0 = 4, 47
for i, t in enumerate(cpts):
    col = i % 4
    row = i // 4
    box(x0 + col*(cw+gap), y0 - row*(ch+1.2), cw, ch, t, C_DATA, fs=9)

# ⑤ 外部連携
header(69, 55, 28, "⑤ 外部連携", C_EXT)
exts = [
    "GAS：書類生成/AIスコア/Asana",
    "Square：決済（申込金/保証/会費等）",
    "Google Maps：陸送費計算",
    "プロライン：LINE通知",
    "マネーフォワード契約：署名(本部のみ)",
    "Notion / bbPress",
]
for i, t in enumerate(exts):
    box(69, 50 - i*5.0, 28, 4.2, t, C_EXT, fs=8.5)

# 下段：決済対象 と 通知/自動処理
header(3, 19, 45, "Square 決済対象（車両本体は対象外）", "#2c3e50")
pay = "申込金・手付金 ／ 保証プラン ／ オプション\n加盟店の販促購入費 ／ 会費"
box(3, 11.5, 45, 6.5, pay, "#34495e", fs=10)

header(52, 19, 45, "自動処理（WP-Cron / 連携）", "#2c3e50")
auto = "返済リマインダー・延滞利息計算\n車検/保険 満了アラート(90/60/30日前)\nステータス連動：Asanaタスク＋LINE通知"
box(52, 9.5, 45, 8.5, auto, "#34495e", fs=10)

# 凡例的な流れ矢印（業務→データ→ポータル）
ax.text(50, 5.5, "業務種別 × 権限ロール × データ(CPT) × ポータル × 外部連携 を一元管理",
        ha="center", va="center", color=C_TITLE, fontsize=12,
        fontweight="bold", fontproperties=jp)

plt.tight_layout()
plt.savefig("/home/user/-/docs/カーメル_全体仕分け図.png", dpi=130,
            bbox_inches="tight", facecolor="white")
print("saved")
