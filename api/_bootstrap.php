<?php

// bezpečné session
require_once __DIR__ . '/../lib/session.php';
// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// databáze
require_once __DIR__ . '/../config/db.php';

// CSRF ochrana
require_once __DIR__ . '/../lib/csrf.php';

// rate limit
require_once __DIR__ . '/../lib/rate_limit.php';