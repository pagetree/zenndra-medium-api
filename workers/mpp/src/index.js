import { env } from "cloudflare:workers";
import { Hono } from "hono";
import { Mppx, tempo } from "mppx/hono";

const app = new Hono();

const mppx = Mppx.create({
  methods: [tempo.charge({ testnet: env.TEMPO_TESTNET !== "false" })],
  secretKey: env.MPP_SECRET_KEY,
});

const USDC = "0x20c0000000000000000000000000000000000000";

const chargeWrite = mppx.charge({
  amount: env.PAY_AMOUNT || "0.01",
  currency: USDC,
  description: "One post on Zenndra",
  recipient: env.PAY_TO,
});

function originBase(c) {
  const configured = String(env.ORIGIN_URL || "").replace(/\/$/, "");
  const host = new URL(c.req.url).hostname;
  if (host === "zenndra.com") {
    return "https://www.zenndra.com";
  }
  return configured;
}

async function proxy(c) {
  const origin = originBase(c);
  if (!origin || origin.includes("YOUR_PUBLIC_ORIGIN")) {
    return c.json({ error: "ORIGIN_URL is not set" }, 503);
  }

  const incoming = new URL(c.req.url);
  const target = origin + incoming.pathname + incoming.search;
  const headers = new Headers(c.req.raw.headers);
  headers.delete("host");

  const init = {
    method: c.req.method,
    headers,
    redirect: "manual",
  };

  if (c.req.method !== "GET" && c.req.method !== "HEAD") {
    init.body = await c.req.arrayBuffer();
  }

  const upstream = await fetch(target, init);
  const out = new Headers(upstream.headers);
  for (const [key, value] of c.res.headers.entries()) {
    if (key.toLowerCase() === "payment-receipt") {
      out.set(key, value);
    }
  }
  return new Response(upstream.body, {
    status: upstream.status,
    statusText: upstream.statusText,
    headers: out,
  });
}

app.post("/api/posts", chargeWrite, (c) => proxy(c));
app.post("/api/posts/", chargeWrite, (c) => proxy(c));
app.post("/@Zenndra/api/posts", chargeWrite, (c) => proxy(c));
app.post("/@Zenndra/api/posts/", chargeWrite, (c) => proxy(c));

app.all("*", (c) => proxy(c));

export default app;
