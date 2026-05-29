<?php // pages/logout.php
session_destroy();
header('Location: ' . BASE_URL . '/index.php?page=login');
exit;
