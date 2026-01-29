<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3;url=/">
    <title>Page introuvable - ChatBot IA</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 24px;
        }

        .error-container {
            text-align: center;
            max-width: 500px;
        }

        .error-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);
        }

        .error-icon svg {
            width: 48px;
            height: 48px;
            fill: white;
        }

        .error-code {
            font-size: 72px;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .error-message {
            font-size: 16px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .redirect-notice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #e2e8f0;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .redirect-text {
            font-size: 14px;
            color: #64748b;
        }

        .countdown {
            font-weight: 600;
            color: #6366f1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }

        .btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg viewBox="0 0 24 24">
                <path d="M11 15h2v2h-2v-2zm0-8h2v6h-2V7zm.99-5C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
            </svg>
        </div>

        <div class="error-code">Oups !</div>
        <h1 class="error-title">Page introuvable</h1>
        <p class="error-message">
            Nous n'avons pas trouvé ce que vous cherchiez.<br>
            La page que vous recherchez n'existe pas ou a été déplacée.
        </p>

        <div class="redirect-notice">
            <div class="spinner"></div>
            <span class="redirect-text">Redirection vers l'accueil dans <span class="countdown" id="countdown">3</span>s...</span>
        </div>

        <a href="/" class="btn">
            <svg viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            Retour à l'accueil
        </a>
    </div>

    <script>
        var seconds = 3;
        var countdownEl = document.getElementById('countdown');

        var interval = setInterval(function() {
            seconds--;
            countdownEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(interval);
            }
        }, 1000);
    </script>
</body>
</html>
