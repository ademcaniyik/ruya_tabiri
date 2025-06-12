<?php

namespace App;

class Config {
    private static $env = [];

    public static function load() {
        if (empty(self::$env)) {
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '#') === 0 || empty($line)) continue;
                    
                    // # işaretinden sonraki yorumları kaldır
                    $line = explode('#', $line)[0];
                    
                    list($key, $value) = explode('=', $line, 2);
                    self::$env[trim($key)] = trim($value);
                }
            }
        }
    }

    public static function get($key, $default = null) {
        self::load();
        return isset(self::$env[$key]) ? self::$env[$key] : $default;
    }

    // Tüm env değerlerini döndür (debug için)
    public static function getAllEnv() {
        self::load();
        return self::$env;
    }
}
