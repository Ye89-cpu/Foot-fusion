<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$pageTitle = "FoodFusion | Cookie Policy";
require_once __DIR__ . '/../config/conn.php';

$PROJECT_BASE = defined('PROJECT_BASE') ? PROJECT_BASE : '/Food-Fusion-web';

require_once __DIR__ . '/../header&footer/header.php';
?>

<div class="container py-5">
  <div class="bg-white rounded-4 p-4 p-lg-5 shadow-sm">
    <h1 class="fw-bold mb-3">Cookie Policy</h1>
    <p class="text-muted mb-4">
      This Cookie Policy explains how FoodFusion uses cookies and similar technologies (such as browser storage)
      to provide essential functionality and improve your experience.
    </p>

    <hr>

    <h5 class="fw-bold mt-4">1. What We Use</h5>
    <ul class="text-muted">
      <li>
        <strong>Consent choice storage:</strong>
        We store your cookie preference (Accept/Reject) so we don’t keep showing the consent banner.
        This may be stored as:
        <ul class="mt-2">
          <li><code>localStorage</code> key: <code>ff_cookie_consent</code></li>
          <li>A browser cookie: <code>ff_cookie_consent</code> (expires after ~180 days)</li>
        </ul>
      </li>
      <li>
        <strong>Essential cookies (if needed):</strong>
        Login/session cookies may be required for authentication and security.
      </li>
    </ul>

    <h5 class="fw-bold mt-4">2. Why We Use Them</h5>
    <ul class="text-muted">
      <li>To remember your consent choice (Accept/Reject).</li>
      <li>To support core site features like login sessions and security.</li>
      <li>To improve usability and performance (if enabled).</li>
    </ul>

    <h5 class="fw-bold mt-4">3. Your Choices</h5>
    <p class="text-muted">
      You can accept or reject non-essential cookies using the cookie banner when you first visit the website.
      If you want to change your decision later, you can:
    </p>
    <ul class="text-muted">
      <li>Use the <strong>Manage cookies</strong> link in the cookie banner (if available), or</li>
      <li>Clear site data (cookies/local storage) in your browser settings to reset the banner.</li>
    </ul>

    <h5 class="fw-bold mt-4">4. More Information</h5>
    <p class="text-muted mb-0">
      Please review our <a href="<?= h($PROJECT_BASE) ?>/public/privacy.php">Privacy Policy</a> for details about how we handle personal data.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
