# 🔌 Socket.IO Entegrasyonu Kurulum Rehberi

## 📋 Gerekli Environment Değişkenleri

`.env` dosyanıza aşağıdaki değişkenleri ekleyin:

```env
# Socket.IO Configuration
SOCKET_URL=http://localhost:3000

# Webhook Configuration
WEBHOOK_MAX_RETRIES=3
WEBHOOK_RETRY_DELAY=1000
```

## 🚀 Socket.IO Server Kurulumu

### 1. Socket.IO Server'ı Başlatın

```bash
cd socket-server
npm install
npm start
```

### 2. Laravel Backend'i Başlatın

```bash
php artisan serve
```

## 🔧 Test Etme

### 1. Health Check

```bash
curl http://localhost:3000/health
```

### 2. Webhook Test

```bash
curl -X POST http://localhost:3000/webhook/quiz-started \
  -H "Content-Type: application/json" \
  -d '{
    "game_id": 1,
    "user_id": 1,
    "game_type": "normal",
    "question": {"id": 1, "text": "Test question"},
    "timestamp": "2024-01-01T00:00:00Z"
  }'
```

## 📱 Frontend Socket Bağlantısı

```javascript
// Socket.IO client bağlantısı
const socket = io('http://localhost:3000');

// Quiz event'lerini dinle
socket.on('quiz_started', (data) => {
  console.log('Quiz başladı:', data);
});

socket.on('quiz_answer_result', (data) => {
  console.log('Cevap sonucu:', data);
});

socket.on('quiz_completed', (data) => {
  console.log('Quiz tamamlandı:', data);
});

// Turnuva event'lerini dinle
socket.on('tournament_started', (data) => {
  console.log('Turnuva başladı:', data);
});

socket.on('tournament_answer_result', (data) => {
  console.log('Turnuva cevap sonucu:', data);
});

socket.on('tournament_finished', (data) => {
  console.log('Turnuva bitti:', data);
});
```

## 🔄 Webhook Akışı

### Quiz Akışı
1. **Quiz Başlatma**: `POST /api/quiz/normal/start` → Webhook: `/webhook/quiz-started`
2. **Cevap Gönderme**: `POST /api/quiz/normal/answer` → Webhook: `/webhook/quiz-answer-submitted`
3. **Quiz Tamamlama**: `POST /api/quiz/normal/end` → Webhook: `/webhook/quiz-completed`

### Turnuva Akışı
1. **Turnuva Başlatma**: `POST /api/tournament-quiz/start` → Webhook: `/webhook/tournament-started`
2. **Cevap Gönderme**: `POST /api/tournament-quiz/answer` → Webhook: `/webhook/tournament-answer-submitted`
3. **Turnuva Bitirme**: Otomatik → Webhook: `/webhook/tournament-finished`

## 🛠️ Hata Ayıklama

### 1. Webhook Logları

```bash
tail -f storage/logs/laravel.log | grep "webhook"
```

### 2. Socket.IO Logları

Socket.IO server konsolunda gerçek zamanlı logları görebilirsiniz.

### 3. Health Check

```bash
# Laravel'den Socket.IO health check
php artisan tinker
>>> app(\App\Http\Services\WebhookService::class)->checkWebhookHealth()
```

## 🔧 Production Ayarları

### 1. Socket.IO Server (PM2 ile)

```bash
npm install -g pm2
pm2 start socket-server/server.js --name "socket-server"
pm2 startup
pm2 save
```

### 2. Nginx Konfigürasyonu

```nginx
# Socket.IO proxy
location /socket.io/ {
    proxy_pass http://localhost:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### 3. Environment Değişkenleri

```env
# Production
SOCKET_URL=https://yourdomain.com
WEBHOOK_MAX_RETRIES=5
WEBHOOK_RETRY_DELAY=2000
```

## 📊 Monitoring

### 1. Webhook Başarı Oranı

WebhookService loglarını takip ederek başarı oranını izleyebilirsiniz.

### 2. Socket.IO Bağlantı Sayısı

```bash
curl http://localhost:3000/health
```

### 3. Laravel Queue (Opsiyonel)

Webhook'ları queue'ya almak için:

```php
// WebhookService'te
dispatch(new SendWebhookJob($endpoint, $data));
```

## 🚨 Sorun Giderme

### 1. Socket.IO Bağlantı Sorunu

- CORS ayarlarını kontrol edin
- Firewall ayarlarını kontrol edin
- Port 3000'in açık olduğundan emin olun

### 2. Webhook Gönderilmiyor

- `SOCKET_URL` değişkenini kontrol edin
- Socket.IO server'ın çalıştığından emin olun
- Laravel loglarını kontrol edin

### 3. Event'ler Dinlenmiyor

- Frontend'de doğru event isimlerini kullandığınızdan emin olun
- Socket bağlantısının aktif olduğunu kontrol edin
- Browser console'da hata mesajlarını kontrol edin
