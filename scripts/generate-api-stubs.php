<?php
/**
 * DEPRECATED — Vercel now uses api/index.php + vercel.json rewrites.
 * Do not run this script; it recreates per-endpoint stubs that break deployment.
 */
declare(strict_types=1);

fwrite(STDERR, "generate-api-stubs.php is deprecated. Use api/index.php router instead.\n");
exit(1);
