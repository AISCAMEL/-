# -*- coding: utf-8 -*-
"""carmel-core 基盤 実装マインドマップ"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch
import matplotlib.font_manager as fm

jp = fm.FontProperties(family="IPAGothic")
plt.rcParams["font.family"] = "IPAGothic"

fig, ax = plt.subplots(figsize=(17, 11))
ax.set_xlim(0, 100); ax.set_ylim(0, 100); ax.axis("off")
ax.add_patch(FancyBboxPatch((0.5, 0.5), 99, 99, boxstyle="round,pad=0.3",
             fc="#f4f6fb", ec="#d0d5e0", lw=1.5, zorder=0))

ax.text(50, 96, "カーメル統合管理システム　基盤実装マインドマップ", ha="center",
        va="center", fontsize=19, fontweight="bold", color="#1a1a2e", fontproperties=jp)
ax.text(50, 92.4, "WordPress プラグイン carmel-core（要件定義 v1.5 / WPが正）", ha="center",
        va="center", fontsize=11, color="#555", fontproperties=jp)

# 中央ノード
cx, cy = 50, 48
ax.add_patch(FancyBboxPatch((cx-9, cy-5), 18, 10, boxstyle="round,pad=0.3,rounding_size=1.5",
             fc="#1a1a2e", ec="#1a1a2e", zorder=5))
ax.text(cx, cy, "carmel-core", ha="center", va="center", color="white",
        fontsize=14, fontweight="bold", fontproperties=jp, zorder=6)

def branch(x, y, w, h, title, leaves, color, side):
    # connector
    anchor_x = x + w if side == "left" else x
    ax.add_patch(FancyArrowPatch((cx + (-9 if side=="left" else 9), cy),
                 (anchor_x, y + h/2), arrowstyle="-", mutation_scale=1,
                 color=color, lw=2, zorder=1,
                 connectionstyle="arc3,rad=" + ("0.15" if side=="left" else "-0.15")))
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.3,rounding_size=1.0",
                 fc="white", ec=color, lw=2, zorder=3))
    # header
    ax.add_patch(FancyBboxPatch((x, y + h - 3.6), w, 3.6,
                 boxstyle="round,pad=0,rounding_size=0.0", fc=color, ec=color, zorder=4))
    ax.text(x + w/2, y + h - 1.8, title, ha="center", va="center", color="white",
            fontsize=10.5, fontweight="bold", fontproperties=jp, zorder=5)
    ax.text(x + 1.2, y + h - 4.4, leaves, ha="left", va="top", color="#333",
            fontsize=8.3, fontproperties=jp, zorder=5, linespacing=1.45)

B = "#2e86de"; P="#8e44ad"; G="#16a085"; O="#e67e22"; R="#c0392b"; T="#2c3e50"; N="#0aa"; M="#d35400"

LW, LH = 39, 16.5
LX, RX = 3, 58
rows = [72.5, 54.5, 36.5, 18.5]

# 左側 4ブランチ
branch(LX, rows[0], LW, LH, "① データ基盤（CPT 9種）",
       "・carmel_deal 案件（正データ）\n・carmel_store / carmel_vehicle 在庫\n・carmel_document / carmel_repayment\n・carmel_support / inspection / insurance\n・carmel_notify_log 配信ログ", B, "left")
branch(LX, rows[1], LW, LH, "② 権限（4ロール＋cap）",
       "・hq_admin / store_owner / store_staff / customer\n・自店スコープは行レベルで担保\n・cap: screening / send_contract\n　 / view_reports / manage_stores", P, "left")
branch(LX, rows[2], LW, LH, "③ 業務フロー（ステートマシン）",
       "・deal_type 別フロー(loan/buyback/lease)\n・遷移cap判定（本部/加盟店）\n・在庫ステータス自動連動\n・監査ログ _carmel_status_history", T, "left")
branch(LX, rows[3], LW, LH, "④ 申込受付",
       "・フォーム→案件作成＋顧客アカウント自動発行\n・CF7 / Gravity Forms / REST 対応\n・パスワード設定リンク方式\n・loan は AIスコア自動依頼", M, "left")

# 右側 4ブランチ
branch(RX, rows[0], LW, LH, "⑤ 画面（ショートコード）",
       "・[carmel_mypage] 顧客PHASE/返済/アフター\n・[carmel_upload] 書類提出(保護保存)\n・[carmel_store] 加盟店ポータル\n・[carmel_hq_screening] 審査\n・[carmel_hq_contracts] 契約 / [_reports] / [_board]", O, "right")
branch(RX, rows[1], LW, LH, "⑥ 通知オーケストレーター（4ch）",
       "・プロライン=顧客 / LINE WORKS=社内\n・Slack=運用監視 / メール=正式・FB\n・ルーティング表＋LINE失敗→メール\n・重複排除・配信ログ・オプトアウト", G, "right")
branch(RX, rows[2], LW, LH, "⑦ 外部連携",
       "・GAS：AIスコア＋書類PDF（書き戻し）\n・Google Maps：陸送費自動計算\n・Square＋WooCommerce：決済\n・マネーフォワード契約：電子署名(本部)", R, "right")
branch(RX, rows[3], LW, LH, "⑧ 定期処理（WP-Cron）",
       "・返済リマインダー(3/1/0日前)\n・延滞検知＋利息計算(1/5/14日)\n・車検90/60/30日前・保険90/30日前\n・週次レポート→Slack＋本部メール", N, "right")

plt.tight_layout()
plt.savefig("/home/user/-/docs/カーメル_基盤マインドマップ.png", dpi=130,
            bbox_inches="tight", facecolor="white")
print("saved")
