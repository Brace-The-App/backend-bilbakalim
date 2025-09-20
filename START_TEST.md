# 🚀 Turnuva Test Sistemi Başlatma

## Hızlı Başlangıç

### 1. Socket Server'ı Başlatın
```bash
cd /Users/d3pi3dr4/Desktop/backend-bilbakalim/socket-server
node server.js
```

### 2. Laravel Server'ı Başlatın
```bash
cd /Users/d3pi3dr4/Desktop/backend-bilbakalim
./vendor/bin/sail up -d
```

### 3. Test Sayfasını Açın
```
http://localhost/test-tournament.html
```

## Test Adımları

### 🏆 Tek Oyunculu Turnuva
1. Sol panelde "Bağlan" butonuna tıklayın
2. "Turnuvaya Katıl" butonuna tıklayın
3. "Oyunu Başlat" butonuna tıklayın
4. Sorulara cevap verin ve skorunuzu takip edin

### 👥 Çoklu Oyunculu Turnuva
1. Sağ panelde "Bağlan" butonuna tıklayın
2. "Turnuvaya Katıl" butonuna tıklayın
3. "Oyunu Başlat" butonuna tıklayın
4. Liderlik tablosunu takip edin
5. Sorulara hızlı cevap verin

## API Testleri

### Test Durumu
```bash
curl http://localhost/api/tournaments/test/status
```

### Tek Oyunculu Test
```bash
curl -X POST http://localhost/api/tournaments/1/test-start
```

### Çoklu Oyunculu Test
```bash
curl -X POST http://localhost/api/tournaments/2/test-start
```

## Beklenen Sonuçlar

✅ **Socket Bağlantıları**: Her iki panel de "Bağlandı" durumunda olmalı
✅ **Turnuva Katılımı**: Log'larda katılım mesajları görünmeli
✅ **Oyun Mekaniği**: Sorular gösterilmeli, timer çalışmalı
✅ **Gerçek Zamanlı Güncellemeler**: Sıralama ve skorlar anlık güncellenmeli
✅ **Cevap Sistemi**: Doğru/yanlış cevaplar renkli gösterilmeli

## Sorun Giderme

### Socket Bağlantı Sorunu
- Socket server'ın çalıştığını kontrol edin
- Port 3000'in açık olduğunu kontrol edin

### API Sorunu
- Laravel server'ın çalıştığını kontrol edin
- Veritabanı bağlantısını kontrol edin

### Test Verisi Sorunu
- Test turnuvalarının oluşturulduğunu kontrol edin
- Migration'ların çalıştığını kontrol edin

## Test Tamamlandı! 🎉

Tüm testler başarılı olduğunda:
- Socket bağlantıları çalışıyor
- Tek oyunculu turnuva çalışıyor
- Çoklu oyunculu turnuva çalışıyor
- Gerçek zamanlı güncellemeler çalışıyor
- Skor sistemi doğru çalışıyor

**Test Raporu:** TOURNAMENT_TEST_GUIDE.md dosyasını doldurun

