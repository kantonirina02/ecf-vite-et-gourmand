<?php
require_once 'includes/security.php';
session_destroy();
header('Location: index');
exit;
?>
