<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - Bilbakalim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .code-container {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(255, 154, 158, 0.3);
        }
        
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #d63031;
            letter-spacing: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin: 10px 0;
        }
        
        .code-label {
            color: #d63031;
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .message {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.8;
        }
        
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        
        .warning-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #f39c12;
            border-radius: 50%;
            margin-right: 10px;
            position: relative;
        }
        
        .warning-icon::before {
            content: "⚠";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer p {
            color: #6c757d;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        
        .expiry-info {
            background-color: #ffe6e6;
            border-left: 4px solid #ff6b6b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .expiry-info strong {
            color: #d63031;
        }
        
        .steps {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .steps h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 6px;
            border-left: 3px solid #ff6b6b;
        }
        
        .step-number {
            background-color: #ff6b6b;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .step-text {
            color: #495057;
            font-size: 14px;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 5px;
            }
            
            .header, .content, .footer {
                padding: 20px 15px;
            }
            
            .code {
                font-size: 28px;
                letter-spacing: 6px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">🔐 Bilbakalim</div>
            <h1>Şifre Sıfırlama</h1>
            <p>Hesabınızın güvenliği için</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="message">
                Merhaba,<br><br>
                Hesabınız için şifre sıfırlama talebinde bulundunuz. 
                Aşağıdaki kodu kullanarak şifrenizi güvenle sıfırlayabilirsiniz.
            </div>
            
            <!-- Code Display -->
            <div class="code-container">
                <div class="code-label">Şifre Sıfırlama Kodunuz</div>
                <div class="code">{{ $code }}</div>
            </div>
            
            <!-- Steps -->
            <div class="steps">
                <h3>📋 Şifre Sıfırlama Adımları</h3>
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-text">Yukarıdaki 6 haneli kodu kopyalayın</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">Şifre sıfırlama sayfasında kodu girin</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">Yeni şifrenizi belirleyin ve kaydedin</div>
                </div>
            </div>
            
            <!-- Expiry Information -->
            <div class="expiry-info">
                <strong>⏰ Önemli:</strong> Bu kod <strong>15 dakika</strong> geçerlidir. 
                Şifre sıfırlama işlemi de <strong>15 dakika</strong> içinde tamamlanmalıdır.
            </div>
            
            <!-- Warning -->
            <div class="warning">
                <span class="warning-icon"></span>
                <strong>Güvenlik Uyarısı:</strong> Bu kodu kimseyle paylaşmayın. 
                Eğer bu talebi siz yapmadıysanız, hesabınızı hemen güvenli hale getirin.
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Bilbakalim</strong> - Bilgi Yarışması Platformu</p>
            <p>Bu e-posta otomatik olarak gönderilmiştir. Lütfen yanıtlamayın.</p>
            <p>© {{ date('Y') }} Bilbakalim. Tüm hakları saklıdır.</p>
        </div>
    </div>
</body>
</html>
