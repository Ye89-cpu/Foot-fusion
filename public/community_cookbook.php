<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}



require_once __DIR__ . '/../config/conn.php';          // $pdo
require_once __DIR__ . '/../config/auth.php';          // is_logged_in(), require_login(), current_user_id(), current_user_role()
if (file_exists(__DIR__ . '/../config/auth_actions.php')) {
  require_once __DIR__ . '/../config/auth_actions.php'; // handle header login/logout forms BEFORE output (if you use it)
}
if (file_exists(__DIR__ . '/../config/admin_auth.php')) {
  require_once __DIR__ . '/../config/admin_auth.php';   // optional is_admin()
}

/* =========================================================
   PAGE CONFIG
========================================================= */
$pageTitle = "Food-Fusion-web | Community Cookbook";

/* =========================================================
   PROJECT BASE
========================================================= */
$PROJECT_BASE = defined('PROJECT_BASE') ? PROJECT_BASE : '/Food-Fusion-web';

/* =========================================================
   CONFIG
========================================================= */
$MAX_BYTES = 20 * 1024 * 1024; // 20MB
$UPLOAD_DIR = __DIR__ . '/../uploads/community/';
$UPLOAD_URL_PREFIX = $PROJECT_BASE . '/uploads/community/';

$PUBLIC_APPROVED_ONLY = true;   // public sees only approved
$CHEF_AUTO_APPROVE    = true;   // chef recipes auto approved

if (!is_dir($UPLOAD_DIR)) {
  mkdir($UPLOAD_DIR, 0777, true);
}

/* =========================================================
   HELPERS
========================================================= */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_cc'])) {
    $_SESSION['csrf_cc'] = bin2hex(random_bytes(32));
  }
  return (string)$_SESSION['csrf_cc'];
}

function csrf_check(): void {
  $t = (string)($_POST['csrf_cc'] ?? '');
  if ($t === '' || empty($_SESSION['csrf_cc']) || !hash_equals((string)$_SESSION['csrf_cc'], $t)) {
    http_response_code(403);
    exit('CSRF token mismatch.');
  }
}

