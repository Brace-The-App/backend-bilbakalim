const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const axios = require('axios');

const app = express();
const server = http.createServer(app);

// CORS yapılandırması
const io = socketIo(server, {
  cors: {
    origin: ["http://localhost", "http://localhost:3000", "http://localhost:8000","https://bilbakalim.online"],
    methods: ["GET", "POST"],
    credentials: true
  }
});

// Middleware
app.use(cors());
app.use(express.json());

// Laravel API base URL
const LARAVEL_API_URL = 'http://laravel.test/api';

// Bağlı kullanıcıları takip et
const connectedUsers = new Map();
const userRooms = new Map();

// Socket bağlantıları
io.on('connection', (socket) => {
  console.log(`🔌 Yeni kullanıcı bağlandı: ${socket.id} - ${new Date().toISOString()}`);

  // Kullanıcı girişi
  socket.on('user_login', (data) => {
    const { userId, token } = data;
    connectedUsers.set(socket.id, { userId, token, socketId: socket.id });
    userRooms.set(userId, socket.id);

    // Kullanıcıyı kendi odasına ekle
    socket.join(`user_${userId}`);

    console.log(`👤 Kullanıcı giriş yaptı: ${userId} - Socket: ${socket.id} - ${new Date().toISOString()}`);
    socket.emit('login_success', { message: 'Başarıyla giriş yaptınız' });
  });

  // Kullanıcı çıkışı
  socket.on('user_logout', () => {
    const user = connectedUsers.get(socket.id);
    if (user) {
      userRooms.delete(user.userId);
      connectedUsers.delete(socket.id);
      console.log(`Kullanıcı çıkış yaptı: ${user.userId}`);
    }
  });

  // Soru güncellemelerini dinle
  socket.on('subscribe_questions', (data) => {
    const { categoryId, tournamentId } = data;

    if (categoryId) {
      socket.join(`category_${categoryId}`);
      console.log(`Kullanıcı kategori ${categoryId} sorularını dinliyor`);
    }

    if (tournamentId) {
      socket.join(`tournament_${tournamentId}`);
      console.log(`Kullanıcı turnuva ${tournamentId} sorularını dinliyor`);
    }
  });

  // Soru güncellemelerini dinlemeyi bırak
  socket.on('unsubscribe_questions', (data) => {
    const { categoryId, tournamentId } = data;

    if (categoryId) {
      socket.leave(`category_${categoryId}`);
    }

    if (tournamentId) {
      socket.leave(`tournament_${tournamentId}`);
    }
  });

  // Bireysel oyun event'leri
  socket.on('join_individual_game', (data) => {
    const { gameId, sessionId } = data;
    socket.join(`individual_game_${gameId}`);
    socket.join(`session_${sessionId}`);
    console.log(`Kullanıcı bireysel oyuna katıldı: Game ${gameId}, Session ${sessionId}`);
  });

  socket.on('leave_individual_game', (data) => {
    const { gameId, sessionId } = data;
    socket.leave(`individual_game_${gameId}`);
    socket.leave(`session_${sessionId}`);
    console.log(`Kullanıcı bireysel oyundan ayrıldı: Game ${gameId}, Session ${sessionId}`);
  });

  socket.on('answer_submitted', (data) => {
    const { gameId, sessionId, questionId, isCorrect, pointsEarned } = data;
    
    // Oyun odasına cevap bildirimi gönder
    io.to(`individual_game_${gameId}`).emit('answer_result', {
      sessionId,
      questionId,
      isCorrect,
      pointsEarned,
      timestamp: new Date()
    });
    
    console.log(`Cevap gönderildi: Game ${gameId}, Session ${sessionId}, Question ${questionId}, Correct: ${isCorrect}`);
  });

  socket.on('game_progress_update', (data) => {
    const { gameId, sessionId, progress } = data;
    
    // Oyun odasına ilerleme güncellemesi gönder
    io.to(`individual_game_${gameId}`).emit('progress_updated', {
      sessionId,
      progress,
      timestamp: new Date()
    });
  });

  socket.on('game_completed', (data) => {
    const { gameId, sessionId, finalScore, coinsEarned } = data;
    
    // Oyun odasına tamamlama bildirimi gönder
    io.to(`individual_game_${gameId}`).emit('game_finished', {
      sessionId,
      finalScore,
      coinsEarned,
      timestamp: new Date()
    });
    
    console.log(`Oyun tamamlandı: Game ${gameId}, Session ${sessionId}, Score: ${finalScore}`);
  });

  // Turnuva event'leri
  socket.on('join_tournament', (data) => {
    const { tournamentId, userId, tournamentType } = data;
    socket.join(`tournament_${tournamentId}`);
    socket.join(`tournament_user_${userId}`);
    console.log(`Kullanıcı turnuvaya katıldı: Tournament ${tournamentId}, User ${userId}, Type: ${tournamentType}`);
    
    // Turnuva odasına katılım bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('user_joined_tournament', {
      userId,
      tournamentId,
      tournamentType,
      timestamp: new Date()
    });
  });

  socket.on('leave_tournament', (data) => {
    const { tournamentId, userId } = data;
    socket.leave(`tournament_${tournamentId}`);
    socket.leave(`tournament_user_${userId}`);
    console.log(`Kullanıcı turnuvadan ayrıldı: Tournament ${tournamentId}, User ${userId}`);
  });

  socket.on('tournament_answer_submitted', (data) => {
    const { tournamentId, userId, questionId, isCorrect, pointsEarned, timeTaken } = data;
    
    // Turnuva odasına cevap bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('tournament_answer_result', {
      userId,
      questionId,
      isCorrect,
      pointsEarned,
      timeTaken,
      timestamp: new Date()
    });
    
    console.log(`Turnuva cevabı: Tournament ${tournamentId}, User ${userId}, Question ${questionId}, Correct: ${isCorrect}`);
  });

  socket.on('tournament_progress_update', (data) => {
    const { tournamentId, userId, progress, currentQuestion, timeRemaining } = data;
    
    // Turnuva odasına ilerleme güncellemesi gönder
    io.to(`tournament_${tournamentId}`).emit('tournament_progress_updated', {
      userId,
      progress,
      currentQuestion,
      timeRemaining,
      timestamp: new Date()
    });
  });

  socket.on('tournament_completed', (data) => {
    const { tournamentId, userId, finalScore, rank, totalParticipants } = data;
    
    // Turnuva odasına tamamlama bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('tournament_finished', {
      userId,
      finalScore,
      rank,
      totalParticipants,
      timestamp: new Date()
    });
    
    console.log(`Turnuva tamamlandı: Tournament ${tournamentId}, User ${userId}, Score: ${finalScore}, Rank: ${rank}`);
  });

  socket.on('tournament_leaderboard_update', (data) => {
    const { tournamentId, leaderboard, tournamentType } = data;
    
    // Turnuva odasına liderlik tablosu güncellemesi gönder
    io.to(`tournament_${tournamentId}`).emit('leaderboard_updated', {
      leaderboard,
      tournamentType,
      timestamp: new Date()
    });
  });

  // Çoklu kullanıcı turnuva event'leri
  socket.on('multiplayer_tournament_start', (data) => {
    const { tournamentId, participants, questions } = data;
    
    // Tüm katılımcılara turnuva başlama bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('multiplayer_tournament_started', {
      tournamentId,
      participants: participants.length,
      questions: questions.length,
      timestamp: new Date()
    });
    
    console.log(`Çoklu kullanıcı turnuvası başladı: Tournament ${tournamentId}, Participants: ${participants.length}`);
  });

  socket.on('multiplayer_answer_submitted', (data) => {
    const { tournamentId, userId, questionId, isCorrect, timeTaken, pointsEarned } = data;
    
    // Tüm katılımcılara cevap bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('multiplayer_answer_result', {
      userId,
      questionId,
      isCorrect,
      timeTaken,
      pointsEarned,
      timestamp: new Date()
    });
    
    console.log(`Çoklu turnuva cevabı: Tournament ${tournamentId}, User ${userId}, Correct: ${isCorrect}, Time: ${timeTaken}ms`);
  });

  socket.on('multiplayer_ranking_update', (data) => {
    const { tournamentId, rankings, currentQuestion } = data;
    
    // Tüm katılımcılara sıralama güncellemesi gönder
    io.to(`tournament_${tournamentId}`).emit('multiplayer_ranking_updated', {
      rankings,
      currentQuestion,
      timestamp: new Date()
    });
  });

  socket.on('multiplayer_tournament_finished', (data) => {
    const { tournamentId, finalRankings, winners } = data;
    
    // Tüm katılımcılara turnuva bitiş bildirimi gönder
    io.to(`tournament_${tournamentId}`).emit('multiplayer_tournament_ended', {
      finalRankings,
      winners,
      timestamp: new Date()
    });
    
    console.log(`Çoklu kullanıcı turnuvası bitti: Tournament ${tournamentId}, Winners: ${winners.length}`);
  });

  // Bağlantı kesildiğinde
  socket.on('disconnect', () => {
    const user = connectedUsers.get(socket.id);
    if (user) {
      userRooms.delete(user.userId);
      connectedUsers.delete(socket.id);
      console.log(`Kullanıcı bağlantısı kesildi: ${user.userId}`);
    }
  });
});

