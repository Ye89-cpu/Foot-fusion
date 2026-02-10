<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('PROJECT_BASE')) define('PROJECT_BASE', '/Food-Fusion-web');

/* expects $pdo; if not present, include conn */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  require_once __DIR__ . '/conn.php';
}

require_once __DIR__ . '/auth.php';

const MAX_LOGIN_ATTEMPTS = 3;
const LOCKOUT_MINUTES    = 3;

if (!function_exists('auth_csrf_token')) {
  function auth_csrf_token(): string {
    if (empty($_SESSION['csrf_auth'])) {
      $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_auth'];
  }
}

if (!function_exists('auth_csrf_check')) {
  function auth_csrf_check(): void {
    $t = (string)($_POST['csrf_auth'] ?? '');
    if ($t === '' || empty($_SESSION['csrf_auth']) || !hash_equals((string)$_SESSION['csrf_auth'], $t)) {
      http_response_code(403);
      exit('CSRF token mismatch (auth).');
    }
  }
}

function auth_flash_set(array $data): void {
  $_SESSION['auth_flash'] = $data;
}

function auth_redirect_back(): void {
  // Go back to same page without re-post (PRG)
  $path = strtok($_SERVER['REQUEST_URI'] ?? (PROJECT_BASE . '/index.php'), '#');
  header('Location: ' . $path);
  exit;
}

function is_email(string $s): bool {
  return (bool)filter_var($s, FILTER_VALIDATE_EMAIL);
}

function is_locked_out(array $user): bool {
  if (empty($user['lockout_until'])) return false;
  return strtotime((string)$user['lockout_until']) > time();
}

function lockout_message(array $user): string {
  $until = strtotime((string)$user['lockout_until']);
  $mins = max(1, (int)ceil(($until - time()) / 60));
  return "Account locked. Try again in about {$mins} minute(s).";
}

/* ---------- LOGOUT (GET) ---------- */
if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
  header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
  exit;
}

/* ---------- AUTH POSTS ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return; // nothing to do
}

/* Only handle our auth forms */
if (!isset($_POST['join_submit']) && !isset($_POST['login_submit'])) {
  return;
}

auth_csrf_check();

