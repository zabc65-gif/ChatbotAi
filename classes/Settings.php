<?php
/**
 * Classe Settings
 * Gestion des paramètres du site stockés en base de données
 */

class Settings
{
    private Database $db;
    private static array $cache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère une valeur de configuration
     */
    public function get(string $key, $default = null)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $sql = "SELECT setting_value, setting_type FROM settings WHERE setting_key = ?";
        $result = $this->db->fetchOne($sql, [$key]);

        if (!$result) {
            return $default;
        }

        $value = $this->castValue($result['setting_value'], $result['setting_type']);
        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * Définit une valeur de configuration
     */
    public function set(string $key, $value, string $type = 'string', string $group = 'general', string $label = ''): bool
    {
        $stringValue = $this->toString($value, $type);

        $sql = "INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = NOW()";

        $this->db->query($sql, [$key, $stringValue, $type, $group, $label]);

        self::$cache[$key] = $value;

        return true;
    }

    /**
     * Récupère tous les paramètres d'un groupe
     */
    public function getGroup(string $group): array
    {
        $sql = "SELECT setting_key, setting_value, setting_type, setting_label
                FROM settings WHERE setting_group = ? ORDER BY id";
        $results = $this->db->fetchAll($sql, [$group]);

        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                'type' => $row['setting_type'],
                'label' => $row['setting_label']
            ];
        }

        return $settings;
    }

    /**
     * Récupère tous les paramètres
     */
    public function getAll(): array
    {
        $sql = "SELECT setting_key, setting_value, setting_type, setting_group, setting_label
                FROM settings ORDER BY setting_group, id";
        $results = $this->db->fetchAll($sql);

        $settings = [];
        foreach ($results as $row) {
            if (!isset($settings[$row['setting_group']])) {
                $settings[$row['setting_group']] = [];
            }
            $settings[$row['setting_group']][$row['setting_key']] = [
                'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                'type' => $row['setting_type'],
                'label' => $row['setting_label']
            ];
        }

        return $settings;
    }

    /**
     * Supprime un paramètre
     */
    public function delete(string $key): bool
    {
        $sql = "DELETE FROM settings WHERE setting_key = ?";
        $this->db->query($sql, [$key]);
        unset(self::$cache[$key]);
        return true;
    }

    /**
     * Cast une valeur selon son type
     */
    private function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return $value === '1' || $value === 'true';
            case 'json':
            case 'array':
                return json_decode($value, true) ?? [];
            case 'text':
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Convertit une valeur en string pour stockage
     */
    private function toString($value, string $type): string
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
            case 'array':
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            default:
                return (string) $value;
        }
    }

    /**
     * Vide le cache
     */
    public function clearCache(): void
    {
        self::$cache = [];
    }
}
