/**
 * Free WhatsApp connection by QR code for the laundry platform.
 *
 * Links a normal WhatsApp number (like WhatsApp Web) using the unofficial Baileys
 * library, so cashiers can chat with customers inside the platform:
 *   - Laravel calls this service to send messages (POST /send) and to show the QR (GET /status);
 *   - customer messages, messages typed on the phone itself and delivery/read receipts are
 *     forwarded to Laravel (POST {APP_URL}/webhooks/whatsapp-web), signed with the shared secret,
 *     so the whole conversation stays in the platform.
 *
 * Unofficial: WhatsApp may block numbers that use it, especially for bulk or
 * automatic messages. The platform therefore only uses it for conversations;
 * automatic notifications go through the official Cloud API.
 *
 * Settings are read from the Laravel .env (one folder up), environment variables win:
 *   WHATSAPP_WEB_TOKEN    shared secret; when empty, the one Laravel generates in
 *                         storage/app/whatsapp-web.token (Admin → WhatsApp settings → QR code)
 *   WHATSAPP_WEB_PORT     default 3010 (listens on 127.0.0.1 only)
 *   WHATSAPP_WEB_WEBHOOK  default {APP_URL}/webhooks/whatsapp-web
 */
import http from "node:http";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import makeWASocket, {
    Browsers,
    DisconnectReason,
    fetchLatestBaileysVersion,
    generateMessageIDV2,
    isJidBroadcast,
    isJidGroup,
    isLidUser,
    jidDecode,
    normalizeMessageContent,
    useMultiFileAuthState,
} from "@whiskeysockets/baileys";
import QRCode from "qrcode";
import pino from "pino";

const here = path.dirname(fileURLToPath(import.meta.url));
const laravelEnv = path.join(here, "..", ".env");
if (fs.existsSync(laravelEnv)) {
    for (const [key, value] of Object.entries(parseEnv(fs.readFileSync(laravelEnv, "utf8")))) {
        if (process.env[key] === undefined) process.env[key] = value;
    }
}

const TOKEN_FILE = path.join(here, "..", "storage", "app", "whatsapp-web.token");
const readToken = () => process.env.WHATSAPP_WEB_TOKEN || (fs.existsSync(TOKEN_FILE) ? fs.readFileSync(TOKEN_FILE, "utf8").trim() : "");
let TOKEN = readToken();
const PORT = Number(process.env.WHATSAPP_WEB_PORT || 3010);
const WEBHOOK = process.env.WHATSAPP_WEB_WEBHOOK || `${(process.env.APP_URL || "http://127.0.0.1:8000").replace(/\/+$/, "")}/webhooks/whatsapp-web`;
const AUTH_DIR = path.join(here, "auth");
const SEND_GAP_MS = 1500; // pause between sends: bursts are what gets numbers blocked


const logger = pino({ level: process.env.WHATSAPP_WEB_LOG_LEVEL || "warn" });
const state = { status: "starting", qr: null, me: null, since: new Date().toISOString() };
let sock = null;
let restartTimer = null;
// Ids of the messages sent through the platform: any other message of ours was typed on the phone.
const sentByPlatform = new Set();
const remember = (id) => {
    sentByPlatform.add(id);
    if (sentByPlatform.size > 5000) sentByPlatform.delete(sentByPlatform.values().next().value);
};

