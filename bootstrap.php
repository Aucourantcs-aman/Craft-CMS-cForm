<?php
/**
 * Shared bootstrap file
 */

// Define path constants
define('CRAFT_BASE_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');

// Load Composer's autoloader
require_once CRAFT_VENDOR_PATH . '/autoload.php';

// Load dotenv?
if (class_exists(Dotenv\Dotenv::class)) {
    // By default, this will allow .env file values to override environment variables
    // with matching names. Use `createUnsafeImmutable` to disable this.
    Dotenv\Dotenv::createUnsafeMutable(CRAFT_BASE_PATH)->safeLoad();
}

// reCAPTCHA keys (optional): define via .env to avoid committing secrets
// Put these in your .env file:
// RECAPTCHA_SITE_KEY="6LcYttQrAAAAAARaGbSHpEaljdV3XNYiEjyG1icY"
// RECAPTCHA_SECRET_KEY="6LcYttQrAAAAABOIFBd1VJ_BZIuzDvPaRnZUTH87"
