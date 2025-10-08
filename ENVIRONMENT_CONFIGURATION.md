# 🔧 Environment Konfigürasyon Rehberi

## 📋 Gerekli Environment Değişkenleri

`.env` dosyanıza aşağıdaki değişkenleri ekleyin:

### 🔌 Socket.IO Konfigürasyonu
```env
# Socket.IO Server URL
SOCKET_URL=http://localhost:3000

# Webhook Retry Ayarları
WEBHOOK_MAX_RETRIES=3
WEBHOOK_RETRY_DELAY=1000
```

### 🎮 Quiz Sistemi Konfigürasyonu
```env
# Normal Quiz Ayarları
QUIZ_NORMAL_TIME_LIMIT=600          # 10 dakika (saniye)

# Premium Quiz Ayarları
QUIZ_PREMIUM_QUESTION_COUNT=15      # Toplam soru sayısı
QUIZ_PREMIUM_TIME_LIMIT=1800        # 30 dakika (saniye)

# Turnuva Ayarları
TOURNAMENT_MIN_PARTICIPANTS=2       # Minimum katılımcı sayısı
TOURNAMENT_MAX_PARTICIPANTS=100     # Maksimum katılımcı sayısı
```

### 🃏 Joker Konfigürasyonu
```env
# Premium Quiz Joker Sayıları
JOKER_FIFTY_FIFTY_COUNT=1           # %50-%50 joker sayısı
JOKER_DOUBLE_ANSWER_COUNT=1         # Çift cevap joker sayısı
JOKER_HINT_COUNT=1                  # İpucu joker sayısı
```

### 🏆 Ödül Sistemi Konfigürasyonu
```env
# Package 3 Ödülleri (80%+ doğruluk)
REWARD_PACKAGE_3_COINS=5000         # Jeton ödülü
REWARD_PACKAGE_3_FIFTY_FIFTY=5      # %50-%50 joker ödülü
REWARD_PACKAGE_3_DOUBLE_ANSWER=5    # Çift cevap joker ödülü
REWARD_PACKAGE_3_HINT=5             # İpucu joker ödülü
REWARD_MIN_ACCURACY_RATE=80         # Minimum doğruluk oranı (%)
```

### ⚡ Hız Bonusu Konfigürasyonu
```env
# Turnuva Hız Bonusu Ayarları
SPEED_BONUS_FAST_THRESHOLD=10       # Hızlı cevap eşiği (saniye)
SPEED_BONUS_FAST_POINTS=10          # Hızlı cevap bonus puanı
SPEED_BONUS_MEDIUM_THRESHOLD=20     # Orta hız eşiği (saniye)
SPEED_BONUS_MEDIUM_POINTS=5         # Orta hız bonus puanı
```

### 🔔 Bildirim Sistemi Konfigürasyonu
```env
# Bildirim Türleri
NOTIFICATION_EMAIL_ENABLED=true     # Email bildirimleri
NOTIFICATION_FCM_ENABLED=true       # FCM bildirimleri
NOTIFICATION_SMS_ENABLED=false      # SMS bildirimleri
```

### 🔥 Firebase Konfigürasyonu (FCM için)
```env
# Firebase Service Account
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour-Private-Key\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
FIREBASE_TOKEN_URI=https://oauth2.googleapis.com/token
FIREBASE_AUTH_PROVIDER_X509_CERT_URL=https://www.googleapis.com/oauth2/v1/certs
FIREBASE_CLIENT_X509_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-xxxxx%40your-project.iam.gserviceaccount.com
```

### 🛠️ Geliştirme/Test Konfigürasyonu
```env
# Geliştirme Modu
QUIZ_DEV_MODE=false                 # Geliştirme modu (bazı validasyonları devre dışı bırakır)
WEBHOOK_DEBUG=false                 # Webhook debug logları
```

## 🚀 Kurulum Adımları

### 1. Environment Dosyasını Kopyalayın
```bash
cp .env.example .env
```

### 2. Gerekli Değişkenleri Düzenleyin
```bash
# APP_KEY oluşturun
php artisan key:generate

# Socket.IO URL'ini ayarlayın
echo "SOCKET_URL=http://localhost:3000" >> .env

# Webhook ayarlarını ekleyin
echo "WEBHOOK_MAX_RETRIES=3" >> .env
echo "WEBHOOK_RETRY_DELAY=1000" >> .env
```

