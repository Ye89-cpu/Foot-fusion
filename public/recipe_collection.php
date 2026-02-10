<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$pageTitle = "FoodFusion | Recipe Collection";

require_once __DIR__ . '/../config/conn.php';          // $pdo
require_once __DIR__ . '/../header&footer/header.php'; // prints <html>..<main>

/* ---------- helpers ---------- */
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Normalize stored image paths:
 * - "/uploads/recipes/a.png" => OK
 * - "/Food-Fusion-web/uploads/recipes/a.png" => remove project prefix
 * - "uploads/recipes/a.png" => ensure leading slash
 */
function normalize_asset(string $path): string {
  $path = trim($path);
  if ($path === '') return '';

  if (preg_match('~^https?://~i', $path)) return $path;

  // remove any project folder prefix like /Food-Fusion-web
  $path = preg_replace('~^/Food-Fusion-web~', '', $path);

  if ($path[0] !== '/') $path = '/' . $path;
  return $path;
}

/* ---------- read filters (GET) ---------- */
$q          = trim((string)($_GET['q'] ?? ''));
$cuisine    = trim((string)($_GET['cuisine'] ?? 'All'));
$dietary    = trim((string)($_GET['dietary'] ?? 'All'));
$difficulty = trim((string)($_GET['difficulty'] ?? 'All'));
$chef_id = (int)($_GET['chef_id'] ?? 0);


$limit  = 60;
$offset = 0;

/* ---------- dropdown options ---------- */
$optCuisine = $pdo->query("SELECT DISTINCT cuisine FROM recipe_collection ORDER BY cuisine")->fetchAll(PDO::FETCH_COLUMN);
$optDietary = $pdo->query("SELECT DISTINCT dietary FROM recipe_collection ORDER BY dietary")->fetchAll(PDO::FETCH_COLUMN);
$optDiff    = $pdo->query("SELECT DISTINCT difficulty FROM recipe_collection ORDER BY difficulty")->fetchAll(PDO::FETCH_COLUMN);

/* ---------- build query ---------- */
$where  = [];
$params = [];
if ($chef_id > 0) {
  $where[] = "rc.chef_id = :chef_id";
  $params[':chef_id'] = $chef_id;
}


if ($q !== '') {
  $where[] = "(rc.name LIKE :q OR rc.ingredients LIKE :q OR rc.description LIKE :q OR rc.recipe_type LIKE :q)";
  $params[':q'] = "%{$q}%";
}
if ($cuisine !== '' && $cuisine !== 'All') {
  $where[] = "rc.cuisine = :cuisine";
  $params[':cuisine'] = $cuisine;
}
if ($dietary !== '' && $dietary !== 'All') {
  $where[] = "rc.dietary = :dietary";
  $params[':dietary'] = $dietary;
}
if ($difficulty !== '' && $difficulty !== 'All') {
  $where[] = "rc.difficulty = :difficulty";
  $params[':difficulty'] = $difficulty;
}

/* ---------- IMPORTANT: base path for your project ---------- */
$PROJECT_BASE = '/Food-Fusion-web'; // adjust if your folder changes

$sql = "
  SELECT
    rc.recipe_id,
    rc.name,
    rc.dietary,
    rc.cuisine,
    rc.difficulty,
    rc.recipe_type,
    rc.ingredients,
    rc.image,
    rc.description,
    c.chef_name
  FROM recipe_collection rc
  LEFT JOIN chef c ON c.chef_id = rc.chef_id
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY rc.created_at DESC, rc.recipe_id DESC LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$countShown = count($recipes);
?>

