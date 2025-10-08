const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);

// CORS ayarları
app.use(cors({
  origin: "*",
  methods: ["GET", "POST"],
  credentials: true
}));

app.use(express.json());

// Socket.IO ayarları
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"],
    credentials: true
  }
});

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
    userSocketMap.set(userId, socket.id); // userId -> socketId mapping
    socket.join(`user_${userId}`);

    console.log(`👤 Kullanıcı girişi: ${userName} (${userId})`);

    // Kullanıcıya hoş geldin mesajı gönder
    socket.emit('welcome', {
      message: `Hoş geldin ${userName}!`,
      userId: userId,
      timestamp: new Date().toISOString()
    });
  });

  // Normal quiz odasına katılma
  socket.on('join_normal_quiz', (data) => {
    const { gameId, userId } = data;
    const roomName = `normal_quiz_${gameId}`;

    socket.join(roomName);
    userRooms.set(socket.id, roomName);

    console.log(`🎮 Normal quiz odasına katıldı: User ${userId} -> Room ${roomName}`);

    socket.emit('joined_room', {
      room: roomName,
      gameId: gameId,
      message: 'Normal quiz odasına katıldınız'
    });
  });

  // Premium quiz odasına katılma
  socket.on('join_premium_quiz', (data) => {
    const { gameId, userId } = data;
    const roomName = `premium_quiz_${gameId}`;

    socket.join(roomName);
    userRooms.set(socket.id, roomName);

    console.log(`💎 Premium quiz odasına katıldı: User ${userId} -> Room ${roomName}`);

    socket.emit('joined_room', {
      room: roomName,
      gameId: gameId,
      message: 'Premium quiz odasına katıldınız'
    });
  });

  // Turnuva odasına katılma
  socket.on('join_tournament', (data) => {
    const { tournamentId, userId } = data;
    const roomName = `tournament_${tournamentId}`;

    socket.join(roomName);
    userRooms.set(socket.id, roomName);

    console.log(`🏆 Turnuva odasına katıldı: User ${userId} -> Tournament ${tournamentId}`);

    socket.emit('joined_tournament', {
      tournamentId: tournamentId,
      message: 'Turnuva odasına katıldınız'
    });
  });

  // Quiz cevabı gönderme
  socket.on('quiz_answer', (data) => {
    const { gameId, userId, questionId, selectedOption, timeSpent } = data;

    console.log(`📝 Quiz cevabı: Game ${gameId}, User ${userId}, Question ${questionId}, Option ${selectedOption}, Time ${timeSpent}s`);

    // Cevap işlendikten sonra sonucu gönder
    socket.emit('quiz_answer_result', {
      gameId,
      userId,
      questionId,
      selectedOption,
      timeSpent,
      timestamp: new Date().toISOString()
    });
  });

  // Joker kullanma
  socket.on('quiz_joker_used', (data) => {
    const { gameId, userId, jokerType, result } = data;

    console.log(`🎯 Joker kullanıldı: Game ${gameId}, User ${userId}, Type: ${jokerType}`);

    // Joker sonucunu gönder
    socket.emit('joker_result', {
      gameId,
      userId,
      jokerType,
      result,
      timestamp: new Date().toISOString()
    });
  });

  // Turnuva cevabı gönderme
  socket.on('tournament_answer', (data) => {
    const { tournamentId, userId, questionId, selectedOption, timeSpent } = data;

    console.log(`🏆 Turnuva cevabı: Tournament ${tournamentId}, User ${userId}, Question ${questionId}, Option ${selectedOption}`);

    // Turnuva odasına cevap bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('tournament_answer_received', {
      tournamentId,
      userId,
      questionId,
      selectedOption,
      timeSpent,
      timestamp: new Date().toISOString()
    });
  });

  // Bağlantı kesilme
  socket.on('disconnect', () => {
    const user = connectedUsers.get(socket.id);
    const room = userRooms.get(socket.id);

    if (room) {
      socket.leave(room);
      userRooms.delete(socket.id);
      console.log(`🚪 Odadan ayrıldı: ${room}`);
    }

    if (user) {
      userRooms.delete(user.userId);
      connectedUsers.delete(socket.id);
      userSocketMap.delete(user.userId); // userId mapping'i de temizle
      console.log(`Kullanıcı bağlantısı kesildi: ${user.userId}`);
    }
  });
});

