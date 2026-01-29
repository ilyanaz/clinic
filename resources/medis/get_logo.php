<?php
// Serve logo from storage/app/uploads/logo/
header('Content-Type: image/png');
$logoPath = __DIR__ . '/../../storage/app/uploads/logo/medical_surveillance_logo.png';

if (file_exists($logoPath)) {
    readfile($logoPath);
} else {
    // Return a 1x1 transparent PNG if logo not found
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}
exit;