// Laravel'den gelen webhook'ları dinle
app.post('/webhook/question-created', (req, res) => {
  const { question, categoryId, tournamentId } = req.body;

  console.log('🆕 YENİ SORU OLUŞTURULDU:', {
    questionId: question?.id,
    questionText: question?.question?.tr || 'N/A',
    categoryId: categoryId,
    tournamentId: tournamentId,
    timestamp: new Date().toISOString()
  });

  // Kategori odasına bildir
  if (categoryId) {
    io.to(`category_${categoryId}`).emit('question_created', {
      question,
      categoryId,
      timestamp: new Date()
    });
    console.log(`📡 Kategori ${categoryId} odasına bildirim gönderildi`);
  }

  // Turnuva odasına bildir
  if (tournamentId) {
    io.to(`tournament_${tournamentId}`).emit('question_created', {
      question,
      tournamentId,
      timestamp: new Date()
    });
    console.log(`📡 Turnuva ${tournamentId} odasına bildirim gönderildi`);
  }

  res.json({ success: true, message: 'Soru güncellemesi gönderildi' });
});

app.post('/webhook/question-updated', (req, res) => {
  const { question, categoryId, tournamentId } = req.body;

  console.log('✏️ SORU GÜNCELLENDİ:', {
    questionId: question?.id,
    questionText: question?.question?.tr || 'N/A',
    categoryId: categoryId,
    tournamentId: tournamentId,
    timestamp: new Date().toISOString()
  });

  if (categoryId) {
    io.to(`category_${categoryId}`).emit('question_updated', {
      question,
      categoryId,
      timestamp: new Date()
    });
    console.log(`📡 Kategori ${categoryId} odasına güncelleme bildirimi gönderildi`);
  }

  if (tournamentId) {
    io.to(`tournament_${tournamentId}`).emit('question_updated', {
      question,
      tournamentId,
      timestamp: new Date()
    });
    console.log(`📡 Turnuva ${tournamentId} odasına güncelleme bildirimi gönderildi`);
  }

  res.json({ success: true, message: 'Soru güncellemesi gönderildi' });
});

