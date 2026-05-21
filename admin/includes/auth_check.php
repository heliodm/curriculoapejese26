<?php
if (!defined('SITE_ROOT')) {
    require_once dirname(__DIR__, 2) . '/config/config.php';
}
requireAuth(BASE_URL . '/admin/index.php');
