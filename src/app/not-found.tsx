import { Button } from "@/components/ui/Button";
import { Container } from "@/components/ui/Container";

export default function NotFound() {
  return (
    <Container className="flex min-h-[60vh] flex-col items-center justify-center py-24 text-center">
      <p className="text-6xl font-bold text-brand-600">404</p>
      <h1 className="mt-4 text-2xl font-bold text-ink-900">ページが見つかりませんでした</h1>
      <p className="mt-3 max-w-md text-sm text-ink-600">
        お探しのページは移動または削除された可能性があります。URLをご確認ください。
      </p>
      <div className="mt-8 flex gap-3">
        <Button href="/">トップに戻る</Button>
        <Button href="/contact" variant="secondary">
          お問い合わせ
        </Button>
      </div>
    </Container>
  );
}
