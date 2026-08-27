const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const fs = require('fs');
const https = require('https');
const axios = require('axios');

const app = express();

// CORS yapılandırması
const options = {
    key: fs.readFileSync('/etc/ssl/private/89-252-191-46.cprapid.com.key'),
    cert: fs.readFileSync('/etc/ssl/certs/89-252-191-46.cprapid.com.crt'),
    ca: fs.readFileSync('/etc/ssl/certs/ca-bundle.crt'),
};

// HTTPS sunucusu oluştur
const server = https.createServer(options, app);

// Socket.IO
const io = socketIo(server, {
    path: '/socket.io',
    cors: {
        origin: ["https://bilbakalim.online"],
        methods: ["GET", "POST"],
        credentials: true
    },
    transports: ['websocket', 'polling']
});

// Middleware
app.use(cors({
    origin: ["https://bilbakalim.online"],
    methods: ["GET", "POST"],
    credentials: true
}));
app.use(express.json());

// Bağlı kullanıcıları takip et
const connectedUsers = new Map(); // socketId -> { userId, userName }
const userRooms = new Map(); // socketId -> roomName
const userSocketMap = new Map(); // userId -> socketId mapping

// Düello eşleşme kuyruğu: multiplier -> [{ userId, socketId, multiplier, joinedAt }]
const duelMatchQueues = new Map();
const duelMatchLocks = new Map(); // multiplier -> Promise chain (serial match)
const duelMatchCancelled = new Set(); // ready iptal / disconnect (in-flight match öncesi)
const botFallbackTimers = new Map(); // userId -> timeoutId
const userDuelMap = new Map(); // userId -> active duelId
const duelDisconnectTimers = new Map(); // userId -> timeoutId (AFK after disconnect)
let botConfigCache = { at: 0, data: null };
const LARAVEL_API_URL = (process.env.LARAVEL_API_URL || 'https://bil-bakalim.com').replace(/\/$/, '');
const SOCKET_INTERNAL_SECRET = process.env.SOCKET_INTERNAL_SECRET || 'bilbakalim-socket-secret';
const VALID_MULTIPLIERS = new Set(['x1', 'x2', 'x4', 'x8']);
const DUEL_AFK_MS = 45000; // socket kopunca / cevap timeout ile aynı: 45 sn
const DUEL_QUEUE_MAX_MS = 300000; // kuyrukta max 5 dk bekleme
const DUEL_SOCKET_DEBUG = process.env.DUEL_SOCKET_DEBUG === '1';

function duelDebugLog(...args) {
    if (DUEL_SOCKET_DEBUG) {
        console.log(...args);
    }
}

function touchUserPresence(userId) {
    const uid = parseInt(userId, 10);
    if (!uid) return;
    axios.post(
        `${LARAVEL_API_URL}/api/users/socket-presence`,
        { user_id: uid },
        {
            headers: { 'X-Socket-Secret': SOCKET_INTERNAL_SECRET },
            timeout: 3000,
        }
    ).catch(() => {});
}

function getMatchQueue(multiplier) {
    if (!duelMatchQueues.has(multiplier)) {
        duelMatchQueues.set(multiplier, []);
    }
    return duelMatchQueues.get(multiplier);
}

function removeUserFromMatchQueues(userId) {
    const uid = parseInt(userId, 10);
    clearBotFallback(uid);
    // Diziyi yerinde mutate et (yeni dizi set etme).
    // Aksi halde eşleşme await sırasında cancel/ready eski referansı bozar.
    for (const [multiplier, queue] of duelMatchQueues.entries()) {
        let removed = false;
        for (let i = queue.length - 1; i >= 0; i--) {
            if (queue[i].userId === uid) {
                queue.splice(i, 1);
                removed = true;
            }
        }
        if (removed) {
            console.log(`🗑️ Kullanıcı ${uid} eşleşme kuyruğundan çıkarıldı (${multiplier})`);
        }
    }
}

function refreshUserSocketInMatchQueues(userId, socketId) {
    const uid = parseInt(userId, 10);
    for (const queue of duelMatchQueues.values()) {
        for (const entry of queue) {
            if (entry.userId === uid) {
                entry.socketId = socketId;
            }
        }
    }
}

function markMatchCancelled(userId) {
    const uid = parseInt(userId, 10);
    if (uid) {
        duelMatchCancelled.add(uid);
    }
}

function clearMatchCancelled(userId) {
    const uid = parseInt(userId, 10);
    if (uid) {
        duelMatchCancelled.delete(uid);
    }
}

function consumeMatchCancelled(userId) {
    const uid = parseInt(userId, 10);
    if (duelMatchCancelled.has(uid)) {
        duelMatchCancelled.delete(uid);
        return true;
    }
    return false;
}

function requeueIfStillReady(entry, queue) {
    const liveSocketId = userSocketMap.get(entry.userId);
    if (!liveSocketId || duelMatchCancelled.has(entry.userId)) {
        return;
    }
    entry.socketId = liveSocketId;
    queue.unshift(entry);
}

async function createDuelMatchOnLaravel(challengerId, opponentId, multiplier) {
    const url = `${LARAVEL_API_URL}/api/duel/socket-match`;
    const response = await axios.post(url, {
        challenger_id: challengerId,
        opponent_id: opponentId,
        multiplier,
        secret: SOCKET_INTERNAL_SECRET,
    }, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
        },
        timeout: 15000,
        validateStatus: () => true,
    });

    const data = response.data || {};
    if (response.status >= 400 || !data.success) {
        const message = data.message || `HTTP ${response.status}`;
        throw new Error(message);
    }
    return data;
}