// ===== QUIZ WEBHOOK'LARI =====

app.post('/webhook/quiz-started', (req, res) => {
    console.log('Quiz started webhook received:', req.body);
    const { game_id, user_id, game_type, question, timestamp } = req.body;

    // Quiz başlatma bildirimi
    const roomName = game_type === 'premium' ? `premium_quiz_${game_id}` : `normal_quiz_${game_id}`;
    io.to(roomName).emit('quiz_started', {
        gameId: game_id,
        userId: user_id,
        gameType: game_type,
      question,
      timestamp: new Date()
    });

    // Kullanıcıya özel bildirim
    io.to(`user_${user_id}`).emit('quiz_started', {
        gameId: game_id,
        gameType: game_type,
      question,
      timestamp: new Date()
    });

    console.log(`Quiz başlatıldı: Game ${game_id}, User ${user_id}, Type: ${game_type}`);
    res.json({ success: true, message: 'Quiz started webhook processed' });
});

app.post('/webhook/quiz-answer-submitted', (req, res) => {
    console.log('Quiz answer webhook received:', req.body);
    const { game_id, user_id, question_id, is_correct, coins_earned, game_type, user_coins, game_stats, timestamp } = req.body;

    // Quiz cevap bildirimi
    const roomName = game_type === 'premium' ? `premium_quiz_${game_id}` : `normal_quiz_${game_id}`;
    io.to(roomName).emit('quiz_answer_result', {
        gameId: game_id,
        userId: user_id,
        questionId: question_id,
        isCorrect: is_correct,
        coinsEarned: coins_earned,
        gameType: game_type,
        userCoins: user_coins,
        gameStats: game_stats,
      timestamp: new Date()
    });

    // Kullanıcıya özel bildirim
    io.to(`user_${user_id}`).emit('quiz_answer_result', {
        gameId: game_id,
        questionId: question_id,
        isCorrect: is_correct,
        coinsEarned: coins_earned,
        gameType: game_type,
        userCoins: user_coins,
        gameStats: game_stats,
      timestamp: new Date()
    });

    console.log(`Quiz cevabı: Game ${game_id}, User ${user_id}, Type: ${game_type}, Correct: ${is_correct}, Coins: ${coins_earned}`);
    res.json({ success: true, message: 'Quiz answer submitted webhook processed' });
});

app.post('/webhook/quiz-joker-used', (req, res) => {
    const { game_id, user_id, joker_type, result, timestamp } = req.body;

    // Premium quiz joker bildirimi
    const roomName = `premium_quiz_${game_id}`;
    io.to(roomName).emit('joker_used', {
        gameId: game_id,
        userId: user_id,
        jokerType: joker_type,
        result: result,
      timestamp: new Date()
    });

    // Kullanıcıya özel bildirim
    io.to(`user_${user_id}`).emit('joker_used', {
        gameId: game_id,
        jokerType: joker_type,
        result: result,
      timestamp: new Date()
    });

    console.log(`Quiz joker kullanıldı: Game ${game_id}, User ${user_id}, Type: ${joker_type}`);
    res.json({ success: true, message: 'Quiz joker used webhook processed' });
});

app.post('/webhook/quiz-completed', (req, res) => {
    const { game_id, user_id, game_type, final_stats, answer_details, reward, timestamp } = req.body;

    // Quiz tamamlanma bildirimi
    const roomName = game_type === 'premium' ? `premium_quiz_${game_id}` : `normal_quiz_${game_id}`;
    io.to(roomName).emit('quiz_completed', {
        gameId: game_id,
        userId: user_id,
        gameType: game_type,
        finalStats: final_stats,
        answerDetails: answer_details,
        reward: reward,
    timestamp: new Date()
  });

    // Kullanıcıya özel bildirim
    io.to(`user_${user_id}`).emit('quiz_completed', {
        gameId: game_id,
        gameType: game_type,
        finalStats: final_stats,
        answerDetails: answer_details,
        reward: reward,
    timestamp: new Date()
  });

    console.log(`Quiz tamamlandı: Game ${game_id}, User ${user_id}, Type: ${game_type}`);
    res.json({ success: true, message: 'Quiz completed webhook processed' });
});

// ===== TURNUVA WEBHOOK'LARI =====

