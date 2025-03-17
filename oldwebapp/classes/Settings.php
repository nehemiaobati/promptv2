<?php

class Settings
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSetting(string $settingName): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = ?");
            $stmt->execute([$settingName]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : null;
        } catch (PDOException $e) {
            error_log("Error fetching setting: " . $e->getMessage());
            return null;
        }
    }
}
