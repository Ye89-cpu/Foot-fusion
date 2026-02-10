<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/conn.php'; // $pdo

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   CONFIG
========================= */
$BASE = '/Food-Fusion-web/admin/admin.php'; 
// NOTE: နင့် server root ပေါ်မူတည်ပြီး
// e.g. /FoodFusion/admin/admin.php လိုသေးရင် ပြောင်းပါ

/* =========================
   HELPERS
========================= */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  $t = $_POST['csrf'] ?? '';
  if (!$t || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
    http_response_code(403);
    exit('CSRF token mismatch.');
  }
}

function admin_logged_in(): bool {
  return !empty($_SESSION['admin_id']);
}
function require_admin(string $base): void {
  if (!admin_logged_in()) {
    header("Location: {$base}");
    exit;
  }
}
function redirect_to(string $url): void {
  header("Location: {$url}");
  exit;
}

function flash_set(string $type, string $msg): void {
  $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
}
function flash_get(): ?array {
  if (empty($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

function safe_delete_upload(?string $webPath): void {
  // only delete if it's within project root and looks like /uploads/...
  if (!$webPath) return;
  $webPath = trim($webPath);
  if ($webPath === '') return;

  // allow only uploads paths (avoid deleting arbitrary files)
  if (strpos($webPath, '/uploads/') !== 0) return;

  $projectRoot = realpath(__DIR__ . '/..'); // Food-Fusion-web
  if (!$projectRoot) return;

  $disk = $projectRoot . $webPath; // because webPath begins with /
  $real = realpath($disk);

  // ensure real path stays inside project root
  if ($real && strpos($real, $projectRoot) === 0 && is_file($real)) {
    @unlink($real);
  }
}

/* =========================
   AUTH: LOGIN / LOGOUT
========================= */
if (isset($_GET['logout'])) {
  unset($_SESSION['admin_id'], $_SESSION['admin_username']);
  session_regenerate_id(true);
  flash_set('success', 'Logged out.');
  redirect_to($BASE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
  csrf_check();
  $username = trim((string)($_POST['username'] ?? ''));
  $pass     = (string)($_POST['password'] ?? '');

  if ($username === '' || $pass === '') {
    flash_set('danger', 'Username and password are required.');
    redirect_to($BASE);
  }

  $st = $pdo->prepare("SELECT admin_id, username, password_hash, is_active FROM admins WHERE username=:u LIMIT 1");
  $st->execute([':u'=>$username]);
  $a = $st->fetch(PDO::FETCH_ASSOC);

  if (!$a || (int)$a['is_active'] !== 1 || !password_verify($pass, (string)$a['password_hash'])) {
    flash_set('danger', 'Invalid admin login.');
    redirect_to($BASE);
  }

  session_regenerate_id(true);
  $_SESSION['admin_id'] = (int)$a['admin_id'];
  $_SESSION['admin_username'] = (string)$a['username'];

  flash_set('success', 'Welcome Admin.');
  redirect_to($BASE . '?section=dashboard');
}

/* =========================
   ROUTING
========================= */
$section = trim((string)($_GET['section'] ?? 'dashboard'));
if (!admin_logged_in()) $section = 'login';
$flash = flash_get();

/* =========================
   POST ACTIONS (CRUD)
========================= */
if (admin_logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  if ($action !== '') csrf_check();

  /* ---------- RECIPE COLLECTION CRUD ---------- */
  if ($action === 'recipe_save') {
    $id = (int)($_POST['recipe_id'] ?? 0);

    $data = [
      'name'        => trim((string)($_POST['name'] ?? '')),
      'dietary'     => trim((string)($_POST['dietary'] ?? '')),
      'cuisine'     => trim((string)($_POST['cuisine'] ?? '')),
      'difficulty'  => trim((string)($_POST['difficulty'] ?? '')),
      'recipe_type' => trim((string)($_POST['recipe_type'] ?? '')),
      'ingredients' => trim((string)($_POST['ingredients'] ?? '')),
      'image'       => trim((string)($_POST['image'] ?? '')),
      'description' => trim((string)($_POST['description'] ?? '')),
      'chef_id'     => (int)($_POST['chef_id'] ?? 0),
    ];

    if ($data['name']==='' || $data['dietary']==='' || $data['cuisine']==='' || $data['difficulty']==='' ||
        $data['recipe_type']==='' || $data['ingredients']==='' || $data['image']==='' || $data['description']==='' || $data['chef_id']<=0) {
      flash_set('danger', 'All fields are required (including Chef).');
      redirect_to($BASE . '?section=recipes' . ($id? '&edit_id='.$id : ''));
    }

    if ($id > 0) {
      $sql = "UPDATE recipe_collection SET
                name=:name, dietary=:dietary, cuisine=:cuisine, difficulty=:difficulty,
                recipe_type=:recipe_type, ingredients=:ingredients, image=:image, description=:description,
                chef_id=:chef_id
              WHERE recipe_id=:id";
      $pdo->prepare($sql)->execute($data + ['id'=>$id]);
      flash_set('success', 'Recipe updated.');
    } else {
      $sql = "INSERT INTO recipe_collection
              (name,dietary,cuisine,difficulty,recipe_type,ingredients,image,description,chef_id)
              VALUES
              (:name,:dietary,:cuisine,:difficulty,:recipe_type,:ingredients,:image,:description,:chef_id)";
      $pdo->prepare($sql)->execute($data);
      flash_set('success', 'Recipe added.');
    }
    redirect_to($BASE . '?section=recipes');
  }

  if ($action === 'recipe_delete') {
    $id = (int)($_POST['recipe_id'] ?? 0);
    if ($id > 0) {
      $st = $pdo->prepare("SELECT image FROM recipe_collection WHERE recipe_id=:id");
      $st->execute([':id'=>$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      $pdo->prepare("DELETE FROM recipe_collection WHERE recipe_id=:id")->execute([':id'=>$id]);

      // optional: delete local uploads only
      if ($row && !empty($row['image'])) safe_delete_upload((string)$row['image']);

      flash_set('success', 'Recipe deleted.');
    }
    redirect_to($BASE . '?section=recipes');
  }

  /* ---------- CHEF CRUD ---------- */
  if ($action === 'chef_save') {
    $id = (int)($_POST['chef_id'] ?? 0);
    $name = trim((string)($_POST['chef_name'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));

    if ($name === '') {
      flash_set('danger', 'Chef name is required.');
      redirect_to($BASE . '?section=chefs' . ($id? '&edit_id='.$id : ''));
    }

    if ($id > 0) {
      $pdo->prepare("UPDATE chef SET chef_name=:n, country=:c WHERE chef_id=:id")
          ->execute([':n'=>$name, ':c'=>($country===''?null:$country), ':id'=>$id]);
      flash_set('success', 'Chef updated.');
    } else {
      $pdo->prepare("INSERT INTO chef (chef_name,country) VALUES (:n,:c)")
          ->execute([':n'=>$name, ':c'=>($country===''?null:$country)]);
      flash_set('success', 'Chef added.');
    }
    redirect_to($BASE . '?section=chefs');
  }

  if ($action === 'chef_delete') {
    $id = (int)($_POST['chef_id'] ?? 0);
    if ($id > 0) {
      // if referenced by recipes, delete may fail due to FK. Better to prevent user confusion.
      try {
        $pdo->prepare("DELETE FROM chef WHERE chef_id=:id")->execute([':id'=>$id]);
        flash_set('success', 'Chef deleted.');
      } catch (Throwable $e) {
        flash_set('danger', 'Cannot delete this chef (may be linked to recipes).');
      }
    }
    redirect_to($BASE . '?section=chefs');
  }

  /* ---------- COOKING EVENTS CRUD ---------- */
  if ($action === 'event_save') {
    $id = (int)($_POST['event_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $dt = trim((string)($_POST['event_datetime'] ?? '')); // HTML datetime-local
    $location = trim((string)($_POST['location'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $image = trim((string)($_POST['image'] ?? ''));
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($title==='' || $dt==='') {
      flash_set('danger', 'Title and Event Date/Time are required.');
      redirect_to($BASE . '?section=events' . ($id? '&edit_id='.$id : ''));
    }

    // convert "YYYY-MM-DDTHH:MM" -> "YYYY-MM-DD HH:MM:SS"
    $dtSql = str_replace('T', ' ', $dt);
    if (strlen($dtSql) === 16) $dtSql .= ':00';

    if ($id > 0) {
      $pdo->prepare("UPDATE cooking_events SET title=:t,event_datetime=:d,location=:l,description=:ds,image=:i,is_active=:a WHERE event_id=:id")
          ->execute([':t'=>$title, ':d'=>$dtSql, ':l'=>($location===''?null:$location), ':ds'=>($desc===''?null:$desc), ':i'=>($image===''?null:$image), ':a'=>$is_active, ':id'=>$id]);
      flash_set('success', 'Event updated.');
    } else {
      $pdo->prepare("INSERT INTO cooking_events (title,event_datetime,location,description,image,is_active) VALUES (:t,:d,:l,:ds,:i,:a)")
          ->execute([':t'=>$title, ':d'=>$dtSql, ':l'=>($location===''?null:$location), ':ds'=>($desc===''?null:$desc), ':i'=>($image===''?null:$image), ':a'=>$is_active]);
      flash_set('success', 'Event added.');
    }
    redirect_to($BASE . '?section=events');
  }

  if ($action === 'event_delete') {
    $id = (int)($_POST['event_id'] ?? 0);
    if ($id > 0) {
      $st = $pdo->prepare("SELECT image FROM cooking_events WHERE event_id=:id");
      $st->execute([':id'=>$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      $pdo->prepare("DELETE FROM cooking_events WHERE event_id=:id")->execute([':id'=>$id]);
      if ($row && !empty($row['image'])) safe_delete_upload((string)$row['image']);
      flash_set('success', 'Event deleted.');
    }
    redirect_to($BASE . '?section=events');
  }

  /* ---------- COMMUNITY MODERATION ---------- */
  if ($action === 'community_set_status') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'pending');
    $reason = trim((string)($_POST['reject_reason'] ?? ''));

    $allowed = ['pending','approved','rejected'];
    if (!in_array($status, $allowed, true)) $status = 'pending';

    if ($postId > 0) {
      if ($status === 'approved') {
        $pdo->prepare("UPDATE community_posts SET status='approved', approved_by=:aid, approved_at=NOW(), reject_reason=NULL WHERE post_id=:id")
            ->execute([':aid'=>(int)$_SESSION['admin_id'], ':id'=>$postId]);
        flash_set('success', 'Post approved.');
      } elseif ($status === 'rejected') {
        $pdo->prepare("UPDATE community_posts SET status='rejected', approved_by=:aid, approved_at=NOW(), reject_reason=:r WHERE post_id=:id")
            ->execute([':aid'=>(int)$_SESSION['admin_id'], ':r'=>($reason===''?null:$reason), ':id'=>$postId]);
        flash_set('success', 'Post rejected.');
      } else {
        $pdo->prepare("UPDATE community_posts SET status='pending', approved_by=NULL, approved_at=NULL, reject_reason=NULL WHERE post_id=:id")
            ->execute([':id'=>$postId]);
        flash_set('success', 'Post set to pending.');
      }
    }
    redirect_to($BASE . '?section=community_view&post_id=' . $postId);
  }

  if ($action === 'community_delete_post') {
    $postId = (int)($_POST['post_id'] ?? 0);

    $st = $pdo->prepare("SELECT cover_image FROM community_posts WHERE post_id=:id");
    $st->execute([':id'=>$postId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if ($postId > 0) {
      $pdo->prepare("DELETE FROM community_comments WHERE post_id=:id")->execute([':id'=>$postId]);
      $pdo->prepare("DELETE FROM community_reactions WHERE post_id=:id")->execute([':id'=>$postId]);
      $pdo->prepare("DELETE FROM community_chef_recipes WHERE post_id=:id")->execute([':id'=>$postId]);
      $pdo->prepare("DELETE FROM community_posts WHERE post_id=:id")->execute([':id'=>$postId]);

      if ($p && !empty($p['cover_image'])) safe_delete_upload((string)$p['cover_image']);

      flash_set('success', 'Post deleted.');
    }
    redirect_to($BASE . '?section=community');
  }

  if ($action === 'community_delete_comment') {
    $cid = (int)($_POST['comment_id'] ?? 0);
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($cid > 0) {
      $pdo->prepare("DELETE FROM community_comments WHERE comment_id=:id")->execute([':id'=>$cid]);
      flash_set('success', 'Comment deleted.');
    }
    redirect_to($BASE . '?section=community_view&post_id=' . $postId);
  }

  if ($action === 'community_clear_reactions') {
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId > 0) {
      $pdo->prepare("DELETE FROM community_reactions WHERE post_id=:id")->execute([':id'=>$postId]);
      flash_set('success', 'Reactions cleared.');
    }
    redirect_to($BASE . '?section=community_view&post_id=' . $postId);
  }

  /* ---------- CONTACT MESSAGES ---------- */
  if ($action === 'contact_update_status') {
    $mid = (int)($_POST['message_id'] ?? 0);
    $status = strtolower(trim((string)($_POST['status'] ?? 'new')));
    $allowed = ['new','read','replied','archived'];
    if (!in_array($status, $allowed, true)) $status = 'new';

    if ($mid > 0) {
      $pdo->prepare("UPDATE contact_messages SET status=:s WHERE message_id=:id")
          ->execute([':s'=>$status, ':id'=>$mid]);
      flash_set('success', 'Status updated.');
    }
    redirect_to($BASE . '?section=contacts');
  }

  if ($action === 'contact_delete') {
    $mid = (int)($_POST['message_id'] ?? 0);
    if ($mid > 0) {
      $pdo->prepare("DELETE FROM contact_messages WHERE message_id=:id")->execute([':id'=>$mid]);
      flash_set('success', 'Message deleted.');
    }
    redirect_to($BASE . '?section=contacts');
  }

  /* ---------- CULINARY RESOURCES CRUD ---------- */
  if ($action === 'culinary_save') {
    $id = (int)($_POST['resource_id'] ?? 0);

    $data = [
      'title' => trim((string)($_POST['title'] ?? '')),
      'topic' => trim((string)($_POST['topic'] ?? '')),
      'category' => trim((string)($_POST['category'] ?? 'Cooking Basics')),
      'resource_type' => trim((string)($_POST['resource_type'] ?? 'Recipe Card')),
      'description' => trim((string)($_POST['description'] ?? '')),
      'thumbnail_url' => trim((string)($_POST['thumbnail_url'] ?? '')),
      'file_url' => trim((string)($_POST['file_url'] ?? '')),
      'video_url' => trim((string)($_POST['video_url'] ?? '')),
      'is_active' => (int)($_POST['is_active'] ?? 1),
    ];

    if ($data['title']==='' || $data['topic']==='') {
      flash_set('danger', 'Title and Topic are required.');
      redirect_to($BASE . '?section=culinary' . ($id? '&edit_id='.$id : ''));
    }

    // normalize nullable fields
    foreach (['description','thumbnail_url','file_url','video_url'] as $k) {
      if ($data[$k] === '') $data[$k] = null;
    }
    if ($data['category']==='') $data['category'] = 'Cooking Basics';

    // resource_type enum guard
    $rtAllowed = ['Recipe Card','Tutorial','Video','Infographic','PDF'];
    if (!in_array((string)$data['resource_type'], $rtAllowed, true)) $data['resource_type'] = 'Recipe Card';

    if ($id > 0) {
      $sql = "UPDATE culinary_resources SET
              title=:title, topic=:topic, category=:category, resource_type=:resource_type,
              description=:description, thumbnail_url=:thumbnail_url, file_url=:file_url, video_url=:video_url,
              is_active=:is_active
              WHERE resource_id=:id";
      $pdo->prepare($sql)->execute($data + ['id'=>$id]);
      flash_set('success', 'Culinary resource updated.');
    } else {
      $sql = "INSERT INTO culinary_resources
              (title,topic,category,resource_type,description,thumbnail_url,file_url,video_url,is_active)
              VALUES
              (:title,:topic,:category,:resource_type,:description,:thumbnail_url,:file_url,:video_url,:is_active)";
      $pdo->prepare($sql)->execute($data);
      flash_set('success', 'Culinary resource added.');
    }
    redirect_to($BASE . '?section=culinary');
  }

  if ($action === 'culinary_delete') {
    $id = (int)($_POST['resource_id'] ?? 0);
    if ($id > 0) {
      $st = $pdo->prepare("SELECT thumbnail_url, file_url FROM culinary_resources WHERE resource_id=:id");
      $st->execute([':id'=>$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      $pdo->prepare("DELETE FROM culinary_resources WHERE resource_id=:id")->execute([':id'=>$id]);

      if ($row) {
        if (!empty($row['thumbnail_url'])) safe_delete_upload((string)$row['thumbnail_url']);
        if (!empty($row['file_url'])) safe_delete_upload((string)$row['file_url']);
      }
      flash_set('success', 'Culinary resource deleted.');
    }
    redirect_to($BASE . '?section=culinary');
  }

  /* ---------- EDUCATIONAL RESOURCES CRUD ---------- */
  if ($action === 'edu_save') {
    $id = (int)($_POST['resource_id'] ?? 0);

    $data = [
      'title' => trim((string)($_POST['title'] ?? '')),
      'topic' => trim((string)($_POST['topic'] ?? '')),
      'category' => trim((string)($_POST['category'] ?? 'Basics')),
      'resource_type' => trim((string)($_POST['resource_type'] ?? 'PDF')),
      'description' => trim((string)($_POST['description'] ?? '')),
      'thumbnail_url' => trim((string)($_POST['thumbnail_url'] ?? '')),
      'file_url' => trim((string)($_POST['file_url'] ?? '')),
      'video_url' => trim((string)($_POST['video_url'] ?? '')),
      'is_active' => (int)($_POST['is_active'] ?? 1),
    ];

    if ($data['title']==='' || $data['topic']==='') {
      flash_set('danger', 'Title and Topic are required.');
      redirect_to($BASE . '?section=educational' . ($id? '&edit_id='.$id : ''));
    }

    foreach (['description','thumbnail_url','file_url','video_url'] as $k) {
      if ($data[$k] === '') $data[$k] = null;
    }
    if ($data['category']==='') $data['category'] = 'Basics';

    $rtAllowed = ['Tutorial','Video','Infographic','PDF','Worksheet'];
    if (!in_array((string)$data['resource_type'], $rtAllowed, true)) $data['resource_type'] = 'PDF';

    if ($id > 0) {
      $sql = "UPDATE educational_resources SET
              title=:title, topic=:topic, category=:category, resource_type=:resource_type,
              description=:description, thumbnail_url=:thumbnail_url, file_url=:file_url, video_url=:video_url,
              is_active=:is_active
              WHERE resource_id=:id";
      $pdo->prepare($sql)->execute($data + ['id'=>$id]);
      flash_set('success', 'Educational resource updated.');
    } else {
      $sql = "INSERT INTO educational_resources
              (title,topic,category,resource_type,description,thumbnail_url,file_url,video_url,is_active)
              VALUES
              (:title,:topic,:category,:resource_type,:description,:thumbnail_url,:file_url,:video_url,:is_active)";
      $pdo->prepare($sql)->execute($data);
      flash_set('success', 'Educational resource added.');
    }
    redirect_to($BASE . '?section=educational');
  }

  if ($action === 'edu_delete') {
    $id = (int)($_POST['resource_id'] ?? 0);
    if ($id > 0) {
      $st = $pdo->prepare("SELECT thumbnail_url, file_url FROM educational_resources WHERE resource_id=:id");
      $st->execute([':id'=>$id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      $pdo->prepare("DELETE FROM educational_resources WHERE resource_id=:id")->execute([':id'=>$id]);

      if ($row) {
        if (!empty($row['thumbnail_url'])) safe_delete_upload((string)$row['thumbnail_url']);
        if (!empty($row['file_url'])) safe_delete_upload((string)$row['file_url']);
      }
      flash_set('success', 'Educational resource deleted.');
    }
    redirect_to($BASE . '?section=educational');
  }

  /* ---------- USERS CONTROL PANEL ---------- */
  if ($action === 'user_update') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $fname = trim((string)($_POST['fname'] ?? ''));
    $lname = trim((string)($_POST['lname'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $gender = trim((string)($_POST['gender'] ?? ''));
    $role = trim((string)($_POST['role'] ?? 'user'));
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($uid <= 0 || $fname==='' || $lname==='' || $email==='' || $username==='') {
      flash_set('danger', 'Required: fname, lname, email, username.');
      redirect_to($BASE . '?section=users&edit_id=' . $uid);
    }

    // unique email check
    $ck = $pdo->prepare("SELECT user_id FROM users WHERE email=:e AND user_id<>:id LIMIT 1");
    $ck->execute([':e'=>$email, ':id'=>$uid]);
    if ($ck->fetch()) {
      flash_set('danger', 'Email already used by another user.');
      redirect_to($BASE . '?section=users&edit_id=' . $uid);
    }

    // unique username check
    $ck2 = $pdo->prepare("SELECT user_id FROM users WHERE username=:u AND user_id<>:id LIMIT 1");
    $ck2->execute([':u'=>$username, ':id'=>$uid]);
    if ($ck2->fetch()) {
      flash_set('danger', 'Username already used by another user.');
      redirect_to($BASE . '?section=users&edit_id=' . $uid);
    }

    $gAllowed = ['Male','Female','Other'];
    if (!in_array($gender, $gAllowed, true)) $gender = null;

    $rAllowed = ['user','chef'];
    if (!in_array($role, $rAllowed, true)) $role = 'user';

    $pdo->prepare("UPDATE users
                   SET fname=:f,lname=:l,phone=:p,username=:u,email=:e,gender=:g,role=:r,is_active=:a
                   WHERE user_id=:id")
        ->execute([
          ':f'=>$fname, ':l'=>$lname, ':p'=>($phone===''?null:$phone),
          ':u'=>$username, ':e'=>$email, ':g'=>$gender, ':r'=>$role, ':a'=>$is_active,
          ':id'=>$uid
        ]);

    flash_set('success', 'User updated.');
    redirect_to($BASE . '?section=users');
  }

  if ($action === 'user_unlock') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
      $pdo->prepare("UPDATE users SET failed_login_attempts=0, lockout_until=NULL WHERE user_id=:id")
          ->execute([':id'=>$uid]);
      flash_set('success', 'User unlocked.');
    }
    redirect_to($BASE . '?section=users');
  }

  if ($action === 'user_lock_10y') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
      $pdo->prepare("UPDATE users SET lockout_until=DATE_ADD(NOW(), INTERVAL 10 YEAR) WHERE user_id=:id")
          ->execute([':id'=>$uid]);
      flash_set('success', 'User locked (10 years).');
    }
    redirect_to($BASE . '?section=users');
  }
}

/* =========================
   DATA FETCH FOR SECTIONS
========================= */
function admin_counts(PDO $pdo): array {
  return [
    'recipes' => (int)$pdo->query("SELECT COUNT(*) FROM recipe_collection")->fetchColumn(),
    'chefs' => (int)$pdo->query("SELECT COUNT(*) FROM chef")->fetchColumn(),
    'events' => (int)$pdo->query("SELECT COUNT(*) FROM cooking_events")->fetchColumn(),
    'posts' => (int)$pdo->query("SELECT COUNT(*) FROM community_posts")->fetchColumn(),
    'contacts' => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(),
    'culinary' => (int)$pdo->query("SELECT COUNT(*) FROM culinary_resources")->fetchColumn(),
    'educational' => (int)$pdo->query("SELECT COUNT(*) FROM educational_resources")->fetchColumn(),
    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  ];
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h('FoodFusion Admin') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{ background:#f3f4f6; }
    .sidebar{
      width:260px; flex:0 0 260px;
      background:#111827; color:#fff;
      min-height:100vh; position:sticky; top:0;
    }
    .sidebar a{ color:#cbd5e1; text-decoration:none; display:block; padding:.6rem .9rem; border-radius:.6rem; }
    .sidebar a:hover{ background:#1f2937; color:#fff; }
    .sidebar a.active{ background:#374151; color:#fff; }
    .brand{ font-weight:800; letter-spacing:.2px; }
    .content{ width:100%; }
    .table td, .table th{ vertical-align:middle; }
    .truncate{ max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .card-soft{ border:0; border-radius:16px; box-shadow:0 1px 2px rgba(0,0,0,.06); }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  </style>
</head>
<body>

<?php if ($section === 'login'): ?>
  <div class="container py-5" style="max-width:520px;">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="mb-3">
          <div class="brand fs-4">FoodFusion Admin</div>
          <div class="text-muted">Login to manage site data.</div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
        <?php endif; ?>

        <form method="post" class="vstack gap-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="admin_login">

          <div>
            <label class="form-label">Admin Username</label>
            <input class="form-control" name="username" required>
          </div>
          <div>
            <label class="form-label">Password</label>
            <input class="form-control" name="password" type="password" required>
          </div>

          <button class="btn btn-dark w-100" type="submit">Login</button>
          <div class="small text-muted">URL: <?= h($BASE) ?></div>
        </form>
      </div>
    </div>
  </div>
<?php exit; endif; ?>

<?php
$counts = admin_counts($pdo);
$adminUser = (string)($_SESSION['admin_username'] ?? 'admin');
function nav_active(string $cur, string $sec): string { return $cur === $sec ? 'active' : ''; }
?>

<div class="d-flex">
  <aside class="sidebar p-3">
    <div class="mb-3">
      <div class="brand fs-5">FoodFusion</div>
      <div class="small text-white-50">Admin Panel</div>
    </div>

    <div class="mb-3 p-2 rounded" style="background:#0b1220;">
      <div class="small text-white-50">Logged in</div>
      <div class="fw-semibold"><?= h($adminUser) ?></div>
      <a class="mt-2 btn btn-sm btn-outline-light w-100" href="<?= h($BASE) ?>?logout=1">Logout</a>
    </div>

    <nav class="vstack gap-1">
      <a class="<?= nav_active($section,'dashboard') ?>" href="<?= h($BASE) ?>?section=dashboard">Dashboard</a>
      <a class="<?= nav_active($section,'recipes') ?>" href="<?= h($BASE) ?>?section=recipes">
        Recipe Collection <span class="badge bg-secondary float-end"><?= (int)$counts['recipes'] ?></span>
      </a>
      <a class="<?= nav_active($section,'chefs') ?>" href="<?= h($BASE) ?>?section=chefs">
        Chefs <span class="badge bg-secondary float-end"><?= (int)$counts['chefs'] ?></span>
      </a>
      <a class="<?= nav_active($section,'events') ?>" href="<?= h($BASE) ?>?section=events">
        Cooking Events <span class="badge bg-secondary float-end"><?= (int)$counts['events'] ?></span>
      </a>
      <a class="<?= nav_active($section,'community') ?>" href="<?= h($BASE) ?>?section=community">
        Community <span class="badge bg-secondary float-end"><?= (int)$counts['posts'] ?></span>
      </a>
      <a class="<?= nav_active($section,'contacts') ?>" href="<?= h($BASE) ?>?section=contacts">
        Contact Messages <span class="badge bg-secondary float-end"><?= (int)$counts['contacts'] ?></span>
      </a>
      <a class="<?= nav_active($section,'culinary') ?>" href="<?= h($BASE) ?>?section=culinary">
        Culinary Resources <span class="badge bg-secondary float-end"><?= (int)$counts['culinary'] ?></span>
      </a>
      <a class="<?= nav_active($section,'educational') ?>" href="<?= h($BASE) ?>?section=educational">
        Educational Resources <span class="badge bg-secondary float-end"><?= (int)$counts['educational'] ?></span>
      </a>
      <a class="<?= nav_active($section,'users') ?>" href="<?= h($BASE) ?>?section=users">
        Users <span class="badge bg-secondary float-end"><?= (int)$counts['users'] ?></span>
      </a>
    </nav>
  </aside>

  <main class="content p-4">
    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($section === 'dashboard'): ?>
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h3 mb-1">Dashboard</h1>
          <div class="text-muted">Manage Recipes, Chefs, Events, Community, Contacts, Resources, Users.</div>
        </div>
      </div>

      <div class="row g-3">
        <?php
          $cards = [
            ['Recipes', $counts['recipes'], 'recipes'],
            ['Chefs', $counts['chefs'], 'chefs'],
            ['Events', $counts['events'], 'events'],
            ['Community Posts', $counts['posts'], 'community'],
            ['Contact Messages', $counts['contacts'], 'contacts'],
            ['Culinary Resources', $counts['culinary'], 'culinary'],
            ['Educational Resources', $counts['educational'], 'educational'],
            ['Users', $counts['users'], 'users'],
          ];
        ?>
        <?php foreach ($cards as [$label,$num,$sec]): ?>
          <div class="col-md-3">
            <div class="card card-soft">
              <div class="card-body">
                <div class="text-muted"><?= h($label) ?></div>
                <div class="fs-3 fw-bold"><?= (int)$num ?></div>
                <a class="btn btn-sm btn-dark mt-2" href="<?= h($BASE) ?>?section=<?= h($sec) ?>">Manage</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($section === 'recipes'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;

        if ($editId > 0) {
          $st = $pdo->prepare("SELECT * FROM recipe_collection WHERE recipe_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }

        $chefs = $pdo->query("SELECT chef_id, chef_name, country FROM chef ORDER BY chef_name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $q = trim((string)($_GET['q'] ?? ''));
        $w = "1=1";
        $p = [];
        if ($q !== '') {
          $w = "(rc.name LIKE :q1 OR rc.cuisine LIKE :q2 OR rc.dietary LIKE :q3 OR c.chef_name LIKE :q4)";
          $like = "%{$q}%";
          $p = [':q1'=>$like, ':q2'=>$like, ':q3'=>$like, ':q4'=>$like];
        }

        $st = $pdo->prepare("
          SELECT rc.*, c.chef_name
          FROM recipe_collection rc
          JOIN chef c ON c.chef_id = rc.chef_id
          WHERE {$w}
          ORDER BY rc.recipe_id DESC
          LIMIT 300
        ");
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Recipe Collection CRUD</h1>
          <div class="text-muted">Add / Edit / Delete</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <form class="row g-2" method="get" action="">
            <input type="hidden" name="section" value="recipes">
            <div class="col-md-6">
              <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search recipe/cuisine/diet/chef...">
            </div>
            <div class="col-md-2"><button class="btn btn-outline-dark w-100" type="submit">Search</button></div>
            <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= h($BASE) ?>?section=recipes">Reset</a></div>
          </form>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3"><?= $edit ? 'Edit Recipe' : 'Add Recipe' ?></h2>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="recipe_save">
            <input type="hidden" name="recipe_id" value="<?= (int)($edit['recipe_id'] ?? 0) ?>">

            <div class="col-md-4">
              <label class="form-label">Name *</label>
              <input class="form-control" name="name" value="<?= h((string)($edit['name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Dietary *</label>
              <input class="form-control" name="dietary" value="<?= h((string)($edit['dietary'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cuisine *</label>
              <input class="form-control" name="cuisine" value="<?= h((string)($edit['cuisine'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Difficulty *</label>
              <input class="form-control" name="difficulty" value="<?= h((string)($edit['difficulty'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Type *</label>
              <input class="form-control" name="recipe_type" value="<?= h((string)($edit['recipe_type'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Ingredients *</label>
              <textarea class="form-control" name="ingredients" rows="3" required><?= h((string)($edit['ingredients'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Description *</label>
              <textarea class="form-control" name="description" rows="3" required><?= h((string)($edit['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-md-6">
              <label class="form-label">Image (path or URL) *</label>
              <input class="form-control" name="image" value="<?= h((string)($edit['image'] ?? '')) ?>" required>
              <div class="small text-muted">Example: /uploads/recipes/xxx.png</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Chef *</label>
              <select class="form-select" name="chef_id" required>
                <option value="">-- Select Chef --</option>
                <?php $curChef = (int)($edit['chef_id'] ?? 0); ?>
                <?php foreach ($chefs as $c): ?>
                  <option value="<?= (int)$c['chef_id'] ?>" <?= $curChef===(int)$c['chef_id']?'selected':'' ?>>
                    <?= h($c['chef_name']) ?><?= !empty($c['country']) ? ' • '.h((string)$c['country']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-dark" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
              <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=recipes">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Recipes (latest 300)</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Chef</th><th>Cuisine</th><th>Diet</th><th>Diff</th><th style="width:180px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['recipe_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$r['name']) ?></td>
                  <td><?= h((string)$r['chef_name']) ?></td>
                  <td><?= h((string)$r['cuisine']) ?></td>
                  <td><?= h((string)$r['dietary']) ?></td>
                  <td><?= h((string)$r['difficulty']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=recipes&edit_id=<?= (int)$r['recipe_id'] ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this recipe?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="recipe_delete">
                      <input type="hidden" name="recipe_id" value="<?= (int)$r['recipe_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'chefs'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;
        if ($editId > 0) {
          $st = $pdo->prepare("SELECT * FROM chef WHERE chef_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }
        $rows = $pdo->query("SELECT * FROM chef ORDER BY chef_id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Chefs CRUD</h1>
          <div class="text-muted">Add / Edit / Delete</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3"><?= $edit ? 'Edit Chef' : 'Add Chef' ?></h2>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="chef_save">
            <input type="hidden" name="chef_id" value="<?= (int)($edit['chef_id'] ?? 0) ?>">

            <div class="col-md-6">
              <label class="form-label">Chef Name *</label>
              <input class="form-control" name="chef_name" value="<?= h((string)($edit['chef_name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Country</label>
              <input class="form-control" name="country" value="<?= h((string)($edit['country'] ?? '')) ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-dark" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
              <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=chefs">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Chefs</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Name</th><th>Country</th><th>Created</th><th style="width:180px;">Actions</th></tr></thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['chef_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$r['chef_name']) ?></td>
                  <td><?= h((string)($r['country'] ?? '')) ?></td>
                  <td class="small text-muted"><?= h((string)$r['created_at']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=chefs&edit_id=<?= (int)$r['chef_id'] ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this chef? (If linked to recipes, it may fail)');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="chef_delete">
                      <input type="hidden" name="chef_id" value="<?= (int)$r['chef_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'events'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;
        if ($editId > 0) {
          $st = $pdo->prepare("SELECT * FROM cooking_events WHERE event_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }
        $rows = $pdo->query("SELECT * FROM cooking_events ORDER BY event_datetime DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);

        // for datetime-local input
        $editDtLocal = '';
        if ($edit && !empty($edit['event_datetime'])) {
          $editDtLocal = str_replace(' ', 'T', substr((string)$edit['event_datetime'], 0, 16));
        }
      ?>
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Cooking Events CRUD</h1>
          <div class="text-muted">Add / Edit / Delete / Activate</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3"><?= $edit ? 'Edit Event' : 'Add Event' ?></h2>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="event_save">
            <input type="hidden" name="event_id" value="<?= (int)($edit['event_id'] ?? 0) ?>">

            <div class="col-md-5">
              <label class="form-label">Title *</label>
              <input class="form-control" name="title" value="<?= h((string)($edit['title'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Event Date/Time *</label>
              <input class="form-control" name="event_datetime" type="datetime-local" value="<?= h($editDtLocal) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Active</label>
              <?php $ia = (int)($edit['is_active'] ?? 1); ?>
              <select class="form-select" name="is_active">
                <option value="1" <?= $ia===1?'selected':'' ?>>Yes</option>
                <option value="0" <?= $ia===0?'selected':'' ?>>No</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Location</label>
              <input class="form-control" name="location" value="<?= h((string)($edit['location'] ?? '')) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Image (path or URL)</label>
              <input class="form-control" name="image" value="<?= h((string)($edit['image'] ?? '')) ?>">
              <div class="small text-muted">Example: /uploads/events/xxx.png</div>
            </div>
            <div class="col-md-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"><?= h((string)($edit['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-dark" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
              <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=events">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Events</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Title</th><th>Date</th><th>Active</th><th>Location</th><th style="width:180px;">Actions</th></tr></thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['event_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$r['title']) ?></td>
                  <td class="small text-muted"><?= h((string)$r['event_datetime']) ?></td>
                  <td><?= ((int)$r['is_active']===1) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                  <td><?= h((string)($r['location'] ?? '')) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=events&edit_id=<?= (int)$r['event_id'] ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this event?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="event_delete">
                      <input type="hidden" name="event_id" value="<?= (int)$r['event_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'community'): ?>
      <?php
        $st = $pdo->query("
          SELECT p.post_id, p.title, p.content, p.post_type, p.status, p.created_at,
                 CONCAT(u.fname,' ',u.lname) AS author_name,
                 (SELECT COUNT(*) FROM community_comments c WHERE c.post_id=p.post_id) AS comment_count,
                 (SELECT COUNT(*) FROM community_reactions r WHERE r.post_id=p.post_id) AS react_count
          FROM community_posts p
          JOIN users u ON u.user_id = p.user_id
          ORDER BY p.created_at DESC
          LIMIT 200
        ");
        $posts = $st->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Community Moderation</h1>
          <div class="text-muted">Approve / Reject / Delete posts, delete comments, clear reactions.</div>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Author</th><th>Comments</th><th>Reactions</th><th>Created</th><th style="width:120px;">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($posts as $p): ?>
                <?php
                  $badge = $p['status']==='approved'?'bg-success':($p['status']==='rejected'?'bg-danger':'bg-warning text-dark');
                ?>
                <tr>
                  <td><?= (int)$p['post_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$p['title']) ?><div class="text-muted small truncate"><?= h((string)$p['content']) ?></div></td>
                  <td><span class="badge bg-light text-dark"><?= h((string)$p['post_type']) ?></span></td>
                  <td><span class="badge <?= $badge ?>"><?= h((string)$p['status']) ?></span></td>
                  <td><?= h((string)$p['author_name']) ?></td>
                  <td><?= (int)$p['comment_count'] ?></td>
                  <td><?= (int)$p['react_count'] ?></td>
                  <td class="small text-muted"><?= h((string)$p['created_at']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=community_view&post_id=<?= (int)$p['post_id'] ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'community_view'): ?>
      <?php
        $postId = (int)($_GET['post_id'] ?? 0);

        $st = $pdo->prepare("
          SELECT p.*, CONCAT(u.fname,' ',u.lname) AS author_name
          FROM community_posts p
          JOIN users u ON u.user_id = p.user_id
          WHERE p.post_id=:id
          LIMIT 1
        ");
        $st->execute([':id'=>$postId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);

        $chefRecipe = null;
        $comments = [];
        $map = ['like'=>0,'heart'=>0];

        if ($p) {
          if ((string)$p['post_type'] === 'chef_recipe') {
            $x = $pdo->prepare("SELECT * FROM community_chef_recipes WHERE post_id=:id LIMIT 1");
            $x->execute([':id'=>$postId]);
            $chefRecipe = $x->fetch(PDO::FETCH_ASSOC);
          }

          $cst = $pdo->prepare("
            SELECT c.*, CONCAT(u.fname,' ',u.lname) AS author_name
            FROM community_comments c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.post_id=:id
            ORDER BY c.created_at ASC
          ");
          $cst->execute([':id'=>$postId]);
          $comments = $cst->fetchAll(PDO::FETCH_ASSOC);

          $rst = $pdo->prepare("
            SELECT reaction_type, COUNT(*) cnt
            FROM community_reactions
            WHERE post_id=:id
            GROUP BY reaction_type
          ");
          $rst->execute([':id'=>$postId]);
          $rc = $rst->fetchAll(PDO::FETCH_ASSOC);
          foreach ($rc as $r) $map[(string)$r['reaction_type']] = (int)$r['cnt'];
        }
      ?>

      <?php if (!$p): ?>
        <div class="alert alert-danger">Post not found.</div>
      <?php else: ?>
        <?php
          $badge = $p['status']==='approved'?'bg-success':($p['status']==='rejected'?'bg-danger':'bg-warning text-dark');
        ?>
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div>
            <h1 class="h5 mb-1">Post #<?= (int)$p['post_id'] ?> — <?= h((string)$p['title']) ?></h1>
            <div class="text-muted small">
              By <?= h((string)$p['author_name']) ?> • <?= h((string)$p['created_at']) ?> •
              <span class="badge <?= $badge ?>"><?= h((string)$p['status']) ?></span>
              <span class="badge bg-light text-dark"><?= h((string)$p['post_type']) ?></span>
            </div>
          </div>
          <a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=community">Back</a>
        </div>

        <div class="card card-soft mb-3">
          <div class="card-body">
            <div class="mb-2"><?= nl2br(h((string)$p['content'])) ?></div>

            <?php if (!empty($p['cover_image'])): ?>
              <div class="mb-2">
                <img src="<?= h((string)$p['cover_image']) ?>" style="max-width:420px;border-radius:12px;" alt="cover">
              </div>
            <?php endif; ?>

            <?php if (!empty($p['cuisine']) || !empty($p['difficulty']) || !empty($p['prep_time']) || !empty($p['cook_time'])): ?>
              <div class="d-flex gap-2 flex-wrap mb-2">
                <?php if (!empty($p['cuisine'])): ?><span class="badge bg-light text-dark">Cuisine: <?= h((string)$p['cuisine']) ?></span><?php endif; ?>
                <?php if (!empty($p['difficulty'])): ?><span class="badge bg-light text-dark">Difficulty: <?= h((string)$p['difficulty']) ?></span><?php endif; ?>
                <?php if (!empty($p['prep_time'])): ?><span class="badge bg-light text-dark">Prep: <?= (int)$p['prep_time'] ?> min</span><?php endif; ?>
                <?php if (!empty($p['cook_time'])): ?><span class="badge bg-light text-dark">Cook: <?= (int)$p['cook_time'] ?> min</span><?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($chefRecipe): ?>
              <hr>
              <div class="mb-2"><span class="badge bg-dark">Chef Recipe Details</span></div>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="small text-muted mb-1">Ingredients</div>
                  <div class="border rounded p-2 bg-light"><?= nl2br(h((string)$chefRecipe['ingredients'])) ?></div>
                </div>
                <div class="col-md-6">
                  <div class="small text-muted mb-1">Instructions</div>
                  <div class="border rounded p-2 bg-light"><?= nl2br(h((string)$chefRecipe['instructions'])) ?></div>
                </div>
                <div class="col-md-3"><span class="badge bg-light text-dark">Servings: <?= (int)($chefRecipe['servings'] ?? 0) ?></span></div>
                <div class="col-md-3"><span class="badge bg-light text-dark">Calories: <?= h((string)($chefRecipe['calories'] ?? '')) ?></span></div>
              </div>
              <?php if (!empty($chefRecipe['tips'])): ?>
                <div class="mt-2">
                  <div class="small text-muted mb-1">Tips</div>
                  <div class="border rounded p-2 bg-light"><?= nl2br(h((string)$chefRecipe['tips'])) ?></div>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <hr>
            <div class="d-flex gap-2 flex-wrap align-items-center">
              <span class="badge bg-light text-dark">👍 <?= (int)$map['like'] ?></span>
              <span class="badge bg-light text-dark">❤️ <?= (int)$map['heart'] ?></span>

              <form method="post" class="ms-auto d-flex gap-2 flex-wrap">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="community_set_status">
                <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                <select class="form-select form-select-sm" name="status" style="width:auto;">
                  <?php foreach (['pending','approved','rejected'] as $st): ?>
                    <option value="<?= h($st) ?>" <?= ((string)$p['status']===$st)?'selected':'' ?>><?= h($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <input class="form-control form-control-sm" name="reject_reason" placeholder="Reject reason (optional)" style="min-width:240px;" value="<?= h((string)($p['reject_reason'] ?? '')) ?>">
                <button class="btn btn-sm btn-dark" type="submit">Update Status</button>
              </form>

              <form method="post" class="d-inline" onsubmit="return confirm('Clear all reactions for this post?');">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="community_clear_reactions">
                <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Clear Reactions</button>
              </form>

              <form method="post" class="d-inline" onsubmit="return confirm('Delete this post and all comments/reactions?');">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="community_delete_post">
                <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete Post</button>
              </form>
            </div>
          </div>
        </div>

        <div class="card card-soft">
          <div class="card-body">
            <h2 class="h6 mb-3">Comments (<?= count($comments) ?>)</h2>
            <?php if (!$comments): ?>
              <div class="text-muted">No comments.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead><tr><th>ID</th><th>Author</th><th>Text</th><th>Hidden</th><th>Created</th><th style="width:120px;">Action</th></tr></thead>
                  <tbody>
                  <?php foreach ($comments as $c): ?>
                    <tr>
                      <td><?= (int)$c['comment_id'] ?></td>
                      <td><?= h((string)$c['author_name']) ?></td>
                      <td><?= h((string)$c['comment_text']) ?></td>
                      <td><?= ((int)$c['is_hidden']===1)?'<span class="badge bg-secondary">Yes</span>':'<span class="badge bg-success">No</span>' ?></td>
                      <td class="small text-muted"><?= h((string)$c['created_at']) ?></td>
                      <td>
                        <form method="post" onsubmit="return confirm('Delete this comment?');">
                          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                          <input type="hidden" name="action" value="community_delete_comment">
                          <input type="hidden" name="comment_id" value="<?= (int)$c['comment_id'] ?>">
                          <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($section === 'contacts'): ?>
      <?php
        $rows = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Contact Messages</h1>
          <div class="text-muted">Read / Update status / Delete</div>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Email</th><th>Type</th><th>Subject</th><th>Status</th><th>Created</th><th style="width:260px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $m): ?>
                <tr>
                  <td><?= (int)$m['message_id'] ?></td>
                  <td><?= h((string)$m['name']) ?></td>
                  <td><?= h((string)$m['email']) ?></td>
                  <td><span class="badge bg-light text-dark"><?= h((string)$m['type']) ?></span></td>
                  <td class="truncate"><?= h((string)$m['subject']) ?></td>
                  <td>
                    <?php
                      $stt = (string)$m['status'];
                      $badge = $stt==='new'?'bg-primary':($stt==='read'?'bg-secondary':($stt==='replied'?'bg-success':'bg-dark'));
                    ?>
                    <span class="badge <?= $badge ?>"><?= h($stt) ?></span>
                  </td>
                  <td class="small text-muted"><?= h((string)$m['created_at']) ?></td>
                  <td>
                    <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#msg<?= (int)$m['message_id'] ?>">View</button>

                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="contact_update_status">
                      <input type="hidden" name="message_id" value="<?= (int)$m['message_id'] ?>">
                      <select name="status" class="form-select form-select-sm d-inline" style="width:auto;display:inline-block;">
                        <?php foreach (['new','read','replied','archived'] as $opt): ?>
                          <option value="<?= h($opt) ?>" <?= $stt===$opt?'selected':'' ?>><?= h($opt) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-dark" type="submit">Update</button>
                    </form>

                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this message?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="contact_delete">
                      <input type="hidden" name="message_id" value="<?= (int)$m['message_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
                <tr class="collapse" id="msg<?= (int)$m['message_id'] ?>">
                  <td colspan="8">
                    <div class="p-3 bg-light rounded">
                      <div class="small text-muted mb-1">Message</div>
                      <div><?= nl2br(h((string)$m['message'])) ?></div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'culinary'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;
        if ($editId > 0) {
          $st = $pdo->prepare("SELECT * FROM culinary_resources WHERE resource_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }
        $rows = $pdo->query("SELECT * FROM culinary_resources ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Culinary Resources CRUD</h1>
          <div class="text-muted">Schema အသစ်အတိုင်း (category/type/file/video/thumbnail)</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3"><?= $edit ? 'Edit Culinary Resource' : 'Add Culinary Resource' ?></h2>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="culinary_save">
            <input type="hidden" name="resource_id" value="<?= (int)($edit['resource_id'] ?? 0) ?>">

            <div class="col-md-5">
              <label class="form-label">Title *</label>
              <input class="form-control" name="title" value="<?= h((string)($edit['title'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Topic *</label>
              <input class="form-control" name="topic" value="<?= h((string)($edit['topic'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Category</label>
              <input class="form-control" name="category" value="<?= h((string)($edit['category'] ?? 'Cooking Basics')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Active</label>
              <?php $ia = (int)($edit['is_active'] ?? 1); ?>
              <select class="form-select" name="is_active">
                <option value="1" <?= $ia===1?'selected':'' ?>>Yes</option>
                <option value="0" <?= $ia===0?'selected':'' ?>>No</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Resource Type</label>
              <?php $cur = (string)($edit['resource_type'] ?? 'Recipe Card'); ?>
              <select class="form-select" name="resource_type">
                <?php foreach (['Recipe Card','Tutorial','Video','Infographic','PDF'] as $t): ?>
                  <option value="<?= h($t) ?>" <?= $cur===$t?'selected':'' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Thumbnail URL</label>
              <input class="form-control" name="thumbnail_url" value="<?= h((string)($edit['thumbnail_url'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">File URL</label>
              <input class="form-control" name="file_url" value="<?= h((string)($edit['file_url'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Video URL</label>
              <input class="form-control" name="video_url" value="<?= h((string)($edit['video_url'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"><?= h((string)($edit['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-dark" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
              <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=culinary">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Culinary Resources</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Title</th><th>Topic</th><th>Category</th><th>Type</th><th>Active</th><th style="width:220px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['resource_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$r['title']) ?><div class="text-muted small truncate"><?= h((string)($r['description'] ?? '')) ?></div></td>
                  <td><?= h((string)$r['topic']) ?></td>
                  <td><?= h((string)$r['category']) ?></td>
                  <td><?= h((string)$r['resource_type']) ?></td>
                  <td><?= ((int)$r['is_active']===1) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=culinary&edit_id=<?= (int)$r['resource_id'] ?>">Edit</a>
                    <?php if (!empty($r['file_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= h((string)$r['file_url']) ?>" target="_blank" rel="noopener">File</a><?php endif; ?>
                    <?php if (!empty($r['video_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= h((string)$r['video_url']) ?>" target="_blank" rel="noopener">Video</a><?php endif; ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this resource?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="culinary_delete">
                      <input type="hidden" name="resource_id" value="<?= (int)$r['resource_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'educational'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;
        if ($editId > 0) {
          $st = $pdo->prepare("SELECT * FROM educational_resources WHERE resource_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }
        $rows = $pdo->query("SELECT * FROM educational_resources ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Educational Resources CRUD</h1>
          <div class="text-muted">Schema အသစ်အတိုင်း (category/type/file/video/thumbnail)</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <h2 class="h6 mb-3"><?= $edit ? 'Edit Educational Resource' : 'Add Educational Resource' ?></h2>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="edu_save">
            <input type="hidden" name="resource_id" value="<?= (int)($edit['resource_id'] ?? 0) ?>">

            <div class="col-md-5">
              <label class="form-label">Title *</label>
              <input class="form-control" name="title" value="<?= h((string)($edit['title'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Topic *</label>
              <input class="form-control" name="topic" value="<?= h((string)($edit['topic'] ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Category</label>
              <input class="form-control" name="category" value="<?= h((string)($edit['category'] ?? 'Basics')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Active</label>
              <?php $ia = (int)($edit['is_active'] ?? 1); ?>
              <select class="form-select" name="is_active">
                <option value="1" <?= $ia===1?'selected':'' ?>>Yes</option>
                <option value="0" <?= $ia===0?'selected':'' ?>>No</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Resource Type</label>
              <?php $cur = (string)($edit['resource_type'] ?? 'PDF'); ?>
              <select class="form-select" name="resource_type">
                <?php foreach (['Tutorial','Video','Infographic','PDF','Worksheet'] as $t): ?>
                  <option value="<?= h($t) ?>" <?= $cur===$t?'selected':'' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Thumbnail URL</label>
              <input class="form-control" name="thumbnail_url" value="<?= h((string)($edit['thumbnail_url'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">File URL</label>
              <input class="form-control" name="file_url" value="<?= h((string)($edit['file_url'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Video URL</label>
              <input class="form-control" name="video_url" value="<?= h((string)($edit['video_url'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"><?= h((string)($edit['description'] ?? '')) ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-dark" type="submit"><?= $edit ? 'Update' : 'Add' ?></button>
              <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=educational">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Educational Resources</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Title</th><th>Topic</th><th>Category</th><th>Type</th><th>Active</th><th style="width:220px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['resource_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$r['title']) ?><div class="text-muted small truncate"><?= h((string)($r['description'] ?? '')) ?></div></td>
                  <td><?= h((string)$r['topic']) ?></td>
                  <td><?= h((string)$r['category']) ?></td>
                  <td><?= h((string)$r['resource_type']) ?></td>
                  <td><?= ((int)$r['is_active']===1) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=educational&edit_id=<?= (int)$r['resource_id'] ?>">Edit</a>
                    <?php if (!empty($r['file_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= h((string)$r['file_url']) ?>" target="_blank" rel="noopener">File</a><?php endif; ?>
                    <?php if (!empty($r['video_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= h((string)$r['video_url']) ?>" target="_blank" rel="noopener">Video</a><?php endif; ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this resource?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="edu_delete">
                      <input type="hidden" name="resource_id" value="<?= (int)$r['resource_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php elseif ($section === 'users'): ?>
      <?php
        $editId = (int)($_GET['edit_id'] ?? 0);
        $edit = null;
        if ($editId > 0) {
          $st = $pdo->prepare("SELECT user_id,fname,lname,phone,username,email,gender,role,is_active,failed_login_attempts,lockout_until,created_at FROM users WHERE user_id=:id");
          $st->execute([':id'=>$editId]);
          $edit = $st->fetch(PDO::FETCH_ASSOC);
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
          $like = "%{$q}%";
          $st = $pdo->prepare("
            SELECT user_id,fname,lname,username,email,role,is_active,failed_login_attempts,lockout_until,created_at
            FROM users
            WHERE email LIKE :q1 OR fname LIKE :q2 OR lname LIKE :q3 OR username LIKE :q4
            ORDER BY user_id DESC LIMIT 200
          ");
          $st->execute([':q1'=>$like, ':q2'=>$like, ':q3'=>$like, ':q4'=>$like]);
          $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
          $rows = $pdo->query("
            SELECT user_id,fname,lname,username,email,role,is_active,failed_login_attempts,lockout_until,created_at
            FROM users ORDER BY user_id DESC LIMIT 200
          ")->fetchAll(PDO::FETCH_ASSOC);
        }
      ?>

      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <h1 class="h4 mb-1">Users Control Panel</h1>
          <div class="text-muted">Edit user profile + role/is_active + Lock/Unlock</div>
        </div>
      </div>

      <div class="card card-soft mb-3">
        <div class="card-body">
          <form class="row g-2" method="get">
            <input type="hidden" name="section" value="users">
            <div class="col-md-6">
              <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search by name/email/username...">
            </div>
            <div class="col-md-2"><button class="btn btn-outline-dark w-100" type="submit">Search</button></div>
            <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= h($BASE) ?>?section=users">Reset</a></div>
          </form>
        </div>
      </div>

      <?php if ($edit): ?>
        <div class="card card-soft mb-3">
          <div class="card-body">
            <h2 class="h6 mb-3">Edit User #<?= (int)$edit['user_id'] ?></h2>
            <form method="post" class="row g-3">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="user_update">
              <input type="hidden" name="user_id" value="<?= (int)$edit['user_id'] ?>">

              <div class="col-md-3">
                <label class="form-label">First Name</label>
                <input class="form-control" name="fname" value="<?= h((string)$edit['fname']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Last Name</label>
                <input class="form-control" name="lname" value="<?= h((string)$edit['lname']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="username" value="<?= h((string)$edit['username']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Email</label>
                <input class="form-control" name="email" type="email" value="<?= h((string)$edit['email']) ?>" required>
              </div>

              <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= h((string)($edit['phone'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Gender</label>
                <?php $g = (string)($edit['gender'] ?? ''); ?>
                <select class="form-select" name="gender">
                  <option value="">--</option>
                  <?php foreach (['Male','Female','Other'] as $x): ?>
                    <option value="<?= h($x) ?>" <?= $g===$x?'selected':'' ?>><?= h($x) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Role</label>
                <?php $r = (string)($edit['role'] ?? 'user'); ?>
                <select class="form-select" name="role">
                  <option value="user" <?= $r==='user'?'selected':'' ?>>user</option>
                  <option value="chef" <?= $r==='chef'?'selected':'' ?>>chef</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Active</label>
                <?php $ia = (int)($edit['is_active'] ?? 1); ?>
                <select class="form-select" name="is_active">
                  <option value="1" <?= $ia===1?'selected':'' ?>>Yes</option>
                  <option value="0" <?= $ia===0?'selected':'' ?>>No</option>
                </select>
              </div>

              <div class="col-12 d-flex gap-2">
                <button class="btn btn-dark" type="submit">Update</button>
                <a class="btn btn-outline-secondary" href="<?= h($BASE) ?>?section=users">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="card card-soft">
        <div class="card-body">
          <h2 class="h6 mb-3">Users (latest 200)</h2>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Active</th><th>Attempts</th><th>Lockout</th><th style="width:320px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $u): ?>
                <?php
                  $locked = !empty($u['lockout_until']) && strtotime((string)$u['lockout_until']) > time();
                ?>
                <tr>
                  <td><?= (int)$u['user_id'] ?></td>
                  <td class="fw-semibold"><?= h((string)$u['fname'].' '.(string)$u['lname']) ?></td>
                  <td class="mono"><?= h((string)$u['username']) ?></td>
                  <td><?= h((string)$u['email']) ?></td>
                  <td><span class="badge bg-light text-dark"><?= h((string)$u['role']) ?></span></td>
                  <td><?= ((int)$u['is_active']===1) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                  <td><?= (int)$u['failed_login_attempts'] ?></td>
                  <td>
                    <?= $locked ? '<span class="badge bg-danger">Locked</span>' : '<span class="badge bg-success">OK</span>' ?>
                    <?php if (!empty($u['lockout_until'])): ?><div class="small text-muted"><?= h((string)$u['lockout_until']) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <a class="btn btn-sm btn-outline-dark" href="<?= h($BASE) ?>?section=users&edit_id=<?= (int)$u['user_id'] ?>">Edit</a>

                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="user_unlock">
                      <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                      <button class="btn btn-sm btn-outline-success" type="submit">Unlock</button>
                    </form>

                    <form method="post" class="d-inline" onsubmit="return confirm('Lock this user for 10 years?');">
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="user_lock_10y">
                      <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Lock</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php else: ?>
      <div class="alert alert-warning">Unknown section.</div>
    <?php endif; ?>

    <div class="small text-muted mt-4">FoodFusion Admin • <?= date('Y-m-d H:i:s') ?></div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
