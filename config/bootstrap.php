<?php

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    function loadEnvFile(string $file): void {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }
            $len = strlen($value);
            if (
                $len >= 2 &&
                (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    function envValue(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    function envBool(string $key, bool $default = false): bool {
        $value = envValue($key, null);
        if ($value === null) {
            return $default;
        }
        $value = strtolower((string)$value);
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    function appLog(string $level, string $message, array $context = []): void {
        $logFile = (string)envValue('APP_ERROR_LOG', __DIR__ . '/../storage/logs/app.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeContext = $context;
        if (!empty($safeContext['password'])) {
            $safeContext['password'] = '[redacted]';
        }
        if (!empty($safeContext['db_password'])) {
            $safeContext['db_password'] = '[redacted]';
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $safeContext ? json_encode($safeContext, JSON_UNESCAPED_SLASHES) : ''
        );
        @error_log($line, 3, $logFile);
    }

    loadEnvFile(__DIR__ . '/../.env');

    date_default_timezone_set((string)envValue('APP_TIMEZONE', 'UTC'));

    $debug = envBool('APP_DEBUG', false);
    $logErrors = envBool('APP_LOG_ERRORS', true);
    $displayErrors = $debug ? '1' : '0';
    ini_set('display_errors', $displayErrors);
    ini_set('log_errors', $logErrors ? '1' : '0');
    ini_set('error_log', (string)envValue('APP_ERROR_LOG', __DIR__ . '/../storage/logs/app.log'));
    error_reporting($debug ? E_ALL : (E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT));

    set_exception_handler(function (Throwable $e): void {
        appLog('error', 'Unhandled exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "Unhandled exception. Check logs.\n");
        } else {
            http_response_code(500);
            echo "Internal Server Error";
        }
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if (!$err) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) {
            return;
        }
        appLog('fatal', 'Fatal shutdown error', $err);
    });
}
