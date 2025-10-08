# Quiz ve Turnuva Sistemi Dokümantasyonu

## 🚀 Sistem Özeti

Bu dokümantasyon, Bilbakalim uygulaması için geliştirilen kapsamlı quiz ve turnuva sistemini açıklamaktadır. Sistem 3 ana modül içerir:

1. **Normal Quiz (Sonsuz Mod)**
2. **Premium Quiz (Paralı Mod)**
3. **Turnuva Sistemi**

## 📋 API Endpoint'leri

### Normal Quiz (Sonsuz Mod)

#### Oyun Başlatma
```
POST /api/quiz/normal/start
```
- Süre veya soru sınırı yok
- İlk 10 soru kolay, sonrakiler rastgele zorlukta
- 10 dakikalık süre sınırı

#### Cevap Gönderme
```
POST /api/quiz/normal/answer
Body: {
  "game_id": 123,
  "question_id": 456,
  "selected_option": "B",
  "time_spent": 30
}
```

#### Oyun Bitirme
```
POST /api/quiz/normal/end
Body: {
  "game_id": 123
}
```

#### Oyun Geçmişi
```
GET /api/quiz/normal/history
```

#### Oyun Detayları (Cevaplar Dahil)
```
GET /api/quiz/normal/details/{game_id}
```

### Premium Quiz (Paralı Mod)

#### Oyun Başlatma
```
POST /api/quiz/premium/start
```
- Sadece premium kullanıcılar
- 15 soru (7 orta + 8 zor)
- 3 joker (50/50, Çift Cevap, Sen Söyle)

#### Cevap Gönderme
```
POST /api/quiz/premium/answer
Body: {
  "game_id": 123,
  "question_id": 456,
  "selected_option": "B",
  "time_spent": 30,
  "joker_used": "fifty_fifty",
  "second_option": "C" // Çift cevap joker için ikinci seçenek
}
```

#### Joker Kullanma
```
POST /api/quiz/premium/joker
Body: {
  "game_id": 123,
  "question_id": 456,
  "joker_type": "fifty_fifty"
}
```

#### Oyun Bitirme
```
POST /api/quiz/premium/end
Body: {
  "game_id": 123
}
```

#### Oyun Detayları (Cevaplar Dahil)
```
GET /api/quiz/premium/details/{game_id}
```

### Turnuva Sistemi

#### Turnuvaya Katılma
```
POST /api/tournament-quiz/join
Body: {
  "tournament_id": 123
}

Response: {
  "success": true,
  "message": "Turnuvaya başarıyla katıldınız.",
  "waiting_message": "Diğer oyuncular bekleniyor... (1/3)",
  "min_participants": 3,
  "current_participants": 1
}
```

#### Turnuvadan Ayrılma
```
POST /api/tournament-quiz/leave
Body: {
  "tournament_id": 123
}
```

#### Turnuva Başlatma (Admin)
```
POST /api/tournament-quiz/start
Body: {
  "tournament_id": 123
}
```

#### Cevap Gönderme
```
POST /api/tournament-quiz/answer
Body: {
  "tournament_id": 123,
  "question_id": 456,
  "selected_option": "B",
  "time_spent": 15
}

Response: {
  "success": true,
  "is_correct": true,
  "correct_option": "B",
  "score_change": 60, // 50 (soru değeri) + 10 (hız bonusu)
  "speed_bonus": 10,
  "new_score": 110,
  "status": "active"
}
```

#### Turnuva Durumu
```
GET /api/tournament-quiz/status/{tournament_id}
```

#### Turnuva Sonuçları
```
GET /api/tournament-quiz/results/{tournament_id}
```

#### Turnuva Soruları
```
GET /api/tournament-quiz/questions/{tournament_id}?question_number=1
```

#### Turnuva Süre Kontrolü
```
POST /api/tournament-quiz/check-time
Body: {
  "tournament_id": 123
}
```

#### Turnuva Bekleme Durumu
```
GET /api/tournament-quiz/waiting-status/{tournament_id}

Response: {
  "success": true,
  "tournament_id": 123,
  "current_participants": 2,
  "min_participants": 3,
  "waiting_message": "Diğer oyuncular bekleniyor... (2/3)",
  "can_start": false,
  "status": "upcoming"
}
```

## 🔌 Laravel-Socket.IO Entegrasyonu

### Webhook Sistemi
Laravel backend'i Socket.IO server ile webhook'lar üzerinden iletişim kurar:

#### Quiz Webhook'ları
- `POST /webhook/quiz-started` - Quiz başlatıldığında
- `POST /webhook/quiz-answer-submitted` - Cevap gönderildiğinde  
- `POST /webhook/quiz-joker-used` - Joker kullanıldığında
- `POST /webhook/quiz-completed` - Quiz tamamlandığında

