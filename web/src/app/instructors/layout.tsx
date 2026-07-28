import { CommunityHeader } from "@/components/community/community-header";

export default function InstructorsLayout({
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
