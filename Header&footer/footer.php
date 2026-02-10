<?php
// header&footer/footer.php
$PROJECT_BASE = '/Food-Fusion-web';
?>
</main>

<footer class="bg-dark text-light pt-5 pb-4 mt-5">
  <div class="container">

    <div class="row g-4">

      <div class="col-12 col-md-6 col-lg-3">
        <h5 class="fw-bold mb-3">FOODFUSION</h5>
        <p class="text-secondary mb-0">
          Thanks for stopping by FoodFusion.<br>
          Cook boldly, share generously, and come<br>
          back hungry for more.
        </p>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <h6 class="fw-bold text-uppercase mb-3">Support</h6>
        <ul class="list-unstyled text-secondary mb-0">
          <li class="mb-2"><a href="#" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Payment</a></li>
          <li class="mb-2"><a href="#" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Delivery</a></li>
          <li class="mb-2"><a href="#" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Exchange &amp; Return</a></li>
          <li><a href="#" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Customer support</a></li>
        </ul>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <h6 class="fw-bold text-uppercase mb-3">Legal</h6>
        <ul class="list-unstyled text-secondary mb-0">
          <li class="mb-2"><a href="<?= $PROJECT_BASE ?>/public/privacy.php" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Privacy Policy</a></li>
          <li><a href="<?= $PROJECT_BASE ?>/public/cookies.php" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">Cookie Policy</a></li>
        </ul>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <h6 class="fw-bold text-uppercase mb-3">Contact</h6>
        <div class="text-secondary">
          <div class="mb-2">A-101, Times Mall, Yangon</div>
          <div class="mb-2"><a href="mailto:foodfusion@email.com" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">foodfusion@email.com</a></div>
          <div class="mb-2"><a href="tel:+959987730238" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">+95 9987730238</a></div>
          <div><a href="tel:+959762007221" class="link-light link-underline-opacity-0 link-underline-opacity-100-hover">+95 9762007221</a></div>
        </div>
      </div>

    </div>

    <hr class="border-secondary my-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <p class="mb-0 text-secondary">
        Copyright © 2026 — <span class="text-light fw-semibold">FoodFusion</span>
      </p>

      <!-- Social Media Platforms Integration -->
      <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-end">
        <a class="btn btn-outline-light btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-2"
           href="https://www.facebook.com/" target="_blank" rel="noopener">
          <i class="bi bi-facebook"></i><span>Facebook</span>
        </a>
        <a class="btn btn-outline-light btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-2"
           href="https://www.instagram.com/" target="_blank" rel="noopener">
          <i class="bi bi-instagram"></i><span>Instagram</span>
        </a>
        <a class="btn btn-outline-light btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-2"
           href="https://www.tiktok.com/" target="_blank" rel="noopener">
          <i class="bi bi-tiktok"></i><span>TikTok</span>
        </a>
        <a class="btn btn-outline-light btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-2"
           href="https://x.com/" target="_blank" rel="noopener">
          <i class="bi bi-twitter-x"></i><span>X</span>
        </a>
        <a class="btn btn-outline-light btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-2"
           href="https://www.youtube.com/" target="_blank" rel="noopener">
          <i class="bi bi-youtube"></i><span>YouTube</span>
        </a>
      </div>
    </div>

  </div>
</footer>

<!-- COOKIE CONSENT (Prominent) -->
<div id="cookieBanner" class="position-fixed bottom-0 start-0 end-0 p-3" style="display:none; z-index:1080;">
  <div class="container">
    <div class="bg-dark text-white rounded-3 p-3 d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between border">
      <div>
        <strong>We use cookies</strong>
        <span class="text-white-50">to improve your experience.</span>
        <a href="<?= $PROJECT_BASE ?>/public/privacy.php" class="text-warning text-decoration-underline ms-1">Privacy Policy</a>
        <a href="<?= $PROJECT_BASE ?>/public/cookies.php" class="text-warning text-decoration-underline ms-2">Cookie Policy</a>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-light btn-sm" id="cookieReject" type="button">Reject</button>
        <button class="btn btn-warning btn-sm" id="cookieAccept" type="button">Accept</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const key = 'ff_cookie_consent';
  const banner = document.getElementById('cookieBanner');
  const accept = document.getElementById('cookieAccept');
  const reject = document.getElementById('cookieReject');

  if (!banner || !accept || !reject) return;

  const v = localStorage.getItem(key);
  if (!v) banner.style.display = 'block';

  accept.addEventListener('click', () => {
    localStorage.setItem(key, 'accepted');
    banner.style.display = 'none';
  });

  reject.addEventListener('click', () => {
    localStorage.setItem(key, 'rejected');
    banner.style.display = 'none';
  });
})();
</script>

</body>
</html>