#### Turnuva Webhook'ları
- `POST /webhook/tournament-started` - Turnuva başladığında
- `POST /webhook/tournament-answer-submitted` - Turnuva cevabı gönderildiğinde
- `POST /webhook/tournament-leaderboard-updated` - Liderlik tablosu güncellendiğinde
- `POST /webhook/tournament-finished` - Turnuva bittiğinde

### Socket.IO Event'leri

### Quiz Event'leri

#### Normal Quiz
```javascript
// Quiz'e katılma
socket.emit('join_normal_quiz', { gameId: 123, userId: 456 });

// Quiz başlatma bildirimi (Laravel'den gelir)
socket.on('quiz_started', (data) => {
  console.log('Quiz başladı:', data);
  // data: { gameId, userId, gameType, question, timestamp }
});

// Cevap gönderme
socket.emit('quiz_answer_submitted', {
  gameId: 123,
  userId: 456,
  questionId: 789,
  isCorrect: true,
  coinsEarned: 50,
  gameType: 'normal'
});

// Cevap sonucu bildirimi (Laravel'den gelir)
socket.on('quiz_answer_result', (data) => {
  console.log('Cevap sonucu:', data);
  // data: { gameId, userId, questionId, isCorrect, coinsEarned, gameType, userCoins, gameStats, timestamp }
});

// Quiz tamamlama
socket.emit('quiz_completed', {
  gameId: 123,
  userId: 456,
  finalStats: {...},
  gameType: 'normal'
});
```

#### Premium Quiz
```javascript
// Premium quiz'e katılma
socket.emit('join_premium_quiz', { gameId: 123, userId: 456 });

// Joker kullanma
socket.emit('quiz_joker_used', {
  gameId: 123,
  userId: 456,
  jokerType: 'fifty_fifty',
  result: {...}
});
```

### Turnuva Event'leri

```javascript
// Turnuvaya katılma
socket.emit('join_tournament', {
  tournamentId: 123,
  userId: 456,
  tournamentType: 'time_based'
});

// Turnuva sorusu gönderme
socket.emit('tournament_question_sent', {
  tournamentId: 123,
  question: {...},
  questionNumber: 1,
  timeLimit: 30
});

// Turnuva cevabı
socket.emit('tournament_answer_submitted', {
  tournamentId: 123,
  userId: 456,
  questionId: 789,
  isCorrect: true,
  scoreChange: 50,
  currentScore: 150
});

// Liderlik tablosu güncelleme
socket.emit('tournament_leaderboard_update', {
  tournamentId: 123,
  leaderboard: [...],
  tournamentType: 'time_based'
});
```

## 🗄️ Veritabanı Yapısı

### Users Tablosu (Güncellenmiş)
```sql
ALTER TABLE users ADD COLUMN coins INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN is_premium BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN fifty_fifty_jokers INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN double_answer_jokers INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN hint_jokers INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL;
```

### Individual Games Tablosu (Güncellenmiş)
```sql
ALTER TABLE individual_games ADD COLUMN current_question_number INTEGER DEFAULT 1;
ALTER TABLE individual_games ADD COLUMN jokers_used JSON NULL;
```

### Game Answers Tablosu (Güncellenmiş)
```sql
ALTER TABLE game_answers ADD COLUMN individual_game_id BIGINT UNSIGNED NULL;
ALTER TABLE game_answers ADD COLUMN selected_option VARCHAR(255) NULL;
ALTER TABLE game_answers ADD COLUMN joker_used VARCHAR(255) NULL;
ALTER TABLE game_answers ADD COLUMN time_spent INTEGER NULL;
```

## 🎯 Özellikler

### Normal Quiz
- ✅ Sonsuz mod (soru sınırı yok)
- ✅ İlk 10 soru kolay, sonrakiler rastgele
- ✅ Doğru cevap → jeton kazanma
- ✅ Yanlış cevap → jeton kaybetme
- ✅ 10 dakika süre sınırı
- ✅ Oyun geçmişi görüntüleme

### Premium Quiz
- ✅ Sadece premium kullanıcılar
- ✅ 15 soru (7 orta + 8 zor)
- ✅ 3 joker türü:
  - %50 Joker (2 yanlış seçeneği kaldırır)
  - Çift Cevap (2 cevap seçebilir, biri doğru olmalı)
  - Sen Söyle (ipucu verir)
- ✅ %80+ başarı → Paket 3 ödülü (5000 jeton + 5x her joker)

### Turnuva Sistemi
- ✅ 2 tür turnuva:
  - **Süreli Turnuva** (`time_based`): Belirli süre boyunca, süre bitince otomatik biter
  - **Soru Sayısına Göre** (`question_based`): Belirli soru sayısı, sorular bitince biter
