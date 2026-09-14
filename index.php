<?php
$config_path = 'config.json';
$state_path = 'state.json';
$version_path = 'VERSION';

$app_version = file_exists($version_path) ? trim(file_get_contents($version_path)) : 'unknown';

$raw_config_text = '{}';
$printer_config = [];
if (file_exists($config_path)) {
    $file_size = filesize($config_path);
    if ($file_size > 0) {
        $file_handle = fopen($config_path, 'r');
        $file_data = fread($file_handle, $file_size);
        fclose($file_handle);
        $raw_config_text = $file_data;
        $printer_config = json_decode($file_data, true) ?: [];
    }
}

$material_list = $printer_config['material_list'] ?? ['PLA'];
unset($printer_config['material_list']);

$color_list = $printer_config['color_list'] ?? [];
unset($printer_config['color_list']);

$club_label = $printer_config['club_label'] ?? 'gehört 35services e.V.';
unset($printer_config['club_label']);

$color_images_by_name = [];
foreach ($color_list as $color) {
    if (is_array($color) && !empty($color['name']) && !empty($color['image'])) {
        $color_images_by_name[$color['name']] = $color['image'];
    }
}

$current_state = [];
if (file_exists($state_path)) {
    $file_size = filesize($state_path);
    if ($file_size > 0) {
        $file_handle = fopen($state_path, 'r');
        $state_data = fread($file_handle, $file_size);
        fclose($file_handle);
        $current_state = json_decode($state_data, true) ?: [];
    }
}

require_once __DIR__ . '/slack.php';
require_once __DIR__ . '/signal.php';

