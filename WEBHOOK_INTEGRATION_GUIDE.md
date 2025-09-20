# 🔗 Webhook Entegrasyon Rehberi

## WebhookController Kullanımı

WebhookController, Laravel uygulamasından Socket.IO sunucusuna gerçek zamanlı bildirimler göndermek için kullanılır.

### Kullanım Örnekleri

#### 1. Turnuva Webhook'ları

```php
use App\Http\Controllers\WebhookController;

$webhookController = new WebhookController();

// Turnuva başlatıldığında
$webhookController->tournamentStarted($tournament, $participants);

// Turnuva bittiğinde
$webhookController->tournamentFinished($tournament, $finalRankings);

// Kullanıcı turnuvaya katıldığında
$webhookController->userJoinedTournament($tournamentId, $userId, $userData);

// Kullanıcı turnuvadan ayrıldığında
$webhookController->userLeftTournament($tournamentId, $userId);
```

#### 2. Bireysel Oyun Webhook'ları

```php
// Bireysel oyun başlatıldığında
$webhookController->individualGameStarted($gameId, $userId, $gameData);

// Bireysel oyun bittiğinde
$webhookController->individualGameFinished($gameId, $userId, $finalScore);
```

#### 3. Cevap Webhook'ları

```php
// Cevap verildiğinde
$webhookController->answerSubmitted($gameId, $userId, $questionId, $isCorrect, $pointsEarned, $timeTaken);

// Sıralama güncellendiğinde
$webhookController->rankingUpdated($tournamentId, $rankings);
```

#### 4. Ödeme Webhook'ları

```php
// Ödeme tamamlandığında
$webhookController->paymentCompleted($userId, $paymentData);

// Jeton satın alındığında
$webhookController->coinPurchased($userId, $coinAmount, $totalCoins);
```

#### 5. Arkadaş Davet Webhook'ları

```php
// Arkadaş davet kabul edildiğinde
$webhookController->friendInviteAccepted($inviterId, $invitedId, $rewardCoins);
```

### Socket Server Webhook Endpoint'leri

Socket server'da aşağıdaki webhook endpoint'leri mevcuttur:

#### Turnuva Webhook'ları
- `POST /webhook/tournament-started`
- `POST /webhook/tournament-finished`
- `POST /webhook/user-joined-tournament`
- `POST /webhook/user-left-tournament`

#### Bireysel Oyun Webhook'ları
- `POST /webhook/individual-game-started`
- `POST /webhook/individual-game-finished`

#### Cevap Webhook'ları
- `POST /webhook/answer-submitted`
- `POST /webhook/ranking-updated`

#### Ödeme Webhook'ları
- `POST /webhook/payment-completed`
- `POST /webhook/coin-purchased`

#### Arkadaş Davet Webhook'ları
- `POST /webhook/friend-invite-accepted`

### Socket Event'leri

Webhook'lar aşağıdaki socket event'lerini tetikler:

#### Turnuva Event'leri
- `tournament_started` - Turnuva başladığında
- `tournament_finished` - Turnuva bittiğinde
- `user_joined_tournament` - Kullanıcı katıldığında
- `user_left_tournament` - Kullanıcı ayrıldığında

#### Bireysel Oyun Event'leri
- `individual_game_started` - Bireysel oyun başladığında
- `individual_game_finished` - Bireysel oyun bittiğinde
- `answer_result` - Cevap verildiğinde

#### Sıralama Event'leri
- `ranking_updated` - Sıralama güncellendiğinde

#### Ödeme Event'leri
- `payment_completed` - Ödeme tamamlandığında
- `coin_purchased` - Jeton satın alındığında

#### Arkadaş Davet Event'leri
- `friend_invite_accepted` - Arkadaş davet kabul edildiğinde

### Test Etme

#### 1. Webhook Test Butonu
Test sayfasında "Webhook'ları Test Et" butonuna tıklayarak webhook'ları test edebilirsiniz.

#### 2. Manuel Test
```bash
# Turnuva başlatma webhook'u test et
curl -X POST http://localhost:3001/webhook/tournament-started \
  -H "Content-Type: application/json" \
  -d '{
    "tournament": {"id": 1, "title": "Test Turnuva"},
    "participants": [{"id": "user1", "name": "Test User"}],
    "timestamp": "2025-09-20T19:30:00Z"
  }'
```

#### 3. Socket Event Dinleme
```javascript
// Socket.IO client'ta event'leri dinle
socket.on('tournament_started', (data) => {
    console.log('Turnuva başladı:', data);
});

socket.on('answer_result', (data) => {
    console.log('Cevap sonucu:', data);
});
```

### Hata Yönetimi

WebhookController tüm hataları loglar ve `false` döndürür:

```php
// Webhook başarısız olursa
if (!$webhookController->tournamentStarted($tournament, $participants)) {
    \Log::error('Turnuva başlatma webhook\'u başarısız');
}
```

### Konfigürasyon

Socket server URL'i `WebhookController`'da ayarlanabilir:

```php
private $socketServerUrl = 'http://localhost:3001';
```

### Loglama

Tüm webhook işlemleri Laravel log dosyasında kaydedilir:

```bash
tail -f storage/logs/laravel.log | grep webhook
```

### Performans

Webhook'lar asenkron olarak çalışır ve ana işlemi bloklamaz. Hata durumunda uygulama çalışmaya devam eder.

### Güvenlik

Webhook'lar sadece internal network'ten erişilebilir. Production'da güvenlik token'ları eklenebilir.

## Örnek Kullanım Senaryoları

### 1. Turnuva Başlatma
```php
// TournamentController'da
public function start(Tournament $tournament)
{
    $tournament->update(['status' => 'active']);
    
    $webhookController = new WebhookController();
    $webhookController->tournamentStarted($tournament, $participants);
    
    return response()->json(['success' => true]);
}
```

### 2. Cevap Verme
```php
// GameAnswerController'da
public function submitAnswer(Request $request)
{
    // Cevap işleme logic'i
    $isCorrect = $this->checkAnswer($question, $userAnswer);
    $pointsEarned = $isCorrect ? 100 : 0;
    
    $webhookController = new WebhookController();
    $webhookController->answerSubmitted($gameId, $userId, $questionId, $isCorrect, $pointsEarned);
    
    return response()->json(['success' => true]);
}
```

### 3. Ödeme Tamamlama
```php
// PaymentController'da
public function webhook(Request $request)
{
    // Ödeme doğrulama logic'i
    $payment = $this->processPayment($request);
    
    $webhookController = new WebhookController();
    $webhookController->paymentCompleted($payment->user_id, $payment->toArray());
    
    return response()->json(['success' => true]);
}
```

Bu entegrasyon sayesinde Laravel uygulamasındaki tüm önemli olaylar Socket.IO sunucusuna gerçek zamanlı olarak bildirilir ve kullanıcılara anında iletilebilir.

