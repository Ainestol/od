<?php

// bezpečné session
require_once __DIR__ . '/../lib/session.php';

// databáze
require_once __DIR__ . '/../config/db.php';

// CSRF ochrana
require_once __DIR__ . '/../lib/csrf.php';

// rate limit
require_once __DIR__ . '/../lib/rate_limit.php';