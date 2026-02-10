<?php
declare(strict_types=1);
$pageTitle = "FoodFusion | Register";
require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/auth_actions.php';
require_once __DIR__ . '/../Header&footer/header.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const el = document.getElementById('joinModal');
  if (el && window.bootstrap) new bootstrap.Modal(el).show();
});
</script>
<?php require_once __DIR__ . '/../Header&footer/footer.php'; ?>
