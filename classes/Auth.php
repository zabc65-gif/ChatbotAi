<?php
/**
 * Classe Auth
 * Gestion de l'authentification des utilisateurs admin
 */

class Auth
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Tente de connecter un utilisateur
     */
    public function login(string $email, string $password): array
    {
        $sql = "SELECT id, username, email, password, role FROM users WHERE email = ? AND active = 1";
        $user = $this->db->fetchOne($sql, [$email]);

        if (!$user) {
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
        }

        // Créer la session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Mettre à jour la dernière connexion
        $this->db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Récupère l'utilisateur courant
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Protège une page (redirige si non connecté)
     */
    public function requireLogin(string $redirectUrl = 'login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Protège une page admin
     */
    public function requireAdmin(string $redirectUrl = 'login.php'): void
    {
        if (!$this->isAdmin()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Hash un mot de passe
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Crée un utilisateur
     */
    public function createUser(string $username, string $email, string $password, string $role = 'admin'): int
    {
        $hashedPassword = self::hashPassword($password);

        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role,
            'active' => 1
        ]);
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists(string $email): bool
    {
        $sql = "SELECT id FROM users WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]) !== null;
    }
}
