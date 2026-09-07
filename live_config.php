<?php
/**
 * Bbeautiful LIVE configuration.
 *
 * The root config.php MUST define a Config class with these constants — the app
 * reads Config::DB_* and Config::BASE_URL at bootstrap. This is the PRODUCTION
 * instance (bbeautiful.me -> port 8086).
 */
class Config
{
    // ------------------------------------------------------------------------
    // GENERAL SETTINGS
    // ------------------------------------------------------------------------

    const BASE_URL = 'https://bbeautiful.me';
    const LANGUAGE = 'english';
    const DEBUG_MODE = false;

    // ------------------------------------------------------------------------
    // DATABASE SETTINGS
    // ------------------------------------------------------------------------

    const DB_HOST = 'mysql';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = 'easyappointments';
    const DB_PASSWORD = 'Billie-Jo-beauty-2026!';

    // ------------------------------------------------------------------------
    // GOOGLE CALENDAR SYNC (Optional)
    // ------------------------------------------------------------------------
    // Optional; leave commented out. Not used.
}
