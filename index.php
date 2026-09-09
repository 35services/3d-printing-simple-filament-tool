<?php
$config_path = 'config.json';
$state_path = 'state.json';

$printer_config = [];
if (file_exists($config_path)) {
    $file_size = filesize($config_path);
    if ($file_size > 0) {
        $file_handle = fopen($config_path, 'r');
        $file_data = fread($file_handle, $file_size);
        fclose($file_handle);
        $printer_config = json_decode($file_data, true) ?: [];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_state = $_POST['data'] ?? [];
    $file_handle = fopen($state_path, 'w');
    if ($file_handle) {
        fwrite($file_handle, json_encode($new_state, JSON_PRETTY_PRINT));
        fclose($file_handle);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$material_array = [
    'PLA', 'PETG', 'PETG HT', 'Glow in the dark', 'ASA', 'ABS',
    'PC (Polycarbonate)', 'CPE', 'PVA / BVOH', 'HIPS',
    'PP (Polypropylene)', 'Flex', 'nGen', 'PA (Nylon)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Management Tool</title>
    <style>
        body { font-family: sans-serif; padding: 1rem; }
        .printer-card { border: 1px solid black; padding: 1rem; margin-bottom: 1rem; }
        .extruder-card { border-left: 1px solid gray; padding-left: 1rem; margin-bottom: 1rem; }
        .row { margin-bottom: 1rem; }
        .row input, .row select, .row label { margin-right: 1rem; vertical-align: middle; }
    </style>
</head>
<body>
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
                        'material' => 'PLA',
                        'owner' => '',
                        'is_club' => '0'
                    ];
                ?>
                    <div class="extruder-card">
                        <h3>Extruder <?php echo $index + 1; ?></h3>
                        
                        <div class="row">
                            <input type="color" id="pick_<?php echo $block_id; ?>" value="<?php echo htmlspecialchars($saved_data['hex']); ?>" oninput="document.getElementById('txt_<?php echo $block_id; ?>').value = this.value">
                            <input type="text" id="txt_<?php echo $block_id; ?>" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][hex]" value="<?php echo htmlspecialchars($saved_data['hex']); ?>" oninput="document.getElementById('pick_<?php echo $block_id; ?>').value = this.value">
                            
                            <input type="text" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][color_name]" value="<?php echo htmlspecialchars($saved_data['color_name']); ?>" placeholder="Color Name">
                            
                            <select name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][material]">
                                <?php foreach ($material_array as $mat): ?>
                                    <option value="<?php echo htmlspecialchars($mat); ?>" <?php echo $saved_data['material'] === $mat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <label>
                                <input type="hidden" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][is_club]" value="0">
                                <input type="checkbox" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][is_club]" value="1" <?php echo $saved_data['is_club'] === '1' ? 'checked' : ''; ?>>
                                gehoert 35services e.V.
                            </label>
                            <input type="text" name="data[<?php echo $machine_id; ?>][<?php echo $index; ?>][owner]" value="<?php echo htmlspecialchars($saved_data['owner']); ?>" placeholder="Owner text">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Save</button>
    </form>
</body>
</html>