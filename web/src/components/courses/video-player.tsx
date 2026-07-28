/* eslint-disable @next/next/no-img-element */

/**
 * 動画レッスンのプレイヤー枠。
 * 実際の動画URLがあれば埋め込み、無ければカバー＋再生アイコンのプレースホルダー。
 */
export function VideoPlayer({
  videoUrl,
  poster,
  title,
}: {
  videoUrl?: string | null;
  poster?: string | null;
  title: string;
}) {
  if (videoUrl) {
    return (
      <div className="aspect-video w-full overflow-hidden rounded-2xl bg-black">
        <iframe
          src={videoUrl}
          title={title}
          allow="accelerated-media; autoplay; encrypted-media; picture-in-picture"
          allowFullScreen
          className="h-full w-full"
        />
      </div>
    );
  }
  return (
    <div className="relative aspect-video w-full overflow-hidden rounded-2xl bg-navy">
      {poster ? (
        <img src={poster} alt={title} className="h-full w-full object-cover opacity-70" />
      ) : (
        <div className="bg-ocean-gradient h-full w-full" />
      )}
      <div className="absolute inset-0 flex flex-col items-center justify-center text-foam">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-2xl backdrop-blur">
          ▶
        </div>
        <p className="mt-3 text-sm">動画レッスン</p>
      </div>
    </div>
  );
}
