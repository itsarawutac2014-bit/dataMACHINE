<?php
/**
 * Application Entry Point & Router
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    redirect('views/dashboard.php');
} else {
    redirect('views/login.php');
}
