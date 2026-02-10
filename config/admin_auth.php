<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Admin session helpers
 * Session key: $_SESSION['admin_id']
 */

function admin_logged_in(): bool {
  return !empty($_SESSION['admin_id']);
}

function current_admin_id(): int {
  return (int)($_SESSION['admin_id'] ?? 0);
}

/**
 * If you want a shared helper name for "is_admin"
 * this returns true when admin session exists.
 */
function is_admin(): bool {
  return admin_logged_in();
}

/**
 * Redirect admin pages to admin login when not logged in
 * Adjust path if your admin login page differs.
 */
function require_admin_login(string $loginPath = '/Food-Fusion-web/admin/admin.php'): void {
  if (!admin_logged_in()) {
    header('Location: ' . $loginPath);
    exit;
  }
}