app.post('/webhook/question-deleted', (req, res) => {
  const { questionId, categoryId, tournamentId } = req.body;

  console.log('🗑️ SORU SİLİNDİ:', {
    questionId: questionId,
    categoryId: categoryId,
    tournamentId: tournamentId,
    timestamp: new Date().toISOString()
  });

  if (categoryId) {
    io.to(`category_${categoryId}`).emit('question_deleted', {
      questionId,
      categoryId,
      timestamp: new Date()
    });
    console.log(`📡 Kategori ${categoryId} odasına silme bildirimi gönderildi`);
  }

  if (tournamentId) {
    io.to(`tournament_${tournamentId}`).emit('question_deleted', {
      questionId,
      tournamentId,
      timestamp: new Date()
    });
    console.log(`📡 Turnuva ${tournamentId} odasına silme bildirimi gönderildi`);
  }

  res.json({ success: true, message: 'Soru silme bildirimi gönderildi' });
});

// Kategori güncellemeleri
app.post('/webhook/category-updated', (req, res) => {
  const { category } = req.body;

  io.emit('category_updated', {
    category,
    timestamp: new Date()
  });

  res.json({ success: true, message: 'Kategori güncellemesi gönderildi' });
});

// Turnuva güncellemeleri
app.post('/webhook/tournament-updated', (req, res) => {
  const { tournament } = req.body;

  io.emit('tournament_updated', {
    tournament,
    timestamp: new Date()
  });

  res.json({ success: true, message: 'Turnuva güncellemesi gönderildi' });
});

