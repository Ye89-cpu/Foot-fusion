<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* =========================
   SESSION (MUST BE FIRST)
========================= */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pageTitle = "FoodFusion | Contact Us";

require_once __DIR__ . '/../config/conn.php'; // $pdo

/* ---------- helpers ---------- */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_contact_token(): string {
  if (empty($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_contact'];
}
function csrf_contact_check(): void {
  $t = (string)($_POST['csrf_contact'] ?? '');
  if ($t === '' || empty($_SESSION['csrf_contact']) || !hash_equals((string)$_SESSION['csrf_contact'], $t)) {
    http_response_code(403);
    exit('CSRF token mismatch.');
  }
}

function flash_set(string $type, string $msg): void {
  $_SESSION['flash_contact'] = ['type' => $type, 'msg' => $msg];
}
function flash_get(): ?array {
  if (empty($_SESSION['flash_contact'])) return null;
  $f = $_SESSION['flash_contact'];
  unset($_SESSION['flash_contact']);
  return $f;
}
function redirect_to(string $url): void {
  header("Location: {$url}");
  exit;
}

/* ---------- Project base (adjust once) ---------- */
$PROJECT_BASE = '/Food-Fusion-web'; // change if your folder name differs
$THIS_PAGE = $PROJECT_BASE . '/public/Contact.php';

/* ---------- Table ensure ---------- */
function ensure_contact_messages_table(PDO $pdo): void {
  $sql = "
    CREATE TABLE IF NOT EXISTS `contact_messages` (
      `message_id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NULL,
      `name` VARCHAR(120) NOT NULL,
      `email` VARCHAR(190) NOT NULL,
      `subject` VARCHAR(200) NOT NULL,
      `type` ENUM('Enquiry','Recipe Request','Feedback','Bug Report') NOT NULL DEFAULT 'Enquiry',
      `message` TEXT NOT NULL,
      `status` ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`message_id`),
      KEY `idx_contact_user_id` (`user_id`),
      KEY `idx_contact_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  $pdo->exec($sql);
}
try { ensure_contact_messages_table($pdo); } catch (Throwable $e) { error_log("contact_messages create failed: ".$e->getMessage()); }

/* ---------- Detect users columns (fname/lname vs first_name/last_name) ---------- */
function users_columns(PDO $pdo): array {
  static $cols = null;
  if ($cols !== null) return $cols;
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
  } catch (Throwable $e) {
    $cols = [];
  }
  return $cols;
}
function users_name_email_by_id(PDO $pdo, int $uid): array {
  $cols = users_columns($pdo);

  $hasFirst = in_array('first_name', $cols, true);
  $hasLast  = in_array('last_name', $cols, true);
  $hasF     = in_array('fname', $cols, true);
  $hasL     = in_array('lname', $cols, true);
  $hasEmail = in_array('email', $cols, true);

  $firstCol = $hasFirst ? 'first_name' : ($hasF ? 'fname' : null);
  $lastCol  = $hasLast  ? 'last_name'  : ($hasL ? 'lname' : null);

  // Build SQL safely (only columns that exist)
  $selectParts = [];
  if ($firstCol) $selectParts[] = "{$firstCol} AS first_part";
  if ($lastCol)  $selectParts[] = "{$lastCol} AS last_part";
  if ($hasEmail) $selectParts[] = "email AS email_part";

  if (!$selectParts) return ['', ''];

  $sql = "SELECT " . implode(", ", $selectParts) . " FROM users WHERE user_id=:id LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([':id' => $uid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) return ['', ''];

  $name = trim(((string)($row['first_part'] ?? '')) . ' ' . ((string)($row['last_part'] ?? '')));
  $email = trim((string)($row['email_part'] ?? ''));

  return [$name, $email];
}

/* =========================
   LOGGED IN AUTO-FILL
========================= */
$loggedIn = !empty($_SESSION['user_id']);
$userName = '';
$userEmail = '';

if ($loggedIn) {
  try {
    $uid = (int)$_SESSION['user_id'];
    [$userName, $userEmail] = users_name_email_by_id($pdo, $uid);
    if ($userName === '' || $userEmail === '') $loggedIn = false;
  } catch (Throwable $e) {
    error_log("Contact autofill error: " . $e->getMessage());
    $loggedIn = false;
  }
}

/* =========================
   HANDLE SUBMIT (PRG)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_contact_check();

  // If logged in → force values from DB
  $name  = $loggedIn ? $userName : trim((string)($_POST['name'] ?? ''));
  $email = $loggedIn ? $userEmail : trim((string)($_POST['email'] ?? ''));

  $subject = trim((string)($_POST['subject'] ?? ''));
  $type    = trim((string)($_POST['type'] ?? 'Enquiry'));
  $message = trim((string)($_POST['message'] ?? ''));

  // Honeypot (optional anti-bot)
  $hp = (string)($_POST['website'] ?? '');
  if ($hp !== '') {
    flash_set('danger', 'Invalid submission.');
    redirect_to($THIS_PAGE);
  }

  $allowedTypes = ['Enquiry','Recipe Request','Feedback','Bug Report'];
  if (!in_array($type, $allowedTypes, true)) $type = 'Enquiry';

  // validation
  if ($name === '' || $email === '' || $subject === '' || $message === '') {
    flash_set('danger', 'Please fill in all required fields.');
    redirect_to($THIS_PAGE);
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger', 'Please enter a valid email address.');
    redirect_to($THIS_PAGE);
  }
  if (mb_strlen($subject) < 3) {
    flash_set('danger', 'Subject is too short.');
    redirect_to($THIS_PAGE);
  }
  if (mb_strlen($message) < 10) {
    flash_set('danger', 'Message is too short. Please provide more details.');
    redirect_to($THIS_PAGE);
  }

  try {
    $userId = $loggedIn ? (int)($_SESSION['user_id']) : null;

    $sql = "INSERT INTO contact_messages (user_id, name, email, subject, type, message)
            VALUES (:uid, :name, :email, :subject, :type, :message)";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':uid' => $userId,
      ':name' => $name,
      ':email' => $email,
      ':subject' => $subject,
      ':type' => $type,
      ':message' => $message,
    ]);

    flash_set('success', 'Thanks! Your message has been saved. We will reply soon.');
    redirect_to($THIS_PAGE);
  } catch (Throwable $e) {
    error_log("Contact insert failed: " . $e->getMessage());
    flash_set('danger', 'Database error. Please try again later.');
    redirect_to($THIS_PAGE);
  }
}

/* =========================
   UI RENDER (SAFE NOW)
========================= */
$flash = flash_get();
$csrf  = csrf_contact_token();

require_once __DIR__ . '/../header&footer/header.php';
?>

<style>

.ff-max { max-width: 1200px; }
.ff-card{ border:1px solid rgba(0,0,0,.08); border-radius: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.06); background:#fff; }
.ff-muted{ color:#65676b; }
.ff-hero-img{ width:100%; height: 280px; object-fit: cover; border-radius: 16px; }
@media (max-width: 768px){ .ff-hero-img{ height: 220px; } }
.ff-pill{ display:inline-flex; align-items:center; gap:.5rem; padding:.45rem .75rem; border-radius: 999px; background:#fff; border:1px solid rgba(0,0,0,.08); box-shadow: 0 1px 2px rgba(0,0,0,.04); font-size:.95rem; }
.ff-pill .k{ font-weight:700; color:#111; }
.ff-pill .v{ color:#65676b; }
.ff-input{ background:#f0f2f5; border:1px solid transparent; border-radius: 12px; }
.ff-input:focus{ background:#fff; border-color: rgba(0,0,0,.15); box-shadow:none; }
.ff-btn-pill{ border-radius:999px; }
.ff-side-card{ background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:16px; padding:16px; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
.ff-side-card ul{ padding-left: 1.15rem; margin-bottom:0; }
.ff-map{ width:100%; height: 280px; border:0; border-radius: 16px; }
.ff-toast-wrap{ position: fixed; top: 16px; right: 16px; z-index: 2000; }
</style>

<div class="ff-toast-wrap">
  <?php if ($flash): ?>
    <div id="ffToastFlash" class="toast align-items-center text-bg-<?= h((string)$flash['type']) ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body"><?= h((string)$flash['msg']) ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", function(){
        var t = document.getElementById("ffToastFlash");
        if (t && window.bootstrap) new bootstrap.Toast(t, { delay: 5500 }).show();
      });
    </script>
  <?php endif; ?>
</div>

<div class="ff-bg py-4">
  <div class="container ff-max">

    <div class="row g-3 align-items-center mb-3">
      <div class="col-12 col-lg-6">
        <div class="ff-card p-4">
          <h1 class="h3 fw-bold mb-2">Contact FoodFusion</h1>
          <p class="ff-muted mb-3">Have a question, recipe request, or feedback? Send us a message and we’ll get back to you.</p>

          <div class="d-flex flex-wrap gap-2">
            <span class="ff-pill"><span class="k">Email</span><span class="v">support@foodfusion.com</span></span>
            <span class="ff-pill"><span class="k">Location</span><span class="v">Yangon, Myanmar</span></span>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <img class="ff-hero-img" src="<?= h($PROJECT_BASE . '/photo/contanct.jpg') ?>" alt="Kitchen tools and ingredients">
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-8">
        <div class="ff-card p-4">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
            <div>
              <h2 class="h5 fw-bold mb-1">Send a message</h2>
              <p class="ff-muted mb-0">Fill in the form and we’ll respond as soon as possible.</p>
            </div>
            <?php if ($loggedIn): ?>
              <span class="badge text-bg-secondary align-self-center">Logged in</span>
            <?php endif; ?>
          </div>

          <form method="POST" action="">
            <input type="hidden" name="csrf_contact" value="<?= h($csrf) ?>">
            <!-- honeypot -->
            <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">

            <div class="row g-3 mt-1">
              <div class="col-12 col-md-6">
                <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text"
                       class="form-control ff-input" placeholder="Your name" required
                       value="<?= h($loggedIn ? $userName : '') ?>"
                       <?= $loggedIn ? 'readonly' : '' ?>>
              </div>

              <div class="col-12 col-md-6">
                <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                <input id="email" name="email" type="email"
                       class="form-control ff-input" placeholder="you@example.com" required
                       value="<?= h($loggedIn ? $userEmail : '') ?>"
                       <?= $loggedIn ? 'readonly' : '' ?>>
              </div>

              <div class="col-12 col-md-6">
                <label for="type" class="form-label fw-semibold">Message Type</label>
                <select id="type" name="type" class="form-select ff-input">
                  <option value="Enquiry">Enquiry</option>
                  <option value="Recipe Request">Recipe Request</option>
                  <option value="Feedback">Feedback</option>
                  <option value="Bug Report">Bug Report</option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                <input id="subject" name="subject" type="text"
                       class="form-control ff-input" placeholder="Write a subject" required>
              </div>

              <div class="col-12">
                <label for="message" class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                <textarea id="message" name="message" rows="7" class="form-control"
                          style="border-radius:14px;" placeholder="Write your message..." required></textarea>
              </div>

              <div class="col-12 d-flex flex-column flex-sm-row gap-2 align-items-start">
                <button class="btn btn-dark ff-btn-pill px-4" type="submit">Send Message</button>
                <div class="small ff-muted">We normally reply within 24–48 hours (business days).</div>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="ff-side-card mb-3">
          <h3 class="h6 fw-bold mb-2">What we can help with</h3>
          <ul class="ff-muted">
            <li>Recipe suggestions and requests</li>
            <li>Community Cookbook support</li>
            <li>Account issues (login/registration)</li>
            <li>Feedback and collaboration</li>
          </ul>
        </div>

        <div class="ff-side-card mb-3">
          <h3 class="h6 fw-bold mb-2">Response time</h3>
          <p class="ff-muted mb-0">We normally reply within 24–48 hours (business days).</p>
        </div>

        <div class="ff-side-card">
          <h3 class="h6 fw-bold mb-2">Find us in Yangon</h3>
          <iframe class="ff-map"
            src="https://www.google.com/maps?q=Yangon,+Myanmar&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            aria-label="Google Map Yangon"></iframe>
          <div class="small ff-muted mt-2">Tip: You can zoom and drag the map.</div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