async function fetchBotMatchmakingConfig() {
    const now = Date.now();
    if (botConfigCache.data && (now - botConfigCache.at) < 2000) {
        return botConfigCache.data;
    }

    try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/duel/bot-matchmaking-config`, {
            headers: {
                'Accept': 'application/json',
                'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
            },
            params: { secret: SOCKET_INTERNAL_SECRET },
            timeout: 5000,
            validateStatus: () => true,
        });
        const data = response.data || {};
        if (response.status >= 400 || !data.success) {
            botConfigCache = { at: now, data: { active: false, pool_active: false, wait_seconds: 3, bot_user_id: 0 } };
            return botConfigCache.data;
        }
        botConfigCache = {
            at: now,
            data: {
                active: !!(data.active || (data.pool_active && data.idle_available)),
                pool_active: !!data.pool_active,
                idle_available: !!data.idle_available,
                wait_seconds: Math.max(1, parseInt(data.wait_seconds || 3, 10)),
                bot_user_id: parseInt(data.bot_user_id || 0, 10),
                difficulty: data.difficulty || null,
            },
        };
        return botConfigCache.data;
    } catch (e) {
        console.error('bot-matchmaking-config hata:', e.message);
        return { active: false, pool_active: false, wait_seconds: 3, bot_user_id: 0 };
    }
}

async function pickBotForChallenger(challengerId) {
    try {
        const response = await axios.post(`${LARAVEL_API_URL}/api/duel/bot-matchmaking-pick`, {
            challenger_id: challengerId,
            secret: SOCKET_INTERNAL_SECRET,
        }, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
            },
            timeout: 8000,
            validateStatus: () => true,
        });
        const data = response.data || {};
        if (response.status >= 400 || !data.success || !data.bot_user_id) {
            return {
                error: true,
                cooldown: !!data.cooldown,
                wait_bump: parseInt(data.wait_bump || 0, 10) || 0,
                message: data.message || null,
            };
        }
        return {
            bot_user_id: parseInt(data.bot_user_id, 10),
            difficulty: data.difficulty || null,
            bot_name: data.bot_name || null,
            wait_bump: parseInt(data.wait_bump || 0, 10) || 0,
            soft_cap: !!data.soft_cap,
            streak: parseInt(data.streak || 0, 10) || 0,
            skill: data.skill || null,
        };
    } catch (e) {
        console.error('bot-matchmaking-pick hata:', e.message);
        return null;
    }
}

function clearBotFallback(userId) {
    const uid = parseInt(userId, 10);
    const timer = botFallbackTimers.get(uid);
    if (timer) {
        clearTimeout(timer);
        botFallbackTimers.delete(uid);
    }
}

/**
 * Kuyrukta yalnız kalanları tarar.
 * Bot sonradan aktif edilse bile: joinedAt + wait_seconds dolmuşsa eşleştirir.
 * (Pasifken girip beklerken aktif etmek artık çalışır — yeniden ready gerekmez.)
 */
let botFallbackScanRunning = false;
function startBotFallbackScanner() {
    if (global.__duelBotFallbackScannerStarted) {
        return;
    }
    global.__duelBotFallbackScannerStarted = true;
    setInterval(() => {
        scanQueuesForBotFallback().catch((err) => {
            console.error('Bot fallback scan hata:', err.message);
        });
    }, 2000);
    console.log('🤖 Bot fallback scanner aktif (2sn)');
}

function evictStaleQueueEntries() {
    const now = Date.now();
    for (const [multiplier, queue] of duelMatchQueues.entries()) {
        if (!queue || queue.length === 0) continue;
        for (let i = queue.length - 1; i >= 0; i--) {
            const entry = queue[i];
            if (!entry || !entry.joinedAt) continue;
            if ((now - entry.joinedAt) < DUEL_QUEUE_MAX_MS) continue;
            queue.splice(i, 1);
            const payload = {
                success: false,
                expired: true,
                userId: entry.userId,
                multiplier,
                message: 'Eşleşme kuyruğu zaman aşımına uğradı. Tekrar deneyin.',
                timestamp: new Date().toISOString(),
            };
            emitToUser(entry.userId, 'duel-ready-expired', payload);
            emitToUser(entry.userId, 'duel_ready_expired', payload);
            console.log(`⏱️ Kuyruk timeout: user ${entry.userId} ${multiplier}`);
        }
    }
}

async function prepareRequeueFromLaravel(userId) {
    try {
        const res = await axios.post(
            `${LARAVEL_API_URL}/api/duel/socket-requeue-prep`,
            { user_id: userId },
            {
                headers: {
                    'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
                    Accept: 'application/json',
                },
                timeout: 8000,
            }
        );
        return res.data || { success: true };
    } catch (err) {
        duelDebugLog(`⚠️ requeue-prep user ${userId}:`, err.response?.data?.message || err.message);
        return { success: false, error: true };
    }
}

async function scanQueuesForBotFallback() {
    if (botFallbackScanRunning) {
        return;
    }
    botFallbackScanRunning = true;
    try {
        evictStaleQueueEntries();
        // 2s TTL (fetchBotMatchmakingConfig) — her tick bust etme; Laravel yükünü düşür
        const cfg = await fetchBotMatchmakingConfig();
        // Havuzda aktif bot yoksa hiç deneme; idle yoksa da (hepsi meşgul) bekle
        if (!cfg.pool_active && !cfg.active) {
            return;
        }
        if (!cfg.idle_available && !cfg.bot_user_id) {
            return;
        }
        const waitMs = Math.max(1, cfg.wait_seconds || 3) * 1000;

        for (const [multiplier, queue] of duelMatchQueues.entries()) {
            if (!queue || queue.length !== 1) {
                continue;
            }
            const entry = queue[0];
            if (!entry) {
                continue;
            }
            if (duelMatchCancelled.has(entry.userId)) {
                continue;
            }
            if (entry._botMatchInFlight) {
                continue;
            }
            const waited = entry.joinedAt ? (Date.now() - entry.joinedAt) : 0;
            if (waited < waitMs) {
                continue;
            }

            entry._botMatchInFlight = true;
            try {
                console.log(`🤖 Scanner: user=${entry.userId} ${Math.round(waited / 1000)}sn bekledi → bot pick`);
                await tryBotFallbackMatch(entry.userId, multiplier);
            } finally {
                const still = getMatchQueue(multiplier).find((e) => e.userId === entry.userId);
                if (still) {
                    still._botMatchInFlight = false;
                }
            }
        }
    } finally {
        botFallbackScanRunning = false;
    }
}

/** @deprecated scanner kullanılıyor; geriye uyumluluk için bırakıldı */
function scheduleBotFallback(userId, multiplier) {
    // No-op: periyodik scanner joinedAt + aktif ayarıyla halleder
    clearBotFallback(userId);
}

async function tryBotFallbackMatch(userId, multiplier) {
    // İnsan–insan ile aynı kilit: pick/create sırasında 2. insan gelirse yarış olmasın
    const prev = duelMatchLocks.get(multiplier) || Promise.resolve();
    const run = prev
        .catch(() => {})
        .then(() => tryBotFallbackMatchLocked(userId, multiplier));
    duelMatchLocks.set(multiplier, run);
    return run;
}

async function tryBotFallbackMatchLocked(userId, multiplier) {
    const uid = parseInt(userId, 10);
    let queue = getMatchQueue(multiplier);
    let idx = queue.findIndex((e) => e.userId === uid);
    if (idx < 0) {
        return;
    }
    // A) Kuyrukta 2. insan varsa bot yok
    if (queue.length >= 2) {
        console.log(`🤖 Bot iptal: kuyrukta ${queue.length} insan (${multiplier})`);
        return;
    }

    if (duelMatchCancelled.has(uid)) {
        return;
    }

    const entryJoinedAt = queue[idx]?.joinedAt || 0;

    const picked = await pickBotForChallenger(uid);
    if (!picked || picked.error || !picked.bot_user_id || picked.bot_user_id === uid) {
        const msg = picked?.message || 'Uygun bot yok';
        console.log(`🤖 Pick: uygun bot yok (user=${uid})`, msg);
        // Coin yoksa sonsuz kuyrukta bekletme — client'a hata bas
        if (/coin|yeterli/i.test(String(msg))) {
            removeUserFromMatchQueues(uid);
            const err = {
                success: false,
                error_code: 'insufficient_coins',
                message: 'Düelloya girmek için yeterli jetonunuz yok.',
                userId: uid,
                multiplier,
            };
            emitToUser(uid, 'duel-match-error', err);
            emitToUser(uid, 'duel_match_error', err);
            console.log(`🚫 Kuyruktan çıkarıldı (coin yok): user=${uid}`);
        }
        return;
    }

    // Soft cap: peş peşe bot → ekstra kuyruk beklemesi
    const bump = parseInt(picked.wait_bump || 0, 10) || 0;
    if (bump > 0) {
        const cfg = await fetchBotMatchmakingConfig();
        const needMs = (Math.max(1, cfg.wait_seconds || 3) + bump) * 1000;
        const waited = entryJoinedAt ? (Date.now() - entryJoinedAt) : 0;
        if (waited < needMs) {
            console.log(
                `🤖 Soft-cap wait: user=${uid} streak soft · ${Math.round(waited / 1000)}s < ${Math.round(needMs / 1000)}s`
            );
            return;
        }
    }

    // Pick sonrası yeniden kontrol (ince ayar: create anında 2. insan gelmiş olabilir)
    queue = getMatchQueue(multiplier);
    idx = queue.findIndex((e) => e.userId === uid);
    if (idx < 0) {
        return;
    }
    if (queue.length >= 2) {
        console.log(`🤖 Bot create iptal: pick sonrası kuyrukta ${queue.length} insan`);
        return;
    }
    if (duelMatchCancelled.has(uid)) {
        return;
    }

    const human = queue.splice(idx, 1)[0];
    if (!human) {
        return;
    }

    // Create öncesi son cancel kontrolü
    if (duelMatchCancelled.has(uid)) {
        console.log(`🤖 Bot create iptal: user ${uid} cancel`);
        return;
    }

    console.log(`🤖 Bot eşleşmesi: ${uid} vs bot#${picked.bot_user_id} ${picked.bot_name || ''} [${picked.difficulty}] (${multiplier})`);

    try {
        // Create öncesi son bir kez: başka çarpan değil, aynı kuyruk yine 2+ olduysa...
        // (splice sonrası bu kullanıcı çıktı; kalanlar insan-insan için kalsın)
        const result = await createDuelMatchOnLaravel(uid, picked.bot_user_id, multiplier);
        clearMatchCancelled(uid);

        const duel = result.duel || {};
        const payload = {
            duelId: duel.duelId,
            challengerId: duel.challengerId,
            opponentId: duel.opponentId,
            multiplier: duel.multiplier || multiplier,
            status: 'matched',
            question: result.question || null,
            challenger: result.challenger || null,
            opponent: result.opponent || null,
            bot_match: true,
            bot_difficulty: picked.difficulty || null,
            timestamp: new Date().toISOString(),
        };

        if (payload.duelId) {
            joinUserSocketToDuelRoom(uid, payload.duelId);
        }

        emitToUser(uid, 'duel-matched', payload);
        if (payload.duelId) {
            io.to(`duel_${payload.duelId}`).emit('duel-matched', payload);
            io.to(`duel_${payload.duelId}`).emit('duel_matched', payload);
        }
        duelDebugLog('✅ Bot duel-matched gönderildi:', JSON.stringify(payload));
    } catch (error) {
        const msg = error.message || 'Bot eşleşmesi başarısız';
        console.error('❌ Bot socket-match hatası:', msg);
        emitToUser(uid, 'duel-match-error', {
            success: false,
            message: msg,
            multiplier,
            timestamp: new Date().toISOString(),
        });

        // Coin yok / kullanıcı yok gibi kalıcı hatalarda kuyruğa geri koyma → sonsuz PICK döngüsü olmasın.
        // Geçici (timeout / 5xx) hatalarda tekrar denenebilir.
        const permanent = /coin|yeterli|bulunamadı|Unauthorized|aktif soru/i.test(msg);
        if (!permanent) {
            requeueIfStillReady(human, queue);
        } else {
            console.log(`🛑 Bot eşleşme iptal (kalıcı): user=${uid} · ${msg}`);
        }
    }
}

function emitToUser(userId, eventName, payload) {
    const uid = parseInt(userId, 10);
    io.to(`user_${uid}`).emit(eventName, payload);
    // snake_case alias
    const snake = eventName.replace(/-/g, '_');
    if (snake !== eventName) {
        io.to(`user_${uid}`).emit(snake, payload);
    }
}

async function tryMatchDuelQueue(multiplier) {
    const prev = duelMatchLocks.get(multiplier) || Promise.resolve();
    const run = prev
        .catch(() => {})
        .then(() => tryMatchDuelQueueLocked(multiplier));
    duelMatchLocks.set(multiplier, run);
    return run;
}

function joinUserSocketToDuelRoom(userId, duelId) {
    const uid = parseInt(userId, 10);
    const did = parseInt(duelId, 10);
    const socketId = userSocketMap.get(uid);
    if (!socketId || !did) {
        return;
    }
    const sock = io.sockets.sockets.get(socketId);
    if (sock) {
        sock.join(`duel_${did}`);
    }
    trackUserDuel(uid, did);
}

function trackUserDuel(userId, duelId) {
    const uid = parseInt(userId, 10);
    const did = parseInt(duelId, 10);
    if (!uid || !did) {
        return;
    }
    userDuelMap.set(uid, did);
    clearDuelDisconnectTimer(uid);
}

function clearUserDuel(userId, duelId = null) {
    const uid = parseInt(userId, 10);
    if (!uid) {
        return;
    }
    if (duelId != null) {
        const did = parseInt(duelId, 10);
        if (userDuelMap.get(uid) === did) {
            userDuelMap.delete(uid);
        }
    } else {
        userDuelMap.delete(uid);
    }
    clearDuelDisconnectTimer(uid);
}

function clearDuelDisconnectTimer(userId) {
    const uid = parseInt(userId, 10);
    const tid = duelDisconnectTimers.get(uid);
    if (tid) {
        clearTimeout(tid);
        duelDisconnectTimers.delete(uid);
    }
}

