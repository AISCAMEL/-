import { CommunityHeader } from "@/components/community/community-header";
import { SiteFooter } from "@/components/site-footer";

export default function SpotsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen flex-col bg-foam">
      <CommunityHeader />
      <div className="flex-1">{children}</div>
      <SiteFooter />
    </div>
  );
}
