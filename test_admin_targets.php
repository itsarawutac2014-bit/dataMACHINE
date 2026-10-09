<?php
require_once __DIR__ . '/config/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

ob_start();
try {
    include 'views/admin_targets.php';
    $output = ob_get_clean();
    echo "SUCCESS! Rendered admin_targets.php: " . strlen($output) . " bytes.\n";
    if (strpos($output, 'tableDepartmentEditor') !== false) {
        echo "Found tableDepartmentEditor.\n";
    }
    if (strpos($output, 'btnAddDepartmentRow') !== false) {
        echo "Found btnAddDepartmentRow.\n";
    }
    if (strpos($output, 'masterTargetLossPct') !== false) {
        echo "Found masterTargetLossPct.\n";
    }
} catch (Throwable $t) {
    ob_end_clean();
    echo "ERROR: " . $t->getMessage() . " at " . $t->getFile() . ":" . $t->getLine() . "\n";
}
