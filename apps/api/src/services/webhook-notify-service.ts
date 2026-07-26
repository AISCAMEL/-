const SLACK_WEBHOOK_URL = process.env.SLACK_WEBHOOK_URL;
const LINE_NOTIFY_TOKEN = process.env.LINE_NOTIFY_TOKEN;

interface NotifyPayload {
  title: string;
  message: string;
  severity: "info" | "warning" | "critical";
}

const SEVERITY_EMOJI: Record<string, string> = {
  info: "😻",
  warning: "😾",
  critical: "🙀",
};

export async function notifyExternal(payload: NotifyPayload): Promise<void> {
  const emoji = SEVERITY_EMOJI[payload.severity] ?? "🐱";
  const text = `${emoji} [${payload.title}] ${payload.message}`;

  const promises: Promise<void>[] = [];
  if (SLACK_WEBHOOK_URL) promises.push(sendSlack(text));
  if (LINE_NOTIFY_TOKEN) promises.push(sendLine(text));
  await Promise.allSettled(promises);
}

async function sendSlack(text: string): Promise<void> {
  if (!SLACK_WEBHOOK_URL) return;
  await fetch(SLACK_WEBHOOK_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ text }),
  });
}

async function sendLine(text: string): Promise<void> {
  if (!LINE_NOTIFY_TOKEN) return;
  await fetch("https://notify-api.line.me/api/notify", {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${LINE_NOTIFY_TOKEN}`,
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({ message: text }),
  });
}

export function isWebhookConfigured(): { slack: boolean; line: boolean } {
  return { slack: !!SLACK_WEBHOOK_URL, line: !!LINE_NOTIFY_TOKEN };
}
