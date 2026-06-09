# -*- coding: utf-8 -*-
"""通知オーケストレーター 配置図"""
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch
import matplotlib.font_manager as fm

jp = fm.FontProperties(family="IPAGothic")
plt.rcParams["font.family"] = "IPAGothic"

fig, ax = plt.subplots(figsize=(16, 10))
ax.set_xlim(0, 100); ax.set_ylim(0, 100); ax.axis("off")

C_EVT="#2e86de"; C_ORCH="#1a1a2e"; C_BG="#f4f6fb"
CH={"proline":"#06C755","lineworks":"#00B900","slack":"#4A154B","mail":"#c0392b"}

def box(x,y,w,h,t,fc,tc="white",fs=11,bold=True,ec=None):
    ax.add_patch(FancyBboxPatch((x,y),w,h,boxstyle="round,pad=0.3,rounding_size=1.2",
                fc=fc,ec=ec or fc,lw=1.5,zorder=2))
    ax.text(x+w/2,y+h/2,t,ha="center",va="center",color=tc,fontsize=fs,
            fontweight="bold" if bold else "normal",fontproperties=jp,zorder=3,linespacing=1.4)

def arrow(x1,y1,x2,y2,color="#888",lw=2,style="-|>"):
    ax.add_patch(FancyArrowPatch((x1,y1),(x2,y2),arrowstyle=style,
                mutation_scale=18,color=color,lw=lw,zorder=1))

ax.add_patch(FancyBboxPatch((1,1),98,98,boxstyle="round,pad=0.3",fc=C_BG,ec="#d0d5e0",lw=1.5,zorder=0))
ax.text(50,95,"通知オーケストレーター 配置図（WPが単一発火点）",ha="center",va="center",
        color=C_ORCH,fontsize=19,fontweight="bold",fontproperties=jp)

# 左：イベント
ax.text(15,86,"イベント発火",ha="center",fontsize=13,fontweight="bold",fontproperties=jp,color=C_EVT)
events=["ステータス変更","審査結果 OK/NG","決済 完了/失敗","返済・延滞(Cron)","車検・保険 期日(Cron)","連携失敗/エラー"]
for i,e in enumerate(events):
    box(3,76-i*7,24,5.2,e,C_EVT,fs=10)

# 中央：オーケストレーター
box(34,40,22,38,"通知\nオーケストレーター\n(WordPress)\n\n・ルーティング表\n・重複排除/冪等\n・リトライ\n・フォールバック判定\n・配信ログ",
    C_ORCH,fs=11)
for i in range(6):
    arrow(27,78.5-i*7,34,68)

# 右：4チャネル
chans=[("proline","プロライン → 顧客LINE\n進捗・審査・返済・車検/保険"),
       ("lineworks","LINE WORKS → 社内\nアサイン・対応依頼・アラート"),
       ("slack","Slack → 運用/開発\n連携失敗・エラー・KPI"),
       ("mail","メール → 正式/控え\nLINE失敗時フォールバック")]
for i,(k,t) in enumerate(chans):
    y=70-i*15
    box(64,y,33,11,t,CH[k],fs=10)
    arrow(56,59,64,y+5.5,color=CH[k],lw=2.5)

# フォールバック点線（proline→mail）
ax.add_patch(FancyArrowPatch((80.5,70),(80.5,36),arrowstyle="-|>",mutation_scale=16,
            color="#c0392b",lw=2,ls=(0,(4,3)),zorder=1,
            connectionstyle="arc3,rad=0.35"))
ax.text(92,52,"LINE未連携/失敗\n→ メール自動\nフォールバック",ha="center",va="center",
        color="#c0392b",fontsize=9.5,fontproperties=jp,fontweight="bold")

# 下：配信ログ
box(34,8,63,7,"carmel_notification_log（全送信を記録：日時・チャネル・結果・リトライ・フォールバック）",
    "#16a085",fs=10.5)
arrow(45,40,50,15,color="#16a085",lw=2)

plt.tight_layout()
plt.savefig("/home/user/-/docs/カーメル_通知配置図.png",dpi=130,bbox_inches="tight",facecolor="white")
print("saved")