<style>
  /* Full width shell */
  .rc-shell{
    width: 100%;
    padding-left: 18px;
    padding-right: 18px;
  }

  .rc-search-card{
    border: 1px solid #e7e7e7;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 6px 22px rgba(0,0,0,.06);
  }

  /* Card */
  .rc-card{
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
    height: 100%;
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .rc-card:hover{
    transform: translateY(-2px);
    box-shadow: 0 14px 34px rgba(0,0,0,.12);
  }

  .rc-thumb{
    width: 100%;
    height: auto;
    object-fit: cover;
    background: #eee;
  }

  .rc-body{
    padding: 14px 14px 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .rc-title{
    font-weight: 800;
    margin: 0;
    line-height: 1.2;
    font-size: 1rem;
  }

  .rc-meta{
    font-size: .86rem;
    color: #6c757d;
  }

  .rc-badges .badge{
    font-size: .72rem;
    border-radius: 999px;
    padding: .38rem .58rem;
  }

  .rc-kv{
    font-size: .86rem;
    color: #333;
  }
  .rc-kv b{
    color: #111;
  }

    .rc-ingredients{
    font-size: .85rem;
    color: #4b5563;
    line-height: 1.35;

    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;

    -webkit-line-clamp: 3; /* current main support */
    line-clamp: 3;         /* standard (lint-friendly / future) */
    }

        .rc-desc .rc-desc-short{
        display: -webkit-box;
        -webkit-box-orient: vertical;
        overflow: hidden;

        -webkit-line-clamp: 3; /* short preview */
        line-clamp: 3;         /* standard */
        }
  .rc-desc .rc-desc-full{
    display: none; /* toggled by JS */
    white-space: pre-line;
  }

  .rc-actions{
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 2px;
  }
  .rc-actions .btn{
    border-radius: 999px;
  }

  .rc-empty{
    border: 1px dashed #ccc;
    border-radius: 18px;
    padding: 28px;
    background: #fafafa;
  }
</style>

<div class="container-fluid py-4 rc-shell">

  <!-- SEARCH / FILTER -->
  <section class="rc-search-card p-4 p-lg-4">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3">
      <div>
        <h3 class="mb-1">Recipe Collection</h3>
        <p class="text-secondary mb-0">
          Browse recipes by cuisine, dietary preference, and difficulty. Use “See more” to expand descriptions inline.
        </p>
      </div>
      <div class="text-secondary">
        Showing <strong><?= (int)$countShown ?></strong> recipe(s).
      </div>
    </div>

    <form class="mt-3" method="get" action="">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label">Search</label>
          <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="e.g., soup, noodles, chicken">
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Cuisine</label>
          <select class="form-select" name="cuisine">
            <option value="All" <?= ($cuisine === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optCuisine as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($cuisine === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Dietary</label>
          <select class="form-select" name="dietary">
            <option value="All" <?= ($dietary === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optDietary as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($dietary === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Difficulty</label>
          <select class="form-select" name="difficulty">
            <option value="All" <?= ($difficulty === 'All' ? 'selected' : '') ?>>All</option>
            <?php foreach ($optDiff as $v): ?>
              <option value="<?= h((string)$v) ?>" <?= ($difficulty === (string)$v ? 'selected' : '') ?>>
                <?= h((string)$v) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-lg-2 d-flex gap-2">
          <button class="btn btn-dark w-100" type="submit">Filter</button>
          <a class="btn btn-outline-secondary w-100" href="recipe_collection.php">Reset</a>
        </div>
      </div>
    </form>
  </section>

  <!-- RESULTS -->
  <section class="mt-4">
    <?php if ($countShown === 0): ?>
      <div class="rc-empty">
        <h5 class="mb-1">No recipes found</h5>
        <div class="text-secondary">Try changing filters or search keywords.</div>
      </div>
    <?php else: ?>

      <div class="row g-4">
        <?php foreach ($recipes as $r): ?>
          <?php
            $imgRaw = (string)($r['image'] ?? '');
            $img = normalize_asset($imgRaw);
            if ($img === '') $img = '/uploads/recipes/placeholder.png';

            // If it's a full URL, keep it. If local path, prefix with project base.
            $imgUrl = preg_match('~^https?://~i', $img) ? $img : ($PROJECT_BASE . $img);

            $chefName = trim((string)($r['chef_name'] ?? ''));
            if ($chefName === '') $chefName = '—';

            $desc = trim((string)($r['description'] ?? ''));
            if ($desc === '') $desc = '—';

            $ingredients = trim((string)($r['ingredients'] ?? ''));
            if ($ingredients === '') $ingredients = '—';

            $rid = (int)$r['recipe_id'];
          ?>

          <!-- 3 per row on lg, 2 per row on md, 1 per row on mobile -->
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card rc-card">

              <img class="rc-thumb"
                   src="<?= h($imgUrl) ?>"
                   alt="<?= h((string)$r['name']) ?>"
                   onerror="this.onerror=null;this.src='<?= h($PROJECT_BASE . '/uploads/recipes/placeholder.png') ?>';">

              <div class="rc-body">
                <div class="rc-badges d-flex flex-wrap gap-2">
                  <span class="badge text-bg-secondary"><?= h((string)$r['cuisine']) ?></span>
                  <span class="badge text-bg-light border"><?= h((string)$r['dietary']) ?></span>
                  <span class="badge text-bg-dark"><?= h((string)$r['difficulty']) ?></span>
                  <span class="badge text-bg-warning text-dark"><?= h((string)($r['recipe_type'] ?? '—')) ?></span>
                </div>

                <div>
                  <p class="rc-title mb-1"><?= h((string)$r['name']) ?></p>
                  <div class="rc-meta">By <?= h($chefName) ?></div>
                </div>

                <div class="rc-kv">
                  <b>Ingredients:</b>
                  <div class="rc-ingredients"><?= h($ingredients) ?></div>
                </div>

                <!-- Description with See more toggle -->
                <div class="rc-desc" id="desc-<?= $rid ?>">
                  <b>Description:</b>
                  <div class="rc-desc-short"><?= h($desc) ?></div>
                  <div class="rc-desc-full"><?= h($desc) ?></div>

                  <?php if ($desc !== '—' && mb_strlen($desc) > 180): ?>
                    <button type="button"
                            class="btn btn-link p-0 mt-1 rc-toggle"
                            data-target="<?= $rid ?>">
                      See more
                    </button>
                  <?php endif; ?>
                </div>

                <div class="rc-actions">
                  <a class="btn btn-sm btn-outline-dark px-3"
                     href="<?= h($PROJECT_BASE . "/public/recipe_details.php?id=" . $rid) ?>">
                    View details
                  </a>
                </div>

              </div>
            </div>
          </div>

        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </section>

</div>

<script>
  // Toggle description (See more / See less) per card
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.rc-toggle');
    if (!btn) return;

    const rid = btn.getAttribute('data-target');
    const wrap = document.getElementById('desc-' + rid);
    if (!wrap) return;

    const shortEl = wrap.querySelector('.rc-desc-short');
    const fullEl  = wrap.querySelector('.rc-desc-full');

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
</script>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
