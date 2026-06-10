<?php
require_once __DIR__ . '/config/config.php';

// Only perform logout on verified POST requests to prevent CSRF via GET links.
// A GET request (e.g. someone linking to /logout.php) just redirects to login.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/login.php');
}

logout();
