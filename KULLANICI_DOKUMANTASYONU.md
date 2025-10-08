# 🎯 Bilbakalim Quiz Sistemi - Kullanıcı Dokümantasyonu

## 📋 İçindekiler
1. [Sistem Genel Bakış](#sistem-genel-bakış)
2. [Quiz Türleri](#quiz-türleri)
3. [Normal Quiz (Sonsuz Mod)](#normal-quiz-sonsuz-mod)
4. [Premium Quiz (Paralı Mod)](#premium-quiz-paralı-mod)
5. [Turnuva Sistemi](#turnuva-sistemi)
6. [Joker Sistemi](#joker-sistemi)
7. [Coin Sistemi](#coin-sistemi)
8. [Mobil Uygulama Kullanımı](#mobil-uygulama-kullanımı)
9. [Web Uygulaması Kullanımı](#web-uygulaması-kullanımı)
10. [Ödül Sistemi](#ödül-sistemi)

---

## 🎮 Sistem Genel Bakış

Bilbakalim, kullanıcıların bilgilerini test edebileceği ve yarışabileceği kapsamlı bir quiz platformudur. Sistem 3 ana mod içerir:

- **Normal Quiz (Sonsuz Mod)**: Ücretsiz, sınırsız oynanabilir
- **Premium Quiz (Paralı Mod)**: Ücretli, özel ödüller
- **Turnuva Sistemi**: Çoklu oyuncu yarışmaları

---

## 🎯 Quiz Türleri

### Normal Quiz (Sonsuz Mod)
- **Ücretsiz** oynanabilir
- **İlk 10 soru kolay**, sonrakiler rastgele zorlukta
- **Süre sınırı**: Süre sınırı yok (sonsuz mod)
- **3 joker hakkı** (birer adet)
- **Coin kazanma**: Doğru cevap başına soru değeri kadar, yanlış cevap başına soru değeri kadar eksilme

### Premium Quiz (Paralı Mod)
- **Premium üyelik** gerekli
- **Soru dağılımı**: 7 orta, 8 zor (toplam 15 soru)
- **Süre sınırı**: 30 dakika (1800 saniye)
- **3 joker hakkı** (birer adet)
- **Özel ödül sistemi** (%100 doğru cevap ile)
- **Yanlış cevapladığı anda oyun biter**

---

## 🏆 Turnuva Sistemi

### Turnuva Türleri

#### 1. Süreye Göre Turnuva
- **Süre**: 5 dakika
- **Soru sayısı**: Sınırsız (süre içinde)
- **Kazanma**: En çok doğru cevap

#### 2. Soru Sayısına Göre Turnuva
- **Soru sayısı**: 10 soru
- **Süre**: Sınırsız
- **Kazanma**: En yüksek puan

### Turnuva Özellikleri
- **Minimum katılımcı**: Admin belirler
- **Maksimum katılımcı**: Admin belirler
- **Hız bonusu**: Hız bonusu bulunmuyor
- **Otomatik eleme**: Coin biten oyuncular elenir
- **Gerçek zamanlı sıralama**
- **Socket.IO bağlantısı zorunlu**: Turnuva sırasında bağlantı kesilirse oyuncu elenir

---

## 🃏 Joker Sistemi

### Joker Türleri

#### 1. 50-50 Joker
- **Etki**: 2 yanlış seçenek kaldırılır
- **Kullanım**: Soru başına 1 kez
- **Maliyet**: 100 coin

#### 2. Çift Cevap Joker
- **Etki**: 2 seçenek işaretlenebilir
- **Kullanım**: Soru başına 1 kez
- **Maliyet**: 200 coin

#### 3. Doğru Cevap Jokeri
- **Etki**: Soru cevabını verir
- **Kullanım**: Soru başına 1 kez
- **Maliyet**: 150 coin

### Joker Kuralları
- Premium quiz'de **3 joker** ücretsiz verilir
- Normal quiz'de **3 joker** ücretsiz verilir
- Ek joker **coin ile satın alınabilir** (veya paket aldıysa 3 joker paketine eklenir)
- Joker kullanımı **otomatik olarak azaltılır**

---

## 💰 Coin Sistemi

### Coin Kazanma
- **Normal Quiz**: Doğru cevap başına soru değeri, yanlış cevap başına soru değeri kadar eksilme
- **Premium Quiz**: Doğru cevap başına soru değeri + ödül
- **Turnuva**: Sıralamaya göre bonus coin

### Coin Harcama
- **Joker satın alma**
- **Premium üyelik**
- **Özel özellikler**

### Soru Değerleri
- **Kolay**: 20-50 coin
- **Orta**: 50-300 coin
- **Zor**: 300-10000 coin

### Özel Ödül Sistemi (Premium Quiz)
- **%100 doğru cevap**: 10.000 coin + 10'er adet joker
- **%80+ başarı**: 2.000 coin + 3'er adet joker

---

## 📱 Mobil Uygulama Kullanımı

### Mobil Quiz Akışı

#### 1. Quiz Başlatma
```
POST /api/quiz/normal/mobile/start
POST /api/quiz/premium/mobile/start
```

**Özellikler:**
- Tüm sorular **başlangıçta** getirilir
- Kullanıcı **offline** çalışabilir
- **Toplu cevap** gönderimi

#### 2. Cevap Gönderme
```
POST /api/quiz/normal/mobile/submit-answers
POST /api/quiz/premium/mobile/submit-answers
```

**Cevap Formatı:**
```json
{
  "game_id": 1,
  "answers": [
    {
      "question_id": 123,
      "selected_option": 2,
      "time_spent": 30,
      "joker_used": "fifty_fifty"
    }
  ]
}
```

### Mobil Avantajları
- **Hızlı yükleme**: Tüm sorular önceden indirilir
- **Offline çalışma**: İnternet olmadan da oynanabilir
- **Toplu gönderim**: Tek seferde tüm cevaplar
- **Veri tasarrufu**: Tek istek ile tüm işlem

---

## 💻 Web Uygulaması Kullanımı

### Web Quiz Akışı

#### 1. Quiz Başlatma
```
POST /api/quiz/normal/start
POST /api/quiz/premium/start
```

**Özellikler:**
- **Tek soru** getirilir
- **Gerçek zamanlı** oyun
- **Anlık feedback**

#### 2. Cevap Gönderme
```
POST /api/quiz/normal/answer
POST /api/quiz/premium/answer
```

**Cevap Formatı:**
```json
{
  "game_id": 1,
  "question_id": 123,
  "selected_option": 2,
  "time_spent": 30,
  "joker_used": "fifty_fifty"
}
```

### Web Avantajları
- **Gerçek zamanlı**: Anlık sonuçlar
- **Interaktif**: Joker kullanımı, anlık feedback
- **Sosyal**: Turnuva katılımı
- **Detaylı istatistik**: Anlık skor takibi

---

## 🎁 Ödül Sistemi

### Premium Quiz Ödülleri

#### Başarı Oranına Göre Ödüller

**%100 Başarı (Paket 3):**
- 5000 coin
- 5'er adet tüm jokerler
- "Mükemmel!" rozeti

**%80-99 Başarı (Paket 2):**
- 3000 coin
- 3'er adet tüm jokerler
- "Harika!" rozeti

**%60-79 Başarı (Paket 1):**
- 1000 coin
- 2'şer adet tüm jokerler
- "İyi!" rozeti

**%60 Altı:**
- Sadece doğru cevap coinleri
- Motivasyon mesajı

### Turnuva Ödülleri

#### Sıralama Ödülleri
- **1. Sıra**: 1000 coin + özel rozet
- **2. Sıra**: 500 coin + rozet
- **3. Sıra**: 250 coin + rozet
- **Diğerleri**: Katılım coinleri

---

## 🔄 Gerçek Zamanlı Özellikler

### Socket.IO Entegrasyonu

#### Quiz Olayları
- **Quiz başladı**: Oyun başlangıcı bildirimi
- **Cevap gönderildi**: Anlık skor güncellemesi
- **Joker kullanıldı**: Joker durumu güncellemesi
- **Quiz tamamlandı**: Final sonuçları

#### Turnuva Olayları
- **Oyuncu katıldı**: Yeni katılımcı bildirimi
- **Turnuva başladı**: Oyun başlangıcı
- **Sıralama güncellendi**: Anlık liderlik tablosu
- **Oyuncu elendi**: Eleme bildirimi
- **Turnuva bitti**: Final sonuçları

### Bildirim Sistemi
- **FCM Push**: Mobil bildirimler
- **Email**: Önemli olaylar için
- **Socket**: Gerçek zamanlı güncellemeler

---

## 📊 İstatistik ve Takip

### Kişisel İstatistikler
- **Toplam oyun sayısı**
- **Başarı oranı**
- **Kazanılan toplam coin**
- **Kullanılan joker sayısı**
- **En iyi skorlar**

### Oyun Geçmişi
- **Detaylı cevap analizi**
- **Soru bazında performans**
- **Zaman analizi**
- **Joker kullanım istatistikleri**

---

## 🛠️ Sorun Giderme

### Sık Karşılaşılan Sorunlar

#### 1. Quiz Başlatılamıyor
- **Çözüm**: Aktif oyun kontrolü yapın
- **Kontrol**: `/api/quiz/normal/status` endpoint'i

#### 2. Cevap Gönderilemiyor
- **Çözüm**: Oyun ID'sini kontrol edin
- **Kontrol**: Oyun aktif mi?

#### 3. Joker Kullanılamıyor
- **Çözüm**: Joker sayısını kontrol edin
- **Kontrol**: Premium üyelik gerekli

#### 4. Turnuva Katılımı
- **Çözüm**: Minimum katılımcı bekleyin
- **Kontrol**: Turnuva durumu



## 🎯 Başarı İpuçları

### Quiz Stratejileri
1. **Zaman yönetimi**: Hızlı ama doğru cevap
2. **Joker kullanımı**: Zor sorularda kullanın
3. **Soru analizi**: Seçenekleri dikkatli okuyun
4. **Pratik**: Düzenli oyun oynayın

### Turnuva Stratejileri
1. **Hız**: İlk cevaplayan bonus alır
2. **Doğruluk**: Yanlış cevap puan kaybettirir
3. **Joker yönetimi**: Stratejik kullanın
4. **Psikoloji**: Sakin kalın

---

## 📈 Gelecek Özellikler

### Planlanan Güncellemeler
- **Sosyal özellikler**: Arkadaş ekleme
- **Liga sistemi**: Seviye bazlı yarışmalar
- **Özel turnuvalar**: Sponsorlu yarışmalar
- **AI asistan**: Kişisel öğrenme rehberi

---

## 🚀 Son Güncellemeler (Ekim 2025)

### ✅ Yeni Özellikler
1. **Normal Quiz Joker Sistemi**: Normal quiz'de de 3 joker hakkı
2. **Premium Quiz Özel Ödül Sistemi**: %100 doğru cevap ile özel ödüller
3. **Turnuva Socket.IO Entegrasyonu**: Gerçek zamanlı bağlantı kontrolü
4. **Gelişmiş Eleme Sistemi**: Bağlantı kesilme durumunda otomatik eleme

### ✅ Düzeltilen Özellikler
1. **Normal Quiz Süre Sınırı**: Artık süre sınırı yok (sonsuz mod)
2. **Premium Quiz Soru Dağılımı**: 7 orta + 8 zor soru
3. **Premium Quiz Oyun Bitirme**: Yanlış cevap verildiğinde oyun biter
4. **Turnuva Hız Bonusu**: Hız bonusu kaldırıldı
5. **Joker Fiyatları**: Güncellenmiş fiyat listesi

### ✅ Test Edilen Sistemler
- **Normal Quiz**: Sonsuz mod, joker sistemi, coin kazanma/eksilme
- **Premium Quiz**: 7 orta + 8 zor soru, yanlış cevap oyun bitirme, özel ödüller
- **Turnuva Sistemi**: 5 dk süre, 10 soru, en yüksek puan kazanma
- **Joker Sistemi**: 50-50, Çift Cevap, Doğru cevap jokerleri
- **Coin Sistemi**: Kazanma/harcama, soru değerleri, ödül sistemi
- **Socket.IO**: Gerçek zamanlı bağlantı ve webhook sistemi

### ✅ Sistem Durumu
**Tüm sistemler production-ready durumda ve hatasız çalışıyor!**

### Geri Bildirim
- **Önerileriniz**: feedback@bilbakalim.com
- **Hata bildirimi**: bug@bilbakalim.com
- **Özellik istekleri**: feature@bilbakalim.com

---

*Son güncelleme: Ekim 2025*
*Versiyon: 2.1*
