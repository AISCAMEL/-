"use client";

/** 破壊的操作（削除・停止など）の確認ダイアログ付き送信ボタン */
export function ConfirmSubmit({
  children,
  className,
  message,
}: {
  children: React.ReactNode;
  className?: string;
  message: string;
}) {
  return (
    <button
      type="submit"
      className={className}
      onClick={(e) => {
        if (!window.confirm(message)) e.preventDefault();
      }}
    >
      {children}
    </button>
  );
}
