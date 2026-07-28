import { CommunityHeader } from "@/components/community/community-header";

export default function ArticlesLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen bg-foam">
      <CommunityHeader />
      {children}
    </div>
  );
}