function scheduleDuelAfkTimeout(userId) {
    const uid = parseInt(userId, 10);
    const duelId = userDuelMap.get(uid);
    if (!uid || !duelId) {
        return;
    }
    clearDuelDisconnectTimer(uid);
    const tid = setTimeout(async () => {
        duelDisconnectTimers.delete(uid);
        // Yeniden bağlandıysa bırak
        if (userSocketMap.has(uid)) {
            console.log(`ℹ️ AFK iptal: user ${uid} tekrar online (duel ${duelId})`);
            return;
        }
        console.log(`⏱️ Düello AFK timeout: user ${uid} duel ${duelId} (${DUEL_AFK_MS}ms)`);
        try {
            const res = await axios.post(
                `${LARAVEL_API_URL}/api/duel/socket-afk-timeout`,
                { duel_id: duelId, user_id: uid, reason: 'disconnect' },
                {
                    headers: {
                        'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
                        Accept: 'application/json',
                    },
                    timeout: 15000,
                }
            );
            duelDebugLog(`✅ AFK forfeit yanıtı user ${uid}:`, JSON.stringify(res.data));
        } catch (err) {
            const msg = err.response?.data || err.message;
            console.error(`❌ AFK forfeit hata user ${uid}:`, msg);
        }
    }, DUEL_AFK_MS);
    duelDisconnectTimers.set(uid, tid);
    console.log(`⏳ AFK timer başlatıldı: user ${uid} duel ${duelId} (${DUEL_AFK_MS}ms)`);
}

/** Socket restart sonrası DB'den aktif düello map'i doldur */
async function hydrateUserDuelMapFromLaravel() {
    try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/duel/socket-active-map`, {
            headers: {
                Accept: 'application/json',
                'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
            },
            params: { secret: SOCKET_INTERNAL_SECRET },
            timeout: 8000,
            validateStatus: () => true,
        });
        const data = response.data || {};
        if (response.status >= 400 || !data.success) {
            console.warn('⚠️ Active duel hydrate başarısız:', data.message || response.status);
            return;
        }
        let n = 0;
        let afkArmed = 0;
        for (const d of data.duels || []) {
            const did = parseInt(d.id, 10);
            const c = parseInt(d.challenger_id, 10);
            const o = parseInt(d.opponent_id, 10);
            if (!did) continue;
            if (c) {
                userDuelMap.set(c, did);
                n++;
                if (!userSocketMap.has(c)) {
                    scheduleDuelAfkTimeout(c);
                    afkArmed++;
                }
            }
            if (o) {
                userDuelMap.set(o, did);
                n++;
                if (!userSocketMap.has(o)) {
                    scheduleDuelAfkTimeout(o);
                    afkArmed++;
                }
            }
        }
        console.log(`🗺️ userDuelMap hydrate: ${data.duels?.length || 0} maç, ${n} kullanıcı, ${afkArmed} offline AFK timer`);
    } catch (err) {
        console.warn('⚠️ Active duel hydrate hata:', err.message);
    }
}

async function fetchUserActiveDuel(userId) {
    const uid = parseInt(userId, 10);
    if (!uid) return null;
    try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/duel/socket-user-active`, {
            headers: {
                Accept: 'application/json',
                'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
            },
            params: { secret: SOCKET_INTERNAL_SECRET, user_id: uid },
            timeout: 5000,
            validateStatus: () => true,
        });
        const data = response.data || {};
        if (response.status >= 400 || !data.success || !data.duel) {
            return null;
        }
        return data.duel;
    } catch (err) {
        return null;
    }
}

/** Bitmiş düello sonucu (token/leave kaçınca UI kurtarma) */
async function fetchDuelSnapshot(duelId, userId) {
    const id = parseInt(duelId, 10);
    if (!id) return null;
    try {
        const response = await axios.get(`${LARAVEL_API_URL}/api/duel/socket-duel-snapshot`, {
            headers: {
                Accept: 'application/json',
                'X-Socket-Secret': SOCKET_INTERNAL_SECRET,
            },
            params: {
                secret: SOCKET_INTERNAL_SECRET,
                duel_id: id,
                user_id: userId ? parseInt(userId, 10) : undefined,
            },
            timeout: 5000,
            validateStatus: () => true,
        });
        const data = response.data || {};
        if (response.status >= 400 || !data.success) {
            return null;
        }
        return data;
    } catch (err) {
        return null;
    }
}

function emitDuelFinishedToSocket(socket, snapshot, userId = null) {
    if (!socket || !snapshot || !snapshot.finished || !snapshot.result) return;
    const result = snapshot.result || {};
    const data = {
        ...result,
        duel_id: snapshot.duel_id || result.duel_id,
        duelId: snapshot.duel_id || result.duel_id,
        already_finished: true,
        timestamp: new Date().toISOString(),
    };
    socket.emit('duel-finished', data);
    socket.emit('duel_finished', data);
    const uid = parseInt(userId || 0, 10);
    if (uid) {
        clearUserDuel(uid, data.duel_id);
    }
    console.log(`📣 duel-finished recovery → socket ${socket.id} duel #${data.duel_id}`);
}

/** Reconnect: odaya geri al + client'a kısa resume; bitmişse finished yayınla */
async function resumeUserDuelRoom(socket, userId) {
    const uid = parseInt(userId, 10);
    if (!uid || !socket) return;

    let duelId = userDuelMap.get(uid) || 0;
    let meta = null;
    if (!duelId) {
        meta = await fetchUserActiveDuel(uid);
        duelId = meta ? parseInt(meta.id, 10) : 0;
        if (duelId) {
            trackUserDuel(uid, duelId);
        }
    }
    if (!duelId) {
        return;
    }

    const snap = await fetchDuelSnapshot(duelId, uid);
    if (snap && snap.finished) {
        socket.join(`user_${uid}`);
        socket.join(`duel_${duelId}`);
        emitDuelFinishedToSocket(socket, snap, uid);
        clearUserDuel(uid, duelId);
        return;
    }

    const roomName = `duel_${duelId}`;
    socket.join(roomName);
    socket.join(`user_${uid}`);
    clearDuelDisconnectTimer(uid);

    const payload = {
        success: true,
        duel_id: duelId,
        room: roomName,
        current_question_number: meta ? meta.current_question_number : null,
        status: meta ? meta.status : 'active',
        multiplier: meta ? meta.multiplier : null,
    };
    socket.emit('duel-resume', payload);
    socket.emit('duel_resume', payload);
    console.log(`🔁 duel-resume: user ${uid} → ${roomName}`);
}

async function tryMatchDuelQueueLocked(multiplier) {
    const queue = getMatchQueue(multiplier);
    while (queue.length >= 2) {
        const first = queue.shift();
        const second = queue.shift();

        if (!first || !second) {
            break;
        }

        // Aynı kullanıcı iki kez kuyruğa girmişse atla
        if (first.userId === second.userId) {
            queue.unshift(first);
            continue;
        }

        // Reconnect sonrası socketId güncel mi? Map'teki canlı socket yeterli.
        const firstSocketId = userSocketMap.get(first.userId);
        const secondSocketId = userSocketMap.get(second.userId);

        if (!firstSocketId && secondSocketId) {
            second.socketId = secondSocketId;
            queue.unshift(second);
            continue;
        }
        if (firstSocketId && !secondSocketId) {
            first.socketId = firstSocketId;
            queue.unshift(first);
            continue;
        }
        if (!firstSocketId && !secondSocketId) {
            continue;
        }

        first.socketId = firstSocketId;
        second.socketId = secondSocketId;

        // Kuyruktan çıktıktan sonra cancel/disconnect olduysa Laravel'e gitme
        const firstCancelled = consumeMatchCancelled(first.userId);
        const secondCancelled = consumeMatchCancelled(second.userId);
        if (firstCancelled || secondCancelled) {
            if (!firstCancelled) {
                requeueIfStillReady(first, queue);
            }
            if (!secondCancelled) {
                requeueIfStillReady(second, queue);
            }
            continue;
        }

        console.log(`⚔️ Eşleşme denemesi: ${first.userId} vs ${second.userId} (${multiplier})`);

        try {
            const result = await createDuelMatchOnLaravel(first.userId, second.userId, multiplier);

            // Create sonrası biri ayrıldıysa yine de matched yayınla (düello DB'de oluştu)
            clearMatchCancelled(first.userId);
            clearMatchCancelled(second.userId);
            clearBotFallback(first.userId);
            clearBotFallback(second.userId);

            const duel = result.duel || {};
            const payload = {
                duelId: duel.duelId,
                challengerId: duel.challengerId,
                opponentId: duel.opponentId,
                multiplier: duel.multiplier || multiplier,
                status: 'matched',
                question: result.question || null,
                challenger: result.challenger || null,
                opponent: result.opponent || null,
                timestamp: new Date().toISOString(),
            };

            if (payload.duelId) {
                joinUserSocketToDuelRoom(first.userId, payload.duelId);
                joinUserSocketToDuelRoom(second.userId, payload.duelId);
            }

            emitToUser(first.userId, 'duel-matched', payload);
            emitToUser(second.userId, 'duel-matched', payload);

            if (payload.duelId) {
                io.to(`duel_${payload.duelId}`).emit('duel-matched', payload);
                io.to(`duel_${payload.duelId}`).emit('duel_matched', payload);
            }

            duelDebugLog('✅ duel-matched gönderildi:', JSON.stringify(payload));
        } catch (error) {
            console.error('❌ Laravel socket-match hatası:', error.message);
            const errorPayload = {
                success: false,
                message: error.message || 'Eşleşme oluşturulamadı',
                multiplier,
                timestamp: new Date().toISOString(),
            };
            emitToUser(first.userId, 'duel-match-error', errorPayload);
            emitToUser(second.userId, 'duel-match-error', errorPayload);

            // Yeniden kuyruğa alma — kalıcı hatalarda sonsuz loop olur; mobil tekrar duel-ready göndersin.
        }
    }
}

