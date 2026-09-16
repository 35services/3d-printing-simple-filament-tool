<?php

function slack_load_config() {
    $path = 'slack.json';
    if (!file_exists($path)) {
        return [];
    }
    $file_size = filesize($path);
    if ($file_size === 0) {
        return [];
    }
    $file_handle = fopen($path, 'r');
    $data = fread($file_handle, $file_size);
    fclose($file_handle);
    return json_decode($data, true) ?: [];
}

function slack_solid_color_png($hex, $size = 50) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return null;
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $row = chr(0) . str_repeat(chr($r) . chr($g) . chr($b), $size);
    $raw = str_repeat($row, $size);

    $chunk = function ($type, $data) {
        $body = $type . $data;
        return pack('N', strlen($data)) . $body . pack('N', crc32($body));
    };

    $ihdr = pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0);
    $idat = gzcompress($raw, 9);

    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IDAT', $idat) . $chunk('IEND', '');
}

function slack_post_text($bot_token, $channel, $text, $api_base = 'https://slack.com/api') {
    $ch = curl_init($api_base . '/chat.postMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $bot_token,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'channel' => $channel,
            'text' => $text,
        ]),
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log('Slack notify failed: ' . curl_error($ch));
        return false;
    }
    $result = json_decode($response, true);
    if (!($result['ok'] ?? false)) {
        error_log('Slack notify error: ' . ($result['error'] ?? 'unknown'));
        return false;
    }
    return true;
}

function slack_upload_swatches($bot_token, $channel, $changes, $text, $api_base = 'https://slack.com/api') {
    $file_ids = [];

    foreach ($changes as $change) {
        $png = slack_solid_color_png($change['hex']);
        if ($png === null) {
            continue;
        }

        $ch = curl_init($api_base . '/files.getUploadURLExternal');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bot_token],
            CURLOPT_POSTFIELDS => [
                'filename' => 'swatch-' . ltrim($change['hex'], '#') . '.png',
                'length' => (string) strlen($png),
            ],
        ]);
        $url_response = json_decode(curl_exec($ch), true);
        if (!($url_response['ok'] ?? false)) {
            error_log('Slack file URL request failed: ' . ($url_response['error'] ?? 'unknown'));
            return false;
        }

        $tmp_path = tempnam(sys_get_temp_dir(), 'swatch');
        file_put_contents($tmp_path, $png);

        $ch = curl_init($url_response['upload_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($tmp_path, 'image/png', basename($tmp_path) . '.png'),
            ],
        ]);
        curl_exec($ch);
        $upload_failed = curl_errno($ch) !== 0;
        unlink($tmp_path);

        if ($upload_failed) {
            error_log('Slack file upload failed for ' . $change['hex']);
            return false;
        }

        $file_ids[] = $url_response['file_id'];
    }

    if (empty($file_ids)) {
        return false;
    }

    $ch = curl_init($api_base . '/files.completeUploadExternal');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $bot_token,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'files' => array_map(function ($id) { return ['id' => $id]; }, $file_ids),
            'channel_id' => $channel,
            'initial_comment' => $text,
        ]),
    ]);
    $complete_response = json_decode(curl_exec($ch), true);
    if (!($complete_response['ok'] ?? false)) {
        error_log('Slack file complete failed: ' . ($complete_response['error'] ?? 'unknown'));
        return false;
    }

    return true;
}

function notify_slack_color_changes($changes) {
    $slack_config = slack_load_config();
    if (empty($slack_config['bot_token']) || empty($slack_config['channel']) || empty($changes)) {
        return;
    }

    $lines = array_map(function ($change) {
        $color = $change['color_name'] !== ''
            ? "{$change['color_name']} (`{$change['hex']}`)"
            : "`{$change['hex']}`";
        $location = $change['extruder_count'] > 1
            ? "{$change['printer']} – Extruder {$change['extruder']}"
            : $change['printer'];
        return "• *{$location}*: {$color}";
    }, $changes);

    $text = "🎨 Filament changed:\n" . implode("\n", $lines);
    $api_base = $slack_config['api_base'] ?? 'https://slack.com/api';

    $sent = slack_upload_swatches($slack_config['bot_token'], $slack_config['channel'], $changes, $text, $api_base);
    if (!$sent) {
        slack_post_text($slack_config['bot_token'], $slack_config['channel'], $text, $api_base);
    }
}
