<?php
/**
 * Page de connexion administration
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/CSRF.php';

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$db = new Database();
$auth = new Auth($db);
$rateLimiter = new RateLimiter($db, 5, 15); // 5 tentatives, blocage 15 min

// Si déjà connecté, rediriger
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$isBlocked = $rateLimiter->isBlocked();
$remainingAttempts = $rateLimiter->getRemainingAttempts();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le rate limiting
    if ($isBlocked) {
        $remainingTime = ceil($rateLimiter->getRemainingLockTime() / 60);
        $error = "Trop de tentatives. Réessayez dans {$remainingTime} minute(s).";
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée. Veuillez rafraîchir la page.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs';
        } else {
            $result = $auth->login($email, $password);
            if ($result['success']) {
                $rateLimiter->reset(); // Réinitialiser les tentatives
                header('Location: index.php');
                exit;
            } else {
                $rateLimiter->recordFailedAttempt();
                $remainingAttempts = $rateLimiter->getRemainingAttempts();
                if ($remainingAttempts > 0) {
                    $error = $result['error'] . " ({$remainingAttempts} tentative(s) restante(s))";
                } else {
                    $error = 'Compte temporairement bloqué. Réessayez dans 15 minutes.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Administration ChatBot IA</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --text: #1e293b;
            --text-light: #64748b;
            --bg: #f1f5f9;
            --white: #ffffff;
            --error: #ef4444;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: var(--white);
            padding: 48px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo p {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .error {
            background: #fef2f2;
            color: var(--error);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fecaca;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ChatBot IA</h1>
            <p>Administration</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= CSRF::inputField() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="votre@email.com">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required
                       placeholder="••••••••">
            </div>

            <button type="submit" class="btn">Se connecter</button>
        </form>

        <a href="../index.php" class="back-link">← Retour au site</a>
    </div>
</body>
</html>
