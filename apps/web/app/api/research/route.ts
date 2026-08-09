import { NextResponse } from "next/server";

const HUB = process.env.HUB_API_URL ?? "http://127.0.0.1:3001";

export async function POST(req: Request) {
  try {
    const body = await req.json();
    const res = await fetch(`${HUB}/research`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    return NextResponse.json(data, { status: res.status });
  } catch (e) {
    return NextResponse.json(
      { error: `Hub API へ接続できません (${HUB})` },
      { status: 502 },
    );
  }
}
