const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const fs = require('fs');
const https = require('https');

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
const connectedUsers = new Map();
const userRooms = new Map();
const userSocketMap = new Map(); // userId -> socketId mapping

// Socket bağlantıları
io.on('connection', (socket) => {
    console.log(`🔌 Yeni bağlantı: ${socket.id}`);

    // Kullanıcı girişi
    socket.on('user_join', (data) => {
        const { userId, userName } = data;

        connectedUsers.set(socket.id, { userId, userName });
        userSocketMap.set(userId, socket.id);
        socket.join(`user_${userId}`);

        console.log(`👤 Kullanıcı girişi: ${userName} (${userId})`);

        // Kullanıcıya hoş geldin mesajı gönder
        socket.emit('welcome', {
            message: `Hoş geldin ${userName}!`,
            userId: userId,
            timestamp: new Date().toISOString()
        });
    });

    // Turnuva odasına katılma
    socket.on('join_tournament', (data) => {
        const { tournamentId, userId } = data;
        
        if (!tournamentId || !userId) {
            socket.emit('join_tournament_error', {
                success: false,
                message: 'tournamentId ve userId gereklidir'
            });
            return;
        }

        const roomName = `tournament_${tournamentId}`;
        const user = connectedUsers.get(socket.id);

        // Kullanıcı bilgilerini güncelle veya oluştur
        if (!user) {
            connectedUsers.set(socket.id, { userId, userName: `User ${userId}` });
            userSocketMap.set(userId, socket.id);
            socket.join(`user_${userId}`);
        }

        socket.join(roomName);
        userRooms.set(socket.id, roomName);

        console.log(`🏆 Turnuva odasına katıldı: User ${userId} -> Tournament ${tournamentId}`);

        // Başarılı response dön
        socket.emit('joined_tournament', {
            success: true,
            tournamentId: tournamentId,
            userId: userId,
            message: 'Turnuva odasına katıldınız',
            timestamp: new Date().toISOString()
        });
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
                io.to(room).emit('user-left-tournament', {
                    tournament_id: tournamentId,
                    user_id: user.userId,
                    user_name: user.userName,
                    reason: 'disconnected',
                    timestamp: new Date().toISOString()
                });
            }
            
            userRooms.delete(socket.id);
            console.log(`🚪 Odadan ayrıldı: ${room}`);
        }

        if (user) {
            // Kullanıcı odasından çık
            socket.leave(`user_${user.userId}`);
            userRooms.delete(user.userId);
            connectedUsers.delete(socket.id);
            userSocketMap.delete(user.userId);
            console.log(`Kullanıcı bağlantısı kesildi: ${user.userId}`);
        }
    });
});

// ===== TURNUVA WEBHOOK'LARI =====

app.post('/socket-webhooks/webhook/user-joined-tournament', (req, res) => {
    const { tournament_id, user_id, user_name, current_participants, min_participants, waiting_message, ready_to_start } = req.body;

    const joinData = {
        tournament_id,
        user_id,
        name: user_name,
        current_participants: current_participants || 0,
        min_participants: min_participants || 2,
        ready_to_start: ready_to_start || false,
        waiting_message: waiting_message || null,
        timestamp: new Date().toISOString()
    };

    // Turnuva odasına katılım bildirimi (yeni format)
    io.to(`tournament_${tournament_id}`).emit('user-joined-tournament', joinData);
    
    // Eski format (geriye uyumluluk için)
    io.to(`tournament_${tournament_id}`).emit('user_joined_tournament', {
        tournament_id,
        user_id,
        user_name,
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
    const isReady = ready_to_start || (current_participants >= min_participants);
    const waitingMsg = waiting_message || (
        isReady 
            ? `Turnuva başlamaya hazır! (${current_participants}/${min_participants})`
            : `Diğer oyuncular bekleniyor... (${current_participants}/${min_participants})`
    );

    io.to(`tournament_${tournament_id}`).emit('waiting-players', {
        tournament_id,
        current_participants: current_participants || 0,
        min_participants: min_participants || 2,
        ready_to_start: isReady,
        waiting_message: waitingMsg,
        timestamp: new Date().toISOString()
    });

    console.log(`Kullanıcı turnuvaya katıldı: ${user_name} (${user_id}) - Tournament ${tournament_id}, Participants: ${current_participants}/${min_participants}`);
    res.json({ success: true, message: 'User joined tournament webhook processed' });
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
    const { tournament_id, user_id, question_id, is_correct, score_change, current_score, speed_bonus, leaderboard } = req.body;
    
    io.to(`tournament_${tournament_id}`).emit('tournament-answer-submitted', {
        tournament_id,
        user_id,
        question_id,
        is_correct,
        score: current_score,
        score_change: score_change || 0,
        speed_bonus: speed_bonus || 0,
        leaderboard: leaderboard || [],
        timestamp: new Date().toISOString()
    });
    
    console.log(`Turnuva cevabı gönderildi: Tournament ${tournament_id}, User ${user_id}, Correct: ${is_correct}, Score: ${current_score}`);
    res.json({ success: true, message: 'Tournament answer submitted webhook processed' });
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

// Health check endpoint
app.get('/socket-webhooks/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        connectedUsers: connectedUsers.size,
        activeRooms: userRooms.size
    });
});

// Kullanıcı bağlantı durumu kontrolü
app.post('/socket-webhooks/check-user-connection', (req, res) => {
    const { userId } = req.body;

    if (!userId) {
        return res.status(400).json({
            success: false,
            message: 'userId gerekli'
        });
    }

    const socketId = userSocketMap.get(userId);
    const isConnected = socketId && connectedUsers.has(socketId);

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

    userIds.forEach(userId => {
        const socketId = userSocketMap.get(userId);
        const isConnected = socketId && connectedUsers.has(socketId);
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
});
