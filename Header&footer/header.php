<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/conn.php';          // $pdo
require_once __DIR__ . '/../config/auth.php';          // helpers
require_once __DIR__ . '/../config/auth_actions.php';  // handle login/join/logout before output

if (!defined('BASE_URL')) define('BASE_URL', PROJECT_BASE);

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/* flash from auth_actions */
$authFlash = $_SESSION['auth_flash'] ?? [];
unset($_SESSION['auth_flash']);

$joinError    = $authFlash['joinError']    ?? null;
$joinSuccess  = $authFlash['joinSuccess']  ?? null;
$loginError   = $authFlash['loginError']   ?? null;
$loginSuccess = $authFlash['loginSuccess'] ?? null;

$openJoin  = (($authFlash['open'] ?? null) === 'join')  || (bool)($joinError || $joinSuccess);
$openLogin = (($authFlash['open'] ?? null) === 'login') || (bool)($loginError || $loginSuccess);

$csrf_auth = auth_csrf_token();

/* Base path */
$PROJECT_BASE = PROJECT_BASE;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? "FoodFusion") ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    body{ background:#895129; }

    header#top, .navbar{ position: sticky; top:0; z-index:1030; }

    /* Layout */
    .navbar .navbar-collapse{ flex-wrap: nowrap; }
    .navbar .navbar-nav{ flex-wrap: nowrap; gap:.300rem; width: auto; }
    .navbar .navbar-nav .nav-item{ flex:0 0 auto; }

    .navbar .ff-nav-right{
      flex-shrink:0;
      white-space:nowrap;
    }
    .ff-nav-user{
      max-width:220px;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    /* Premium link hover */
    .navbar .navbar-nav .nav-link{
      position: relative;
      padding: .48rem .85rem;
      border-radius: 999px;
      color:#1f2937;
      font-weight:600;
      letter-spacing:.1px;
      transition: background-color .18s ease, color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .navbar .navbar-nav .nav-link::after{
      content:"";
      position:absolute;
      left:18%;
      right:18%;
      bottom:6px;
      height:2px;
      border-radius:999px;
      background: currentColor;
      opacity:0;
      transform: translateY(3px);
      transition: opacity .18s ease, transform .18s ease;
    }
    .navbar .navbar-nav .nav-link:hover,
    .navbar .navbar-nav .nav-link:focus{
      background: rgba(0,0,0,.045);
      color:#111827;
      transform: translateY(-1px);
      box-shadow: 0 8px 18px rgba(0,0,0,.10);
      text-decoration:none;
    }
    .navbar .navbar-nav .nav-link:hover::after,
    .navbar .navbar-nav .nav-link:focus::after{
      opacity:.55;
      transform: translateY(0);
    }

    /* Buttons */
    .navbar .btn{ border-radius:999px; }
  </style>
</head>

<body>
<header id="top">
  <nav class="navbar sticky-top navbar-expand-xl bg-body-tertiary border-bottom">
    <div class="container">

      <a class="navbar-brand fw-bold" href="<?= h($PROJECT_BASE) ?>/index.php">FoodFusion</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
              aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <!-- IMPORTANT: keep nav links inside collapse -->
      <div class="collapse navbar-collapse" id="mainNav">

        <ul class="navbar-nav mx-auto mb-2 mb-xl-0">
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/aboutus.php">About</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/recipe_collection.php">Recipe Collection</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/community_cookbook.php">Community Cookbook</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/contact.php">Contact Us</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/culinary_resources.php">Culinary Resources</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h($PROJECT_BASE) ?>/public/educational_resources.php">Educational Resources</a></li>
        </ul>

        <div class="d-flex align-items-center gap-2 ff-nav-right">
          <?php if (is_logged_in()): ?>
            <?php $roleText = (string)current_user_role($pdo); ?>
            <span class="small text-secondary ff-nav-user">
               <?= h(current_user_name()) ?> (<?= h($roleText) ?>)
            </span>
            <a class="btn btn-outline-secondary btn-sm" href="?logout=1">Logout</a>
          <?php else: ?>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#loginModal">
              Login
            </button>
            <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#joinModal">
              Sign up now
            </button>
          <?php endif; ?>
        </div>

      </div><!-- /collapse -->
    </div>
  </nav>
</header>

<!-- JOIN MODAL -->
<div class="modal fade" id="joinModal" tabindex="-1" aria-labelledby="joinModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="joinModalTitle">Sign up for FoodFusion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?php if ($joinSuccess): ?><div class="alert alert-success mb-3"><?= h($joinSuccess) ?></div><?php endif; ?>
        <?php if ($joinError): ?><div class="alert alert-danger mb-3"><?= h($joinError) ?></div><?php endif; ?>

        <form method="post" action="#top" class="row g-3">
          <input type="hidden" name="csrf_auth" value="<?= h($csrf_auth) ?>">

          <div class="col-12 col-md-6">
            <label class="form-label" for="fname">First Name</label>
            <input class="form-control" type="text" id="fname" name="fname" required>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="lname">Last Name</label>
            <input class="form-control" type="text" id="lname" name="lname" required>
          </div>

          <div class="col-12">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" type="text" id="username" name="username" required>
            <div class="form-text">3-30 chars: letters, numbers, _ or .</div>
          </div>

          <div class="col-12">
            <label class="form-label" for="phone">Phone (optional)</label>
            <input class="form-control" type="text" id="phone" name="phone">
          </div>

          <div class="col-12">
            <label class="form-label" for="gender">Gender (optional)</label>
            <select class="form-select" id="gender" name="gender">
              <option value="">—</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role" required>
              <option value="user">User</option>
              <option value="chef">Chef</option>
            </select>
          </div>

          <div class="col-12" id="joinChefFields" style="display:none;">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="chef_name">Chef Name</label>
                <input class="form-control" type="text" id="chef_name" name="chef_name" placeholder="e.g., Chef Aung Aung">
              </div>
              <div class="col-12">
                <label class="form-label" for="country">Country (optional)</label>
                <input class="form-control" type="text" id="country" name="country" placeholder="e.g., Myanmar">
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required>
          </div>

          <div class="col-12">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="col-12">
            <button class="btn btn-dark w-100" type="submit" name="join_submit">Create my account</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="loginModalTitle">Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?php if ($loginSuccess): ?><div class="alert alert-success mb-3"><?= h($loginSuccess) ?></div><?php endif; ?>
        <?php if ($loginError): ?><div class="alert alert-danger mb-3"><?= h($loginError) ?></div><?php endif; ?>

        <form method="post" action="#top" class="row g-3">
          <input type="hidden" name="csrf_auth" value="<?= h($csrf_auth) ?>">

          <div class="col-12">
            <label class="form-label" for="login_identifier">Email or Username</label>
            <input class="form-control" type="text" id="login_identifier" name="login_identifier" required>
          </div>

          <div class="col-12">
            <label class="form-label" for="login_password">Password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="login_password" name="login_password" required autocomplete="current-password">
              <button class="btn btn-outline-secondary" type="button" data-toggle-password="#login_password" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="col-12">
            <button class="btn btn-dark w-100" type="submit" name="login_submit">Login</button>
          </div>
        </form>

        <div class="small text-muted mt-3">
          Security: account locks after <?= (int)MAX_LOGIN_ATTEMPTS ?> failed attempts for <?= (int)LOCKOUT_MINUTES ?> minutes.
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($openJoin || $openLogin): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($openJoin): ?> new bootstrap.Modal(document.getElementById('joinModal')).show(); <?php endif; ?>
  <?php if ($openLogin): ?> new bootstrap.Modal(document.getElementById('loginModal')).show(); <?php endif; ?>
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Role -> Chef fields (JOIN MODAL)
  const roleEl = document.getElementById('role');
  const chefWrap = document.getElementById('joinChefFields');
  const chefName = document.getElementById('chef_name');

  function syncChefFields() {
    if (!roleEl || !chefWrap) return;
    const isChef = roleEl.value === 'chef';
    chefWrap.style.display = isChef ? '' : 'none';
    if (chefName) chefName.required = isChef;
  }
  if (roleEl) {
    roleEl.addEventListener('change', syncChefFields);
    syncChefFields();
  }

  // Password eye toggle (join/login)
  document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', () => {
      const selector = btn.getAttribute('data-toggle-password');
      const input = document.querySelector(selector);
      if (!input) return;

      const icon = btn.querySelector('i');
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';

      if (icon) {
        icon.classList.toggle('bi-eye', showing);
        icon.classList.toggle('bi-eye-slash', !showing);
      }
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  });
});
</script>

<main class="py-3">
