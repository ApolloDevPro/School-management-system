<?php
declare(strict_types=1);

/**
 * Public web entry point.
 *
 * The application itself lives under backend/; this file only hands the
 * request over so the site URL can stay http://host/school mgt system/.
 * Apache blocks direct access to backend/ (see .htaccess), so all traffic
 * enters here and BASE_URL keeps pointing at the project root.
 */

require __DIR__ . '/backend/index.php';
