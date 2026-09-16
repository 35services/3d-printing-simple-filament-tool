<?php
// A minimal fake Slack API for local testing, so slack.php's real HTTP-calling
// code can be exercised without ever hitting the real Slack workspace.
//
// Usage:
//   php -S localhost:8999 mock-slack-server.php
//   # then set "api_base": "http://localhost:8999" in your test slack.json
//
// Every call is appended to mock-slack-server.log (path, headers, body) so
// you can verify exactly what slack.php sent, without any of it reaching Slack.

$log_path = __DIR__ . '/mock-slack-server.log';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// php://input is empty for multipart/form-data (PHP consumes it into $_POST/$_FILES),
// so log whichever actually has content.
$body = file_get_contents('php://input');
if ($body === '' && (!empty($_POST) || !empty($_FILES))) {
    $body = json_encode(['post' => $_POST, 'files' => array_map(function ($f) {
        return ['name' => $f['name'], 'size' => $f['size']];
    }, $_FILES)]);
}

file_put_contents($log_path, "=== {$_SERVER['REQUEST_METHOD']} {$path} ===\n{$body}\n\n", FILE_APPEND);

header('Content-Type: application/json');

if ($path === '/files.getUploadURLExternal') {
    $host = $_SERVER['HTTP_HOST'];
    echo json_encode([
        'ok' => true,
        'upload_url' => "http://{$host}/mock-upload",
        'file_id' => 'F_MOCK_' . substr(md5(uniqid()), 0, 9),
    ]);
    exit;
}

if ($path === '/mock-upload') {
    echo 'ok';
    exit;
}

if ($path === '/files.completeUploadExternal' || $path === '/chat.postMessage') {
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'unknown_mock_endpoint']);
