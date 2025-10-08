# 🔧 Bilbakalim Quiz Sistemi - Frontend & Mobile API Dokümantasyonu

## 📋 İçindekiler
1. [Sistem Mimarisi](#sistem-mimarisi)
2. [API Endpoints](#api-endpoints)
3. [Socket.IO Entegrasyonu](#socketio-entegrasyonu)
4. [Authentication](#authentication)
5. [Response Formatları](#response-formatları)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Webhook Sistemi](#webhook-sistemi)
9. [Database Schema](#database-schema)
10. [Migration Güncellemeleri](#migration-güncellemeleri)

---

## 🏗️ Sistem Mimarisi

### Teknoloji Stack
- **Backend**: Laravel 10.x (PHP 8.1+)
- **Real-time**: Socket.IO (Node.js)
- **Authentication**: Laravel Sanctum
- **API Base URL**: https://bilbakalim.online/api
- **Socket URL**: https://bilbakalim.online

### Mimari Diyagram
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Client    │    │  Mobile Client  │    │  Admin Panel    │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          └──────────────────────┼──────────────────────┘
                                 │
                    ┌─────────────▼─────────────┐
                    │     Laravel API           │
                    │  (Authentication,         │
                    │   Business Logic)         │
                    └─────────────┬─────────────┘
                                  │
                    ┌─────────────▼─────────────┐
                    │     Socket.IO Server      │
                    │  (Real-time Events)       │
                    └─────────────┬─────────────┘
                                  │
                    ┌─────────────▼─────────────┐
                    │     MySQL Database        │
                    │  (Data Persistence)       │
                    └───────────────────────────┘
```

---

## 🔌 API Endpoints

### Authentication Endpoints

#### Register
```http
POST https://bilbakalim.online/api/auth/register
Content-Type: application/x-www-form-urlencoded

name=John&email=john@example.com&phone=+905551234567&password=password123&password_confirmation=password123
```

#### Login
```http
POST https://bilbakalim.online/api/auth/login
Content-Type: application/x-www-form-urlencoded

email=john@example.com&password=password123
```

### Normal Quiz Endpoints

#### Web API (Tek Soru)
```http
# Quiz Başlat
POST https://bilbakalim.online/api/quiz/normal/start
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

question_count=10

# Cevap Gönder
POST https://bilbakalim.online/api/quiz/normal/answer
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1&question_id=123&selected_option=2&time_spent=30

# Quiz Bitir
POST https://bilbakalim.online/api/quiz/normal/end
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1
```

#### Mobile API (Toplu)
```http
# Quiz Başlat (Tüm Sorular)
POST https://bilbakalim.online/api/quiz/normal/mobile/start
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

question_count=10

# Toplu Cevap Gönder
POST https://bilbakalim.online/api/quiz/normal/mobile/submit-answers
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1&answers=[{"question_id":123,"selected_option":2,"time_spent":30}]
```

### Premium Quiz Endpoints

#### Web API
```http
# Quiz Başlat
POST https://bilbakalim.online/api/quiz/premium/start
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

question_count=15

# Cevap Gönder
POST https://bilbakalim.online/api/quiz/premium/answer
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1&question_id=123&selected_option=2&time_spent=30&joker_used=fifty_fifty

# Joker Kullan
POST https://bilbakalim.online/api/quiz/premium/use-joker
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1&question_id=123&joker_type=fifty_fifty

# Joker Satın Al
POST https://bilbakalim.online/api/quiz/premium/buy-joker
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

joker_type=fifty_fifty&quantity=5
```

#### Mobile API
```http
# Quiz Başlat
POST https://bilbakalim.online/api/quiz/premium/mobile/start
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

question_count=15

# Toplu Cevap Gönder
POST https://bilbakalim.online/api/quiz/premium/mobile/submit-answers
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

game_id=1&answers=[{"question_id":123,"selected_option":2,"time_spent":30,"joker_used":"fifty_fifty"}]
```

### Tournament Endpoints

```http
# Turnuva Oluştur/Katıl (Socket bağlantısı zorunlu)
POST https://bilbakalim.online/api/tournament-quiz/create-or-join
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

type=time_based&duration_minutes=5&min_participants=2

# Turnuva Katıl (Socket bağlantısı zorunlu)
POST https://bilbakalim.online/api/tournament-quiz/join
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

tournament_id=1

# Turnuva Başlat (Admin)
POST https://bilbakalim.online/api/tournament-quiz/start
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

tournament_id=1

# Turnuva Cevabı (Socket bağlantısı zorunlu)
POST https://bilbakalim.online/api/tournament-quiz/answer
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

tournament_id=1&question_id=123&selected_option=2&time_spent=15

# Turnuva Durumu
GET https://bilbakalim.online/api/tournament-quiz/status/{tournament_id}
Authorization: Bearer {token}

# Turnuva Sonuçları
GET https://bilbakalim.online/api/tournament-quiz/results/{tournament_id}
Authorization: Bearer {token}

# Turnuva Soruları
GET https://bilbakalim.online/api/tournament-quiz/questions/{tournament_id}
Authorization: Bearer {token}

# Turnuva Ayrıl
POST https://bilbakalim.online/api/tournament-quiz/leave
Authorization: Bearer {token}
Content-Type: application/x-www-form-urlencoded

tournament_id=1
```

#### Tournament Response Examples

##### Create/Join Tournament Response
```json
{
  "success": true,
  "message": "Turnuvaya başarıyla katıldınız.",
  "tournament": {
    "id": 1,
    "title": "Süreye Göre Turnuva (5 dk)",
    "tournament_type": "time_based",
    "min_participants": 2,
    "current_participants": 2,
    "status": "upcoming"
  },
  "waiting_message": "Turnuva başlamaya hazır! (2/2)",
  "ready_to_start": true
}
```

##### Tournament Status Response
```json
{
  "success": true,
  "tournament": {
    "id": 1,
    "status": "active",
    "current_participants": 2,
    "end_time": "2025-10-08T10:35:00.000000Z"
  },
  "leaderboard": [
    {
      "user": {"id": 1, "name": "Player 1"},
      "score": 20,
      "correct_answers": 1,
      "status": "participating",
      "rank": 1
    }
  ],
  "time_remaining": 180
}
```

---

## 🔌 Socket.IO Entegrasyonu

### Socket Connection
```javascript
// Socket.IO bağlantısı
const socket = io('https://bilbakalim.online', {
  transports: ['websocket', 'polling'],
  timeout: 20000,
  forceNew: true
});

// Kullanıcı girişi
socket.emit('user_join', {
    userId: user.id,
    userName: user.name
});

// Bağlantı durumu kontrolü
socket.on('connect', () => {
  console.log('Socket bağlantısı kuruldu:', socket.id);
});

socket.on('disconnect', () => {
  console.log('Socket bağlantısı kesildi');
});

socket.on('connect_error', (error) => {
  console.error('Socket bağlantı hatası:', error);
});

// Hoş geldin mesajı
socket.on('welcome', (data) => {
  console.log('Hoş geldin:', data.message);
});
```

### Socket Bağlantı Zorunluluğu

#### Turnuva Sistemi İçin Socket Bağlantısı Zorunlu
Turnuva sisteminde Socket.IO bağlantısı **zorunludur**:

1. **Turnuvaya Katılım**: Socket bağlantısı olmadan turnuvaya katılım yapılamaz
2. **Turnuva Başlatma**: Socket bağlantısı olmayan kullanıcılar turnuva başlatılamaz
3. **Cevap Gönderme**: Socket bağlantısı kesilirse kullanıcı turnuvadan elenir
4. **Bağlantı Kontrolü**: Her turnuva işleminde socket bağlantısı kontrol edilir

#### Socket Bağlantı Kontrolü
```javascript
// Turnuva işlemleri öncesi socket bağlantısı kontrolü
if (!socket.connected) {
    // Socket bağlantısı yoksa uyarı göster
    showError('Turnuva için socket bağlantısı gereklidir. Lütfen uygulamayı yeniden başlatın.');
    return;
}

// Socket bağlantısı varsa turnuva işlemlerine devam et
joinTournament();
```

#### Bağlantı Kesilme Durumu
```javascript
socket.on('disconnect', () => {
    // Turnuva aktifse kullanıcıyı uyar
    if (isInActiveTournament) {
        showError('Bağlantınız kesildi. Turnuvadan elendiniz.');
        // Turnuva sayfasından çık
        leaveTournament();
    }
});

// Yeniden bağlanma
socket.on('connect', () => {
    if (wasInTournament) {
        showInfo('Bağlantınız yeniden kuruldu. Yeni turnuvaya katılabilirsiniz.');
    }
});
```

### Socket Events

#### Client → Server Events
```javascript
// Normal Quiz
socket.emit('join_normal_quiz', { game_id: 1, user_id: 123 });

// Premium Quiz
socket.emit('join_premium_quiz', { game_id: 1, user_id: 123 });

// Tournament
socket.emit('join_tournament', { tournament_id: 1, user_id: 123 });
```

#### Server → Client Events (Tüm Webhook'lar)

##### Quiz Events
```javascript
// Quiz başlatıldı
socket.on('quiz-started', (data) => {
  console.log('Quiz başladı:', data);
  // data: { game_id, user_id, game_type, question_count, time_limit, timestamp }
});

// Cevap gönderildi
socket.on('quiz-answer-submitted', (data) => {
  console.log('Cevap gönderildi:', data);
  // data: { game_id, user_id, question_id, is_correct, coins_earned, game_type, user_coins, game_stats, timestamp }
});

// Joker kullanıldı
socket.on('quiz-joker-used', (data) => {
  console.log('Joker kullanıldı:', data);
  // data: { game_id, user_id, joker_type, result, timestamp }
});

// Quiz tamamlandı
socket.on('quiz-completed', (data) => {
  console.log('Quiz tamamlandı:', data);
  // data: { game_id, user_id, game_type, final_stats, answer_details, reward, timestamp }
});
```

##### Tournament Events
```javascript
// Turnuva başladı
socket.on('tournament-started', (data) => {
  console.log('Turnuva başladı:', data);
  // data: { tournament_id, tournament_type, participants, time_limit, timestamp }
});

// Turnuva cevabı gönderildi
socket.on('tournament-answer-submitted', (data) => {
  console.log('Turnuva cevabı:', data);
  // data: { tournament_id, user_id, question_id, is_correct, score, speed_bonus, timestamp }
});

// Sıralama güncellendi
socket.on('tournament-ranking-updated', (data) => {
  console.log('Sıralama güncellendi:', data);
  // data: { tournament_id, rankings, timestamp }
});

// Oyuncu elendi
socket.on('player-eliminated', (data) => {
  console.log('Oyuncu elendi:', data);
  // data: { tournament_id, user_id, name, final_score, position, reason, timestamp }
});

// Turnuva bitti
socket.on('tournament-finished', (data) => {
  console.log('Turnuva bitti:', data);
  // data: { tournament_id, final_rankings, winner, end_reason, timestamp }
});

// Kullanıcı turnuvaya katıldı
socket.on('user-joined-tournament', (data) => {
  console.log('Kullanıcı katıldı:', data);
  // data: { tournament_id, user_id, name, current_participants, min_participants, ready_to_start, timestamp }
});

// Oyuncular bekleniyor
socket.on('waiting-players', (data) => {
  console.log('Oyuncular bekleniyor:', data);
  // data: { tournament_id, current_participants, min_participants, waiting_message, timestamp }
});
```

### Socket Event Data Structures

#### Quiz Started Data
```json
{
  "game_id": 1,
  "user_id": 123,
  "game_type": "normal",
  "question_count": 10,
  "time_limit": 600,
  "timestamp": "2025-10-08T09:00:00.000000Z"
}
```

#### Quiz Answer Submitted Data
```json
{
  "game_id": 1,
  "user_id": 123,
  "question_id": 456,
  "is_correct": true,
  "coins_earned": 20,
  "game_type": "normal",
  "user_coins": 1000,
  "game_stats": {
    "current_question": 3,
    "total_questions": 10,
    "correct_answers": 2,
    "wrong_answers": 1,
    "total_coins": 40
  },
  "timestamp": "2025-10-08T09:02:00.000000Z"
}
```

#### Quiz Joker Used Data
```json
{
  "game_id": 1,
  "user_id": 123,
  "joker_type": "fifty_fifty",
  "result": {
    "is_correct": true,
    "remaining_jokers": {
      "fifty_fifty": 2,
      "double_answer": 3,
      "hint": 1
    }
  },
  "timestamp": "2025-10-08T09:02:30.000000Z"
}
```

#### Quiz Completed Data
```json
{
  "game_id": 1,
  "user_id": 123,
  "game_type": "premium",
  "final_stats": {
    "total_questions": 15,
    "correct_answers": 12,
    "wrong_answers": 3,
    "accuracy_rate": 80.0,
    "total_coins": 2400,
    "total_time": 900
  },
  "answer_details": [
    {
      "question_id": 123,
      "is_correct": true,
      "time_spent": 30,
      "coins_earned": 200
    }
  ],
  "reward": {
    "type": "package_2",
    "coins": 3000,
    "jokers": {
      "fifty_fifty": 3,
      "double_answer": 3,
      "hint": 3
    },
    "message": "Tebrikler! Paket 2 ödülünü kazandınız!"
  },
  "timestamp": "2025-10-08T09:15:00.000000Z"
}
```

#### Tournament Started Data
```json
{
  "tournament_id": 1,
  "tournament_type": "time_based",
  "participants": [
    {
      "user_id": 123,
      "name": "John Doe",
      "score": 0,
      "position": 1
    },
    {
      "user_id": 124,
      "name": "Jane Smith",
      "score": 0,
      "position": 2
    }
  ],
  "time_limit": 300,
  "timestamp": "2025-10-08T09:00:00.000000Z"
}
```

#### Tournament Answer Submitted Data
```json
{
  "tournament_id": 1,
  "user_id": 123,
  "question_id": 456,
  "is_correct": true,
  "score": 100,
  "speed_bonus": 20,
  "total_score": 120,
  "current_position": 1,
  "timestamp": "2025-10-08T09:01:00.000000Z"
}
```

#### Tournament Ranking Updated Data
```json
{
  "tournament_id": 1,
  "rankings": [
    {
      "user_id": 123,
      "name": "John Doe",
      "score": 120,
      "position": 1,
      "correct_answers": 1
    },
    {
      "user_id": 124,
      "name": "Jane Smith",
      "score": 80,
      "position": 2,
      "correct_answers": 1
    }
  ],
  "timestamp": "2025-10-08T09:01:00.000000Z"
}
```

#### Player Eliminated Data
```json
{
  "tournament_id": 1,
  "user_id": 125,
  "name": "Bob Wilson",
  "final_score": 0,
  "position": 3,
  "reason": "coins_finished",
  "timestamp": "2025-10-08T09:03:00.000000Z"
}
```

#### Tournament Finished Data
```json
{
  "tournament_id": 1,
  "final_rankings": [
    {
      "user_id": 123,
      "name": "John Doe",
      "final_score": 1200,
      "correct_answers": 6,
      "wrong_answers": 0,
      "position": 1,
      "total_time": 45
    }
  ],
  "winner": {
    "user_id": 123,
    "name": "John Doe",
    "final_score": 1200,
    "correct_answers": 6
  },
  "end_reason": "only_one_participant_left",
  "timestamp": "2025-10-08T09:05:00.000000Z"
}
```

#### User Joined Tournament Data
```json
{
  "tournament_id": 1,
  "user_id": 126,
  "name": "Alice Johnson",
  "current_participants": 3,
  "min_participants": 2,
  "ready_to_start": true,
  "timestamp": "2025-10-08T09:00:30.000000Z"
}
```

#### Waiting Players Data
```json
{
  "tournament_id": 1,
  "current_participants": 1,
  "min_participants": 2,
  "waiting_message": "Diğer oyuncular bekleniyor... (1/2)",
  "timestamp": "2025-10-08T09:00:30.000000Z"
}
```

### Webhook Endpoints

#### Laravel → Socket.IO Webhooks
```http
POST https://bilbakalim.online/webhook/quiz-started
POST https://bilbakalim.online/webhook/quiz-answer-submitted
POST https://bilbakalim.online/webhook/quiz-joker-used
POST https://bilbakalim.online/webhook/quiz-completed
POST https://bilbakalim.online/webhook/tournament-started
POST https://bilbakalim.online/webhook/tournament-answer-submitted
POST https://bilbakalim.online/webhook/tournament-ranking-updated
POST https://bilbakalim.online/webhook/player-eliminated
POST https://bilbakalim.online/webhook/tournament-finished
POST https://bilbakalim.online/webhook/user-joined-tournament
```

---

## 🔐 Authentication

### Token-based Authentication
- **Laravel Sanctum** kullanılır
- **Bearer Token** ile authentication
- Token'lar **otomatik olarak yenilenir**

### Authentication Flow
```javascript
// 1. Login
const loginResponse = await fetch('https://bilbakalim.online/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: 'email=user@example.com&password=password123'
});

const { token } = await loginResponse.json();

// 2. Token'ı sakla
localStorage.setItem('auth_token', token);

// 3. API isteklerinde kullan
const apiResponse = await fetch('https://bilbakalim.online/api/quiz/normal/start', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: 'question_count=10'
});
```

---

## 📋 Response Formatları

### Success Response
```json
{
  "success": true,
  "message": "İşlem başarılı",
  "data": {
    "game_id": 1,
    "user_id": 123,
    "game_type": "normal",
    "question_count": 10,
    "time_limit_seconds": 600
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Hata mesajı",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Quiz Start Response
```json
{
  "success": true,
  "message": "Normal quiz başlatıldı.",
  "game": {
    "id": 1,
    "user_id": 123,
    "game_type": "normal",
    "difficulty_level": "mixed",
    "question_count": 10,
    "time_limit_seconds": 600,
    "joker_count": 0,
    "score": 0,
    "correct_answers": 0,
    "wrong_answers": 0,
    "coins_earned": 0,
    "status": "active",
    "started_at": "2025-10-08T09:00:00.000000Z",
    "settings": {
      "easy_questions_count": 10,
      "current_difficulty": "easy",
      "total_questions_count": 10
    }
  },
  "current_question": {
    "id": 123,
    "question": "Soru metni",
    "one_choice": "A seçeneği",
    "two_choice": "B seçeneği",
    "three_choice": "C seçeneği",
    "four_choice": "D seçeneği",
    "category_id": 1,
    "question_level": "easy",
    "coin_value": 20,
    "image": null
  }
}
```

### Mobile Quiz Start Response
```json
{
  "success": true,
  "message": "Mobil normal quiz başlatıldı.",
  "game": {
    "id": 1,
    "user_id": 123,
    "game_type": "normal",
    "difficulty_level": "mixed",
    "question_count": 10,
    "time_limit_seconds": 600,
    "joker_count": 0,
    "score": 0,
    "correct_answers": 0,
    "wrong_answers": 0,
    "coins_earned": 0,
    "status": "active",
    "started_at": "2025-10-08T09:00:00.000000Z"
  },
  "questions": [
    {
      "id": 123,
      "question": "Soru metni",
      "one_choice": "A seçeneği",
      "two_choice": "B seçeneği",
      "three_choice": "C seçeneği",
      "four_choice": "D seçeneği",
      "correct_answer": "2",
      "category_id": 1,
      "question_level": "easy",
      "coin_value": 20,
      "image": null
    }
  ]
}
```

### Quiz Completion Response
```json
{
  "success": true,
  "message": "Quiz başarıyla tamamlandı.",
  "final_stats": {
    "total_questions": 10,
    "correct_answers": 8,
    "wrong_answers": 2,
    "accuracy_rate": 80.0,
    "total_coins": 160,
    "total_time": 300
  },
  "answer_details": [
    {
      "question_id": 123,
      "question_text": "Soru metni",
      "choices": {
        "1": "A seçeneği",
        "2": "B seçeneği",
        "3": "C seçeneği",
        "4": "D seçeneği"
      },
      "correct_answer": "2",
      "correct_answer_text": "B seçeneği",
      "user_answer": "2",
      "user_answer_text": "B seçeneği",
      "is_correct": true,
      "time_spent": 30,
      "coins_earned": 20,
      "answered_at": "2025-10-08T09:05:00.000000Z"
    }
  ]
}
```

---

## ⚠️ Error Handling

### HTTP Status Codes
- **200**: Başarılı
- **400**: Bad Request (Geçersiz parametreler)
- **401**: Unauthorized (Token geçersiz)
- **403**: Forbidden (Yetki yok)
- **404**: Not Found (Kaynak bulunamadı)
- **422**: Validation Error (Doğrulama hatası)
- **500**: Internal Server Error (Sunucu hatası)

### Common Error Messages
```json
// Authentication Error
{
  "success": false,
  "message": "Unauthenticated."
}

// Validation Error
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "question_count": ["The question count must be between 1 and 50."]
  }
}

// Premium Required
{
  "success": false,
  "message": "Premium üyelik gerekli."
}

// Active Game Exists
{
  "success": false,
  "message": "Zaten aktif bir oyununuz var.",
  "active_game": {
    "id": 1,
    "game_type": "normal",
    "status": "active"
  }
}
```

### Error Handling Example
```javascript
try {
  const response = await fetch('https://bilbakalim.online/api/quiz/normal/start', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'question_count=10'
  });

  const data = await response.json();

  if (!response.ok) {
    if (response.status === 401) {
      // Token geçersiz, login sayfasına yönlendir
      window.location.href = '/login';
    } else if (response.status === 422) {
      // Validation hatası, hataları göster
      console.error('Validation errors:', data.errors);
    } else {
      // Diğer hatalar
      console.error('Error:', data.message);
    }
    return;
  }

  // Başarılı response
  console.log('Quiz started:', data);
} catch (error) {
  console.error('Network error:', error);
}
```

---

## 🚦 Rate Limiting

### API Rate Limits
- **Authentication endpoints**: 5 istek/dakika
- **Quiz endpoints**: 10 istek/dakika
- **Tournament endpoints**: 20 istek/dakika
- **General API**: 60 istek/dakika

### Rate Limit Headers
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```

### Rate Limit Exceeded Response
```json
{
  "success": false,
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

---

## 🔗 Webhook Sistemi

### Webhook Data Formats

#### Quiz Started Webhook
```json
{
  "game_id": 1,
  "user_id": 123,
  "game_type": "normal",
  "question_count": 10,
  "time_limit": 600,
  "timestamp": "2025-10-08T09:00:00.000000Z"
}
```

#### Quiz Answer Submitted Webhook
```json
{
  "game_id": 1,
  "user_id": 123,
  "question_id": 456,
  "is_correct": true,
  "coins_earned": 20,
  "game_type": "normal",
  "user_coins": 1000,
  "game_stats": {
    "current_question": 3,
    "total_questions": 10,
    "correct_answers": 2,
    "wrong_answers": 1,
    "total_coins": 40
  },
  "timestamp": "2025-10-08T09:02:00.000000Z"
}
```

#### Tournament Started Webhook
```json
{
  "tournament_id": 1,
  "tournament_type": "time_based",
  "participants": [
    {
      "user_id": 123,
      "name": "John Doe",
      "score": 0
    }
  ],
  "time_limit": 300,
  "timestamp": "2025-10-08T09:00:00.000000Z"
}
```

#### Player Eliminated Webhook
```json
{
  "tournament_id": 1,
  "user_id": 123,
  "name": "John Doe",
  "final_score": 0,
  "position": 5,
  "reason": "coins_finished",
  "timestamp": "2025-10-08T09:05:00.000000Z"
}
```

---

## 🔗 Socket.IO Kullanım Rehberi

### ⚠️ Önemli: Socket Bağlantısı Zorunlu

**Socket.IO bağlantısı aşağıdaki durumlar için ZORUNLUDUR:**

#### Web Uygulaması (Frontend)
- ✅ **Normal Quiz** - Anlık soru-cevap için
- ✅ **Premium Quiz** - Anlık soru-cevap ve joker kullanımı için
- ✅ **Turnuva Sistemi** - Gerçek zamanlı yarışma için **ZORUNLU**

#### Mobil Uygulama
- ❌ **Normal Quiz (Mobile API)** - Toplu cevap gönderimi, socket gerekmez
- ❌ **Premium Quiz (Mobile API)** - Toplu cevap gönderimi, socket gerekmez
- ✅ **Turnuva Sistemi** - Gerçek zamanlı yarışma için **ZORUNLU**

#### Turnuva Sistemi Socket Zorunluluğu
Turnuva sisteminde Socket.IO bağlantısı **kesinlikle zorunludur**:

1. **Turnuvaya Katılım**: Socket bağlantısı olmadan turnuvaya katılım yapılamaz
2. **Turnuva Başlatma**: Socket bağlantısı olmayan kullanıcılar turnuva başlatılamaz
3. **Cevap Gönderme**: Socket bağlantısı kesilirse kullanıcı turnuvadan elenir
4. **Bağlantı Kontrolü**: Her turnuva işleminde socket bağlantısı kontrol edilir
5. **Disconnect Handling**: Bağlantı kesilirse kullanıcı otomatik olarak turnuvadan elenir

### Socket Bağlantısı Gereken Durumlar

#### 1. Web Quiz (Tek Soru-Cevap) - ZORUNLU
```javascript
// Web quiz için socket ZORUNLU
// Çünkü:
// - Anlık soru getirme (quiz-started event)
// - Cevap gönderme sonrası anlık feedback (quiz-answer-submitted)
// - Joker kullanımı bildirimi (quiz-joker-used)
// - Quiz tamamlama bildirimi (quiz-completed)
// - Gerçek zamanlı skor güncelleme
```

#### 2. Mobil Quiz (Toplu Cevap) - OPSIYONEL
```javascript
// Mobil quiz için socket OPSIYONEL
// Çünkü:
// - Tüm sorular başlangıçta gelir (API ile)
// - Cevaplar toplu gönderilir (API ile)
// - Socket sadece bildirimler için kullanılır
// - Offline çalışabilir
// - Ama gerçek zamanlı bildirimler için önerilir
```

#### 3. Turnuva Sistemi - ZORUNLU
```javascript
// Turnuva için socket ZORUNLU
// Çünkü:
// - Gerçek zamanlı sıralama (tournament-ranking-updated)
// - Oyuncu eleme bildirimi (player-eliminated)
// - Turnuva başlangıç bildirimi (tournament-started)
// - Turnuva bitiş bildirimi (tournament-finished)
// - Yeni katılımcı bildirimi (user-joined-tournament)
// - Anlık skor güncelleme (tournament-answer-submitted)
// - Bağlantı kesilme kontrolü (disconnect handling)
// - Socket bağlantısı olmadan turnuvaya katılım yapılamaz
// - Socket bağlantısı kesilirse kullanıcı turnuvadan elenir
```

### Socket Bağlantısı Olmadan Çalışan Durumlar

#### Mobil Quiz (Offline Mode)
```javascript
// Mobil quiz tamamen offline çalışabilir
// 1. Quiz başlat (API)
// 2. Tüm soruları al (API)
// 3. Offline çöz
// 4. Cevapları toplu gönder (API)
// 5. Sonuçları al (API)
// Socket bağlantısı olmadan da çalışır
```

### Socket Bağlantısı Gerekli Durumlar

#### Web Quiz (Online Mode)
```javascript
// Web quiz socket olmadan çalışmaz
// Çünkü:
// - Her soru ayrı ayrı getirilir
// - Her cevap ayrı ayrı gönderilir
// - Anlık feedback gerekli
// - Gerçek zamanlı skor güncelleme
```

#### Turnuva (Multiplayer Mode)
```javascript
// Turnuva socket olmadan çalışmaz
// Çünkü:
// - Çoklu oyuncu yarışması
// - Gerçek zamanlı sıralama
// - Anlık oyuncu eleme
// - Socket bağlantısı zorunlu
// - Bağlantı kesilirse otomatik elenme
// - Canlı yarışma deneyimi
```

### Bağlantı Kurulumu
```javascript
// Socket.IO kütüphanesini yükleyin
npm install socket.io-client

// Bağlantı kurun
import io from 'socket.io-client';

const socket = io('https://bilbakalim.online', {
  transports: ['websocket', 'polling'],
  autoConnect: true,
  reconnection: true,
  reconnectionDelay: 1000,
  reconnectionAttempts: 5,
  timeout: 20000
});
```

### Event Listener'ları Kurulumu
```javascript
// Bağlantı durumu kontrolü
socket.on('connect', () => {
  console.log('Socket bağlantısı kuruldu:', socket.id);
  // Kullanıcıyı quiz/turnuvaya katıl
});

socket.on('disconnect', (reason) => {
  console.log('Socket bağlantısı kesildi:', reason);
  // Yeniden bağlanma girişimi
});

socket.on('connect_error', (error) => {
  console.error('Socket bağlantı hatası:', error);
  // Hata yönetimi
});

// Quiz event'leri
socket.on('quiz-started', (data) => {
  // Quiz başladı - UI güncelle
  updateQuizUI(data);
});

socket.on('quiz-answer-submitted', (data) => {
  // Cevap gönderildi - Skor güncelle
  updateScore(data);
});

socket.on('quiz-joker-used', (data) => {
  // Joker kullanıldı - Joker durumu güncelle
  updateJokerStatus(data);
});

socket.on('quiz-completed', (data) => {
  // Quiz tamamlandı - Sonuçları göster
  showQuizResults(data);
});

// Turnuva event'leri
socket.on('tournament-started', (data) => {
  // Turnuva başladı - Turnuva UI'ını göster
  showTournamentUI(data);
});

socket.on('tournament-answer-submitted', (data) => {
  // Turnuva cevabı - Skor güncelle
  updateTournamentScore(data);
});

socket.on('tournament-ranking-updated', (data) => {
  // Sıralama güncellendi - Liderlik tablosunu güncelle
  updateRankings(data);
});

socket.on('player-eliminated', (data) => {
  // Oyuncu elendi - Bildirim göster
  showEliminationNotification(data);
});

socket.on('tournament-finished', (data) => {
  // Turnuva bitti - Final sonuçları göster
  showTournamentResults(data);
});

socket.on('user-joined-tournament', (data) => {
  // Yeni katılımcı - Katılımcı listesini güncelle
  updateParticipants(data);
});
```

### Quiz/Turnuva Katılımı
```javascript
// Normal quiz'e katıl
function joinNormalQuiz(gameId, userId) {
  socket.emit('join_normal_quiz', { 
    game_id: gameId, 
    user_id: userId 
  });
}

// Premium quiz'e katıl
function joinPremiumQuiz(gameId, userId) {
  socket.emit('join_premium_quiz', { 
    game_id: gameId, 
    user_id: userId 
  });
}

// Turnuvaya katıl
function joinTournament(tournamentId, userId) {
  socket.emit('join_tournament', { 
    tournament_id: tournamentId, 
    user_id: userId 
  });
}
```

### Bağlantı Yönetimi
```javascript
// Manuel bağlantı
function connectSocket() {
  if (!socket.connected) {
    socket.connect();
  }
}

// Manuel bağlantı kesme
function disconnectSocket() {
  if (socket.connected) {
    socket.disconnect();
  }
}

// Bağlantı durumu kontrolü
function isSocketConnected() {
  return socket.connected;
}

// Socket ID al
function getSocketId() {
  return socket.id;
}
```

---

## 🗄️ Database Schema

### Güncellenmiş Tablolar

#### `individual_games` Tablosu
```sql
-- time_limit_seconds kolonu nullable yapıldı
ALTER TABLE individual_games MODIFY COLUMN time_limit_seconds INT NULL;
```

#### `tournament_users` Tablosu
```sql
-- Yeni kolonlar eklendi
ALTER TABLE tournament_users ADD COLUMN eliminated_at TIMESTAMP NULL;
ALTER TABLE tournament_users ADD COLUMN elimination_reason VARCHAR(255) NULL;
ALTER TABLE tournament_users ADD COLUMN current_question_number INT DEFAULT 1;
```

#### `tournaments` Tablosu
```sql
-- end_date ve end_time kolonları nullable yapıldı
ALTER TABLE tournaments MODIFY COLUMN end_date DATE NULL;
ALTER TABLE tournaments MODIFY COLUMN end_time TIMESTAMP NULL;
```

---

## 🔄 Migration Güncellemeleri

### Son Eklenen Migration'lar

#### 1. `2025_10_08_171605_modify_individual_games_time_limit_nullable.php`
```php
public function up(): void
{
    Schema::table('individual_games', function (Blueprint $table) {
        $table->integer('time_limit_seconds')->nullable()->change();
    });
}
```

#### 2. `2025_10_08_172420_add_elimination_fields_to_tournament_users_table.php`
```php
public function up(): void
{
    Schema::table('tournament_users', function (Blueprint $table) {
        $table->timestamp('eliminated_at')->nullable();
        $table->string('elimination_reason')->nullable();
    });
}
```

#### 3. `2025_10_08_172607_add_current_question_to_tournament_users_table.php`
```php
public function up(): void
{
    Schema::table('tournament_users', function (Blueprint $table) {
        $table->integer('current_question_number')->default(1);
    });
}
```

---

## 🚀 Son Güncellemeler (Ekim 2025)

### ✅ Düzeltilen Hatalar
1. **Normal Quiz**: `time_limit_seconds` null değer alabilir hale getirildi
2. **Turnuva Sistemi**: `end_date` ve `end_time` null değer alabilir hale getirildi
3. **Turnuva Katılımcıları**: Eleme alanları eklendi (`eliminated_at`, `elimination_reason`)
4. **Turnuva Soru Takibi**: `current_question_number` alanı eklendi

### ✅ Yeni Özellikler
1. **Socket.IO Bağlantı Kontrolü**: Gerçek zamanlı bağlantı durumu kontrolü
2. **Turnuva Eleme Sistemi**: Bağlantı kesilme durumunda otomatik eleme
3. **Gelişmiş Turnuva Takibi**: Soru bazlı ilerleme takibi

### ✅ Test Edilen Sistemler
1. **Normal Quiz**: Sonsuz mod, joker sistemi, coin kazanma/eksilme
2. **Premium Quiz**: 7 orta + 8 zor soru, yanlış cevap oyun bitirme
3. **Turnuva Sistemi**: 5 dk süre, 10 soru, en yüksek puan kazanma
4. **Joker Sistemi**: 50-50, Çift Cevap, Doğru cevap jokerleri
5. **Coin Sistemi**: Kazanma/harcama, soru değerleri
6. **Socket.IO**: Gerçek zamanlı bağlantı ve webhook sistemi

---

*Son güncelleme: Ekim 2025*
*Versiyon: 2.1*
*Frontend & Mobile API Dokümantasyonu*