async function handleDuelReady(socket, data) {
    duelDebugLog('📥 duel-ready event alındı:', JSON.stringify(data));

    const userId = parseInt(data?.userId ?? data?.user_id, 10);
    const multiplier = String(data?.multiplier || 'x1').toLowerCase();

    if (!userId) {
        socket.emit('duel-ready-error', {
            success: false,
            message: 'userId gereklidir',
        });
        socket.emit('duel_ready_error', {
            success: false,
            message: 'userId gereklidir',
        });
        return;
    }

    if (!VALID_MULTIPLIERS.has(multiplier)) {
        const err = {
            success: false,
            message: 'Geçersiz multiplier. x1, x2, x4, x8 olmalı.',
        };
        socket.emit('duel-ready-error', err);
        socket.emit('duel_ready_error', err);
        return;
    }

    // user_join yapılmamışsa bu event ile map'e yaz
    connectedUsers.set(socket.id, {
        userId,
        userName: data?.userName || data?.user_name || connectedUsers.get(socket.id)?.userName,
    });
    userSocketMap.set(userId, socket.id);
    socket.join(`user_${userId}`);
    clearMatchCancelled(userId);
    clearDuelDisconnectTimer(userId);

    // Ghost waiting / bot maçlarını temizle; aktif insan–insan’a dokunma
    const prep = await prepareRequeueFromLaravel(userId);
    if (prep && prep.blocked) {
        const duelId = prep.duel_id || null;
        const err = {
            success: false,
            blocked: true,
            duel_id: duelId,
            message: prep.message || 'Zaten aktif bir düellodasınız.',
        };
        socket.emit('duel-ready-error', err);
        socket.emit('duel_ready_error', err);
        if (duelId) {
            resumeUserDuelRoom(socket, userId).catch(() => {});
        }
        console.log(`⛔ duel-ready reddedildi: user ${userId} aktif insan–insan #${duelId || '?'}`);
        return;
    }

    // Önce diğer kuyruklardan çıkar, sonra bu çarpana ekle
    removeUserFromMatchQueues(userId);

    const queue = getMatchQueue(multiplier);
    queue.push({
        userId,
        socketId: socket.id,
        multiplier,
        joinedAt: Date.now(),
    });

    const ack = {
        success: true,
        queued: true,
        userId,
        multiplier,
        position: queue.length,
        message: queue.length === 1
            ? 'Eşleşme kuyruğuna alındınız. Rakip bekleniyor.'
            : 'Eşleşme kuyruğuna alındınız.',
        timestamp: new Date().toISOString(),
    };

    socket.emit('duel-ready-ack', ack);
    socket.emit('duel_ready_ack', ack);
    console.log(`✅ Kullanıcı ${userId} kuyruğa girdi (${multiplier}), sıra: ${queue.length}`);

    tryMatchDuelQueue(multiplier);
}

function handleDuelCancelReady(socket, data) {
    duelDebugLog('📥 duel-cancel-ready event alındı:', JSON.stringify(data));

    const userId = parseInt(
        data?.userId ?? data?.user_id ?? connectedUsers.get(socket.id)?.userId,
        10
    );

    if (!userId) {
        const err = {
            success: false,
            message: 'userId gereklidir',
        };
        socket.emit('duel-cancel-ready-ack', err);
        socket.emit('duel_cancel_ready_ack', err);
        return;
    }

    removeUserFromMatchQueues(userId);
    markMatchCancelled(userId);

    const ack = {
        success: true,
        userId,
        message: 'Eşleşme kuyruğundan çıktınız.',
        timestamp: new Date().toISOString(),
    };
    socket.emit('duel-cancel-ready-ack', ack);
    socket.emit('duel_cancel_ready_ack', ack);
}

function getUsersInTournamentRoom(roomName) {
    const room = io.sockets.adapter.rooms.get(roomName);
    if (!room) {
        return [];
    }

    const socketIds = Array.from(room);
    const users = [];
    socketIds.forEach(socketId => {
        const user = connectedUsers.get(socketId);
        if (user) {
            users.push({
                userId: user.userId,
                userName: user.userName,
                socketId
            });
        }
    });

    return users;
}

function broadcastUserJoined(roomName, payload) {
    io.to(roomName).emit('user-joined-tournament', payload);
    io.to(roomName).emit('user_joined_tournament', payload); // geriye uyumluluk
}

function broadcastUserLeft(roomName, payload) {
    io.to(roomName).emit('user-left-tournament', payload);
}

function broadcastTournamentStarted(roomName, payload) {
    io.to(roomName).emit('tournament-started', payload);
}

