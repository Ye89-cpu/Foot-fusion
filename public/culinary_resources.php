<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/conn.php'; // $pdo

/* ========= CONFIG ========= */
$PROJECT_BASE = '/Food-Fusion-web';               // must match your folder name
$APP_ROOT     = realpath(__DIR__ . '/..');        // C:\xampp\htdocs\Food-Fusion-web
$PER_PAGE     = 10;

/* ========= HELPERS ========= */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

function normalize_asset(string $path): string {
  $path = trim($path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;          // absolute URL
  $path = preg_replace('~^/Food-Fusion-web~i', '', $path);       // strip project prefix if stored
  if ($path === '' ) return '';
  if ($path[0] !== '/') $path = '/' . $path;                    // ensure leading slash
  return $path;
}

function redirect_with_toast(string $toUrl, string $msg, string $type = 'danger'): void {
  $sep = (str_contains($toUrl, '?') ? '&' : '?');
  $url = $toUrl . $sep . 'toast=' . rawurlencode($msg) . '&ttype=' . rawurlencode($type);
  header('Location: ' . $url);
  exit;
}

function is_safe_local_file(string $appRoot, string $normalizedPath): bool {
  // normalizedPath like "/uploads/resources/x.pdf"
  $full = realpath($appRoot . $normalizedPath);
  if ($full === false) return false;

  // ensure file is inside project root
  $root = rtrim(str_replace('\\', '/', $appRoot), '/');
  $fullN = str_replace('\\', '/', $full);
  return str_starts_with($fullN, $root . '/');
}

/* ==========================================================
   1) DOWNLOAD HANDLER (MUST RUN BEFORE header.php OUTPUT)
========================================================== */
if (isset($_GET['download'])) {
  $rid = (int)($_GET['download'] ?? 0);

  if ($rid <= 0) {
    redirect_with_toast('culinary_resources.php', 'Invalid download request.', 'danger');
  }

  $st = $pdo->prepare("SELECT file_url FROM culinary_resources WHERE resource_id=:id AND is_active=1 LIMIT 1");
  $st->execute([':id' => $rid]);
  $fileUrlRaw = (string)($st->fetchColumn() ?: '');

  if ($fileUrlRaw === '') {
    redirect_with_toast('culinary_resources.php', 'Download file is not available for this resource.', 'warning');
  }

  $fileUrl = normalize_asset($fileUrlRaw);

  // Log download (best effort)
  try {
    $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $ins = $pdo->prepare("
      INSERT INTO culinary_downloads(resource_id, user_id, ip_address, user_agent)
      VALUES(:rid, :uid, :ip, :ua)
    ");
    $ins->execute([':rid'=>$rid, ':uid'=>$userId, ':ip'=>$ip, ':ua'=>$ua]);
  } catch (Throwable $e) {
    error_log("Download log failed: " . $e->getMessage());
  }

  // If local path, ensure file exists
  if (!preg_match('~^https?://~i', $fileUrl)) {
    if ($APP_ROOT === false || !is_safe_local_file($APP_ROOT, $fileUrl) || !file_exists($APP_ROOT . $fileUrl)) {
      redirect_with_toast('culinary_resources.php', 'Download file is missing on the server. Please contact support.', 'danger');
    }
    $go = $PROJECT_BASE . $fileUrl;
    header("Location: " . $go);
    exit;
  }

  // External URL
  header("Location: " . $fileUrl);
  exit;
}

/* ==========================================================
   2) PAGE OUTPUT (SAFE TO INCLUDE HEADER NOW)
========================================================== */
$pageTitle = "FoodFusion | Culinary Resources";
require_once __DIR__ . '/../header&footer/header.php';

/* ==========================================================
   3) FILTERS + PAGINATION
========================================================== */
$q          = trim((string)($_GET['q'] ?? ''));
$topic      = trim((string)($_GET['topic'] ?? 'All'));
$category   = trim((string)($_GET['category'] ?? 'All'));
$type       = trim((string)($_GET['type'] ?? 'All'));
$page       = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$where  = [];
$params = [];

if ($q !== '') {
  $where[] = "(title LIKE :q OR description LIKE :q)";
  $params[':q'] = "%{$q}%";
}
if ($topic !== '' && $topic !== 'All') {
  $where[] = "topic = :topic";
  $params[':topic'] = $topic;
}
if ($category !== '' && $category !== 'All') {
  $where[] = "category = :category";
  $params[':category'] = $category;
}
if ($type !== '' && $type !== 'All') {
  $where[] = "resource_type = :type";
  $params[':type'] = $type;
}

$whereSql = $where ? (" WHERE is_active=1 AND " . implode(" AND ", $where)) : " WHERE is_active=1";

/* dropdown options */
$optTopics = $pdo->query("SELECT DISTINCT topic FROM culinary_resources WHERE is_active=1 ORDER BY topic")->fetchAll(PDO::FETCH_COLUMN);
$optCats   = $pdo->query("SELECT DISTINCT category FROM culinary_resources WHERE is_active=1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$optTypes  = $pdo->query("SELECT DISTINCT resource_type FROM culinary_resources WHERE is_active=1 ORDER BY resource_type")->fetchAll(PDO::FETCH_COLUMN);

/* total count for pagination */
$cntSql = "SELECT COUNT(*) FROM culinary_resources {$whereSql}";
$cntSt  = $pdo->prepare($cntSql);
$cntSt->execute($params);
$total = (int)$cntSt->fetchColumn();

$totalPages = (int)max(1, ceil($total / $PER_PAGE));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $PER_PAGE;

$listSql = "
  SELECT resource_id, title, topic, category, resource_type,
         description, thumbnail_url, file_url, video_url, created_at
  FROM culinary_resources
  {$whereSql}
  ORDER BY created_at DESC, resource_id DESC
  LIMIT {$PER_PAGE} OFFSET {$offset}
";
$listSt = $pdo->prepare($listSql);
$listSt->execute($params);
$rows = $listSt->fetchAll(PDO::FETCH_ASSOC);

/* preserve query params (except page) */
$baseQs = [
  'q'        => $q,
  'topic'    => $topic,
  'category' => $category,
  'type'     => $type,
];
function build_page_url(array $baseQs, int $page): string {
  $qs = $baseQs;
  $qs['page'] = $page;
  // remove "All" and empty for cleaner URLs
  foreach ($qs as $k => $v) {
    if ($v === '' || $v === 'All') unset($qs[$k]);
  }
  return 'culinary_resources.php' . (empty($qs) ? '' : ('?' . http_build_query($qs)));
}

/* toast from GET */
$toastMsg  = trim((string)($_GET['toast'] ?? ''));
$toastType = trim((string)($_GET['ttype'] ?? 'danger'));
$toastType = in_array($toastType, ['success','info','warning','danger','secondary','dark','primary'], true) ? $toastType : 'danger';

?>
<style>
  .cr-wrap{ width:100%; padding: 18px 18px 28px; }
  .cr-hero{
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(0,0,0,.78), rgba(0,0,0,.28));
    padding: 18px 18px;
    color: #fff;
    border: 1px solid rgba(255,255,255,.12);
  }

  .cr-filter{
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 6px 22px rgba(0,0,0,.06);
  }

  .cr-card{
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 10px 28px rgba(0,0,0,.10);
    height: 100%;
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .cr-card:hover{ transform: translateY(-2px); box-shadow: 0 16px 38px rgba(0,0,0,.14); }
  .cr-thumb{ width:100%; height: 190px; object-fit: cover; background:#eee; display:block; }
  .cr-body{ padding: 14px 14px 12px; display:flex; flex-direction:column; gap:10px; }
  .cr-title{ margin:0; font-weight: 800; font-size: 1rem; line-height: 1.25; }
  .cr-meta{ font-size:.86rem; color:#6c757d; }
  .cr-desc{ font-size:.88rem; color:#4b5563; line-height:1.45; }
  .cr-desc .short{
    display:-webkit-box; -webkit-box-orient:vertical; overflow:hidden;
    -webkit-line-clamp:3; line-clamp:3;
  }
  .cr-desc .full{ display:none; white-space: pre-line; }
  .cr-badges .badge{ font-size:.72rem; border-radius:999px; }
  .cr-actions{ display:flex; flex-wrap:wrap; gap:8px; }
  .cr-actions .btn{ border-radius:999px; }
  .cr-empty{
    border: 1px dashed #c9c9c9;
    border-radius: 18px;
    padding: 26px;
    background: #fafafa;
  }

  .cr-toast-wrap{
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 2100;
    width: min(360px, calc(100vw - 32px));
  }
</style>

<!-- TOAST (Top-right) -->
<div class="cr-toast-wrap">
  <?php if ($toastMsg !== ''): ?>
    <div id="crToast" class="toast align-items-center text-bg-<?= h($toastType) ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body"><?= h($toastMsg) ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const t = document.getElementById('crToast');
  if (t && window.bootstrap) new bootstrap.Toast(t, { delay: 5500 }).show();
});
</script>

<div class="container-fluid cr-wrap">

  <!-- HERO -->
  <div class="cr-hero mb-3">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
      <div>
        <h1 class="h3 fw-bold mb-1">Culinary Resources</h1>
        <div class="opacity-75">Downloadable cards, tutorials, and videos. Filter by type, topic, and category.</div>
      </div>
      <div class="text-white-50 align-self-md-end">
        Total: <b><?= (int)$total ?></b> • Page <b><?= (int)$page ?></b> / <b><?= (int)$totalPages ?></b>
      </div>
    </div>
  </div>

  <!-- FILTER UI -->
  <section class="cr-filter p-3 p-lg-4 mb-4">
    <form method="get" action="">
      <div class="row g-3 align-items-end">

        <div class="col-12 col-lg-4">
          <label class="form-label">Search</label>
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="e.g., knife, sauce, safety">
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Type</label>
          <select class="form-select" name="type">
            <option value="All" <?= ($type === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optTypes as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($type === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">Topic</label>
          <select class="form-select" name="topic">
            <option value="All" <?= ($topic === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optTopics as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($topic === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <label class="form-label">Category</label>
          <select class="form-select" name="category">
            <option value="All" <?= ($category === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optCats as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($category === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-dark px-4" type="submit">Apply</button>
          <a class="btn btn-outline-secondary" href="culinary_resources.php">Reset</a>
        </div>

      </div>
    </form>
  </section>

  <!-- RESULTS -->
  <?php if (empty($rows)): ?>
    <div class="cr-empty">
      <h5 class="mb-1">No resources found</h5>
      <div class="text-secondary">Try adjusting filters or search keywords.</div>
    </div>
  <?php else: ?>

    <div class="row g-4">
      <?php foreach ($rows as $r): ?>
        <?php
          $rid = (int)$r['resource_id'];

          $thumbRaw = (string)($r['thumbnail_url'] ?? '');
          $thumb = normalize_asset($thumbRaw);
          if ($thumb === '') $thumb = '/uploads/resource_thumbs/placeholder.png';
          $thumbUrl = preg_match('~^https?://~i', $thumb) ? $thumb : ($PROJECT_BASE . $thumb);

          $desc = trim((string)($r['description'] ?? ''));
          if ($desc === '') $desc = '—';

          $hasFile  = trim((string)($r['file_url'] ?? '')) !== '';
          $hasVideo = trim((string)($r['video_url'] ?? '')) !== '';
        ?>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="cr-card">
            <img class="cr-thumb"
                 src="<?= $thumbUrl ?>"
                 alt="<?= h((string)$r['title']) ?>"
                 onerror="this.onerror=null;this.src='<?= $PROJECT_BASE ?>/uploads/resource_thumbs/placeholder.png';">

            <div class="cr-body">
              <div class="cr-badges d-flex flex-wrap gap-2">
                <span class="badge text-bg-dark"><?= h((string)$r['resource_type']) ?></span>
                <span class="badge text-bg-secondary"><?= h((string)$r['category']) ?></span>
                <span class="badge text-bg-light border text-dark"><?= h((string)$r['topic']) ?></span>
              </div>

              <div>
                <p class="cr-title mb-1"><?= h((string)$r['title']) ?></p>
                <div class="cr-meta">Added: <?= h((string)$r['created_at']) ?></div>
              </div>

              <div class="cr-desc" id="desc-<?= $rid ?>">
                <div class="short"><?= h($desc) ?></div>
                <div class="full"><?= h($desc) ?></div>
                <?php if (mb_strlen($desc) > 220): ?>
                  <button type="button" class="btn btn-link p-0 mt-1 cr-toggle" data-target="<?= $rid ?>">See more</button>
                <?php endif; ?>
              </div>

              <div class="cr-actions">
                <?php if ($hasFile): ?>
                  <a class="btn btn-sm btn-outline-dark px-3" href="culinary_resources.php?download=<?= $rid ?>">
                    Download
                  </a>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-secondary px-3" type="button" disabled>Download</button>
                <?php endif; ?>

                <?php if ($hasVideo): ?>
                  <button class="btn btn-sm btn-dark px-3"
                          type="button"
                          data-bs-toggle="modal"
                          data-bs-target="#videoModal"
                          data-video="<?= h((string)$r['video_url']) ?>"
                          data-title="<?= h((string)$r['title']) ?>">
                    Watch Video
                  </button>
                <?php else: ?>
                  <button class="btn btn-sm btn-secondary px-3" type="button" disabled>Watch Video</button>
                <?php endif; ?>
              </div>

            </div>
          </div>
        </div>

      <?php endforeach; ?>
    </div>

    <!-- PAGINATION -->
    <nav class="mt-4" aria-label="Culinary resources pagination">
      <ul class="pagination justify-content-center flex-wrap">

        <?php
          $prev = max(1, $page - 1);
          $next = min($totalPages, $page + 1);

          $window = 2; // show around current page
          $start = max(1, $page - $window);
          $end   = min($totalPages, $page + $window);
        ?>

        <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
          <a class="page-link" href="<?= h(build_page_url($baseQs, 1)) ?>">First</a>
        </li>

        <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
          <a class="page-link" href="<?= h(build_page_url($baseQs, $prev)) ?>">Prev</a>
        </li>

        <?php if ($start > 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
          <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
            <a class="page-link" href="<?= h(build_page_url($baseQs, $p)) ?>"><?= (int)$p ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>

        <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
          <a class="page-link" href="<?= h(build_page_url($baseQs, $next)) ?>">Next</a>
        </li>

        <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
          <a class="page-link" href="<?= h(build_page_url($baseQs, $totalPages)) ?>">Last</a>
        </li>

      </ul>
    </nav>

  <?php endif; ?>

</div>

<!-- VIDEO MODAL -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title" id="videoTitle">Video</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="ratio ratio-16x9">
          <iframe id="videoFrame" src="" title="Video" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JS: see more + video validation + toast on video fail -->
<script>
  // See more toggle
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.cr-toggle');
    if (!btn) return;

    const rid = btn.getAttribute('data-target');
    const wrap = document.getElementById('desc-' + rid);
    if (!wrap) return;

    const shortEl = wrap.querySelector('.short');
    const fullEl  = wrap.querySelector('.full');

    const isOpen = fullEl.style.display === 'block';
    if (isOpen) {
      fullEl.style.display = 'none';
      shortEl.style.display = '-webkit-box';
      btn.textContent = 'See more';
    } else {
      fullEl.style.display = 'block';
      shortEl.style.display = 'none';
      btn.textContent = 'See less';
    }
  });

  // Helper: show toast (client-side)
  function showToast(message, type){
    if (!window.bootstrap) return alert(message);
    const wrap = document.querySelector('.cr-toast-wrap');
    if (!wrap) return;

    const html = `
      <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>`;
    wrap.insertAdjacentHTML('beforeend', html);
    const t = wrap.lastElementChild;
    new bootstrap.Toast(t, { delay: 4500 }).show();
    t.addEventListener('hidden.bs.toast', () => t.remove());
  }

  // Video modal embed + validate
  const videoModal = document.getElementById('videoModal');
  if (videoModal) {
    videoModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const url = (button && button.getAttribute('data-video')) ? button.getAttribute('data-video').trim() : '';
      const title = (button && button.getAttribute('data-title')) ? button.getAttribute('data-title') : 'Video';
      document.getElementById('videoTitle').textContent = title;

      if (!url) {
        showToast('Video link is not available for this resource.', 'warning');
        // prevent showing modal content
        document.getElementById('videoFrame').src = '';
        return;
      }

      let embed = url;
      try {
        if (url.includes('youtube.com/watch')) {
          const u = new URL(url);
          const v = u.searchParams.get('v');
          if (v) embed = 'https://www.youtube.com/embed/' + v;
        }
        if (url.includes('youtu.be/')) {
          const id = url.split('youtu.be/')[1].split('?')[0];
          embed = 'https://www.youtube.com/embed/' + id;
        }
      } catch (e) {
        showToast('Invalid video link format.', 'danger');
      }

      document.getElementById('videoFrame').src = embed;
    });

    videoModal.addEventListener('hidden.bs.modal', function () {
      document.getElementById('videoFrame').src = '';
    });
  }
</script>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
