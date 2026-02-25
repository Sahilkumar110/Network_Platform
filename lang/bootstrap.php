<?php

if (!defined('APP_LANG_BOOTSTRAPPED')) {
    define('APP_LANG_BOOTSTRAPPED', true);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $supported_locales = ['en', 'hi'];
    $default_locale = 'en';

    $requested_locale = $_GET['lang'] ?? ($_SESSION['lang'] ?? $default_locale);
    $requested_locale = strtolower((string)$requested_locale);
    if (!in_array($requested_locale, $supported_locales, true)) {
        $requested_locale = $default_locale;
    }

    $_SESSION['lang'] = $requested_locale;

    $lang_file = __DIR__ . DIRECTORY_SEPARATOR . $requested_locale . '.php';
    $translations = [];
    if (is_file($lang_file)) {
        $loaded = require $lang_file;
        if (is_array($loaded)) {
            $translations = $loaded;
        }
    }

    $GLOBALS['app_locale'] = $requested_locale;
    $GLOBALS['app_translations'] = $translations;

    if (!function_exists('app_locale')) {
        function app_locale() {
            return $GLOBALS['app_locale'] ?? 'en';
        }
    }

    if (!function_exists('t')) {
        function t($key, $fallback = null) {
            $map = $GLOBALS['app_translations'] ?? [];
            if (isset($map[$key])) {
                return $map[$key];
            }
            return $fallback !== null ? $fallback : $key;
        }
    }
}

