<?php

$filePath = __DIR__ . '/app/Filament/Tenant/Pages/Settings.php';
$content = file_get_contents($filePath);

$searchString = "                                                'smtp' => 'SMTP (Generic)',
                                                'gmail' => 'Gmail',";

$replaceWith = "                                                'smtp' => 'SMTP (Generic)',
                                                'brevo' => 'Brevo',
                                                'gmail' => 'Gmail',";

$newContent = str_replace($searchString, $replaceWith, $content);

if ($newContent === $content) {
    echo "ERROR: Could not find the insertion point!\n";
    exit(1);
}

file_put_contents($filePath, $newContent);
echo "✅ Successfully added Brevo to mail provider options\n";