### 3. Veritabanını Hazırlayın
```bash
# Migration'ları çalıştırın
php artisan migrate

# Seed'leri çalıştırın
php artisan db:seed
```

### 4. Socket.IO Server'ı Başlatın
```bash
cd socket-server
npm install
npm start
```

### 5. Laravel'i Başlatın
```bash
php artisan serve
```

## 🔧 Production Ayarları

### Production Environment
```env
# Production ayarları
APP_ENV=production
APP_DEBUG=false
SOCKET_URL=https://yourdomain.com
WEBHOOK_MAX_RETRIES=5
WEBHOOK_RETRY_DELAY=2000
QUIZ_DEV_MODE=false
WEBHOOK_DEBUG=false
```

### Nginx Konfigürasyonu
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

### PM2 Konfigürasyonu
```bash
# Socket.IO server'ı PM2 ile başlatın
pm2 start socket-server/server.js --name "socket-server"
pm2 startup
pm2 save
```

## 📊 Monitoring ve Debug

### Webhook Durumunu Kontrol Etme
```bash
# Laravel tinker ile
php artisan tinker
>>> app(\App\Http\Services\WebhookService::class)->checkWebhookHealth()
```

### Log Dosyalarını İzleme
```bash
# Webhook logları
tail -f storage/logs/laravel.log | grep "webhook"

# Socket.IO logları
pm2 logs socket-server
```

### Health Check
```bash
# Socket.IO health check
curl http://localhost:3000/health

# Laravel health check
curl http://localhost:8000/api/health
```

## 🚨 Sorun Giderme

### 1. Socket.IO Bağlantı Sorunu
- `SOCKET_URL` değişkenini kontrol edin
- Socket.IO server'ın çalıştığından emin olun
- Firewall ayarlarını kontrol edin

### 2. Webhook Gönderilmiyor
- `WEBHOOK_DEBUG=true` yaparak detaylı logları kontrol edin
- Socket.IO server loglarını kontrol edin
- Network bağlantısını test edin

### 3. Environment Değişkenleri Çalışmıyor
```bash
# Config cache'i temizleyin
php artisan config:clear
php artisan config:cache
```

### 4. Firebase FCM Çalışmıyor
- Firebase service account key'ini kontrol edin
- `FIREBASE_PRIVATE_KEY` formatını kontrol edin (newline karakterleri önemli)
- Firebase project ID'sini kontrol edin

## 📝 Örnek .env Dosyası

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Socket.IO
SOCKET_URL=http://localhost:3000
WEBHOOK_MAX_RETRIES=3
WEBHOOK_RETRY_DELAY=1000

# Quiz System
QUIZ_NORMAL_TIME_LIMIT=600
QUIZ_PREMIUM_QUESTION_COUNT=15
QUIZ_PREMIUM_TIME_LIMIT=1800
TOURNAMENT_MIN_PARTICIPANTS=2
TOURNAMENT_MAX_PARTICIPANTS=100

# Jokers
JOKER_FIFTY_FIFTY_COUNT=1
JOKER_DOUBLE_ANSWER_COUNT=1
JOKER_HINT_COUNT=1

# Rewards
REWARD_PACKAGE_3_COINS=5000
REWARD_PACKAGE_3_FIFTY_FIFTY=5
REWARD_PACKAGE_3_DOUBLE_ANSWER=5
REWARD_PACKAGE_3_HINT=5
REWARD_MIN_ACCURACY_RATE=80

# Speed Bonus
SPEED_BONUS_FAST_THRESHOLD=10
SPEED_BONUS_FAST_POINTS=10
SPEED_BONUS_MEDIUM_THRESHOLD=20
SPEED_BONUS_MEDIUM_POINTS=5

# Notifications
NOTIFICATION_EMAIL_ENABLED=true
NOTIFICATION_FCM_ENABLED=true
NOTIFICATION_SMS_ENABLED=false

# Development
QUIZ_DEV_MODE=false
WEBHOOK_DEBUG=false
```
