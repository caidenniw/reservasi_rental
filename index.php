<?php
require_once __DIR__ . '/includes/functions.php';

redirect(isLoggedIn() ? BASE_URL . '/pages/beranda.php' : BASE_URL . '/auth/login.php');
