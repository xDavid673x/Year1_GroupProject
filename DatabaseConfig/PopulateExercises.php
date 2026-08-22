<?php
declare(strict_types=1);

// Backwards-compatible entrypoint; the canonical migration also seeds exercises.
require __DIR__ . "/migrate.php";
