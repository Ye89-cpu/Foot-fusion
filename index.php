<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$pageTitle = "FoodFusion | Home";

require_once __DIR__ . '/config/conn.php';
require_once __DIR__ . '/header&footer/header.php';

$PROJECT_BASE = '/Food-Fusion-web';

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
function normalize_path(string $path): string {
  $path = trim($path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  $path = preg_replace('~^/Food-Fusion-web~', '', $path);
  if ($path !== '' && $path[0] !== '/') $path = '/' . $path;
  return $path;
}

/* Featured recipes */
$featured = [];
try {
  $stmt = $pdo->query("
    SELECT recipe_id, name, cuisine, dietary, difficulty, image, description, created_at
    FROM recipe_collection
    ORDER BY created_at DESC, recipe_id DESC
    LIMIT 6
  ");
  $featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* Upcoming events */
$events = [];
try {
  $stmt = $pdo->query("
    SELECT event_id, title, event_datetime, location, description, image
    FROM cooking_events
    WHERE is_active = 1 AND event_datetime >= NOW()
    ORDER BY event_datetime ASC
    LIMIT 10
  ");
  $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>

<style>
  .section-title{ font-weight: 800; }
  .glass-card{
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    backdrop-filter: blur(6px);
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,.18);
  }
  .mini-card{
    border-radius: 18px;
    overflow: hidden;
    background: rgba(255,255,255,.10);
    border: 1px solid rgba(255,255,255,.14);
    box-shadow: 0 10px 28px rgba(0,0,0,.18);
    height: 100%;
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .mini-card:hover{ transform: translateY(-3px); box-shadow: 0 14px 40px rgba(0,0,0,.26); }
  .mini-thumb{ width:100%; height:140px; object-fit:cover; background: rgba(255,255,255,.12); display:block; }
  .mini-meta{ font-size:.85rem; color: rgba(255,255,255,.75); }
.mini-desc{
  font-size:.9rem;
  color: rgba(255,255,255,.75);

  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;

  /* ✅ standard property (for compatibility) */
  line-clamp: 2;

  overflow: hidden;
  min-height: 2.6em;
}

  .event-img{ width:100%; height:auto; object-fit:cover; border-radius:18px; background: rgba(255,255,255,.12); }
</style>

<!-- HERO -->
<section class="py-5">
  <div class="container">
    <div class="row align-items-center g-4">

      <div class="col-12 col-lg-5 text-center text-lg-start">
        <img src="<?= h($PROJECT_BASE) ?>/photo/logo.png" alt="FoodFusion logo" class="img-fluid" style="max-width:320px;">
      </div>

      <div class="col-12 col-lg-7">
        <h1 class="display-5 fw-bold mb-3 text-white">FoodFusion</h1>
        <p class="fs-4 mb-4 text-white">
          FoodFusion is your destination for bold flavors, creative recipes, and culinary ideas inspired by kitchens
          around the world. We blend tradition with innovation to turn simple ingredients into unforgettable meals—made
          to be cooked, shared, and enjoyed.
        </p>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <a href="<?= h($PROJECT_BASE) ?>/public/aboutus.php" class="btn btn-dark px-4">About Us</a>
          <button class="btn btn-outline-dark px-4" type="button" data-bs-toggle="modal" data-bs-target="#joinModal">
            Sign up now
          </button>
        </div>

        <div class="mt-3 text-white-50 small">
          Privacy & Cookies: see footer links.
        </div>
      </div>

    </div>
  </div>
</section>

<!-- NEWS FEED -->
<section class="py-5">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
      <div>
        <h2 class="section-title text-white mb-1">News Feed</h2>
        <div class="text-white-50">Featured recipes and culinary trends.</div>
      </div>
      <a class="btn btn-outline-light btn-sm" href="<?= h($PROJECT_BASE) ?>/public/recipe_collection.php">
        Browse recipes
      </a>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-8">
        <div class="glass-card p-3 p-lg-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="text-white mb-0">Featured Recipes</h5>
            <span class="text-white-50 small">Auto-updated</span>
          </div>

          <div class="row g-3">
            <?php if (!$featured): ?>
              <div class="text-white-50">No recipes found yet.</div>
            <?php else: ?>
              <?php foreach ($featured as $f): ?>
                <?php
                  $img = normalize_path((string)($f['image'] ?? ''));
                  if ($img === '') $img = '/uploads/recipes/placeholder.png';
                  $imgUrl = $PROJECT_BASE . $img;
                  $desc = trim((string)($f['description'] ?? ''));
                  if ($desc === '') $desc = '—';
                  $url = $PROJECT_BASE . '/public/recipe_details.php?id=' . (int)$f['recipe_id'];
                ?>
                <div class="col-12 col-md-6 col-lg-4">
                  <a href="<?= h($url) ?>" class="text-decoration-none">
                    <div class="mini-card">
                      <img class="mini-thumb" src="<?= h($imgUrl) ?>"
                           alt="<?= h((string)$f['name']) ?>"
                           onerror="this.onerror=null;this.src='<?= h($PROJECT_BASE . '/uploads/recipes/placeholder.png') ?>';">
                      <div class="p-3">
                        <div class="text-white fw-bold mb-1"><?= h((string)$f['name']) ?></div>
                        <div class="mini-meta mb-2">
                          <?= h((string)$f['cuisine']) ?> • <?= h((string)$f['dietary']) ?> • <?= h((string)$f['difficulty']) ?>
                        </div>
                        <div class="mini-desc"><?= h($desc) ?></div>
                      </div>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="glass-card p-3 p-lg-4 h-100">
          <h5 class="text-white mb-3">Culinary Trends</h5>
          <div class="d-flex flex-column gap-3">
            <div>
              <div class="text-white fw-semibold">High-protein meal prep</div>
              <div class="text-white-50 small">Batch cooking with smart macros and storage.</div>
            </div>
            <div>
              <div class="text-white fw-semibold">Global street food at home</div>
              <div class="text-white-50 small">Fast techniques, authentic flavors, simple ingredients.</div>
            </div>
            <div>
              <div class="text-white fw-semibold">Plant-forward comfort foods</div>
              <div class="text-white-50 small">Vegan-friendly versions of classic favorites.</div>
            </div>
          </div>

          <div class="mt-4">
            <a class="btn btn-outline-light btn-sm" href="<?= h($PROJECT_BASE) ?>/public/culinary_resources.php">
              Learn more
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- EVENTS CAROUSEL -->
<section class="py-5">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
      <div>
        <h2 class="section-title text-white mb-1">Upcoming Cooking Events</h2>
        <div class="text-white-50">Workshops and community cooking sessions.</div>
      </div>
    </div>

    <?php if (!$events): ?>
      <div class="glass-card p-4 text-white-50">
        No upcoming events yet. Add records in <code>cooking_events</code>.
      </div>
    <?php else: ?>
      <div id="eventsCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($events as $i => $ev): ?>
            <?php
              $img = normalize_path((string)($ev['image'] ?? ''));
              if ($img === '') $img = '/uploads/events/placeholder.png';
              $imgUrl = $PROJECT_BASE . $img;

              $dt = (string)($ev['event_datetime'] ?? '');
              $timeText = $dt ? date('D • h:i A', strtotime($dt)) : '';
              $dateText = $dt ? date('Y-m-d', strtotime($dt)) : '';
              $loc = trim((string)($ev['location'] ?? ''));
              $desc = trim((string)($ev['description'] ?? ''));
              if ($desc === '') $desc = '—';
            ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
              <div class="row g-4 align-items-center">
                <div class="col-12 col-lg-6">
                  <img class="event-img" src="<?= h($imgUrl) ?>" alt="<?= h((string)$ev['title']) ?>"
                       onerror="this.onerror=null;this.src='<?= h($PROJECT_BASE . '/uploads/events/placeholder.png') ?>';">
                </div>

                <div class="col-12 col-lg-6">
                  <div class="glass-card p-4">
                    <h3 class="text-white mb-1"><?= h((string)$ev['title']) ?></h3>
                    <div class="text-white-50 mb-3">
                      <?= h($timeText) ?><?= $loc !== '' ? ' • ' . h($loc) : '' ?><?= $dateText !== '' ? ' • ' . h($dateText) : '' ?>
                    </div>
                    <p class="text-white-50 mb-4"><?= h($desc) ?></p>
                    <a class="btn btn-dark" href="<?= h($PROJECT_BASE) ?>/public/contact.php">Join / Ask Details</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#eventsCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#eventsCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/header&footer/footer.php'; ?>