$page = $_GET['page'] ?? 'home';
$config_error = '';
$config_saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['config_json'])) {
    $page = 'config';
    $submitted_config = $_POST['config_json'];
    $decoded_config = json_decode($submitted_config, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_config)) {
        $file_handle = fopen($config_path, 'w');
        if ($file_handle) {
            fwrite($file_handle, json_encode($decoded_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fclose($file_handle);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=config&saved=1");
        exit;
    }

    $config_error = 'Invalid JSON: ' . json_last_error_msg();
    $raw_config_text = $submitted_config;
}

$config_display = $raw_config_text;
if ($config_error === '') {
    $decoded_for_display = json_decode($raw_config_text, true);
    if ($decoded_for_display !== null) {
        $config_display = json_encode($decoded_for_display, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
    $raw_state = $_POST['data'] ?? null;
    $clean_state = [];

    if (is_array($raw_state)) {
        foreach ($raw_state as $printer_id => $extruder_group) {
            if (is_array($extruder_group)) {
                $clean_state[$printer_id] = [];
                foreach ($extruder_group as $index => $item) {
                    $clean_item = [
                        'hex' => '#ffffff',
                        'color_name' => '',
                        'image' => '',
                        'material' => 'PLA',
                        'owner' => '',
                        'is_club' => '0'
                    ];

                    if (isset($item['hex']) && preg_match('/^#[a-fA-F0-9]{6}$/', $item['hex'])) {
                        $clean_item['hex'] = $item['hex'];
                    }

                    if (isset($item['color_name'])) {
                        $clean_item['color_name'] = htmlspecialchars($item['color_name'], ENT_QUOTES, 'UTF-8');
                    }

                    if (isset($item['image']) && preg_match('#^https://#', $item['image'])) {
                        $clean_item['image'] = htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8');
                    }

                    if (isset($item['material']) && in_array($item['material'], $material_list)) {
                        $clean_item['material'] = $item['material'];
                    }

                    if (isset($item['owner'])) {
                        $clean_item['owner'] = htmlspecialchars($item['owner'], ENT_QUOTES, 'UTF-8');
                    }

                    if (isset($item['is_club']) && $item['is_club'] === '1') {
                        $clean_item['is_club'] = '1';
                    }

                    $clean_state[$printer_id][intval($index)] = $clean_item;
                }
            }
        }
    }

    $color_changes = [];
    foreach ($clean_state as $printer_id => $extruder_group) {
        foreach ($extruder_group as $index => $item) {
            $previous = $current_state[$printer_id][$index] ?? null;
            $is_new_default = $previous === null && $item['hex'] === '#ffffff' && $item['color_name'] === '';
            $changed = $previous === null
                ? !$is_new_default
                : ($previous['hex'] !== $item['hex'] || $previous['color_name'] !== $item['color_name']);

            if ($changed) {
                $color_changes[] = [
                    'printer' => $printer_config[$printer_id]['name'] ?? $printer_id,
                    'extruder' => $index + 1,
                    'extruder_count' => $printer_config[$printer_id]['extruder_count'] ?? 1,
                    'hex' => $item['hex'],
                    'color_name' => $item['color_name'],
                ];
            }
        }
    }

    $file_handle = fopen($state_path, 'w');
    if ($file_handle) {
        fwrite($file_handle, json_encode($clean_state, JSON_PRETTY_PRINT));
        fclose($file_handle);
    }

    notify_slack_color_changes($color_changes);
    notify_signal_color_changes($color_changes);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Management Tool</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --extruder-bg: #f9fafb;
            --border: #e2e4e9;
            --border-strong: #d4d7dd;
            --text: #1f2328;
            --text-muted: #6b7280;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 2rem 1rem 5rem;
        }

        form {
            max-width: 960px;
            margin: 0 auto;
        }

        .page-header {
            max-width: 960px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .btn-secondary {
            display: inline-block;
            font: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
            background: #fff;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-secondary:hover { background: var(--extruder-bg); }

        .config-form {
            max-width: 960px;
            margin: 0 auto;
        }

        .config-textarea {
            width: 100%;
            min-height: 60vh;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.85rem;
            line-height: 1.5;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius);
            padding: 1rem;
            background: var(--card-bg);
            color: var(--text);
            box-sizing: border-box;
            resize: vertical;
            margin-bottom: 1rem;
        }

        .config-textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .alert {
            max-width: 960px;
            margin: 0 auto 1rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .printer-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .printer-card h2 {
            margin: 0 0 1.25rem;
            font-size: 1.15rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .extruder-card {
            background: var(--extruder-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .extruder-card:last-child { margin-bottom: 0; }

        .extruder-card h3 {
            margin: 0 0 0.85rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 1rem;
            margin-bottom: 0.85rem;
        }

        .row:last-child { margin-bottom: 0; }

        .field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .field[hidden] {
            display: none;
        }

        .field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .swatch-group {
            display: flex;
            gap: 0.4rem;
        }

        input[type="text"],
        input[type="color"],
        select {
            font: inherit;
            font-size: 0.9rem;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            padding: 0.45rem 0.6rem;
            background: #fff;
            color: var(--text);
        }

        input[type="text"]:focus,
        input[type="color"]:focus,
        select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        input[type="color"] {
            padding: 0.2rem;
            width: 2.75rem;
            height: 2.35rem;
            cursor: pointer;
        }

        .color-thumb {
            width: 3.5rem;
            height: 2.35rem;
            object-fit: contain;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: #fff;
        }

        input[name$="[hex]"] { width: 6.5rem; }
        input[placeholder^="Color Name"] { width: 13rem; }
        input[placeholder="Owner text"] { width: 12rem; }

        .club-field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            justify-content: flex-end;
        }

        .club-field label {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.9rem;
            color: var(--text);
            font-weight: 400;
            text-transform: none;
            letter-spacing: normal;
            white-space: nowrap;
            padding-bottom: 0.5rem;
        }

        input[type="checkbox"] {
            width: 1.05rem;
            height: 1.05rem;
            accent-color: var(--accent);
            cursor: pointer;
        }

        button[type="submit"] {
            display: block;
            margin: 0 auto;
            font: inherit;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            background: var(--accent);
            border: none;
            border-radius: 8px;
            padding: 0.7rem 2.5rem;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.1);
        }

        button[type="submit"]:hover { background: var(--accent-hover); }

        .app-version {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        @media (max-width: 600px) {
            body { padding: 1rem 0.75rem 4rem; }
            .row { gap: 0.75rem; }
            input[placeholder^="Color Name"],
            input[placeholder="Owner text"] { width: 100%; }
        }
    </style>
</head>
<body>
    <?php if ($page === 'config'): ?>
        <div class="page-header">
            <h1>Edit Config</h1>
            <a class="btn-secondary" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Back to overview</a>
        </div>

        <?php if ($config_error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($config_error); ?></div>
        <?php elseif ($config_saved): ?>
            <div class="alert alert-success">Config saved.</div>
        <?php endif; ?>

        <form method="POST" action="?page=config" class="config-form">
            <textarea name="config_json" class="config-textarea" spellcheck="false"><?php echo htmlspecialchars($config_display); ?></textarea>
            <button type="submit">Save Config</button>
        </form>
    <?php else: ?>
    <div class="page-header">
        <h1>Filament Management</h1>
        <a class="btn-secondary" href="?page=config">Edit Config</a>
    </div>
    <form method="POST">
        <?php foreach ($printer_config as $machine_id => $machine_data): ?>
            <div class="printer-card">
                <h2><?php echo htmlspecialchars($machine_data['name']); ?></h2>
                <?php
                $extruder_limit = $machine_data['extruder_count'];
                for ($index = 0; $index < $extruder_limit; $index++):
                    $block_id = $machine_id . '_' . $index;
                    $saved_data = $current_state[$machine_id][$index] ?? [
                        'hex' => '#ffffff',
                        'color_name' => '',
                        'image' => '',
                        'material' => 'PLA',
                        'owner' => '',
                        'is_club' => '0'
                    ];
                    $saved_data['image'] = $saved_data['image'] ?? '';
                    if ($saved_data['image'] === '' && isset($color_images_by_name[$saved_data['color_name']])) {
                        $saved_data['image'] = $color_images_by_name[$saved_data['color_name']];
                    }
                ?>
                    <div class="extruder-card">
                        <?php if ($extruder_limit > 1): ?>
                            <h3>Extruder <?php echo $index + 1; ?></h3>
                        <?php endif; ?>

                        <div class="row">
                            <div class="field">
                                <label>Color</label>
                                <div class="swatch-group">
                                    <input type="color" id="pick_<?php echo $block_id; ?>" value="<?php echo htmlspecialchars($saved_data['hex']); ?>" oninput="document.getElementById('txt_<?php echo $block_id; ?>').value = this.value">
                                    <input type="text" id="txt_<?php echo $block_id; ?>" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][hex]" value="<?php echo htmlspecialchars($saved_data['hex']); ?>" oninput="document.getElementById('pick_<?php echo $block_id; ?>').value = this.value">
                                    <img id="thumb_<?php echo $block_id; ?>" class="color-thumb" src="<?php echo htmlspecialchars($saved_data['image']); ?>" alt="" <?php echo $saved_data['image'] === '' ? 'hidden' : ''; ?>>
                                </div>
                            </div>

                            <div class="field">
                                <label>Color name</label>
                                <select onchange="
                                    if (!this.value) { return; }
                                    document.getElementById('colorname_<?php echo $block_id; ?>').value = this.value;
                                    var opt = this.options[this.selectedIndex];
                                    var hex = opt.dataset.hex;
                                    if (hex) {
                                        document.getElementById('txt_<?php echo $block_id; ?>').value = hex;
                                        document.getElementById('pick_<?php echo $block_id; ?>').value = hex;
                                    }
                                    var image = opt.dataset.image || '';
                                    var thumb = document.getElementById('thumb_<?php echo $block_id; ?>');
                                    document.getElementById('colorimage_<?php echo $block_id; ?>').value = image;
                                    thumb.src = image;
                                    thumb.hidden = !image;
                                ">
                                    <option value="">-- Choose color --</option>
                                    <?php foreach ($color_list as $color):
                                        $color_name = is_array($color) ? ($color['name'] ?? '') : $color;
                                        $color_hex = is_array($color) ? ($color['hex'] ?? '') : '';
                                        $color_image = is_array($color) ? ($color['image'] ?? '') : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($color_name); ?>" data-hex="<?php echo htmlspecialchars($color_hex); ?>" data-image="<?php echo htmlspecialchars($color_image); ?>" <?php echo $saved_data['color_name'] === $color_name ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($color_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label>&nbsp;</label>
                                <input type="text" id="colorname_<?php echo $block_id; ?>" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][color_name]" value="<?php echo htmlspecialchars($saved_data['color_name']); ?>" placeholder="Color Name (or pick above)">
                                <input type="hidden" id="colorimage_<?php echo $block_id; ?>" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][image]" value="<?php echo htmlspecialchars($saved_data['image']); ?>">
                            </div>

                            <div class="field">
                                <label>Material</label>
                                <select name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][material]">
                                    <?php foreach ($material_list as $mat): ?>
                                        <option value="<?php echo htmlspecialchars($mat); ?>" <?php echo $saved_data['material'] === $mat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="club-field">
                                <label>
                                    <input type="hidden" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][is_club]" value="0">
                                    <input type="checkbox" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][is_club]" value="1" <?php echo $saved_data['is_club'] === '1' ? 'checked' : ''; ?> onchange="
                                        var ownerField = document.getElementById('ownerfield_<?php echo $block_id; ?>');
                                        var ownerInput = document.getElementById('owner_<?php echo $block_id; ?>');
                                        ownerField.hidden = this.checked;
                                        ownerInput.disabled = this.checked;
                                    ">
                                    <?php echo htmlspecialchars($club_label); ?>
                                </label>
                            </div>

                            <div class="field" id="ownerfield_<?php echo $block_id; ?>" <?php echo $saved_data['is_club'] === '1' ? 'hidden' : ''; ?>>
                                <label>Owner</label>
                                <input type="text" id="owner_<?php echo $block_id; ?>" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][owner]" value="<?php echo htmlspecialchars($saved_data['owner']); ?>" placeholder="Owner text" <?php echo $saved_data['is_club'] === '1' ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Save</button>
    </form>
    <?php endif; ?>
    <footer class="app-version">v<?php echo htmlspecialchars($app_version); ?></footer>
</body>
</html>