<?php
require __DIR__ . '/inc.php';
error_page((int)($_GET['c'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404)));
