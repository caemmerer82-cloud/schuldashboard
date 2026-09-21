<?php
require_once __DIR__.'/includes/auth.php';
logout();header('Location: '.base_path('login.php'));exit;
