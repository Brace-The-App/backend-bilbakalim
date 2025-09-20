# 🎮 Turnuva Test Rehberi

## Test Sistemi Kurulumu

### 1. Socket Server'ı Başlatma
```bash
cd socket-server
node server.js
```

### 2. Laravel Server'ı Başlatma
```bash
./vendor/bin/sail up -d
```

### 3. Test Sayfasını Açma
```
http://localhost/test-tournament.html
```

## Test Senaryoları

### 🏆 Tek Oyunculu Turnuva Testi

1. **Bağlantı Testi**
   - "Bağlan" butonuna tıklayın
   - Durum "Bağlandı" olmalı
   - Log'da "✅ Tek oyunculu socket bağlandı" mesajı görünmeli

2. **Turnuvaya Katılım**
   - "Turnuvaya Katıl" butonuna tıklayın
   - Log'da katılım mesajı görünmeli

3. **Oyun Başlatma**
   - "Oyunu Başlat" butonuna tıklayın
   - Soru ekranı görünmeli
   - 30 saniyelik timer başlamalı

4. **Cevap Verme**
   - Sorulara cevap verin
   - Doğru cevaplar yeşil, yanlış cevaplar kırmızı olmalı
   - Skor güncellenmeli

### 👥 Çoklu Oyunculu Turnuva Testi

1. **Bağlantı Testi**
   - "Bağlan" butonuna tıklayın
   - Durum "Bağlandı" olmalı

2. **Turnuvaya Katılım**
   - "Turnuvaya Katıl" butonuna tıklayın
   - Log'da katılım mesajı görünmeli

3. **Oyun Başlatma**
   - "Oyunu Başlat" butonuna tıklayın
   - Liderlik tablosu görünmeli
   - Soru ekranı görünmeli

4. **Gerçek Zamanlı Sıralama**
   - Cevaplar verildikçe sıralama güncellenmeli
   - Log'da sıralama güncellemeleri görünmeli

## API Test Endpoint'leri

### Test Durumu Kontrolü
```bash
curl -X GET http://localhost/api/tournaments/test/status
```

### Tek Oyunculu Turnuva Başlatma
```bash
curl -X POST http://localhost/api/tournaments/1/test-start
```

### Çoklu Oyunculu Turnuva Başlatma
```bash
curl -X POST http://localhost/api/tournaments/2/test-start
```

## Socket Event Testleri

### Gönderilen Event'ler
- `join_tournament` - Turnuvaya katılım
- `tournament_answer_submitted` - Cevap gönderme
- `multiplayer_tournament_start` - Çoklu turnuva başlatma
- `multiplayer_answer_submitted` - Çoklu turnuva cevap
- `multiplayer_ranking_update` - Sıralama güncelleme

### Alınan Event'ler
- `user_joined_tournament` - Kullanıcı katılım bildirimi
- `tournament_answer_result` - Cevap sonucu
- `multiplayer_tournament_started` - Çoklu turnuva başlama
- `multiplayer_answer_result` - Çoklu turnuva cevap sonucu
- `multiplayer_ranking_updated` - Sıralama güncellemesi
- `multiplayer_tournament_ended` - Çoklu turnuva bitiş

## Beklenen Sonuçlar

### ✅ Başarılı Test Kriterleri

1. **Socket Bağlantısı**
   - Her iki socket de başarıyla bağlanmalı
   - Bağlantı kesildiğinde uyarı verilmeli

2. **Turnuva Katılımı**
   - Turnuvalara başarıyla katılım sağlanmalı
   - Katılım bildirimleri alınmalı

3. **Oyun Mekaniği**
   - Sorular doğru şekilde gösterilmeli
   - Timer çalışmalı
   - Cevaplar doğru işlenmeli

4. **Gerçek Zamanlı Güncellemeler**
   - Sıralama güncellemeleri anlık olmalı
   - Cevap sonuçları hemen görünmeli

5. **Skor Sistemi**
   - Doğru cevaplar için puan verilmeli
   - Skorlar doğru hesaplanmalı

### ❌ Hata Durumları

1. **Bağlantı Hataları**
   - Socket server çalışmıyorsa bağlantı kurulamaz
   - Network sorunlarında otomatik yeniden bağlanma

2. **Turnuva Hataları**
   - Dolu turnuvalara katılım engellenmeli
   - Süresi dolmuş turnuvalara katılım engellenmeli

3. **Oyun Hataları**
   - Geçersiz cevaplar işlenmemeli
   - Süre dolduğunda otomatik cevap verilmeli

## Test Verileri

### Test Kullanıcıları
- `test-user-1` - Tek oyunculu turnuva
- `test-user-2` - Çoklu oyunculu turnuva
- `test-user-3` - Çoklu oyunculu turnuva (rakip)
- `test-user-4` - Çoklu oyunculu turnuva (rakip)

### Test Soruları
- Türkiye'nin başkenti
- Dünyanın en büyük okyanusu
- Hangi gezegen Güneş'e en yakın
- Türkiye'nin en büyük gölü
- Hangi element periyodik tabloda 'O' ile gösterilir
- İstanbul'un fethi hangi yılda

## Sorun Giderme

### Socket Bağlantı Sorunu
1. Socket server'ın çalıştığını kontrol edin
2. Port 3000'in açık olduğunu kontrol edin
3. Firewall ayarlarını kontrol edin

### API Bağlantı Sorunu
1. Laravel server'ın çalıştığını kontrol edin
2. CORS ayarlarını kontrol edin
3. API endpoint'lerinin doğru olduğunu kontrol edin

### Veritabanı Sorunu
1. Migration'ların çalıştığını kontrol edin
2. Veritabanı bağlantısını kontrol edin
3. Test verilerinin oluşturulduğunu kontrol edin

## Test Raporu

Test tamamlandıktan sonra aşağıdaki bilgileri raporlayın:

- [ ] Socket bağlantıları başarılı
- [ ] Tek oyunculu turnuva çalışıyor
- [ ] Çoklu oyunculu turnuva çalışıyor
- [ ] Gerçek zamanlı güncellemeler çalışıyor
- [ ] Skor sistemi doğru çalışıyor
- [ ] Hata durumları doğru işleniyor

**Test Tarihi:** ___________
**Test Eden:** ___________
**Sonuç:** ___________
**Notlar:** ___________