- ✅ Herkes aynı soruyu görür
- ✅ Jetonu sıfırlanan oyuncu elenir
- ✅ Anlık liderlik tablosu
- ✅ Socket ile gerçek zamanlı güncellemeler
- ✅ Süre kontrolü ve otomatik turnuva bitirme
- ✅ Minimum katılım sayısı kontrolü
- ✅ "Diğer oyuncular bekleniyor..." bekleme mesajı
- ✅ FCM ve Email bildirimleri
- ✅ Hız faktörü ile bonus puan sistemi

## 📊 Cevap Detayları Örneği

Quiz tamamlandığında kullanıcı şu bilgileri görebilir:

```json
{
  "success": true,
  "message": "Oyun tamamlandı.",
  "final_stats": {
    "total_questions": 15,
    "correct_answers": 12,
    "wrong_answers": 3,
    "accuracy_rate": 80.0,
    "total_coins": 450,
    "total_time": 720
  },
  "answer_details": [
    {
      "question_id": 123,
      "question_text": "Türkiye'nin başkenti neresidir?",
      "choices": {
        "1": "İstanbul",
        "2": "Ankara",
        "3": "İzmir",
        "4": "Bursa"
      },
      "correct_answer": "2",
      "correct_answer_text": "Ankara",
      "user_answer": "2",
      "user_answer_text": "Ankara",
      "is_correct": true,
      "time_spent": 15,
      "joker_used": null,
      "coins_earned": 50,
      "answered_at": "2024-01-15T10:30:00Z"
    },
    {
      "question_id": 124,
      "question_text": "Hangi gezegen güneşe en yakındır?",
      "choices": {
        "1": "Venüs",
        "2": "Merkür",
        "3": "Mars",
        "4": "Dünya"
      },
      "correct_answer": "2",
      "correct_answer_text": "Merkür",
      "user_answer": "1",
      "user_answer_text": "Venüs",
      "is_correct": false,
      "time_spent": 25,
      "joker_used": "fifty_fifty",
      "coins_earned": -30,
      "answered_at": "2024-01-15T10:30:25Z"
    }
  ]
}
```

## 🔄 Akış Diyagramları

### Normal Quiz Akışı
```
Kullanıcı → Quiz Başlat → İlk Soru (Kolay) → Cevap → 
Jeton Hesapla → Sonraki Soru → ... → Oyun Bitir
```

### Premium Quiz Akışı
```
Premium Kullanıcı → Quiz Başlat → 7 Orta Soru → 8 Zor Soru → 
Joker Kullanımı → Cevap → Ödül Hesapla → Oyun Bitir
```

### Turnuva Akışı
```
Kullanıcı → Turnuvaya Katıl → Bekleme → Turnuva Başla → 
Sorular → Cevap → Liderlik Güncelle → Elenme/Kazanma
```

## 🚀 Kurulum

1. Migration'ları çalıştırın:
```bash
php artisan migrate
```

2. Socket server'ı başlatın:
```bash
cd socket-server
npm install
npm start
```

3. Laravel server'ı başlatın:
```bash
php artisan serve
```

## 📱 Frontend Entegrasyonu

### Socket Bağlantısı
```javascript
const socket = io('http://localhost:3001');

socket.on('connect', () => {
  console.log('Socket bağlandı');
});

socket.on('quiz_answer_result', (data) => {
  console.log('Cevap sonucu:', data);
});

socket.on('tournament_question', (data) => {
  console.log('Yeni soru:', data);
});
```

### API Çağrıları
```javascript
// Quiz başlatma
const response = await fetch('/api/quiz/normal/start', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  }
});

// Cevap gönderme
const response = await fetch('/api/quiz/normal/answer', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    game_id: 123,
    question_id: 456,
    selected_option: 'B',
    time_spent: 30
  })
});
```

## 🔧 Webhook'lar

Sistem, Laravel'den Socket.IO'ya webhook'lar gönderir:

- `/webhook/quiz-started`
- `/webhook/quiz-answer-submitted`
- `/webhook/quiz-joker-used`
- `/webhook/quiz-completed`
- `/webhook/tournament-started`
- `/webhook/tournament-answer-submitted`
- `/webhook/tournament-leaderboard-updated`

## 📊 Performans

- Socket.IO ile gerçek zamanlı iletişim
- Veritabanı optimizasyonları
- Caching stratejileri
- Rate limiting

## 🛡️ Güvenlik

- JWT token doğrulama
- Rate limiting
- Input validation
- SQL injection koruması
- XSS koruması

## 📈 Gelecek Geliştirmeler

- [ ] Turnuva ödül sistemi
- [ ] Sosyal özellikler
- [ ] İstatistikler ve analitik
- [ ] Mobil push bildirimleri
- [ ] Çoklu dil desteği
- [ ] Turnuva şablonları
- [ ] Özel turnuva oluşturma

---

**Not**: Bu sistem tamamen test edilmiş ve production'a hazır durumdadır. Tüm API endpoint'leri ve Socket event'leri dokümante edilmiştir.
