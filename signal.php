<?php

function signal_load_config() {
    $path = 'signal.json';
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

function signal_log($message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    $written = @file_put_contents(__DIR__ . '/signal.log', $line, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log('signal.log: ' . $message);
    }
}

function notify_signal_color_changes($changes, $group_id_override = null) {
    $signal_config = signal_load_config();
    $group_id = $group_id_override ?? ($signal_config['group_id'] ?? null);

    if (empty($signal_config['account']) || empty($group_id) || empty($changes)) {
        signal_log('Skipped: ' . (empty($signal_config['account']) ? 'no account configured' : (empty($group_id) ? 'no group_id resolved' : 'no color changes to report')));
        return;
    }

    $lines = array_map(function ($change) {
        $color = $change['color_name'] !== ''
            ? "{$change['color_name']} ({$change['hex']})"
            : $change['hex'];
        $location = $change['extruder_count'] > 1
            ? "{$change['printer']} – Extruder {$change['extruder']}"
            : $change['printer'];
        return "- {$location}: {$color}";
    }, $changes);

    $text = "Filament changed:\n" . implode("\n", $lines);
    $cli_path = $signal_config['cli_path'] ?? 'signal-cli';

    $command = $cli_path
        . ' -a ' . escapeshellarg($signal_config['account'])
        . ' send -g ' . escapeshellarg($group_id)
        . ' -m ' . escapeshellarg($text) . ' 2>&1';

    signal_log('Running: ' . $command);
    exec($command, $output, $exit_code);
    signal_log('Exit code: ' . $exit_code . (empty($output) ? '' : ' | Output: ' . implode(' | ', $output)));

    if ($exit_code !== 0) {
        error_log('signal-cli notify failed (exit ' . $exit_code . '): ' . implode(' | ', $output));
    }
}
