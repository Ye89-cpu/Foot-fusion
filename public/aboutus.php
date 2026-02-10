<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "FoodFusion | About Us";

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../header&footer/header.php'; 

/* =========================
   FETCH ALL CHEFS
========================= */
$chefs = [];
try {
  $stmt = $pdo->query("
    SELECT chef_id, chef_name, country, created_at
    FROM chef
    ORDER BY chef_id ASC
  ");
  $chefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $chefs = [];
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>

<style>
  .ff-team-card{
    border-radius:18px;
    box-shadow:0 10px 28px rgba(0,0,0,.12);
    border:0;
    height:100%;
  }


  .ff-avatar{
    width:64px;
    height:64px;
    border-radius:16px;
    background:#f1f3f5;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    font-weight:800;
    color:#495057;
  }
/* Inline only (no external css) */
.ff-hero-img{
  width:100%;
  max-height:380px;
  object-fit:cover;
}
.ff-soft-card{
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    backdrop-filter: blur(6px);
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,.18);
}
.ff-value-card{
  height:100%;
}
.ff-team-img{
  width:100%;
  height:220px;
  object-fit:cover;
}
</style>

<main class="py-4">
    
    
    <div class="container">

      <!-- HERO -->
    <section class="rounded-4 p-4 p-lg-5 mb-4 ff-soft-card border">
      <div class="row align-items-center g-4">
        <div class="col-12 col-lg-6">
          <h1 class="display-6 fw-bold mb-3 text-white">About FoodFusion</h1>
          <p class="text-secondary fs-5 mb-4 text-white">
            FoodFusion is a home-cooking community built to make cooking feel simple, creative, and enjoyable.
            We share recipes, practical kitchen tips, and cooking events that bring people together.
          </p>

          <div class="d-flex flex-column flex-sm-row gap-2">
            <a class="btn btn-dark px-4" href="/FoodFusion/public/recipe_collection.php">Explore Recipes</a>
            <a class="btn btn-outline-dark px-4" href="/FoodFusion/public/community_cookbook.php">Community Cookbook</a>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <img class="ff-hero-img rounded-4 shadow-sm"
               src="/Food-Fusion-web/photo/about.png"
               alt="People cooking together at home">
        </div>
      </div>
    </section>

    <!-- MISSION / VISION -->
    <section class="row g-3 mb-4 ">
      <div class="col-12 col-lg-6 ">
        <div class="card border-0 shadow-sm h-100 ff-soft-card border">
          <div class="card-body p-4">
            <h2 class="h4 fw-bold mb-2 text-white">Our Mission</h2>
            <p class="text-secondary mb-0 text-white">
              To encourage home cooking by providing inspiring recipes, easy techniques, and a friendly space where
              anyone can learn and share.
            </p>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100 ff-soft-card border">
          <div class="card-body p-4">
            <h2 class="h4 fw-bold mb-2 text-white">Our Vision</h2>
            <p class="text-secondary mb-0 text-white">
              A vibrant community where food connects cultures—where every member can explore global flavours and
              confidently cook at home.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- VALUES -->
    <section class="mb-4">
      <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <h2 class="h3 fw-bold mb-0 text-white">Our Values</h2>
        <span class="text-secondary small text-white">What FoodFusion stands for</span>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm ff-value-card ff-soft-card ">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2 text-white">Creativity</h3>
              <p class="text-secondary mb-0 text-white">We encourage experimentation, substitutions, and personal twists.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm ff-value-card ff-soft-card ">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2 text-white">Accessibility</h3>
              <p class="text-secondary mb-0 text-white">Simple instructions, clear categories, and options for different diets.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm ff-value-card ff-soft-card">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2 text-white">Community</h3>
              <p class="text-secondary mb-0 text-white">Members share recipes, tips, and stories through the Community Cookbook.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm ff-value-card ff-soft-card">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2 text-white">Respect for Culture</h3>
              <p class="text-secondary mb-0 text-white ">We celebrate global cuisines and give credit to authentic traditions.</p>
            </div>
          </div>
        </div>
      </div>
    </section>




    <!-- TEAM SECTION -->
    <section class="mb-4">
      <h2 class="fw-bold mb-3">Meet Our Chefs</h2>

      <?php if (!$chefs): ?>
        <div class="alert alert-warning">
          No chefs available at the moment.
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($chefs as $c): ?>
            <?php
              $name = $c['chef_name'];
              $country = $c['country'] ?: '—';
              $initial = strtoupper(mb_substr($name, 0, 1));
            ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card ff-team-card">
                <div class="card-body p-4">

                  <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="ff-avatar"><?= h($initial) ?></div>
                    <div>
                      <h5 class="mb-0"><?= h($name) ?></h5>
                      <div class="text-secondary small"><?= h($country) ?></div>
                    </div>
                  </div>

                  <p class="text-secondary mb-3">
                    Passionate chef sharing authentic recipes and culinary traditions.
                  </p>

                  <a class="btn btn-dark btn-sm"
                     href="/Food-Fusion-web/public/recipe_collection.php?chef_id=<?= (int)$c['chef_id'] ?>">
                    View Recipes
                  </a>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

        <!-- TEAM -->
    <section class="mb-4">
      <h2 class="h3 fw-bold mb-2 text-white">Meet the Team</h2>
      <p class="text-secondary mb-3 text-black">
        FoodFusion is maintained by a small team of food lovers and developers who care about learning, sharing,
        and building a safe community.
      </p>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <img class="ff-team-img rounded-top" src="/Food-Fusion-web/photo/FounderEditor.jpg" alt="Founder / Editor">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2">Founder / Editor</h3>
              <p class="text-secondary mb-0">Curates recipes, checks quality, and keeps the community active.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <img class="ff-team-img rounded-top" src="/Food-Fusion-web/photo/CommunityManager.jpg" alt="Community Manager">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2">Community Manager</h3>
              <p class="text-secondary mb-0">Supports members, moderates submissions, and responds to feedback.</p>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <img class="ff-team-img rounded-top" src="/Food-Fusion-web/photo/WebDeveloper.jpg" alt="Web Developer">
            <div class="card-body p-4">
              <h3 class="h5 fw-bold mb-2">Web Developer</h3>
              <p class="text-secondary mb-0">Builds features like login security, recipe pages, and database integration.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="text-center mt-5 ff-soft-card p-4">
      <h3 class="fw-bold mb-2 text-white">Want to become a chef?</h3>
      <p class="text-secondary mb-3 text-white">
        Join FoodFusion and share your recipes with the world.
      </p>
      <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#joinModal">
        Sign Up Now
      </button>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
