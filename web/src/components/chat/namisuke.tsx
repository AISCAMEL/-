"use client";

import { useId } from "react";

// ============================================================
// なみすけ — IWASAWA SURF BASE のAIアシスタントのキャラクター
// ------------------------------------------------------------
// 海の色のしずく形の体に、白い波しぶきの髪。小さなサーフボードに乗っている。
// 表情・動きは mood で切り替える（アニメーションは globals.css の .nm-*）。
// prefers-reduced-motion の環境では動きを止める。
// ============================================================

export type NamisukeMood = "idle" | "thinking" | "talking" | "happy" | "wave";

export function Namisuke({
  mood = "idle",
  size = 64,
  className = "",
  title = "なみすけ",
}: {
  mood?: NamisukeMood;
  size?: number;
  className?: string;
  title?: string;
}) {
  const uid = useId().replace(/:/g, "");
  const bodyId = `nmBody${uid}`;
  const boardId = `nmBoard${uid}`;
  const eyesUp = mood === "thinking";
  const happy = mood === "happy" || mood === "wave";

  return (
    <svg
      viewBox="0 0 120 120"
      width={size}
      height={size}
      role="img"
      aria-label={title}
      className={`nm nm-${mood} ${className}`}
    >
      <defs>
        <radialGradient id={bodyId} cx="40%" cy="35%" r="70%">
          <stop offset="0%" stopColor="#5fd8f5" />
          <stop offset="55%" stopColor="#1fa4dc" />
          <stop offset="100%" stopColor="#0a6fae" />
        </radialGradient>
        <linearGradient id={boardId} x1="0" y1="0" x2="1" y2="0">
          <stop offset="0%" stopColor="#ffd27a" />
          <stop offset="100%" stopColor="#ff9f6b" />
        </linearGradient>
      </defs>

      {/* 考え中の泡 */}
      <g className="nm-bubbles" opacity={mood === "thinking" ? 1 : 0}>
        <circle cx="96" cy="30" r="3.5" fill="#bdeefc" />
        <circle cx="104" cy="18" r="5" fill="#bdeefc" />
        <circle cx="94" cy="8" r="3" fill="#bdeefc" />
      </g>

      {/* 影 */}
      <ellipse className="nm-shadow" cx="60" cy="112" rx="30" ry="4" fill="#0b2540" opacity="0.12" />

      <g className="nm-float">
        {/* サーフボード */}
        <g className="nm-board">
          <path d="M18 100 Q60 90 102 100 Q60 108 18 100 Z" fill={`url(#${boardId})`} />
          <path d="M26 100 Q60 94 94 100" fill="none" stroke="#fff" strokeOpacity="0.7" strokeWidth="1.5" />
        </g>

        <g className="nm-body">
          {/* 体（しずく形） */}
          <path
            d="M60 22 C82 22 92 44 92 64 C92 84 78 96 60 96 C42 96 28 84 28 64 C28 44 38 22 60 22 Z"
            fill={`url(#${bodyId})`}
          />
          {/* つや */}
          <ellipse cx="46" cy="44" rx="7" ry="11" fill="#fff" opacity="0.28" transform="rotate(-20 46 44)" />

          {/* 波しぶきの髪（くるっと巻いた波） */}
          <g className="nm-crest">
            <path
              d="M33 42 C31 24 46 11 64 11 C80 11 92 20 92 31 C92 39 85 43 79 40 C75 38 74 33 77 31 C80 29 83 32 81 35 C77 42 71 41 68 37 C64 43 56 43 53 38 C49 43 39 45 33 42 Z"
              fill="#ffffff"
              stroke="#9fdcf2"
              strokeWidth="2"
              strokeLinejoin="round"
            />
            <path d="M44 22 C50 16 58 14 64 15" fill="none" stroke="#cfeffa" strokeWidth="2.5" strokeLinecap="round" />
            <circle cx="96" cy="22" r="2.5" fill="#ffffff" stroke="#9fdcf2" strokeWidth="1" />
            <circle cx="100" cy="31" r="1.8" fill="#ffffff" stroke="#9fdcf2" strokeWidth="1" />
          </g>

          {/* ほっぺ */}
          <ellipse cx="41" cy="70" rx="6" ry="3.5" fill="#ff8fa3" opacity="0.55" />
          <ellipse cx="79" cy="70" rx="6" ry="3.5" fill="#ff8fa3" opacity="0.55" />

          {/* 目 */}
          {happy ? (
            <g fill="none" stroke="#0b2540" strokeWidth="3" strokeLinecap="round">
              <path d="M44 60 Q49 54 54 60" />
              <path d="M66 60 Q71 54 76 60" />
            </g>
          ) : (
            <g className="nm-eyes">
              <ellipse cx="49" cy={eyesUp ? 56 : 60} rx="5" ry="6.5" fill="#0b2540" />
              <ellipse cx="71" cy={eyesUp ? 56 : 60} rx="5" ry="6.5" fill="#0b2540" />
              <circle cx="51" cy={eyesUp ? 53 : 57} r="1.8" fill="#fff" />
              <circle cx="73" cy={eyesUp ? 53 : 57} r="1.8" fill="#fff" />
            </g>
          )}

          {/* 口 */}
          {mood === "thinking" ? (
            <path d="M55 76 Q60 74 65 76" fill="none" stroke="#0b2540" strokeWidth="2.5" strokeLinecap="round" />
          ) : (
            <g className="nm-mouth">
              <path d="M53 73 Q60 82 67 73 Z" fill="#0b2540" />
              <path d="M56 77 Q60 80 64 77" fill="#ff8fa3" />
            </g>
          )}

          {/* 腕 */}
          <path d="M30 70 Q22 74 22 82" fill="none" stroke="#1693cd" strokeWidth="5" strokeLinecap="round" />
          <g className="nm-arm">
            <path d="M90 68 Q100 60 102 50" fill="none" stroke="#1693cd" strokeWidth="5" strokeLinecap="round" />
          </g>
        </g>
      </g>
    </svg>
  );
}