async function start() {
    clearTimeout(restartTimer);
    state.status = "starting";
    state.qr = null;

    const { state: auth, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    let version;
    try {
        ({ version } = await fetchLatestBaileysVersion());
    } catch {
        version = undefined; // offline: use the version bundled with the library
    }

    sock = makeWASocket({
        version,
        auth,
        logger,
        browser: Browsers.ubuntu("WS Laundry"),
        markOnlineOnConnect: false,
        syncFullHistory: false,
    });

    sock.ev.on("creds.update", saveCreds);

    sock.ev.on("connection.update", async ({ connection, lastDisconnect, qr }) => {
        if (qr) {
            state.status = "qr";
            state.qr = await QRCode.toDataURL(qr, { margin: 1, width: 280 });
        }
        if (connection === "open") {
            state.status = "open";
            state.qr = null;
            state.me = jidDecode(sock.user?.id)?.user ?? null;
            state.since = new Date().toISOString();
            console.log(`Connected as +${state.me}`);
        }
        if (connection === "close") {
            const code = lastDisconnect?.error?.output?.statusCode;
            if (code === DisconnectReason.loggedOut) {
                // Unlinked from the phone (or via /logout): forget the session and show a new QR.
                fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                state.status = "logged_out";
                state.me = null;
                restartTimer = setTimeout(start, 1000);
            } else {
                state.status = "reconnecting";
                restartTimer = setTimeout(start, code === DisconnectReason.restartRequired ? 0 : 3000);
            }
        }
    });

    sock.ev.on("messages.upsert", ({ messages, type }) => {
        for (const m of messages) {
            if (m.key.fromMe) {
                // Answered on the phone (or WhatsApp Web): keep it in the platform's conversation too.
                if (sentByPlatform.has(m.key.id)) continue;
                const echo = toMessage(m);
                if (echo) forward({ echoes: [echo] });
            } else if (type === "notify") {
                const inbound = toMessage(m);
                if (inbound) forward({ contacts: [{ wa_id: inbound.from, profile: { name: m.pushName || null } }], messages: [inbound] });
            }
        }
    });

    // Delivery ticks for the messages the platform sent.
    const STATUS = { 0: "failed", 2: "sent", 3: "delivered", 4: "read", 5: "read" };
    sock.ev.on("messages.update", (updates) => {
        const statuses = updates
            .filter((u) => u.key?.fromMe && STATUS[u.update?.status] !== undefined)
            .map((u) => ({ id: u.key.id, status: STATUS[u.update.status] }));
        if (statuses.length) forward({ statuses });
    });
}

/**
 * A message of a one-to-one chat in the same shape as the Meta Cloud API webhook.
 * "from" is the customer's number, also for the messages we sent from the phone.
 */
function toMessage(m) {
    if (!m.message) return null;

    let jid = m.key.remoteJid;
    if (!jid || isJidGroup(jid) || isJidBroadcast(jid) || jid.endsWith("@newsletter")) return null;
    if (isLidUser(jid)) jid = m.key.senderPn || m.key.remoteJidAlt; // privacy id: use the real number when WhatsApp gives it
    const from = jidDecode(jid || undefined)?.user;
    if (!from || !/^\d{8,15}$/.test(from) || from === state.me) return null;

    const c = normalizeMessageContent(m.message) || {};
    const base = { from, id: m.key.id, timestamp: String(m.messageTimestamp || Math.floor(Date.now() / 1000)) };

    if (c.conversation || c.extendedTextMessage) {
        return { ...base, type: "text", text: { body: c.conversation || c.extendedTextMessage.text || "" } };
    }
    for (const [key, type] of [["imageMessage", "image"], ["videoMessage", "video"], ["documentMessage", "document"], ["audioMessage", "audio"], ["stickerMessage", "sticker"]]) {
        if (c[key]) return { ...base, type, [type]: { caption: c[key].caption || null, mime_type: c[key].mimetype || null } };
    }
    if (c.locationMessage) {
        return { ...base, type: "location", location: { name: c.locationMessage.name || "", address: c.locationMessage.address || "" } };
    }
    if (c.buttonsResponseMessage || c.listResponseMessage || c.templateButtonReplyMessage) {
        const text = c.buttonsResponseMessage?.selectedDisplayText || c.listResponseMessage?.title || c.templateButtonReplyMessage?.selectedDisplayText || "";
        return { ...base, type: "button", button: { text } };
    }
    return null; // reactions, protocol messages, polls…
}

/** POST to Laravel, signed like Meta does, with a few retries if Laravel is down. */
async function forward(value, attempt = 0) {
    const body = JSON.stringify({ object: "whatsapp_web", entry: [{ changes: [{ field: "messages", value }] }] });
    const signature = "sha256=" + crypto.createHmac("sha256", TOKEN).update(body).digest("hex");
    try {
        const res = await fetch(WEBHOOK, { method: "POST", headers: { "Content-Type": "application/json", "X-Signature-256": signature }, body });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
    } catch (e) {
        if (attempt < 4) setTimeout(() => forward(value, attempt + 1), [2000, 10000, 60000, 300000][attempt]);
        else console.error(`Could not deliver to ${WEBHOOK}: ${e.message}`);
    }
}

let sendChain = Promise.resolve();
function send(to, text) {
    const job = sendChain.then(async () => {
        const [result] = await sock.onWhatsApp(`${to}@s.whatsapp.net`);
        if (!result?.exists) throw Object.assign(new Error("Este número não tem WhatsApp."), { status: 422 });
        const messageId = generateMessageIDV2(sock.user?.id);
        remember(messageId);
        const sent = await sock.sendMessage(result.jid, { text }, { messageId });
        await new Promise((r) => setTimeout(r, SEND_GAP_MS));
        return sent.key.id;
    });
    sendChain = job.catch(() => {});
    return job;
}

const server = http.createServer(async (req, res) => {
    const reply = (status, data) => {
        res.writeHead(status, { "Content-Type": "application/json" });
        res.end(JSON.stringify(data));
    };

    if (TOKEN.length < 16) return reply(503, { error: "no secret yet" });
    const auth = req.headers.authorization || "";
    const given = Buffer.from(auth.startsWith("Bearer ") ? auth.slice(7) : "");
    const expected = Buffer.from(TOKEN);
    if (given.length !== expected.length || !crypto.timingSafeEqual(given, expected)) return reply(401, { error: "unauthorized" });

    try {
        if (req.method === "GET" && req.url === "/status") return reply(200, state);

        if (req.method === "POST" && req.url === "/send") {
            const { to, text } = JSON.parse(await readBody(req));
            if (!/^\d{8,15}$/.test(String(to)) || typeof text !== "string" || text.trim() === "" || text.length > 4096) {
                return reply(422, { error: "Número ou texto inválido." });
            }
            if (state.status !== "open") return reply(503, { error: "WhatsApp não está ligado: leia o código QR nas definições." });
            return reply(200, { id: await send(String(to), text) });
        }

        if (req.method === "POST" && req.url === "/logout") {
            if (sock && state.status === "open") await sock.logout().catch(() => {});
            else {
                fs.rmSync(AUTH_DIR, { recursive: true, force: true });
                start();
            }
            return reply(200, { ok: true });
        }

        reply(404, { error: "not found" });
    } catch (e) {
        reply(e.status || 500, { error: e.message || "error" });
    }
});

function readBody(req) {
    return new Promise((resolve, reject) => {
        let data = "";
        req.on("data", (chunk) => {
            data += chunk;
            if (data.length > 65536) req.destroy(new Error("too large"));
        });
        req.on("end", () => resolve(data || "{}"));
        req.on("error", reject);
    });
}

function parseEnv(text) {
    const out = {};
    for (const line of text.split(/\r?\n/)) {
        const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (m) out[m[1]] = m[2].replace(/^(['"])(.*)\1$/, "$2");
    }
    return out;
}

server.listen(PORT, "127.0.0.1", () => {
    console.log(`WhatsApp QR service on http://127.0.0.1:${PORT} -> ${WEBHOOK}`);
    writePid();
});
server.on("error", (e) => {
    console.error(e.code === "EADDRINUSE" ? `Port ${PORT} is already in use (is the service already running?).` : e.message);
    process.exit(1);
});

// The platform starts / stops the service (Admin → WhatsApp settings): it needs our process id.
const PID_FILE = path.join(here, "..", "storage", "app", "whatsapp-web.pid");
let ownsPid = false;
function writePid() {
    try {
        fs.writeFileSync(PID_FILE, String(process.pid));
        fs.rmSync(path.join(here, "..", "storage", "app", "whatsapp-web.starting"), { force: true });
        ownsPid = true;
    } catch {
        /* storage not writable: stopping from the platform will not work, the rest does */
    }
}
const cleanUp = () => {
    if (!ownsPid) return;
    try {
        if (fs.readFileSync(PID_FILE, "utf8") === String(process.pid)) fs.rmSync(PID_FILE, { force: true });
    } catch {
        /* already gone */
    }
};
process.on("exit", cleanUp);
for (const signal of ["SIGINT", "SIGTERM"]) process.on(signal, () => process.exit(0));

// The secret appears once "QR code" is chosen in the platform: wait for it instead of quitting.
let waitingLogged = false;
(function waitForToken() {
    TOKEN = readToken();
    if (TOKEN.length >= 16) return start();
    if (!waitingLogged) console.log("Waiting for the shared secret: choose \"QR code\" in Admin → WhatsApp settings (or set WHATSAPP_WEB_TOKEN).");
    waitingLogged = true;
    setTimeout(waitForToken, 5000);
})();
