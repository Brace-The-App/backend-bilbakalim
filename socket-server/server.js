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
    origin: ["http://localhost", "http://localhost:3000", "http://localhost:8000"],
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

const PORT = process.env.PORT || 3001;

server.listen(PORT, () => {
  console.log(`🚀 Socket.IO sunucusu ${PORT} portunda çalışıyor`);
  console.log(`📡 WebSocket URL: ws://localhost:${PORT}`);
});
