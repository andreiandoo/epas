<?php

$file = 'app/Http/Controllers/Admin/PackageController.php';
$content = file_get_contents($file);

// Change from relative to absolute path
$content = str_replace(
    '<script src="./tixello-loader.min.js"',
    '<script src="/tixello-loader.min.js"',
    $content
);

file_put_contents($file, $content);

echo "PackageController.php updated - script path changed to absolute!\n";
