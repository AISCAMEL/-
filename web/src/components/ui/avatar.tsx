/* eslint-disable @next/next/no-img-element */

/** アバター表示。画像があれば写真、なければ頭文字の円。 */
export function Avatar({
  url,
  name,
  size = 56,
  light = false,
}: {
  url?: string | null;
  name: string;
  size?: number;
  light?: boolean;
}) {
  const dim = { width: size, height: size };
  if (url) {
    return (
      <img
        src={url}
        alt={name}
        style={dim}
        className="shrink-0 rounded-full object-cover"
      />
    );
  }
  return (
    <div
      style={dim}
      className={`flex shrink-0 items-center justify-center rounded-full font-semibold ${
        light ? "bg-white/15 text-foam" : "bg-ocean-gradient text-foam"
      }`}
    >
      {name.slice(0, 1)}
    </div>
  );
}