/* ---------- JOIN / REGISTER ---------- */
if (isset($_POST['join_submit'])) {
  $fname    = trim((string)($_POST['fname'] ?? ''));
  $lname    = trim((string)($_POST['lname'] ?? ''));
  $username = trim((string)($_POST['username'] ?? ''));
  $phone    = trim((string)($_POST['phone'] ?? ''));
  $gender   = trim((string)($_POST['gender'] ?? ''));
  $role     = strtolower(trim((string)($_POST['role'] ?? 'user'))); // user|chef
  $email    = trim((string)($_POST['email'] ?? ''));
  $pass     = (string)($_POST['password'] ?? '');

  $chef_name = trim((string)($_POST['chef_name'] ?? ''));
  $country   = trim((string)($_POST['country'] ?? ''));

  $joinError = null;
  $joinSuccess = null;

  if ($fname === '' || $lname === '' || $username === '' || $email === '' || $pass === '' || $role === '') {
    $joinError = "Please fill in all required fields.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $joinError = "Invalid email address.";
  } elseif (strlen($pass) < 6) {
    $joinError = "Password must be at least 6 characters.";
  } elseif (!preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $username)) {
    $joinError = "Username must be 3-30 chars (letters, numbers, _ or .).";
  } elseif ($gender !== '' && !in_array($gender, ['Male','Female','Other'], true)) {
    $joinError = "Invalid gender value.";
  } elseif (!in_array($role, ['user','chef'], true)) {
    $joinError = "Invalid role value.";
  } elseif ($role === 'chef' && $chef_name === '') {
    $joinError = "Chef Name is required when role is Chef.";
  }

  if ($joinError) {
    auth_flash_set(['open' => 'join', 'joinError' => $joinError]);
    auth_redirect_back();
  }

  try {
    $dup = $pdo->prepare("SELECT user_id FROM users WHERE email=:email OR username=:username LIMIT 1");
    $dup->execute([':email' => $email, ':username' => $username]);

    if ($dup->fetch()) {
      auth_flash_set(['open' => 'join', 'joinError' => "Email or username already exists."]);
      auth_redirect_back();
    }

    $pdo->beginTransaction();

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $insUser = $pdo->prepare("
      INSERT INTO users
        (fname, lname, username, phone, email, password_hash, gender, role, is_active,
         failed_login_attempts, lockout_until, last_login_at, created_at, updated_at)
      VALUES
        (:fname, :lname, :username, :phone, :email, :hash, :gender, :role, 1,
         0, NULL, NULL, NOW(), NOW())
    ");
    $insUser->execute([
      ':fname'    => $fname,
      ':lname'    => $lname,
      ':username' => $username,
      ':phone'    => ($phone === '' ? null : $phone),
      ':email'    => $email,
      ':hash'     => $hash,
      ':gender'   => ($gender === '' ? null : $gender),
      ':role'     => $role,
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    if ($role === 'chef') {
      if ($chef_name === '') $chef_name = trim($fname . ' ' . $lname);

      $insChef = $pdo->prepare("
        INSERT INTO chef_profiles (user_id, chef_name, country)
        VALUES (:user_id, :chef_name, :country)
      ");
      $insChef->execute([
        ':user_id'   => $newUserId,
        ':chef_name' => $chef_name,
        ':country'   => ($country === '' ? null : $country),
      ]);
    }

    $pdo->commit();

    set_user_session([
      'user_id' => $newUserId,
      'fname'   => $fname,
      'lname'   => $lname,
      'username'=> $username,
      'role'    => $role,
    ]);

    auth_flash_set(['open' => null, 'joinSuccess' => "Account created! You are now logged in."]);
    auth_redirect_back();

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    auth_flash_set(['open' => 'join', 'joinError' => "Signup failed. Please try again."]);
    auth_redirect_back();
  }
}

/* ---------- LOGIN ---------- */
if (isset($_POST['login_submit'])) {
  $identifier = trim((string)($_POST['login_identifier'] ?? ''));
  $pass       = (string)($_POST['login_password'] ?? '');

  if ($identifier === '' || $pass === '') {
    auth_flash_set(['open' => 'login', 'loginError' => "Please enter email/username and password."]);
    auth_redirect_back();
  }

  if (!is_email($identifier) && !preg_match('/^[a-zA-Z0-9_\.]{3,30}$/', $identifier)) {
    auth_flash_set(['open' => 'login', 'loginError' => "Enter a valid email or username (3-30 chars)."]);
    auth_redirect_back();
  }

  $byEmail = is_email($identifier);

  $stmt = $pdo->prepare("
    SELECT user_id, fname, lname, username, role, password_hash,
           failed_login_attempts, lockout_until, is_active
    FROM users
    WHERE " . ($byEmail ? "email = :ident" : "username = :ident") . "
    LIMIT 1
  ");
  $stmt->execute([':ident' => $identifier]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  $genericFail = "Invalid credentials.";

  if (!$user) {
    auth_flash_set(['open' => 'login', 'loginError' => $genericFail]);
    auth_redirect_back();
  }

  if ((int)$user['is_active'] !== 1) {
    auth_flash_set(['open' => 'login', 'loginError' => "This account is disabled."]);
    auth_redirect_back();
  }

  // reset after lockout expired
  if (!empty($user['lockout_until']) && strtotime((string)$user['lockout_until']) <= time()) {
    $pdo->prepare("UPDATE users SET failed_login_attempts=0, lockout_until=NULL WHERE user_id=:id")
        ->execute([':id' => (int)$user['user_id']]);
    $user['failed_login_attempts'] = 0;
    $user['lockout_until'] = null;
  }

  if (is_locked_out($user)) {
    auth_flash_set(['open' => 'login', 'loginError' => lockout_message($user)]);
    auth_redirect_back();
  }

  if (!password_verify($pass, (string)$user['password_hash'])) {
    $attempts  = (int)$user['failed_login_attempts'] + 1;
    $lockUntil = null;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
      $lockUntil = date('Y-m-d H:i:s', time() + (LOCKOUT_MINUTES * 60));
      $attempts  = MAX_LOGIN_ATTEMPTS;
    }

    $upd = $pdo->prepare("UPDATE users SET failed_login_attempts=:a, lockout_until=:l WHERE user_id=:id");
    $upd->execute([
      ':a'  => $attempts,
      ':l'  => $lockUntil,
      ':id' => (int)$user['user_id']
    ]);

    auth_flash_set(['open' => 'login', 'loginError' => $lockUntil ? "Too many attempts. Locked for " . LOCKOUT_MINUTES . " minutes." : $genericFail]);
    auth_redirect_back();
  }

  // success
  $pdo->prepare("UPDATE users SET failed_login_attempts=0, lockout_until=NULL, last_login_at=NOW() WHERE user_id=:id")
      ->execute([':id' => (int)$user['user_id']]);

  set_user_session($user);

  auth_flash_set(['open' => null, 'loginSuccess' => "Logged in successfully."]);
  auth_redirect_back();
}
