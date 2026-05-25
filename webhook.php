<?php
/**
 * GitHub Webhook Handler
 *
 * Configure no GitHub: Settings → Webhooks → Add webhook
 *   Payload URL:  https://seu-site.com/webhook.php
 *   Content type: application/json
 *   Secret:       (mesmo valor de "Segredo do Webhook" em Configurações › GitHub)
 *   Events:       Just the push event
 */

if (!file_exists(__DIR__ . '/config/config.php')) {
    http_response_code(503);
    exit('Sistema não instalado.');
}

// Supress HTML errors — this endpoint returns JSON only
ini_set('display_errors', '0');
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$payload   = file_get_contents('php://input');
$event     = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$secret    = getSetting('github_webhook_secret', '');
$branch    = getSetting('github_branch', 'main');

// Verify HMAC signature when a secret is configured
if ($secret !== '') {
    $sig      = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $sig)) {
        http_response_code(401);
        exit(json_encode(['error' => 'Unauthorized — invalid signature']));
    }
}

// Ignore non-push events
if ($event !== 'push') {
    header('Content-Type: application/json');
    http_response_code(200);
    exit(json_encode(['status' => 'ignored', 'event' => $event]));
}

$data = json_decode($payload, true);
$ref  = $data['ref'] ?? '';

// Only deploy on the configured branch
if ($ref !== "refs/heads/{$branch}") {
    header('Content-Type: application/json');
    http_response_code(200);
    exit(json_encode(['status' => 'ignored', 'ref' => $ref, 'monitored' => "refs/heads/{$branch}"]));
}

// Respond to GitHub immediately so the webhook doesn't time out,
// then run the (potentially slow) update in the background.
header('Content-Type: application/json');
http_response_code(200);
echo json_encode(['status' => 'accepted', 'ref' => $ref]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level()) ob_end_flush();
    flush();
}

ignore_user_abort(true);
set_time_limit(300);

performUpdate();
