<?php
define('DB_DRIVER', 'mysql');
const DB_HOST    = 'database-5020287861.webspace-host.com';
const DB_PORT    = 3306;
const DB_NAME    = 'dbs15593008';
const DB_USER    = 'dbu299572';
const DB_PASS    = 'schul_dashboard';
const DB_CHARSET = 'utf8mb4';
const DB_SQLITE_PATH = __DIR__ . '/../data/schul_dashboard.sqlite';
const JWT_SECRET = 'Str@t0-FamilieC@emmerer-JWT-Secret-2024!xK9#mP';
const JWT_TTL    = 86400 * 30;  // 30 Tage Standard
const JWT_TTL_LONG = 86400 * 90; // 90 Tage bei "Angemeldet bleiben"
const SUPERADMIN_USER = 'admin';
const SUPERADMIN_PASS = '$chulPl@n3r!';
date_default_timezone_set('Europe/Berlin');
