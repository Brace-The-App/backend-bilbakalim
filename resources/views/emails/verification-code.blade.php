<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }
        
        .code {
            font-size: 36px;
            font-weight: bold;
            color: white;
            letter-spacing: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 10px 0;
        }
        
        .code-label {
            color: white;
            font-size: 16px;
            margin-bottom: 15px;
            opacity: 0.9;
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
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .expiry-info strong {
            color: #1976d2;
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
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">🧠 Bilbakalim</div>
            <h1>{{ $purposeTitle }}</h1>
            <p>Güvenli doğrulama sistemi</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <div class="message">
                Merhaba,<br><br>
                {!! $messageText !!}
            </div>
            
            <!-- Code Display -->
            <div class="code-container">
                <div class="code-label">Doğrulama Kodunuz</div>
                <div class="code">{{ $code }}</div>
            </div>
            
            <!-- Expiry Information -->
            <div class="expiry-info">
                <strong>⏰ Önemli:</strong> Bu kod <strong>15 dakika</strong> geçerlidir. 
                Doğrulama işlemi de <strong>15 dakika</strong> içinde tamamlanmalıdır.
            </div>
            
            <!-- Warning -->
            <div class="warning">
                <span class="warning-icon"></span>
                <strong>Güvenlik Uyarısı:</strong> Bu kodu kimseyle paylaşmayın. 
                Bilbakalim ekibi hiçbir zaman sizden doğrulama kodunuzu talep etmez.
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