// Soru listesi endpoint'i
app.get('/api/questions', async (req, res) => {
  try {
    const { categoryId, search, page = 1, perPage = 15 } = req.query;

    // Laravel API'den soruları çek (public endpoint)
    let url = `${LARAVEL_API_URL}/questions?page=${page}&per_page=${perPage}`;

    if (categoryId) {
      url += `&category_id=${categoryId}`;
    }

    if (search) {
      url += `&search=${encodeURIComponent(search)}`;
    }

    const response = await axios.get(url, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    console.log('📋 Sorular getirildi:', {
      count: response.data.data.data.length,
      page: page,
      categoryId: categoryId,
      search: search,
      timestamp: new Date().toISOString()
    });

    res.json({
      success: true,
      data: response.data.data,
      meta: response.data.meta
    });
  } catch (error) {
    console.error('Soru listesi hatası:', error.message);
    res.status(500).json({
      success: false,
      message: 'Sorular yüklenirken hata oluştu',
      error: error.message
    });
  }
});

// Kategori listesi endpoint'i
app.get('/api/categories', async (req, res) => {
  try {
    const response = await axios.get(`${LARAVEL_API_URL}/categories`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    res.json({
      success: true,
      data: response.data.data,
      meta: response.data.meta
    });
  } catch (error) {
    console.error('Kategori listesi hatası:', error.message);
    res.status(500).json({
      success: false,
      message: 'Kategoriler yüklenirken hata oluştu',
      error: error.message
    });
  }
});

// Sunucu durumu
app.get('/health', (req, res) => {
  res.json({
    status: 'OK',
    connectedUsers: connectedUsers.size,
    uptime: process.uptime()
  });
});

// ===== TURNUVA WEBHOOK'LARI =====

app.post('/webhook/tournament-started', (req, res) => {
    const { tournament, participants, timestamp } = req.body;
    io.emit('tournament_started', { tournament, participants, timestamp });
    console.log(`Turnuva başlatıldı: ${tournament.title}, Katılımcılar: ${participants.length}`);
    res.json({ success: true, message: 'Tournament started webhook processed' });
});

app.post('/webhook/tournament-finished', (req, res) => {
    const { tournament, finalRankings, timestamp } = req.body;
    io.emit('tournament_finished', { tournament, finalRankings, timestamp });
    console.log(`Turnuva bitti: ${tournament.title}, Final sıralama: ${finalRankings.length} oyuncu`);
    res.json({ success: true, message: 'Tournament finished webhook processed' });
});

app.post('/webhook/user-joined-tournament', (req, res) => {
    const { tournamentId, userId, userData, timestamp } = req.body;
    io.to(`tournament_${tournamentId}`).emit('user_joined_tournament', { 
        tournamentId, userId, userData, timestamp 
    });
    console.log(`Kullanıcı turnuvaya katıldı: Tournament ${tournamentId}, User ${userId}`);
    res.json({ success: true, message: 'User joined tournament webhook processed' });
});

app.post('/webhook/user-left-tournament', (req, res) => {
    const { tournamentId, userId, timestamp } = req.body;
    io.to(`tournament_${tournamentId}`).emit('user_left_tournament', { 
        tournamentId, userId, timestamp 
    });
    console.log(`Kullanıcı turnuvadan ayrıldı: Tournament ${tournamentId}, User ${userId}`);
    res.json({ success: true, message: 'User left tournament webhook processed' });
});

// ===== BİREYSEL OYUN WEBHOOK'LARI =====

app.post('/webhook/individual-game-started', (req, res) => {
    const { gameId, userId, gameData, timestamp } = req.body;
    io.to(`individual_game_${gameId}`).emit('individual_game_started', { 
        gameId, userId, gameData, timestamp 
    });
    console.log(`Bireysel oyun başlatıldı: Game ${gameId}, User ${userId}`);
    res.json({ success: true, message: 'Individual game started webhook processed' });
});

app.post('/webhook/individual-game-finished', (req, res) => {
    const { gameId, userId, finalScore, timestamp } = req.body;
    io.to(`individual_game_${gameId}`).emit('individual_game_finished', { 
        gameId, userId, finalScore, timestamp 
    });
    console.log(`Bireysel oyun bitti: Game ${gameId}, User ${userId}, Score: ${finalScore}`);
    res.json({ success: true, message: 'Individual game finished webhook processed' });
});

// ===== CEVAP WEBHOOK'LARI =====

app.post('/webhook/answer-submitted', (req, res) => {
    const { gameId, userId, questionId, isCorrect, pointsEarned, timeTaken, timestamp } = req.body;
    io.to(`individual_game_${gameId}`).emit('answer_result', { 
        gameId, userId, questionId, isCorrect, pointsEarned, timeTaken, timestamp 
    });
    console.log(`Cevap gönderildi: Game ${gameId}, User ${userId}, Correct: ${isCorrect}, Points: ${pointsEarned}`);
    res.json({ success: true, message: 'Answer submitted webhook processed' });
});

app.post('/webhook/ranking-updated', (req, res) => {
    const { tournamentId, rankings, timestamp } = req.body;
    io.to(`tournament_${tournamentId}`).emit('ranking_updated', { 
        tournamentId, rankings, timestamp 
    });
    console.log(`Sıralama güncellendi: Tournament ${tournamentId}, Rankings: ${rankings.length} oyuncu`);
    res.json({ success: true, message: 'Ranking updated webhook processed' });
});

// ===== ÖDEME WEBHOOK'LARI =====

app.post('/webhook/payment-completed', (req, res) => {
    const { userId, paymentData, timestamp } = req.body;
    io.to(`user_${userId}`).emit('payment_completed', { 
        userId, paymentData, timestamp 
    });
    console.log(`Ödeme tamamlandı: User ${userId}, Amount: ${paymentData.amount}`);
    res.json({ success: true, message: 'Payment completed webhook processed' });
});

app.post('/webhook/coin-purchased', (req, res) => {
    const { userId, coinAmount, totalCoins, timestamp } = req.body;
    io.to(`user_${userId}`).emit('coin_purchased', { 
        userId, coinAmount, totalCoins, timestamp 
    });
    console.log(`Jeton satın alındı: User ${userId}, Amount: ${coinAmount}, Total: ${totalCoins}`);
    res.json({ success: true, message: 'Coin purchased webhook processed' });
});

// ===== ARKADAŞ DAVET WEBHOOK'LARI =====

app.post('/webhook/friend-invite-accepted', (req, res) => {
    const { inviterId, invitedId, rewardCoins, timestamp } = req.body;
    io.to(`user_${inviterId}`).emit('friend_invite_accepted', { 
        inviterId, invitedId, rewardCoins, timestamp 
    });
    io.to(`user_${invitedId}`).emit('friend_invite_accepted', { 
        inviterId, invitedId, rewardCoins, timestamp 
    });
    console.log(`Arkadaş davet kabul edildi: Inviter ${inviterId}, Invited ${invitedId}, Reward: ${rewardCoins}`);
    res.json({ success: true, message: 'Friend invite accepted webhook processed' });
});

const PORT = process.env.PORT || 3001;

server.listen(PORT, () => {
  console.log(`🚀 Socket.IO sunucusu ${PORT} portunda çalışıyor`);
  console.log(`📡 WebSocket URL: ws://localhost:${PORT}`);
});
