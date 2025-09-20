<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
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
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .notification-title {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .notification-content {
            font-size: 16px;
            line-height: 1.8;
            color: #4a5568;
            margin-bottom: 30px;
            white-space: pre-line;
        }
        
        .notification-meta {
            background-color: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .meta-label {
            font-weight: 600;
            color: #2d3748;
        }
        
        .meta-value {
            color: #4a5568;
        }
        
        .footer {
            background-color: #2d3748;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .footer p {
            margin-bottom: 10px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            margin: 20px 0;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 5px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .notification-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">BB</div>
            <h1>BilBakalim</h1>
            <p>Bildirim Sistemi</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <h2 class="notification-title">{{ $title }}</h2>
            
            <div class="notification-content">{{ $content }}</div>
            
            <div class="divider"></div>
            
            <div class="notification-meta">
                <div class="meta-item">
                    <span class="meta-label">Gönderim Tarihi:</span>
                    <span class="meta-value">{{ $sentAt }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Bildirim Tipi:</span>
                    <span class="meta-value">{{ strtoupper($type) }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Gönderen:</span>
                    <span class="meta-value">BilBakalim Admin</span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>BilBakalim</strong> - Bilgi Yarışması Platformu</p>
            <p>Bu email otomatik olarak gönderilmiştir. Lütfen yanıtlamayın.</p>
            <p>
                <a href="{{ config('app.url') }}">Web Sitesi</a> | 
                <a href="{{ config('app.url') }}/contact">İletişim</a>
            </p>
        </div>
    </div>
</body>
</html>
