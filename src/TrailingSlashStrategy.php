<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Strategy for handling trailing slashes in request paths.
 *
 * - STRIP:        (default) Remove trailing slash before matching. `/foo/` → `/foo`
 * - REDIRECT_301: Return a 301 redirect to the canonical path (without trailing slash).
 * - ALLOW_BOTH:   Match routes with or without trailing slash (no normalization).
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
enum TrailingSlashStrategy: string
{
    case STRIP        = 'strip';
    case REDIRECT_301 = 'redirect';
    case ALLOW_BOTH   = 'both';
}
