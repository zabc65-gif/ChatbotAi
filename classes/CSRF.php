<?php
/**
 * Classe CSRF
 * Protection contre les attaques Cross-Site Request Forgery
 */

class CSRF
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LIFETIME = 3600; // 1 heure

    /**
     * Génère ou récupère un token CSRF
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier si le token existe et n'est pas expiré
        if (isset($_SESSION[self::TOKEN_NAME], $_SESSION[self::TOKEN_NAME . '_time'])) {
            if (time() - $_SESSION[self::TOKEN_NAME . '_time'] < self::TOKEN_LIFETIME) {
                return $_SESSION[self::TOKEN_NAME];
            }
        }

        // Générer un nouveau token
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_NAME . '_time'] = time();

        return $token;
    }

    /**
     * Vérifie si le token CSRF est valide
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || !isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        // Vérification time-safe
        if (!hash_equals($_SESSION[self::TOKEN_NAME], $token)) {
            return false;
        }

        // Vérifier l'expiration
        if (isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            if (time() - $_SESSION[self::TOKEN_NAME . '_time'] > self::TOKEN_LIFETIME) {
                return false;
            }
        }

        return true;
    }

    /**
     * Génère le champ input hidden pour les formulaires
     */
    public static function inputField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Vérifie le token et termine le script si invalide
     */
    public static function verify(): void
    {
        $token = $_POST[self::TOKEN_NAME] ?? null;

        if (!self::validateToken($token)) {
            http_response_code(403);
            die('Erreur de sécurité : token CSRF invalide. Veuillez rafraîchir la page et réessayer.');
        }
    }
}
