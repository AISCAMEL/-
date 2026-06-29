# -*- coding: utf-8 -*-
"""カーメル統合管理システム 全体図（最新）。ショートコード/モジュール/基盤を俯瞰。"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle
import matplotlib.font_manager as fm

plt.rcParams["font.family"] = "IPAGothic"
PURPLE = "#5b2a86"; PACC = "#7c3aed"; INK = "#1a1a2e"
COLS = {"公開/入口": "#e67e22", "ユーザー": "#2e86de", "加盟店": "#16a085", "本部HQ": "#6b4fbb", "外部・自動": "#7a7488"}


def rrect(ax, x, y, w, h, fc, ec=None, lw=1.0, rs=2.2, z=2, alpha=1.0):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle=f"round,pad=0.2,rounding_size={rs}",
                 fc=fc, ec=ec or fc, lw=lw, zorder=z, alpha=alpha))


fig, ax = plt.subplots(figsize=(14, 9))
ax.set_xlim(0, 140); ax.set_ylim(0, 92); ax.axis("off")
ax.add_patch(Rectangle((0, 0), 140, 92, fc="#faf9fc", zorder=0))

ax.text(70, 88, "カーメル統合管理システム ｜ 全体図", ha="center", va="center",
        fontsize=21, fontweight="bold", color=INK)
ax.text(70, 83.5, "1つのWordPress + プラグイン carmel-core（WPが正・認証は1つ／入った先だけロールで分岐）",
        ha="center", va="center", fontsize=11, color="#666")

columns = [
    ("公開/入口", 4, [
        "[carmel_login] 統合ログイン",
        "[carmel_application_form] 申込",
        "[carmel_franchise_form] 加盟店募集",
        "[carmel_inventory] 在庫ページ",
        "  └ ログイン分け・詳細・SEO",
    ]),
    ("ユーザー", 31.5, [
        "[carmel_mypage] 進捗/返済/愛車",
        "[carmel_upload] 書類提出",
        "[carmel_my_documents] 発行書類",
        "[carmel_customer_guide] 使い方",
        "在庫: お気に入り/比較/保存検索",
    ]),
    ("加盟店", 59, [
        "[carmel_store] ダッシュボード",
        "[carmel_store_inventory] 在庫/共有",
        "[carmel_store_billing] 帳票/契約",
        "[carmel_sales_support] 販売支援",
        "[carmel_store_content] ガイド/FAQ",
        "[carmel_community] 掲示板",
    ]),
    ("本部HQ", 86.5, [
        "[carmel_hq_dashboard] KPI集約",
        "[carmel_hq_screening] 審査",
        "[carmel_hq_board] 横断カンバン",
        "[carmel_hq_contracts] 電子契約",
        "[carmel_hq_reports] 売上/在庫KPI",
        "[carmel_hq_stores] 加盟店管理",
        "[carmel_hq_commissions] 手数料",
        "[carmel_hq_content] コンテンツ作成",
    ]),
    ("外部・自動", 114, [
        "GAS: AIスコア/PDF",
        "Google Maps: 陸送/地図",
        "Square/WooCommerce: 決済",
        "マネーフォワード契約: 署名",
        "通知4ch / WP-Cron",
    ]),
]

cw = 24
for name, x, items in columns:
    color = COLS[name]
    rrect(ax, x, 74, cw, 5.2, color, z=3)
    ax.text(x + cw / 2, 76.6, name, ha="center", va="center", color="white",
            fontsize=12.5, fontweight="bold", zorder=4)
    y = 70.5
    for it in items:
        h = 4.2
        rrect(ax, x, y - h, cw, h - 0.6, "white", "#e7e2ef", 1.0, z=3)
        ax.text(x + 0.8, y - h / 2 - 0.3, it, ha="left", va="center",
                color=INK if not it.strip().startswith("└") else "#888",
                fontsize=8.0, zorder=4)
        y -= h

# 基盤バンド
rrect(ax, 4, 14, 134, 12.5, "#f1ecfb", "#ddd2f5", 1.2, z=2)
ax.text(71, 24.2, "共通基盤（中核エンジン）", ha="center", va="center",
        fontsize=12, fontweight="bold", color=PURPLE)
engines = [
    "案件ステートマシン\n通知・在庫連動・監査を自動発火",
    "通知オーケストレーター\nプロライン/LINE WORKS/Slack/メール\nLINE失敗→メール・重複排除・ログ",
    "帳票エンジン\n見積/請求/契約テンプレ→印刷HTML",
    "アクセス制御\nnonce+cap+行レベル(store_id/顧客)",
]
ex = 7
for i, e in enumerate(engines):
    w = 31.5
    rrect(ax, ex, 15.5, w, 6.6, "white", PACC, 1.0, z=3)
    ax.text(ex + w / 2, 18.8, e, ha="center", va="center", fontsize=8.2, color=INK, zorder=4)
    ex += w + 1.7

# データモデル
rrect(ax, 4, 3.5, 134, 8.5, "#eef2fb", "#cdd9f0", 1.2, z=2)
ax.text(71, 10.0, "カスタム投稿タイプ（11）", ha="center", va="center",
        fontsize=11, fontweight="bold", color="#2e86de")
ax.text(71, 6.0,
        "carmel_deal 案件 / carmel_store 加盟店 / carmel_vehicle 在庫 / carmel_document 書類・帳票 / carmel_repayment 返済 /\n"
        "carmel_support 問合せ / carmel_inspection 車検 / carmel_insurance 保険 / carmel_content コンテンツ / carmel_notify_log 通知ログ / carmel_community 掲示板",
        ha="center", va="center", fontsize=8.2, color=INK)

plt.tight_layout()
fig.savefig("docs/カーメル_全体図v2.png", dpi=140, bbox_inches="tight", facecolor="#faf9fc")
print("saved docs/カーメル_全体図v2.png")