app.post('/webhook/user-joined-tournament', (req, res) => {
    const { tournament_id, user_id, user_name, current_participants, min_participants, waiting_message } = req.body;

    // Turnuva odasına katılım bildirimi
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

    console.log(`Kullanıcı turnuvaya katıldı: ${user_name} (${user_id}) - Tournament ${tournament_id}, Participants: ${current_participants}/${min_participants}`);
    res.json({ success: true, message: 'User joined tournament webhook processed' });
});

app.post('/webhook/tournament-started', (req, res) => {
    const { tournament_id, participants, question_count } = req.body;
    io.to(`tournament_${tournament_id}`).emit('tournament_started', {
        tournament_id, participants, question_count, timestamp: new Date().toISOString()
    });
    console.log(`Turnuva başladı: Tournament ${tournament_id}, Participants: ${participants.length}`);
    res.json({ success: true, message: 'Tournament started webhook processed' });
});

app.post('/webhook/tournament-finished', (req, res) => {
    const { tournament_id, final_rankings, winners } = req.body;
    io.to(`tournament_${tournament_id}`).emit('tournament_finished', {
        tournament_id, final_rankings, winners, timestamp: new Date().toISOString()
    });
    console.log(`Turnuva bitti: Tournament ${tournament_id}`);
    res.json({ success: true, message: 'Tournament finished webhook processed' });
});

app.post('/webhook/tournament-next-question', (req, res) => {
    const { tournament_id, question, question_number, total_questions } = req.body;
    io.to(`tournament_${tournament_id}`).emit('tournament_next_question', {
        tournament_id, question, question_number, total_questions, timestamp: new Date().toISOString()
    });
    console.log(`Turnuva sonraki soru: Tournament ${tournament_id}, Question ${question_number}/${total_questions}`);
    res.json({ success: true, message: 'Tournament next question webhook processed' });
});

app.post('/webhook/tournament-player-eliminated', (req, res) => {
    const { tournament_id, user_id, reason, remaining_players } = req.body;
    io.to(`tournament_${tournament_id}`).emit('tournament_player_eliminated', {
        tournament_id, user_id, reason, remaining_players, timestamp: new Date().toISOString()
    });
    console.log(`Turnuva oyuncusu elendi: User ${user_id} - Tournament ${tournament_id}, Reason: ${reason}`);
    res.json({ success: true, message: 'Tournament player eliminated webhook processed' });
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        connectedUsers: connectedUsers.size,
        activeRooms: userRooms.size
    });
});

// Kullanıcı bağlantı durumu kontrolü
app.post('/check-user-connection', (req, res) => {
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
app.post('/check-users-connection', (req, res) => {
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

// Test için kullanıcı bağlantısı simülasyonu
app.post('/simulate-user-connection', (req, res) => {
    const { userId, userName } = req.body;

    if (!userId) {
        return res.status(400).json({
            success: false,
            message: 'userId gerekli'
        });
    }

    const socketId = `test_socket_${userId}`;
    const userData = { userId, userName: userName || `Test User ${userId}` };

    // Kullanıcıyı bağlı olarak işaretle
    connectedUsers.set(socketId, userData);
    userSocketMap.set(userId, socketId);

    console.log(`🧪 Test: Kullanıcı ${userId} bağlantısı simüle edildi`);

    res.json({
        success: true,
        message: `Kullanıcı ${userId} bağlantısı simüle edildi`,
        userId: userId,
        socketId: socketId
    });
});

// Test için kullanıcı bağlantısını kes
app.post('/simulate-user-disconnection', (req, res) => {
    const { userId } = req.body;

    if (!userId) {
        return res.status(400).json({
            success: false,
            message: 'userId gerekli'
        });
    }

    const socketId = userSocketMap.get(userId);

    if (socketId) {
        connectedUsers.delete(socketId);
        userSocketMap.delete(userId);
        console.log(`🧪 Test: Kullanıcı ${userId} bağlantısı kesildi`);

        res.json({
            success: true,
            message: `Kullanıcı ${userId} bağlantısı kesildi`,
            userId: userId
        });
    } else {
        res.json({
            success: false,
            message: `Kullanıcı ${userId} bulunamadı`,
            userId: userId
        });
    }
});

const PORT = process.env.PORT || 3001;

server.listen(PORT, () => {
    console.log('🚀 Socket.IO sunucusu 3001 portunda çalışıyor');
    console.log('📡 WebSocket URL: ws://localhost:3001');
});
