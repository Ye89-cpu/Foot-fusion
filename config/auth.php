<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!defined('PROJECT_BASE')) {
  define('PROJECT_BASE', '/Food-Fusion-web');
}

if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
  }
}

if (!function_exists('current_user_id')) {
  function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
  }
}

if (!function_exists('current_user_name')) {
  function current_user_name(): string {
    $name = trim((string)($_SESSION['user_name'] ?? ''));
    if ($name !== '') return $name;

    $fname = trim((string)($_SESSION['fname'] ?? ''));
    if ($fname !== '') return $fname;

    return 'User';
  }
}

if (!function_exists('current_user_role')) {
  function current_user_role(?PDO $pdo = null): string {
    if (!is_logged_in()) return 'guest';

    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role !== '') return $role;

    if ($pdo) {
      try {
        $st = $pdo->prepare("SELECT role FROM users WHERE user_id = :uid LIMIT 1");
        $st->execute([':uid' => current_user_id()]);
        $dbRole = $st->fetchColumn();
        if (is_string($dbRole) && trim($dbRole) !== '') {
          $_SESSION['role'] = strtolower(trim($dbRole));
          return $_SESSION['role'];
        }
      } catch (Throwable $e) {
        // ignore
      }
    }

    return 'user';
  }
}

if (!function_exists('set_user_session')) {
  function set_user_session(array $row): void {
    $_SESSION['user_id'] = (int)($row['user_id'] ?? 0);

    $fname = trim((string)($row['fname'] ?? ''));
    $lname = trim((string)($row['lname'] ?? ''));
    $full  = trim($fname . ' ' . $lname);

    if ($full !== '') {
      $_SESSION['user_name'] = $full;
    } elseif (!empty($row['username'])) {
      $_SESSION['user_name'] = (string)$row['username'];
    } else {
      $_SESSION['user_name'] = 'User';
    }

    $_SESSION['fname'] = $fname !== '' ? $fname : $_SESSION['user_name'];

    if (!empty($row['role'])) {
      $_SESSION['role'] = strtolower(trim((string)$row['role']));
    }
  }
}

if (!function_exists('require_login')) {
  function require_login(?string $next = null): void {
    if (!is_logged_in()) {
      $url = PROJECT_BASE . '/public/login.php';
      if ($next) $url .= '?next=' . urlencode($next);
      header('Location: ' . $url);
      exit;
    }
  }
}