function flash_set(string $type, string $msg): void {
  $_SESSION['flash_cc'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
  if (empty($_SESSION['flash_cc'])) return null;
  $f = $_SESSION['flash_cc'];
  unset($_SESSION['flash_cc']);
  return $f;
}

function redirect_to(string $url): void {
  header("Location: {$url}");
  exit;
}

function page_url(string $base, array $qs = []): string {
  if (!$qs) return $base;
  return $base . '?' . http_build_query($qs);
}

function mb_trim(?string $s): string {
  return trim((string)$s);
}

function is_admin_user(PDO $pdo): bool {
  // prefer admin_auth helper if exists
  if (function_exists('is_admin')) {
    try { return (bool)is_admin(); } catch (Throwable $e) { /* ignore */ }
  }

  // fallback to users.role == 'admin'
  if (!is_logged_in()) return false;
  try {
    $role = strtolower(trim((string)current_user_role($pdo)));
    return $role === 'admin';
  } catch (Throwable $e) {
    return false;
  }
}

function can_view_post(PDO $pdo, array $post): bool {
  $status = (string)($post['status'] ?? 'approved');
  if ($status === 'approved') return true;

  if (!is_logged_in()) return false;

  $uid = (int)current_user_id();
  if ((int)($post['user_id'] ?? 0) === $uid) return true;

  if (is_admin_user($pdo)) return true;

  return false;
}

/* =========================================================
   ROUTES
========================================================= */
$THIS_PAGE = $PROJECT_BASE . '/public/community_cookbook.php';

/* =========================================================
   POST ACTIONS (PRG)
   NOTE:
   - community actions use cc_action to avoid conflict with auth_actions.php (login/logout)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cc_action'])) {
  $ccAction = (string)($_POST['cc_action'] ?? '');
  if ($ccAction !== '') {
    csrf_check();
  }

  /* -----------------------------
     CREATE POST (login only)
  ----------------------------- */
  if ($ccAction === 'create_post') {
    require_login();

    $role    = strtolower(trim((string)current_user_role($pdo))); // user/chef/admin
    $isAdmin = is_admin_user($pdo);

    $postKind = (string)($_POST['post_kind'] ?? 'user_post'); // user_post or chef_recipe

    // only chef/admin can create chef_recipe
    if (!in_array($role, ['chef', 'admin'], true)) {
      $postKind = 'user_post';
    }
    if (!$isAdmin && $role !== 'chef') {
      $postKind = 'user_post';
    }

    $title   = mb_trim($_POST['title'] ?? '');
    $content = mb_trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
      flash_set('danger', 'Title and content are required.');
      redirect_to(page_url($THIS_PAGE, ['open' => '1']));
    }
    if (mb_strlen($title) > 200) {
      flash_set('danger', 'Title too long (max 200 chars).');
      redirect_to(page_url($THIS_PAGE, ['open' => '1']));
    }

    // cover image upload (optional)
    $coverImage = null;
    if (!empty($_FILES['cover_image']['name'])) {
      $size = (int)($_FILES['cover_image']['size'] ?? 0);
      if ($size > $MAX_BYTES) {
        flash_set('danger', 'Image too large. Max ' . (int)($MAX_BYTES / 1024 / 1024) . 'MB.');
        redirect_to(page_url($THIS_PAGE, ['open' => '1']));
      }

      $ext = strtolower(pathinfo((string)$_FILES['cover_image']['name'], PATHINFO_EXTENSION));
      $allowed = ['jpg', 'jpeg', 'png', 'webp'];
      if (!in_array($ext, $allowed, true)) {
        flash_set('danger', 'Image type not allowed. Use jpg/jpeg/png/webp.');
        redirect_to(page_url($THIS_PAGE, ['open' => '1']));
      }

      $new  = 'cc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $dest = $UPLOAD_DIR . $new;

      if (!move_uploaded_file((string)$_FILES['cover_image']['tmp_name'], $dest)) {
        flash_set('danger', 'Failed to upload image.');
        redirect_to(page_url($THIS_PAGE, ['open' => '1']));
      }

      $coverImage = $UPLOAD_URL_PREFIX . $new;
    }

    // chef fields
    $cuisine = null;
    $difficulty = null;
    $prep_time = null;
    $cook_time = null;
    $ingredients = null;
    $instructions = null;

    if ($postKind === 'chef_recipe') {
      $cuisine = mb_trim($_POST['cuisine'] ?? '');
      $difficulty = (string)($_POST['difficulty'] ?? '');
      $prep_time = (int)($_POST['prep_time'] ?? 0);
      $cook_time = (int)($_POST['cook_time'] ?? 0);
      $ingredients = mb_trim($_POST['ingredients'] ?? '');
      $instructions = mb_trim($_POST['instructions'] ?? '');

      if (
        $cuisine === '' ||
        !in_array($difficulty, ['Easy','Medium','Hard'], true) ||
        $ingredients === '' ||
        $instructions === ''
      ) {
        flash_set('danger', 'Chef recipe requires: cuisine, difficulty, ingredients, instructions.');
        redirect_to(page_url($THIS_PAGE, ['open' => '1']));
      }
      if ($prep_time < 0) $prep_time = 0;
      if ($cook_time < 0) $cook_time = 0;
    }

    // status policy
    $status = 'pending';
    if ($postKind === 'chef_recipe' && $CHEF_AUTO_APPROVE) $status = 'approved';
    if ($role === 'admin') $status = 'approved';

    try {
      if ($postKind === 'chef_recipe') {
        $stmt = $pdo->prepare("
          INSERT INTO community_posts
            (user_id, post_type, title, content, cover_image, cuisine, difficulty, prep_time, cook_time, ingredients, instructions, status)
          VALUES
            (:uid, 'chef_recipe', :t, :c, :img, :cu, :df, :pt, :ct, :ing, :ins, :st)
        ");
        $stmt->execute([
          ':uid' => (int)current_user_id(),
          ':t'   => $title,
          ':c'   => $content,
          ':img' => $coverImage,
          ':cu'  => $cuisine,
          ':df'  => $difficulty,
          ':pt'  => $prep_time ?: null,
          ':ct'  => $cook_time ?: null,
          ':ing' => $ingredients,
          ':ins' => $instructions,
          ':st'  => $status,
        ]);
      } else {
        $stmt = $pdo->prepare("
          INSERT INTO community_posts
            (user_id, post_type, title, content, cover_image, status)
          VALUES
            (:uid, 'user_post', :t, :c, :img, :st)
        ");
        $stmt->execute([
          ':uid' => (int)current_user_id(),
          ':t'   => $title,
          ':c'   => $content,
          ':img' => $coverImage,
          ':st'  => $status,
        ]);
      }
    } catch (Throwable $e) {
      flash_set('danger', 'DB insert failed. Check your community_posts columns match schema.');
      redirect_to(page_url($THIS_PAGE, ['open' => '1']));
    }

    flash_set('success', $status === 'pending'
      ? 'Posted! Waiting for admin approval.'
      : 'Post created successfully!'
    );
    redirect_to($THIS_PAGE);
  }

  /* -----------------------------
     DELETE POST (owner only)
  ----------------------------- */
  if ($ccAction === 'delete_post') {
    require_login();

    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId <= 0) {
      flash_set('danger', 'Invalid post.');
      redirect_to($THIS_PAGE);
    }

    $st = $pdo->prepare("
      SELECT post_id, user_id, cover_image
      FROM community_posts
      WHERE post_id=:pid AND user_id=:uid
      LIMIT 1
    ");
    $st->execute([':pid' => $postId, ':uid' => (int)current_user_id()]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
      flash_set('danger', 'You cannot delete this post.');
      redirect_to($THIS_PAGE);
    }

    $pdo->prepare("DELETE FROM community_comments WHERE post_id=:pid")->execute([':pid' => $postId]);
    $pdo->prepare("DELETE FROM community_reactions WHERE post_id=:pid")->execute([':pid' => $postId]);
    $pdo->prepare("DELETE FROM community_posts WHERE post_id=:pid AND user_id=:uid")
        ->execute([':pid' => $postId, ':uid' => (int)current_user_id()]);

    // delete cover image file (optional)
    if (!empty($p['cover_image'])) {
      $path = parse_url((string)$p['cover_image'], PHP_URL_PATH) ?: (string)$p['cover_image'];
      if (str_starts_with($path, $PROJECT_BASE)) {
        $path = substr($path, strlen($PROJECT_BASE));
      }
      $disk = realpath(__DIR__ . '/..') . $path;
      if ($disk && file_exists($disk)) @unlink($disk);
    }

    flash_set('success', 'Post deleted.');
    redirect_to($THIS_PAGE);
  }

  /* -----------------------------
     REACT (approved only)
  ----------------------------- */
  if ($ccAction === 'react') {
    require_login();

    $postId = (int)($_POST['post_id'] ?? 0);
    $type   = (string)($_POST['reaction_type'] ?? '');

    if ($postId <= 0 || !in_array($type, ['like', 'heart'], true)) {
      flash_set('danger', 'Invalid reaction.');
      redirect_to($THIS_PAGE);
    }

    $pst = $pdo->prepare("SELECT * FROM community_posts WHERE post_id=:pid LIMIT 1");
    $pst->execute([':pid' => $postId]);
    $post = $pst->fetch(PDO::FETCH_ASSOC);

    if (!$post || !can_view_post($pdo, $post)) {
      flash_set('danger', 'You cannot react to this post.');
      redirect_to($THIS_PAGE);
    }
    if ((string)($post['status'] ?? '') !== 'approved') {
      flash_set('danger', 'Pending posts cannot receive reactions.');
      redirect_to($THIS_PAGE);
    }

    $pdo->prepare("DELETE FROM community_reactions WHERE post_id=:pid AND user_id=:uid")
        ->execute([':pid' => $postId, ':uid' => (int)current_user_id()]);

    $pdo->prepare("
      INSERT INTO community_reactions (post_id, user_id, reaction_type)
      VALUES (:pid, :uid, :rt)
    ")->execute([':pid' => $postId, ':uid' => (int)current_user_id(), ':rt' => $type]);

    flash_set('success', 'Reaction saved!');
    redirect_to(page_url($THIS_PAGE, ['goto' => (string)$postId]) . '#p' . $postId);
  }

  /* -----------------------------
     COMMENT (approved only)
  ----------------------------- */
  if ($ccAction === 'comment') {
    require_login();

    $postId = (int)($_POST['post_id'] ?? 0);
    $txt    = mb_trim($_POST['comment_text'] ?? '');

    if ($postId <= 0) {
      flash_set('danger', 'Invalid post.');
      redirect_to($THIS_PAGE);
    }
    if ($txt === '') {
      flash_set('danger', 'Comment cannot be empty.');
      redirect_to(page_url($THIS_PAGE, ['goto' => (string)$postId]) . '#p' . $postId);
    }
    if (mb_strlen($txt) > 800) {
      flash_set('danger', 'Comment too long (max 800 chars).');
      redirect_to(page_url($THIS_PAGE, ['goto' => (string)$postId]) . '#p' . $postId);
    }

    $pst = $pdo->prepare("SELECT * FROM community_posts WHERE post_id=:pid LIMIT 1");
    $pst->execute([':pid' => $postId]);
    $post = $pst->fetch(PDO::FETCH_ASSOC);

    if (!$post || !can_view_post($pdo, $post)) {
      flash_set('danger', 'You cannot comment on this post.');
      redirect_to($THIS_PAGE);
    }
    if ((string)($post['status'] ?? '') !== 'approved') {
      flash_set('danger', 'Pending posts cannot receive comments.');
      redirect_to($THIS_PAGE);
    }

    $pdo->prepare("
      INSERT INTO community_comments (post_id, user_id, comment_text, is_hidden)
      VALUES (:pid, :uid, :t, 0)
    ")->execute([':pid' => $postId, ':uid' => (int)current_user_id(), ':t' => $txt]);

    flash_set('success', 'Comment added!');
    redirect_to(page_url($THIS_PAGE, ['goto' => (string)$postId]) . '#p' . $postId);
  }

  flash_set('danger', 'Unknown action.');
  redirect_to($THIS_PAGE);
}

/* =========================================================
   GET: FETCH FEED
========================================================= */
$flash = flash_get();
$csrf  = csrf_token();

$role    = strtolower(trim((string)(is_logged_in() ? current_user_role($pdo) : 'guest')));
$isAdmin = is_admin_user($pdo);

$where = [];
$params = [];

if ($isAdmin) {
  $where[] = "1=1";
} else {
  if ($PUBLIC_APPROVED_ONLY) {
    $where[] = "(p.status='approved'";
    if (is_logged_in()) {
      $where[] = " OR p.user_id=:me";
      $params[':me'] = (int)current_user_id();
    }
    $where[] = ")";
  } else {
    $where[] = "1=1";
  }
}

$whereSql = $where ? implode(' ', $where) : "1=1";

$st = $pdo->prepare("
  SELECT
    p.*,
    CONCAT_WS(' ', u.fname, u.lname) AS author_name
  FROM community_posts p
  JOIN users u ON u.user_id = p.user_id
  WHERE {$whereSql}
  ORDER BY p.created_at DESC
  LIMIT 200
");
$st->execute($params);
$posts = $st->fetchAll(PDO::FETCH_ASSOC);

// reaction counts
$reactionCounts = [];
$rx = $pdo->query("
  SELECT post_id, reaction_type, COUNT(*) cnt
  FROM community_reactions
  GROUP BY post_id, reaction_type
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rx as $r) {
  $pid = (int)$r['post_id'];
  $rt  = (string)$r['reaction_type'];
  $reactionCounts[$pid][$rt] = (int)$r['cnt'];
}

// comments grouped
$commentsByPost = [];
$cx = $pdo->query("
  SELECT
    c.*,
    CONCAT_WS(' ', u.fname, u.lname) AS author_name
  FROM community_comments c
  JOIN users u ON u.user_id = c.user_id
  WHERE c.is_hidden = 0
  ORDER BY c.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cx as $c) {
  $commentsByPost[(int)$c['post_id']][] = $c;
}

/* =========================================================
   HTML HEADER (SAFE NOW)
========================================================= */
require_once __DIR__ . '/../Header&footer/header.php';
?>

<style>
/* modal height control */
#createPostModal .modal-content{
  max-height: 80vh;
  overflow: hidden;
}
#createPostModal .modal-body{
  overflow-y: auto;
}
#createPostModal .modal-header{
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 5;
}

.ff-card{ border:1px solid rgba(0,0,0,.08); border-radius:16px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
.ff-muted{ color:#e5e7eb; }
.ff-avatar{
  width:42px;height:42px;border-radius:50%;
  background:#e4e6eb; display:flex;align-items:center;justify-content:center;
  font-weight:800;color:#111;
}
.ff-media{ width:100%; overflow:hidden; border-radius:12px; background:#f0f2f5; }
.ff-media img{ width:100%; height:220px; object-fit:cover; border-radius:12px; }
@media (max-width: 992px){ .ff-media img{ height:200px; } }
@media (max-width: 576px){ .ff-media img{ height:180px; } }
.ff-action-btn{
  border:0;background:transparent; padding:.45rem .75rem; border-radius:10px;
  font-weight:700; color:#65676b;
}
.ff-action-btn:hover{ background:#f2f2f2; color:#111; }
.ff-chip{
  display:inline-flex;align-items:center;gap:.35rem;
  background:#f0f2f5; border-radius:999px; padding:.2rem .55rem;
  font-size:.85rem; color:#111;
}
.ff-input{ background:#f0f2f5; border:1px solid transparent; border-radius:999px; }
.ff-input:focus{ background:#fff; border-color: rgba(0,0,0,.15); box-shadow:none; }
.ff-btn-pill{ border-radius:999px; }
.ff-status{
  font-size:.78rem; padding:.18rem .5rem; border-radius:999px;
  font-weight:800; text-transform:capitalize;
}
.ff-approved{ background:#dcfce7; color:#166534; }
.ff-pending{  background:#fef9c3; color:#854d0e; }
.ff-rejected{ background:#fee2e2; color:#991b1b; }
.ff-comment{ background:#f0f2f5; border-radius:14px; padding:.6rem .8rem; }
.ff-comment small{ color:#65676b; }
.ff-recipe-meta{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.35rem; }
.ff-meta-pill{
  background:#111827; color:#fff; border-radius:999px;
  padding:.18rem .55rem; font-size:.78rem; font-weight:700;
}
</style>

<div class="ff-feed-bg py-4">
  <div class="container-fluid px-3 px-md-4 px-lg-5">
    <div class="mx-auto" style="max-width:1400px;">

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h1 class="h3 fw-bold mb-1 text-white">Community Cookbook</h1>
          <p class="mb-0 ff-muted">Share recipes, cooking tips, and culinary experiences with the FoodFusion community.</p>
        </div>

        <?php if (is_logged_in()): ?>
          <button class="btn btn-dark ff-btn-pill px-4" type="button" data-bs-toggle="modal" data-bs-target="#createPostModal">
            + Post
          </button>
        <?php endif; ?>
      </div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= h((string)$flash['type']) ?> ff-card"><?= h((string)$flash['msg']) ?></div>
      <?php endif; ?>

      <?php if (!is_logged_in()): ?>
        <div class="alert alert-warning ff-card">
          Please <a href="<?= h($PROJECT_BASE) ?>/public/login.php" class="fw-semibold">Login</a> or
          <a href="<?= h($PROJECT_BASE) ?>/public/register.php" class="fw-semibold">Register</a> to post, react, and comment.
        </div>
      <?php endif; ?>

      <!-- Create Post Modal -->
      <?php if (is_logged_in()): ?>
      <div class="modal fade" id="createPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">

          <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Create a Post</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <?php $roleNow = $role; ?>
              <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_cc" value="<?= h($csrf) ?>">
                <input type="hidden" name="cc_action" value="create_post">

                <?php if (in_array($roleNow, ['chef','admin'], true)): ?>
                <div class="col-12">
                  <label class="form-label fw-semibold">Post Type</label>
                  <select class="form-select" name="post_kind" id="cc_post_kind">
                    <option value="chef_recipe">Chef Recipe</option>
                    <option value="user_post">Normal Post</option>
                  </select>
                </div>
                <?php else: ?>
                  <input type="hidden" name="post_kind" value="user_post">
                <?php endif; ?>

                <div class="col-12">
                  <label class="form-label fw-semibold">Title *</label>
                  <input name="title" class="form-control ff-input" maxlength="200" placeholder="Post title..." required>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Content *</label>
                  <textarea name="content" class="form-control" rows="4" required style="border-radius:14px;" placeholder="Write your recipe, tips, or story..."></textarea>
                </div>

                  <div class="col-12" id="ccChefFields" style="display:none;">
                    <div class="row g-3">
                      <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Cuisine *</label>
                        <input name="cuisine" class="form-control" placeholder="e.g., Myanmar / Italian">
                      </div>

                      <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Difficulty *</label>
                        <select name="difficulty" class="form-select">
                          <option value="">— Select —</option>
                          <option value="Easy">Easy</option>
                          <option value="Medium">Medium</option>
                          <option value="Hard">Hard</option>
                        </select>
                      </div>

                      <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Prep Time (min)</label>
                        <input name="prep_time" type="number" class="form-control" min="0" placeholder="e.g., 15">
                      </div>

                      <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold">Cook Time (min)</label>
                        <input name="cook_time" type="number" class="form-control" min="0" placeholder="e.g., 25">
                      </div>

                      <div class="col-12">
                        <label class="form-label fw-semibold">Ingredients *</label>
                        <textarea name="ingredients" class="form-control" rows="3" style="border-radius:14px;" placeholder="List ingredients..."></textarea>
                      </div>

                      <div class="col-12">
                        <label class="form-label fw-semibold">Instructions *</label>
                        <textarea name="instructions" class="form-control" rows="4" style="border-radius:14px;" placeholder="Write step-by-step..."></textarea>
                      </div>
                    </div>
                  </div>


                <div class="col-12">
                  <label class="form-label fw-semibold">Cover Image (optional)</label>
                  <input type="file" name="cover_image" accept="image/*" class="form-control">
                  <div class="small text-muted mt-1">Max upload: <?= (int)($MAX_BYTES/1024/1024) ?>MB</div>
                  <?php if (!in_array($roleNow, ['chef','admin'], true)): ?>
                    <div class="small text-muted mt-1">User post will be <b>pending</b> until admin approves.</div>
                  <?php endif; ?>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-dark ff-btn-pill px-4" type="submit">Post</button>
                  <button class="btn btn-outline-secondary ff-btn-pill px-4" type="button" data-bs-dismiss="modal">Cancel</button>
                </div>
              </form>
            </div>

          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($_GET['open']) && $_GET['open'] === '1'): ?>
        <script>
          document.addEventListener('DOMContentLoaded', function(){
            const el = document.getElementById('createPostModal');
            if (el && window.bootstrap) new bootstrap.Modal(el).show();
          });
        </script>
      <?php endif; ?>

      <script>
        // show chef fields only when post_kind=chef_recipe (robust inside modal)
        document.addEventListener('DOMContentLoaded', function () {
          const modal = document.getElementById('createPostModal');
          if (!modal) return;

          const kind = modal.querySelector('#cc_post_kind');
          const chefFields = modal.querySelector('#ccChefFields');
          if (!kind || !chefFields) return;

          const reqSelectors = [
            'input[name="cuisine"]',
            'select[name="difficulty"]',
            'textarea[name="ingredients"]',
            'textarea[name="instructions"]'
          ];

          function sync() {
            const isChefRecipe = (kind.value === 'chef_recipe');
            chefFields.style.display = isChefRecipe ? 'block' : 'none';
            reqSelectors.forEach(sel => {
              const el = modal.querySelector(sel);
              if (el) el.required = isChefRecipe;
            });
          }

          kind.addEventListener('change', sync);
          modal.addEventListener('shown.bs.modal', sync);
          sync();
        });
      </script>

      <!-- POSTS GRID -->
      <div class="row g-3 mt-1">
        <?php foreach ($posts as $p): $pid = (int)$p['post_id']; ?>
          <?php
            if (!can_view_post($pdo, $p)) continue;

            $author  = trim((string)($p['author_name'] ?? 'User'));
            $initial = strtoupper(mb_substr($author, 0, 1));

            $like  = $reactionCounts[$pid]['like']  ?? 0;
            $heart = $reactionCounts[$pid]['heart'] ?? 0;

            $status = (string)($p['status'] ?? 'approved');
            $statusClass = $status === 'approved' ? 'ff-approved' : ($status === 'pending' ? 'ff-pending' : 'ff-rejected');

            $isOwner = is_logged_in() && ((int)$p['user_id'] === (int)current_user_id());
            $type = (string)($p['post_type'] ?? 'user_post');
          ?>

          <div class="col-12 col-md-6 col-lg-4" id="p<?= $pid ?>">
            <div class="card ff-card h-100">
              <div class="card-body p-3">

                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <div class="ff-avatar"><?= h($initial) ?></div>
                    <div>
                      <div class="fw-semibold small"><?= h($author) ?></div>
                      <div class="small text-muted"><?= h((string)$p['created_at']) ?></div>
                    </div>
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <span class="ff-status <?= $statusClass ?>"><?= h($status) ?></span>

                    <?php if ($isOwner): ?>
                      <form method="post" onsubmit="return confirm('Delete this post?');">
                        <input type="hidden" name="csrf_cc" value="<?= h($csrf) ?>">
                        <input type="hidden" name="cc_action" value="delete_post">
                        <input type="hidden" name="post_id" value="<?= $pid ?>">
                        <button class="btn btn-outline-danger btn-sm ff-btn-pill" type="submit">Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>

                <h3 class="h6 fw-bold mt-2 mb-1"><?= h((string)$p['title']) ?></h3>

                <?php if ($type === 'chef_recipe'): ?>
                  <div class="ff-recipe-meta">
                    <?php if (!empty($p['cuisine'])): ?>
                      <span class="ff-meta-pill"><?= h((string)$p['cuisine']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($p['difficulty'])): ?>
                      <span class="ff-meta-pill"><?= h((string)$p['difficulty']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($p['prep_time'])): ?>
                      <span class="ff-meta-pill">Prep <?= (int)$p['prep_time'] ?>m</span>
                    <?php endif; ?>
                    <?php if (!empty($p['cook_time'])): ?>
                      <span class="ff-meta-pill">Cook <?= (int)$p['cook_time'] ?>m</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="small mb-2 mt-2"><?= nl2br(h((string)$p['content'])) ?></div>

                <?php if (!empty($p['cover_image'])): ?>
                  <div class="ff-media mt-2">
                    <img src="<?= h((string)$p['cover_image']) ?>" alt="cover image">
                  </div>
                <?php endif; ?>

                <?php if ($type === 'chef_recipe' && (!empty($p['ingredients']) || !empty($p['instructions']))): ?>
                  <details class="mt-2">
                    <summary class="small fw-semibold">Recipe Details</summary>
                    <?php if (!empty($p['ingredients'])): ?>
                      <div class="small mt-2"><b>Ingredients:</b><br><?= nl2br(h((string)$p['ingredients'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($p['instructions'])): ?>
                      <div class="small mt-2"><b>Instructions:</b><br><?= nl2br(h((string)$p['instructions'])) ?></div>
                    <?php endif; ?>
                  </details>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-2">
                  <span class="ff-chip">👍 <?= (int)$like ?></span>
                  <span class="ff-chip">❤️ <?= (int)$heart ?></span>
                </div>

                <hr class="my-2">

                <?php if (is_logged_in() && $status === 'approved'): ?>
                  <form method="post" class="d-flex flex-wrap gap-1">
                    <input type="hidden" name="csrf_cc" value="<?= h($csrf) ?>">
                    <input type="hidden" name="cc_action" value="react">
                    <input type="hidden" name="post_id" value="<?= $pid ?>">

                    <button class="ff-action-btn" name="reaction_type" value="like" type="submit">👍 Like</button>
                    <button class="ff-action-btn" name="reaction_type" value="heart" type="submit">❤️ Heart</button>
                    <a class="ff-action-btn text-decoration-none" href="#c<?= $pid ?>">💬 Comment</a>
                  </form>
                <?php elseif ($status !== 'approved'): ?>
                  <div class="small text-muted">Pending posts cannot receive reactions/comments.</div>
                <?php else: ?>
                  <div class="small text-muted">Login to react</div>
                <?php endif; ?>

                <hr class="my-2">

                <div id="c<?= $pid ?>">
                  <div class="small fw-semibold mb-2">Comments</div>

                  <?php if (!empty($commentsByPost[$pid])): ?>
                    <div class="d-flex flex-column gap-2">
                      <?php foreach ($commentsByPost[$pid] as $c): ?>
                        <div class="ff-comment">
                          <div class="d-flex justify-content-between gap-2">
                            <div class="fw-semibold small"><?= h((string)$c['author_name']) ?></div>
                            <small><?= h((string)$c['created_at']) ?></small>
                          </div>
                          <div class="small mt-1"><?= h((string)$c['comment_text']) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="small text-muted">No comments yet.</div>
                  <?php endif; ?>

                  <?php if (is_logged_in() && $status === 'approved'): ?>
                    <form method="post" class="mt-2">
                      <input type="hidden" name="csrf_cc" value="<?= h($csrf) ?>">
                      <input type="hidden" name="cc_action" value="comment">
                      <input type="hidden" name="post_id" value="<?= $pid ?>">

                      <div class="d-flex gap-2">
                        <input name="comment_text" class="form-control ff-input" placeholder="Write a comment..." maxlength="800" required>
                        <button class="btn btn-dark ff-btn-pill px-3" type="submit">Send</button>
                      </div>
                    </form>
                  <?php elseif (!is_logged_in()): ?>
                    <div class="small text-muted mt-2">Login to comment</div>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../Header&footer/footer.php'; ?>
