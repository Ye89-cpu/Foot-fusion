<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$pageTitle = "FoodFusion | Privacy Policy";
require_once __DIR__ . '/../config/conn.php';

$PROJECT_BASE = defined('PROJECT_BASE') ? PROJECT_BASE : '/Food-Fusion-web';

require_once __DIR__ . '/../header&footer/header.php';
?>

<div class="container py-5">
  <div class="bg-white rounded-4 p-4 p-lg-5 shadow-sm">
    <h1 class="fw-bold mb-3">Privacy Policy</h1>
    <p class="text-muted">
      This Privacy Policy explains how FoodFusion collects, uses, and protects personal information when you use our website.
    </p>

    <hr>

    <h5 class="fw-bold mt-4">1. Information We Collect</h5>
    <ul class="text-muted">
      <li><strong>Account data:</strong> name, username, email, password (stored as a secure hash), role (user/chef/admin if applicable).</li>
      <li><strong>Optional profile data:</strong> phone number, gender, chef name, country (if you register as a chef).</li>
      <li><strong>User content:</strong> posts, comments, recipes, images you upload (Community Cookbook / Recipe features).</li>
      <li><strong>Technical/security data:</strong> basic device/browser information, logs for protecting the service from abuse.</li>
    </ul>

    <h5 class="fw-bold mt-4">2. How We Use Your Information</h5>
    <ul class="text-muted">
      <li>To create and manage user accounts.</li>
      <li>To authenticate users and provide secure login.</li>
      <li>To allow posting, commenting, reactions, and other community features.</li>
      <li>To improve performance, fix bugs, and enhance user experience.</li>
      <li>To respond to inquiries (e.g., Contact Us submissions).</li>
    </ul>

    <h5 class="fw-bold mt-4">3. Security</h5>
    <p class="text-muted">
      We apply practical security measures such as password hashing and basic rate limiting.
      For example, the system may lock an account after multiple failed login attempts (e.g., 3 failed attempts for a short period).
    </p>

    <h5 class="fw-bold mt-4">4. Cookies and Consent</h5>
    <p class="text-muted">
      FoodFusion uses cookies or browser storage to remember your cookie consent preference and support essential functionality
      (such as login sessions). You can review details in our Cookie Policy.
    </p>
    <a class="btn btn-outline-dark btn-sm" href="<?= h($PROJECT_BASE) ?>/public/cookies.php">View Cookie Policy</a>

    <h5 class="fw-bold mt-4">5. Data Retention</h5>
    <p class="text-muted">
      We keep your data as long as needed to provide the service and for legitimate purposes such as security and compliance.
      You may request assistance if you want to remove your account data (subject to project rules).
    </p>

    <h5 class="fw-bold mt-4">6. Contact</h5>
    <p class="text-muted mb-0">
      If you have questions about privacy, contact us at
      <a href="mailto:foodfusion@email.com">foodfusion@email.com</a>.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/../header&footer/footer.php'; ?>