// Socket bağlantıları
io.on('connection', (socket) => {
    console.log(`🔌 Yeni bağlantı: ${socket.id}`);

    // Kullanıcı girişi
    socket.on('user_join', (data) => {
        const userId = parseInt(data?.userId, 10);
        const userName = data?.userName;

        if (!userId) {
            console.warn('⚠️ user_join: geçersiz userId', data);
            return;
        }

        connectedUsers.set(socket.id, { userId, userName });
        userSocketMap.set(userId, socket.id);
        socket.join(`user_${userId}`);
        clearDuelDisconnectTimer(userId);
        refreshUserSocketInMatchQueues(userId, socket.id);
        touchUserPresence(userId);

        console.log(`👤 Kullanıcı girişi: ${userName} (${userId})`);

        // Kullanıcıya hoş geldin mesajı gönder
        socket.emit('welcome', {
            message: `Hoş geldin ${userName}!`,
            userId: userId,
            timestamp: new Date().toISOString()
        });

        // Aktif düello varsa odaya geri al (reconnect / socket restart)
        resumeUserDuelRoom(socket, userId).catch((err) => {
            console.warn('⚠️ duel-resume hata:', err.message);
        });
    });

    // Genel oda katılımı (duel_68, user_36 vb.) – room_joined döner
    socket.on('join-room', (data) => {
        const room = data && (data.room || data.roomName);
        if (!room || typeof room !== 'string') {
            socket.emit('room_joined', { success: false, message: 'room veya roomName gereklidir' });
            return;
        }
        socket.join(room);
        console.log(`✅ Socket ${socket.id} odaya katıldı: ${room}`);
        socket.emit('room_joined', { success: true, room });
    });

    // Düello match ekranı: eşleşmeye hazır
    socket.on('duel-ready', (data) => handleDuelReady(socket, data));
    socket.on('duel_ready', (data) => handleDuelReady(socket, data));

    // Match ekranından çıkış
    socket.on('duel-cancel-ready', (data) => handleDuelCancelReady(socket, data));
    socket.on('duel_cancel_ready', (data) => handleDuelCancelReady(socket, data));

    // Düello odasına katılma
    socket.on('join_duel', async (data) => {
        duelDebugLog('📥 join_duel event alındı:', JSON.stringify(data, null, 2));

        if (!data || !data.duelId || !data.userId) {
            socket.emit('join_duel_error', {
                success: false,
                message: 'duelId ve userId gereklidir'
            });
            return;
        }

        const { duelId, userId, userName } = data;
        const roomName = `duel_${duelId}`;

        socket.join(roomName);
        socket.join(`user_${userId}`);
        trackUserDuel(userId, duelId);

        console.log(`✅ Kullanıcı ${userId} düello odasına katıldı: ${roomName}`);

        socket.emit('duel_joined', {
            success: true,
            duel_id: duelId,
            room: roomName
        });

        // Maç zaten bitmişse finished event'i hemen gönder (kaçmış event / token sonrası)
        try {
            const snap = await fetchDuelSnapshot(duelId, userId);
            if (snap && snap.finished) {
                emitDuelFinishedToSocket(socket, snap, userId);
                clearUserDuel(userId, duelId);
            }
        } catch (err) {
            console.warn('join_duel snapshot hata:', err.message);
        }
    });

    // Client takılınca / token bitince: durum çek → bitmişse duel-finished bas
    const handleDuelStatusRequest = async (data) => {
        const duelId = data?.duelId ?? data?.duel_id;
        const userId = data?.userId ?? data?.user_id;
        if (!duelId) {
            socket.emit('duel_status_error', { success: false, message: 'duelId gerekli' });
            return;
        }
        const snap = await fetchDuelSnapshot(duelId, userId);
        if (!snap) {
            socket.emit('duel_status_error', { success: false, message: 'Düello durumu alınamadı' });
            return;
        }
        if (snap.finished) {
            emitDuelFinishedToSocket(socket, snap, userId);
            if (userId) clearUserDuel(userId, duelId);
            return;
        }
        socket.emit('duel-status', snap);
        socket.emit('duel_status', snap);
    };
    socket.on('request_duel_status', handleDuelStatusRequest);
    socket.on('duel_request_status', handleDuelStatusRequest);
    socket.on('request-duel-status', handleDuelStatusRequest);

    // Turnuva odasına katılma
    socket.on('join_tournament', (data) => {
        console.log('📥 join_tournament event alındı:', JSON.stringify(data, null, 2));
        console.log('📥 Data type:', typeof data);
        console.log('📥 Data keys:', data ? Object.keys(data) : 'data is null/undefined');

        // Data kontrolü
        if (!data) {
            console.error('❌ join_tournament: data null veya undefined');
            socket.emit('join_tournament_error', {
                success: false,
                message: 'Data gönderilmedi'
            });
            return;
        }

        const { tournamentId, userId, userName, minParticipants } = data;

        if (!tournamentId || !userId) {
            console.error('❌ join_tournament: tournamentId veya userId eksik', {
                tournamentId,
                userId,
                receivedData: data
            });
            socket.emit('join_tournament_error', {
                success: false,
                message: 'tournamentId ve userId gereklidir',
                receivedData: data
            });
            return;
        }

        const roomName = `tournament_${tournamentId}`;
        let user = connectedUsers.get(socket.id);

        // Kullanıcı bilgilerini güncelle veya oluştur
        if (!user) {
            connectedUsers.set(socket.id, { userId, userName: userName || `User ${userId}` });
            userSocketMap.set(userId, socket.id);
            socket.join(`user_${userId}`);
            clearDuelDisconnectTimer(userId);
            console.log(`👤 Kullanıcı otomatik oluşturuldu: User ${userId}`);
            user = connectedUsers.get(socket.id);
        } else if (userName && user.userName !== userName) {
            connectedUsers.set(socket.id, { userId, userName });
            user = connectedUsers.get(socket.id);
        }

        socket.join(roomName);
        userRooms.set(socket.id, roomName);

        const roomSize = io.sockets.adapter.rooms.get(roomName)?.size || 0;
        console.log(`🏆 Turnuva odasına katıldı: User ${userId} -> Tournament ${tournamentId}, Room: ${roomName}`);
        console.log(`📊 Odadaki socket sayısı: ${roomSize}`);

        // Başarılı response dön
        const responseData = {
            success: true,
            tournamentId: parseInt(tournamentId),
            userId: parseInt(userId),
            roomName: roomName,
            roomSize: roomSize,
            message: 'Turnuva odasına katıldınız',
            timestamp: new Date().toISOString()
        };

        console.log('📤 joined_tournament event gönderiliyor:', JSON.stringify(responseData, null, 2));
        socket.emit('joined_tournament', responseData);
        console.log('✅ joined_tournament event gönderildi');

        const usersInRoom = getUsersInTournamentRoom(roomName);
        const joinBroadcast = {
            tournament_id: parseInt(tournamentId),
            user_id: parseInt(userId),
            name: user?.userName || `User ${userId}`,
            user_name: user?.userName || `User ${userId}`,
            current_participants: usersInRoom.length,
            users_in_room: usersInRoom,
            timestamp: new Date().toISOString()
        };

        console.log('📣 user-joined-tournament broadcast:', JSON.stringify(joinBroadcast, null, 2));
        broadcastUserJoined(roomName, joinBroadcast);

        const requiredParticipants = parseInt(minParticipants) || 2;
        if (usersInRoom.length >= requiredParticipants) {
            const startPayload = {
                tournament_id: parseInt(tournamentId),
                tournament_type: (data && data.tournamentType) ? data.tournamentType : 'question_based',
                auto_start: true,
                reason: 'ready_threshold_met',
                current_participants: usersInRoom.length,
                min_participants: requiredParticipants,
                participants_info: usersInRoom,
                timestamp: new Date().toISOString()
            };

            console.log('🚀 tournament-started (auto) gönderiliyor:', JSON.stringify(startPayload, null, 2));
            broadcastTournamentStarted(roomName, startPayload);
        }
    });

    socket.on('leave_tournament', (data) => {
        console.log('📥 leave_tournament event alındı:', JSON.stringify(data, null, 2));
        const { tournamentId, userId } = data || {};

        if (!tournamentId || !userId) {
            socket.emit('leave_tournament_error', {
                success: false,
                message: 'tournamentId ve userId gereklidir'
            });
            return;
        }

        const roomName = `tournament_${tournamentId}`;
        const currentRoom = userRooms.get(socket.id);

        if (currentRoom === roomName) {
            socket.leave(roomName);
            userRooms.delete(socket.id);
            console.log(`🚪 Kullanıcı odadan ayrıldı (manual): ${roomName}`);
        } else {
            console.warn(`⚠️ leave_tournament: Kullanıcı oda içinde değil`, { socketId: socket.id, roomName, currentRoom });
        }

        const usersInRoom = getUsersInTournamentRoom(roomName);
        const payload = {
            tournament_id: parseInt(tournamentId),
            user_id: parseInt(userId),
            user_name: connectedUsers.get(socket.id)?.userName || `User ${userId}`,
            current_participants: usersInRoom.length,
            users_in_room: usersInRoom,
            reason: 'left',
            timestamp: new Date().toISOString()
        };

        console.log('📣 user-left-tournament broadcast (manual leave):', JSON.stringify(payload, null, 2));
        broadcastUserLeft(roomName, payload);

        socket.emit('left_tournament', {
            success: true,
            tournamentId: parseInt(tournamentId)
        });
    });

    // Test event'leri - Socket üzerinden event'leri simüle et ve gerçek data döndür
    socket.on('test-user-joined-tournament', (data) => {
        console.log('🧪 Test event: test-user-joined-tournament', JSON.stringify(data, null, 2));
        const { tournament_id, user_id, user_name, current_participants, min_participants, waiting_message, ready_to_start } = data;

        const roomName = `tournament_${tournament_id}`;
        const room = io.sockets.adapter.rooms.get(roomName);
        const roomSize = room ? room.size : 0;

        // Odadaki gerçek kullanıcıları al
        const socketIds = room ? Array.from(room) : [];
        const usersInRoom = [];
        socketIds.forEach(socketId => {
            const user = connectedUsers.get(socketId);
            if (user) {
                usersInRoom.push({
                    userId: user.userId,
                    userName: user.userName,
                    socketId: socketId
                });
            }
        });

        const joinData = {
            tournament_id: parseInt(tournament_id),
            user_id: parseInt(user_id),
            name: user_name || `User ${user_id}`,
            user_name: user_name || `User ${user_id}`,
            current_participants: current_participants || usersInRoom.length || 0,
            min_participants: parseInt(min_participants) || 2,
            ready_to_start: ready_to_start === true || ready_to_start === 'true' || ready_to_start === 1,
            waiting_message: waiting_message || null,
            users_in_room: usersInRoom, // Gerçek kullanıcılar
            room_size: roomSize,
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('user-joined-tournament', joinData);
        io.to(`tournament_${tournament_id}`).emit('user_joined_tournament', joinData);
        io.to(`user_${user_id}`).emit('tournament_joined', joinData);

        const isReady = ready_to_start || (joinData.current_participants >= joinData.min_participants);
        const waitingMsg = waiting_message || (
            isReady
                ? `Turnuva başlamaya hazır! (${joinData.current_participants}/${joinData.min_participants})`
                : `Diğer oyuncular bekleniyor... (${joinData.current_participants}/${joinData.min_participants})`
        );

        const waitingData = {
            tournament_id: parseInt(tournament_id),
            current_participants: joinData.current_participants,
            min_participants: joinData.min_participants,
            ready_to_start: isReady,
            waiting_message: waitingMsg,
            users_in_room: usersInRoom, // Bekleyen kullanıcılar
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('waiting-players', waitingData);
        socket.emit('test-response', {
            success: true,
            event: 'user-joined-tournament',
            data: joinData,
            waiting_data: waitingData,
            message: 'Event gönderildi, gerçek kullanıcı bilgileri ile'
        });
    });

    socket.on('test-tournament-started', (data) => {
        console.log('🧪 Test event: test-tournament-started', JSON.stringify(data, null, 2));
        const { tournament_id, tournament_type, participants, question_count, time_limit, first_question, start_time } = data;

        const roomName = `tournament_${tournament_id}`;
        const room = io.sockets.adapter.rooms.get(roomName);
        const roomSize = room ? room.size : 0;

        // Odadaki gerçek kullanıcıları al
        const socketIds = room ? Array.from(room) : [];
        const realParticipants = [];
        socketIds.forEach(socketId => {
            const user = connectedUsers.get(socketId);
            if (user) {
                realParticipants.push({
                    userId: user.userId,
                    userName: user.userName
                });
            }
        });

        const startData = {
            tournament_id: parseInt(tournament_id),
            tournament_type: tournament_type || 'question_based',
            participants: realParticipants.length > 0 ? realParticipants.map(p => p.userId) : (participants || []),
            participants_info: realParticipants, // Gerçek kullanıcı bilgileri
            question_count: question_count || 0,
            time_limit: time_limit || null,
            start_time: start_time || new Date().toISOString(),
            first_question: first_question || null,
            room_size: roomSize,
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('tournament-started', startData);

        socket.emit('test-response', {
            success: true,
            event: 'tournament-started',
            data: startData,
            message: 'Event gönderildi, gerçek katılımcı bilgileri ile'
        });
    });

    socket.on('test-tournament-answer-submitted', (data) => {
        console.log('🧪 Test event: test-tournament-answer-submitted', JSON.stringify(data, null, 2));
        const { tournament_id, user_id, question_id, is_correct, score_change, current_score, speed_bonus, leaderboard } = data;

        const answerData = {
            tournament_id: parseInt(tournament_id),
            user_id: parseInt(user_id),
            question_id: question_id,
            is_correct: is_correct,
            score: current_score,
            score_change: score_change || 0,
            speed_bonus: speed_bonus || 0,
            leaderboard: leaderboard || [],
            timestamp: new Date().toISOString()
        };

        if (!is_correct && data.correct_answer) {
            answerData.correct_answer = data.correct_answer;
            answerData.correct_option = data.correct_option ?? data.correct_answer;
            if (data.correct_answer_text) {
                answerData.correct_answer_text = data.correct_answer_text;
            }
        }

        io.to(`tournament_${tournament_id}`).emit('tournament-answer-submitted', answerData);

        socket.emit('test-response', {
            success: true,
            event: 'tournament-answer-submitted',
            data: answerData,
            message: 'Event gönderildi'
        });
    });

    socket.on('test-tournament-ranking-updated', (data) => {
        console.log('🧪 Test event: test-tournament-ranking-updated', JSON.stringify(data, null, 2));
        const { tournament_id, rankings } = data;

        const rankingData = {
            tournament_id: parseInt(tournament_id),
            rankings: rankings || [],
            timestamp: new Date().toISOString()
        };

        io.to(`tournament_${tournament_id}`).emit('tournament-ranking-updated', rankingData);

        socket.emit('test-response', {
            success: true,
            event: 'tournament-ranking-updated',
            data: rankingData,
            message: 'Event gönderildi'
        });
    });

    socket.on('test-tournament-next-question', (data) => {
        console.log('🧪 Test event: test-tournament-next-question', JSON.stringify(data, null, 2));
        const { tournament_id, question, question_number, total_questions } = data;

        const payload = {
            tournament_id: parseInt(tournament_id),
            question: question,
            question_number: question_number,
            total_questions: total_questions,
            timestamp: new Date().toISOString()
        };

        io.to(`tournament_${tournament_id}`).emit('tournament-next-question', payload);

        socket.emit('test-response', {
            success: true,
            event: 'tournament-next-question',
            data: payload,
            message: 'Event gönderildi'
        });
    });

    socket.on('test-player-eliminated', (data) => {
        console.log('🧪 Test event: test-player-eliminated', JSON.stringify(data, null, 2));
        const { tournament_id, user_id, user_name, reason, remaining_players, final_score, position } = data;

        const roomName = `tournament_${tournament_id}`;
        const room = io.sockets.adapter.rooms.get(roomName);
        const roomSize = room ? room.size : 0;

        const eliminationData = {
            tournament_id: parseInt(tournament_id),
            user_id: parseInt(user_id),
            name: user_name,
            final_score: final_score || 0,
            position: position || null,
            reason: reason || 'unknown',
            remaining_players: remaining_players || roomSize,
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('player-eliminated', eliminationData);

        socket.emit('test-response', {
            success: true,
            event: 'player-eliminated',
            data: eliminationData,
            message: 'Event gönderildi'
        });
    });

    socket.on('test-tournament-finished', (data) => {
        console.log('🧪 Test event: test-tournament-finished', JSON.stringify(data, null, 2));
        const { tournament_id, final_rankings, final_leaderboard, winner, winners, end_reason } = data;

        const finishData = {
            tournament_id: parseInt(tournament_id),
            final_rankings: final_rankings || final_leaderboard || [],
            winner: winner || (final_rankings && final_rankings[0]) || null,
            winners: winners || (final_rankings ? final_rankings.slice(0, 3) : []),
            end_reason: end_reason || 'completed',
            timestamp: new Date().toISOString()
        };

        io.to(`tournament_${tournament_id}`).emit('tournament-finished', finishData);

        socket.emit('test-response', {
            success: true,
            event: 'tournament-finished',
            data: finishData,
            message: 'Event gönderildi'
        });
    });

    // Turnuva odasındaki kullanıcıları getir
    socket.on('get-tournament-users', (data) => {
        console.log('📥 get-tournament-users event alındı:', JSON.stringify(data, null, 2));

        const { tournamentId } = data;

        if (!tournamentId) {
            socket.emit('tournament-users-error', {
                success: false,
                message: 'tournamentId gereklidir'
            });
            return;
        }

        const roomName = `tournament_${tournamentId}`;
        const room = io.sockets.adapter.rooms.get(roomName);

        if (!room) {
            socket.emit('tournament-users', {
                success: true,
                tournamentId: parseInt(tournamentId),
                users: [],
                count: 0,
                message: 'Turnuva odası bulunamadı veya boş'
            });
            return;
        }

        // Odadaki tüm socket'leri al
        const socketIds = Array.from(room);
        const users = [];

        socketIds.forEach(socketId => {
            const user = connectedUsers.get(socketId);
            if (user) {
                users.push({
                    userId: user.userId,
                    userName: user.userName,
                    socketId: socketId
                });
            }
        });

        const responseData = {
            success: true,
            tournamentId: parseInt(tournamentId),
            roomName: roomName,
            users: users,
            count: users.length,
            timestamp: new Date().toISOString()
        };

        console.log('📤 tournament-users event gönderiliyor:', JSON.stringify(responseData, null, 2));
        socket.emit('tournament-users', responseData);
    });

    // Bağlantı kesilme
    socket.on('disconnect', () => {
        const user = connectedUsers.get(socket.id);
        const room = userRooms.get(socket.id);

        if (room) {
            // Turnuva odasından çık
            socket.leave(room);

            // Diğer kullanıcılara bildir (turnuva başladıysa kullanıcı elenmiş olabilir)
            if (user && room.startsWith('tournament_')) {
                const tournamentId = room.replace('tournament_', '');
                const usersInRoom = getUsersInTournamentRoom(room);
                const payload = {
                    tournament_id: parseInt(tournamentId),
                    user_id: user.userId,
                    user_name: user.userName,
                    current_participants: usersInRoom.length,
                    users_in_room: usersInRoom,
                    reason: 'disconnected',
                    timestamp: new Date().toISOString()
                };

                console.log('📣 user-left-tournament broadcast (disconnect):', JSON.stringify(payload, null, 2));
                broadcastUserLeft(room, payload);
            }

            userRooms.delete(socket.id);
            console.log(`🚪 Odadan ayrıldı: ${room}`);
        }

        if (user) {
            // Eşleşme kuyruğundan çıkar + in-flight match'i iptal bayrağı
            removeUserFromMatchQueues(user.userId);
            markMatchCancelled(user.userId);

            // Kullanıcı odasından çık
            socket.leave(`user_${user.userId}`);
            connectedUsers.delete(socket.id);
            // Başka cihaz/socket aynı userId ile bağlıysa map'i bozma
            if (userSocketMap.get(user.userId) === socket.id) {
                userSocketMap.delete(user.userId);
                // Aktif düelloda socket koptu → 45 sn sonra leave (yeniden bağlanırsa iptal)
                scheduleDuelAfkTimeout(user.userId);
                touchUserPresence(user.userId);
            }
            console.log(`Kullanıcı bağlantısı kesildi: ${user.userId}`);
        }
    });
});

// ===== TURNUVA WEBHOOK'LARI =====

app.post('/socket-webhooks/webhook/user-joined-tournament', (req, res) => {
    try {
        console.log('📥 Webhook alındı: user-joined-tournament', JSON.stringify(req.body));

        const { tournament_id, user_id, user_name, user_avatar, current_participants, min_participants, waiting_message, ready_to_start, allow_auto_start, is_scheduled, starts_at } = req.body;

        if (!tournament_id || !user_id) {
            console.error('❌ Webhook: tournament_id veya user_id eksik', req.body);
            return res.status(400).json({
                success: false,
                message: 'tournament_id ve user_id gereklidir'
            });
        }

        const roomName = `tournament_${tournament_id}`;
        const room = io.sockets.adapter.rooms.get(roomName);
        const roomSize = room ? room.size : 0;

        console.log(`📊 Turnuva odası: ${roomName}, Socket sayısı: ${roomSize}`);

        if (roomSize === 0) {
            console.warn(`⚠️ Uyarı: ${roomName} odasında hiç socket yok! Kullanıcılar henüz join_tournament yapmamış olabilir.`);
        }

        const joinData = {
            tournament_id: parseInt(tournament_id),
            user_id: parseInt(user_id),
            name: user_name || `User ${user_id}`,
            user_name: user_name || `User ${user_id}`, // Geriye uyumluluk için
            user_avatar: user_avatar || null, // Avatar URL'i
            avatar: user_avatar || null, // Geriye uyumluluk için
            current_participants: parseInt(current_participants) || 0,
            min_participants: parseInt(min_participants) || 2,
            ready_to_start: ready_to_start === true || ready_to_start === 'true' || ready_to_start === 1,
            allow_auto_start: allow_auto_start !== false && allow_auto_start !== 'false' && allow_auto_start !== 0,
            is_scheduled: is_scheduled === true || is_scheduled === 'true' || is_scheduled === 1,
            starts_at: starts_at || null,
            waiting_message: waiting_message || null,
            timestamp: new Date().toISOString()
        };

        console.log('📤 user-joined-tournament event gönderiliyor:', JSON.stringify(joinData, null, 2));
        console.log(`📤 Room: ${roomName}, Room Size: ${roomSize}`);

        // Turnuva odasına katılım bildirimi (yeni format)
        io.to(roomName).emit('user-joined-tournament', joinData);
        console.log('✅ user-joined-tournament event gönderildi (room:', roomName + ')');

        // Eski format (geriye uyumluluk için)
        io.to(`tournament_${tournament_id}`).emit('user_joined_tournament', {
            tournament_id,
            user_id,
            user_name,
            user_avatar: user_avatar || null,
            avatar: user_avatar || null,
            current_participants,
            min_participants,
            waiting_message,
            timestamp: new Date().toISOString()
        });

        // Kullanıcıya özel bildirim
        io.to(`user_${user_id}`).emit('tournament_joined', {
            tournament_id,
            current_participants,
            min_participants,
            waiting_message,
            timestamp: new Date().toISOString()
        });

        // Her zaman waiting-players event'i gönder (ready_to_start durumu ile birlikte)
        const isScheduled = is_scheduled === true || is_scheduled === 'true' || is_scheduled === 1;
        const canAutoStart = allow_auto_start !== false && allow_auto_start !== 'false' && allow_auto_start !== 0;
        const isReady = !isScheduled && (ready_to_start || (current_participants >= min_participants));
        const waitingMsg = waiting_message || (
            isScheduled && starts_at
                ? `Turnuva ${new Date(starts_at).toLocaleString('tr-TR')} tarihinde başlayacak.`
                : isReady
                    ? `Turnuva başlamaya hazır! (${current_participants}/${min_participants})`
                    : `Diğer oyuncular bekleniyor... (${current_participants}/${min_participants})`
        );

        const waitingData = {
            tournament_id,
            current_participants: current_participants || 0,
            min_participants: min_participants || 2,
            ready_to_start: isReady,
            is_scheduled: isScheduled,
            starts_at: starts_at || null,
            waiting_message: waitingMsg,
            timestamp: new Date().toISOString()
        };

        console.log('📤 waiting-players event gönderiliyor:', JSON.stringify(waitingData));
        io.to(roomName).emit('waiting-players', waitingData);

        if (isReady && canAutoStart) {
            const usersInRoom = getUsersInTournamentRoom(roomName);
            const startPayload = {
                tournament_id: parseInt(tournament_id),
                tournament_type: (req.body && req.body.tournament_type) ? req.body.tournament_type : 'question_based',
                auto_start: true,
                reason: 'ready_threshold_met',
                current_participants: current_participants || usersInRoom.length || 0,
                min_participants: min_participants || 2,
                participants_info: usersInRoom,
                timestamp: new Date().toISOString()
            };

            console.log('🚀 tournament-started (auto via webhook) gönderiliyor:', JSON.stringify(startPayload, null, 2));
            broadcastTournamentStarted(roomName, startPayload);
        }

        console.log(`✅ Kullanıcı turnuvaya katıldı: ${user_name} (${user_id}) - Tournament ${tournament_id}, Participants: ${current_participants}/${min_participants}, Room: ${roomName}, Room Size: ${roomSize}`);
        res.json({ success: true, message: 'User joined tournament webhook processed' });
    } catch (error) {
        console.error('❌ Webhook işleme hatası:', error);
        res.status(500).json({
            success: false,
            message: 'Webhook işlenirken hata oluştu',
            error: error.message
        });
    }
});

app.post('/socket-webhooks/webhook/tournament-started', (req, res) => {
    const { tournament_id, tournament_type, participants, question_count, time_limit, first_question, start_time } = req.body;

    io.to(`tournament_${tournament_id}`).emit('tournament-started', {
        tournament_id,
        tournament_type: tournament_type || 'question_based',
        participants: participants || [],
        question_count: question_count || 0,
        time_limit: time_limit || null,
        start_time: start_time || new Date().toISOString(),
        first_question: first_question || null,
        timestamp: new Date().toISOString()
    });

    console.log(`Turnuva başladı: Tournament ${tournament_id}, Participants: ${participants?.length || 0}`);
    res.json({ success: true, message: 'Tournament started webhook processed' });
});

app.post('/socket-webhooks/webhook/tournament-answer-submitted', (req, res) => {
    const {
        tournament_id,
        user_id,
        question_id,
        is_correct,
        score_change,
        current_score,
        speed_bonus,
        leaderboard,
        correct_answer,
        correct_option,
        correct_answer_text
    } = req.body;

    const payload = {
        tournament_id,
        user_id,
        question_id,
        is_correct,
        score: current_score,
        score_change: score_change || 0,
        speed_bonus: speed_bonus || 0,
        leaderboard: leaderboard || [],
        timestamp: new Date().toISOString()
    };

    if (!is_correct) {
        payload.correct_answer = correct_answer ?? correct_option ?? null;
        payload.correct_option = correct_option ?? correct_answer ?? null;
        if (correct_answer_text) {
            payload.correct_answer_text = correct_answer_text;
        }
    }

    io.to(`tournament_${tournament_id}`).emit('tournament-answer-submitted', payload);

    console.log(`Turnuva cevabı gönderildi: Tournament ${tournament_id}, User ${user_id}, Correct: ${is_correct}, Score: ${current_score}`);
    res.json({ success: true, message: 'Tournament answer submitted webhook processed' });
});

app.post('/socket-webhooks/webhook/tournament-joker-used', (req, res) => {
    try {
        const { tournament_id, user_id, joker_type, result, remaining_jokers } = req.body;

        if (!tournament_id || !user_id || !joker_type) {
            console.error('❌ Webhook: tournament_id, user_id veya joker_type eksik', req.body);
            return res.status(400).json({
                success: false,
                message: 'tournament_id, user_id ve joker_type gereklidir'
            });
        }

        const roomName = `tournament_${tournament_id}`;
        const user = connectedUsers.get(userSocketMap.get(parseInt(user_id)));

        const jokerData = {
            tournament_id: parseInt(tournament_id),
            user_id: parseInt(user_id),
            user_name: user?.userName || `User ${user_id}`,
            joker_type: joker_type,
            result: result || {},
            remaining_jokers: remaining_jokers || 0,
            timestamp: new Date().toISOString()
        };

        console.log('📤 tournament-joker-used event gönderiliyor:', JSON.stringify(jokerData, null, 2));
        io.to(roomName).emit('tournament-joker-used', jokerData);
        console.log(`✅ Turnuva joker kullanıldı: User ${user_id} - Tournament ${tournament_id}, Joker: ${joker_type}, Room: ${roomName}`);

        res.json({ success: true, message: 'Tournament joker used webhook processed' });
    } catch (error) {
        console.error('❌ Webhook işleme hatası:', error);
        res.status(500).json({
            success: false,
            message: 'Webhook işlenirken hata oluştu',
            error: error.message
        });
    }
});

app.post('/socket-webhooks/webhook/tournament-ranking-updated', (req, res) => {
    const { tournament_id, rankings } = req.body;

    io.to(`tournament_${tournament_id}`).emit('tournament-ranking-updated', {
        tournament_id,
        rankings: rankings || [],
        timestamp: new Date().toISOString()
    });

    console.log(`Turnuva sıralaması güncellendi: Tournament ${tournament_id}`);
    res.json({ success: true, message: 'Tournament ranking updated webhook processed' });
});

app.post('/socket-webhooks/webhook/tournament-player-eliminated', (req, res) => {
    const { tournament_id, user_id, user_name, reason, remaining_players, final_score, position } = req.body;

    const eliminationData = {
        tournament_id,
        user_id,
        name: user_name,
        final_score: final_score || 0,
        position: position || null,
        reason: reason || 'unknown',
        remaining_players: remaining_players || 0,
        timestamp: new Date().toISOString()
    };

    io.to(`tournament_${tournament_id}`).emit('player-eliminated', eliminationData);

    console.log(`Turnuva oyuncusu elendi: User ${user_id} (${user_name}) - Tournament ${tournament_id}, Reason: ${reason}`);
    res.json({ success: true, message: 'Tournament player eliminated webhook processed' });
});

app.post('/socket-webhooks/webhook/tournament-finished', (req, res) => {
    const { tournament_id, final_rankings, final_leaderboard, winner, winners, end_reason } = req.body;

    const finishData = {
        tournament_id,
        final_rankings: final_rankings || final_leaderboard || [],
        winner: winner || (final_rankings && final_rankings[0]) || null,
        winners: winners || (final_rankings ? final_rankings.slice(0, 3) : []),
        end_reason: end_reason || 'completed',
        timestamp: new Date().toISOString()
    };

    io.to(`tournament_${tournament_id}`).emit('tournament-finished', finishData);

    console.log(`Turnuva bitti: Tournament ${tournament_id}, Reason: ${end_reason || 'completed'}`);
    res.json({ success: true, message: 'Tournament finished webhook processed' });
});

app.post('/socket-webhooks/webhook/tournament-next-question', (req, res) => {
    const { tournament_id, question, question_number, total_questions } = req.body;
    const payload = {
        tournament_id,
        question,
        question_number,
        total_questions,
        timestamp: new Date().toISOString()
    };

    io.to(`tournament_${tournament_id}`).emit('tournament-next-question', payload);

    console.log(`Turnuva sonraki soru: Tournament ${tournament_id}, Question ${question_number}/${total_questions}`);
    res.json({ success: true, message: 'Tournament next question webhook processed' });
});

// ===== DÜELLO (MEYDAN OKUMA) WEBHOOK'LARI =====

app.post('/socket-webhooks/webhook/duel-created', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-created', JSON.stringify(req.body));
        const { duel_id, challenger_id, opponent_id, multiplier, question_value, requires_acceptance } = req.body;

        if (!duel_id || !challenger_id || !opponent_id) {
            return res.status(400).json({ success: false, message: 'Eksik parametreler' });
        }

        const roomName = `duel_${duel_id}`;
        const data = {
            duel_id: parseInt(duel_id),
            challenger_id: parseInt(challenger_id),
            opponent_id: parseInt(opponent_id),
            multiplier: multiplier || 'x1',
            question_value: question_value || 1,
            requires_acceptance: requires_acceptance || false, // X2/X4/X8 için true
            timestamp: new Date().toISOString()
        };

        // X2/X4/X8 ise sadece rakibe bildirim gönder (kabul/reddet seçeneği ile)
        if (requires_acceptance) {
            io.to(`user_${opponent_id}`).emit('duel-challenge-request', {
                ...data,
                message: `${multiplier} düello isteği geldi! Kabul et veya reddet.`
            });
        } else {
            // X1 ise her iki kullanıcıya da bildirim gönder
            io.to(`user_${challenger_id}`).emit('duel-created', data);
            io.to(`user_${opponent_id}`).emit('duel-created', data);
        }

        io.to(roomName).emit('duel-created', data);

        console.log('✅ duel-created event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel created webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-started', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-started', JSON.stringify(req.body));
        const {
            duel_id,
            challenger_id,
            opponent_id,
            question,
            multiplier,
            challenger,
            opponent,
        } = req.body;

        if (!duel_id) {
            return res.status(400).json({ success: false, message: 'duel_id gerekli' });
        }

        const duelId = parseInt(duel_id, 10);
        const challengerId = parseInt(challenger_id, 10);
        const opponentId = parseInt(opponent_id, 10);
        const roomName = `duel_${duelId}`;
        const data = {
            duel_id: duelId,
            challenger_id: challengerId,
            opponent_id: opponentId,
            question: question,
            timestamp: new Date().toISOString()
        };

        // Bot / API eşleşmesinde mobil hâlâ duel-matched bekliyor — kuyruktan çıkar + matched yayınla
        if (challengerId) {
            removeUserFromMatchQueues(challengerId);
            clearMatchCancelled(challengerId);
            joinUserSocketToDuelRoom(challengerId, duelId);
        }
        if (opponentId) {
            removeUserFromMatchQueues(opponentId);
            clearMatchCancelled(opponentId);
            joinUserSocketToDuelRoom(opponentId, duelId);
        }

        const matchedPayload = {
            duelId,
            challengerId,
            opponentId,
            multiplier: multiplier || 'x1',
            status: 'matched',
            question: question || null,
            challenger: challenger || null,
            opponent: opponent || null,
            timestamp: new Date().toISOString(),
        };

        io.to(roomName).emit('duel-started', data);
        if (challengerId) {
            io.to(`user_${challengerId}`).emit('duel-started', data);
            emitToUser(challengerId, 'duel-matched', matchedPayload);
            emitToUser(challengerId, 'duel_matched', matchedPayload);
        }
        if (opponentId) {
            io.to(`user_${opponentId}`).emit('duel-started', data);
            emitToUser(opponentId, 'duel-matched', matchedPayload);
            emitToUser(opponentId, 'duel_matched', matchedPayload);
        }
        if (duelId) {
            io.to(roomName).emit('duel-matched', matchedPayload);
            io.to(roomName).emit('duel_matched', matchedPayload);
        }

        duelDebugLog('✅ duel-started + duel-matched event gönderildi', JSON.stringify(matchedPayload));
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel started webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-answer', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-answer', JSON.stringify(req.body));
        const {
            duel_id,
            user_id,
            is_correct,
            both_answered,
            challenger_id,
            opponent_id,
            correct_answer,
            correct_option,
            correct_answer_text
        } = req.body;

        if (!duel_id || !user_id) {
            return res.status(400).json({ success: false, message: 'Eksik parametreler' });
        }

        const roomName = `duel_${duel_id}`;
        const data = {
            duel_id: parseInt(duel_id),
            user_id: parseInt(user_id),
            is_correct: is_correct,
            both_answered: both_answered,
            timestamp: new Date().toISOString()
        };

        if (!is_correct) {
            data.correct_answer = correct_answer ?? correct_option ?? null;
            data.correct_option = correct_option ?? correct_answer ?? null;
            if (correct_answer_text) {
                data.correct_answer_text = correct_answer_text;
            }
        }

        io.to(roomName).emit('duel-answer', data);
        if (challenger_id) io.to(`user_${challenger_id}`).emit('duel-answer', data);
        if (opponent_id) io.to(`user_${opponent_id}`).emit('duel-answer', data);

        console.log('✅ duel-answer event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel answer webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-question-bet-requested', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-question-bet-requested', JSON.stringify(req.body));
        const { duel_id, question_id, initiator_id, opponent_id, multiplier, status } = req.body;

        if (!duel_id || !question_id || !initiator_id || !opponent_id) {
            return res.status(400).json({ success: false, message: 'Eksik parametreler' });
        }

        const roomName = `duel_${duel_id}`;
        const data = {
            duel_id: parseInt(duel_id),
            question_id: parseInt(question_id),
            initiator_id: parseInt(initiator_id),
            opponent_id: parseInt(opponent_id),
            multiplier: parseInt(multiplier || 1),
            status: status || 'pending',
            timestamp: new Date().toISOString()
        };

        // Odaya ve özellikle rakip kullanıcıya gönder
        io.to(roomName).emit('duel-question-bet-requested', data);
        io.to(`user_${opponent_id}`).emit('duel-question-bet-requested', data);

        console.log('✅ duel-question-bet-requested event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel question bet requested webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-question-bet-responded', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-question-bet-responded', JSON.stringify(req.body));
        const { duel_id, question_id, initiator_id, opponent_id, multiplier, status, accepted } = req.body;

        if (!duel_id || !question_id || !initiator_id || !opponent_id) {
            return res.status(400).json({ success: false, message: 'Eksik parametreler' });
        }

        const roomName = `duel_${duel_id}`;
        const data = {
            duel_id: parseInt(duel_id),
            question_id: parseInt(question_id),
            initiator_id: parseInt(initiator_id),
            opponent_id: parseInt(opponent_id),
            multiplier: parseInt(multiplier || 1),
            status: status || null,
            accepted: !!accepted,
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('duel-question-bet-responded', data);
        io.to(`user_${initiator_id}`).emit('duel-question-bet-responded', data);
        io.to(`user_${opponent_id}`).emit('duel-question-bet-responded', data);

        console.log('✅ duel-question-bet-responded event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel question bet responded webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-next-question', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-next-question', JSON.stringify(req.body));
        const { duel_id, question, question_number, challenger_id, opponent_id } = req.body;

        if (!duel_id || !question) {
            return res.status(400).json({ success: false, message: 'Eksik parametreler' });
        }

        const roomName = `duel_${duel_id}`;
        const data = {
            duel_id: parseInt(duel_id),
            question: question,
            question_number: question_number,
            timestamp: new Date().toISOString()
        };

        io.to(roomName).emit('duel-next-question', data);
        if (challenger_id) io.to(`user_${challenger_id}`).emit('duel-next-question', data);
        if (opponent_id) io.to(`user_${opponent_id}`).emit('duel-next-question', data);

        console.log('✅ duel-next-question event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel next question webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

app.post('/socket-webhooks/webhook/duel-finished', (req, res) => {
    try {
        duelDebugLog('📥 Webhook alındı: duel-finished', JSON.stringify(req.body));
        const body = req.body || {};
        const duelId = parseInt(body.duel_id ?? body.duelId, 10);
        const challengerId = body.challenger_id ?? body.challengerId;
        const opponentId = body.opponent_id ?? body.opponentId;

        if (!duelId) {
            return res.status(400).json({ success: false, message: 'duel_id gerekli' });
        }

        const roomName = `duel_${duelId}`;
        const data = {
            ...body,
            duel_id: duelId,
            duelId,
            winner_id: body.winner_id ?? body.winnerId ?? null,
            winnerId: body.winner_id ?? body.winnerId ?? null,
            challenger_id: challengerId ? parseInt(challengerId, 10) : null,
            challengerId: challengerId ? parseInt(challengerId, 10) : null,
            opponent_id: opponentId ? parseInt(opponentId, 10) : null,
            opponentId: opponentId ? parseInt(opponentId, 10) : null,
            timestamp: body.timestamp || new Date().toISOString(),
        };

        io.to(roomName).emit('duel-finished', data);
        io.to(roomName).emit('duel_finished', data);
        if (data.challenger_id) {
            io.to(`user_${data.challenger_id}`).emit('duel-finished', data);
            io.to(`user_${data.challenger_id}`).emit('duel_finished', data);
            clearUserDuel(data.challenger_id, duelId);
        }
        if (data.opponent_id) {
            io.to(`user_${data.opponent_id}`).emit('duel-finished', data);
            io.to(`user_${data.opponent_id}`).emit('duel_finished', data);
            clearUserDuel(data.opponent_id, duelId);
        }

        console.log('✅ duel-finished event gönderildi');
        res.json({ success: true });
    } catch (error) {
        console.error('❌ Duel finished webhook error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// Health check endpoint
/** Acil reset: kuyruk/map/timer belleğini temizle (DB kapatıldıktan sonra). */
app.post('/socket-webhooks/emergency-purge', (req, res) => {
    const secret = req.header('X-Socket-Secret') || req.body?.secret;
    if (secret !== SOCKET_INTERNAL_SECRET) {
        return res.status(401).json({ success: false, message: 'Unauthorized' });
    }

    let queueEntries = 0;
    for (const queue of duelMatchQueues.values()) {
        queueEntries += queue?.length || 0;
    }

    const cleared = {
        queue_entries: queueEntries,
        user_duel_map: userDuelMap.size,
        disconnect_timers: duelDisconnectTimers.size,
        bot_fallback_timers: botFallbackTimers.size,
        match_cancelled: duelMatchCancelled.size,
        match_locks: duelMatchLocks.size,
    };

    for (const tid of duelDisconnectTimers.values()) {
        clearTimeout(tid);
    }
    duelDisconnectTimers.clear();

    for (const tid of botFallbackTimers.values()) {
        clearTimeout(tid);
    }
    botFallbackTimers.clear();

    duelMatchQueues.clear();
    userDuelMap.clear();
    duelMatchCancelled.clear();
    duelMatchLocks.clear();

    console.log('🧹 Emergency purge:', JSON.stringify(cleared));

    return res.json({
        success: true,
        message: 'Socket belleği temizlendi.',
        cleared,
    });
});

app.get('/socket-webhooks/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        connectedUsers: connectedUsers.size,
        activeRooms: userRooms.size
    });
});

// Socket'e bağlı (çevrimiçi) kullanıcı listesi — admin panel için
app.get('/socket-webhooks/online-users', (req, res) => {
    const userIds = [];
    for (const [userId, socketId] of userSocketMap.entries()) {
        if (socketId && connectedUsers.has(socketId)) {
            userIds.push(parseInt(userId, 10));
        }
    }

    res.json({
        success: true,
        count: userIds.length,
        userIds,
        timestamp: new Date().toISOString()
    });
});

// Meydan oku (duel-ready) kuyruğundaki kullanıcılar — bot sadece bunlarla eşleşmeli
app.get('/socket-webhooks/duel-ready-queue', (req, res) => {
    const entries = [];
    for (const [multiplier, queue] of duelMatchQueues.entries()) {
        for (const entry of queue) {
            entries.push({
                userId: entry.userId,
                multiplier: entry.multiplier || multiplier,
                joinedAt: entry.joinedAt || null,
                waitingMs: entry.joinedAt ? (Date.now() - entry.joinedAt) : null,
            });
        }
    }

    res.json({
        success: true,
        count: entries.length,
        entries,
        userIds: entries.map((e) => e.userId),
        timestamp: new Date().toISOString(),
    });
});

// Kullanıcı bağlantı durumu kontrolü
app.post('/socket-webhooks/check-user-connection', (req, res) => {
    const userId = parseInt(req.body?.userId, 10);

    if (!userId) {
        return res.status(400).json({
            success: false,
            message: 'userId gerekli'
        });
    }

    const socketId = userSocketMap.get(userId);
    const isConnected = !!(socketId && connectedUsers.has(socketId));

    res.json({
        success: true,
        userId: userId,
        isConnected: isConnected,
        socketId: socketId || null
    });
});

// Çoklu kullanıcı bağlantı durumu kontrolü
app.post('/socket-webhooks/check-users-connection', (req, res) => {
    const { userIds } = req.body;

    if (!userIds || !Array.isArray(userIds)) {
        return res.status(400).json({
            success: false,
            message: 'userIds array gerekli'
        });
    }

    const connectionStatus = {};

    userIds.forEach(rawUserId => {
        const userId = parseInt(rawUserId, 10);
        const socketId = userSocketMap.get(userId);
        const isConnected = !!(socketId && connectedUsers.has(socketId));
        connectionStatus[userId] = {
            isConnected: isConnected,
            socketId: socketId || null
        };
    });

    res.json({
        success: true,
        connectionStatus: connectionStatus
    });
});

const PORT = process.env.PORT || 3001;

server.listen(PORT, () => {
    console.log(`🚀 Socket.IO sunucusu ${PORT} portunda çalışıyor`);
    console.log(`📡 WebSocket URL: wss://bilbakalim.online/socket.io`);
    console.log(`🔒 HTTPS aktif`);
    console.log(`📋 Health check: https://bilbakalim.online/socket-webhooks/health`);
    startBotFallbackScanner();
    hydrateUserDuelMapFromLaravel().catch(() => {});
});
