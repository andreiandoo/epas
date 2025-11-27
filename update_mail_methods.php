<?php

$filePath = __DIR__ . '/app/Filament/Tenant/Pages/Settings.php';
$content = file_get_contents($filePath);

// 1. Add mail settings to mount() method
$mountSearch = "                'social_linkedin' => \$settings['social']['linkedin'] ?? '',

                // Payment Credentials";

$mountReplace = "                'social_linkedin' => \$settings['social']['linkedin'] ?? '',

                // Mail Settings
                'mail_driver' => \$settings['mail']['driver'] ?? '',
                'mail_host' => \$settings['mail']['host'] ?? '',
                'mail_port' => \$settings['mail']['port'] ?? '',
                'mail_username' => \$settings['mail']['username'] ?? '',
                'mail_password' => '', // Never load password from DB for security
                'mail_encryption' => \$settings['mail']['encryption'] ?? '',
                'mail_from_address' => \$settings['mail']['from_address'] ?? '',
                'mail_from_name' => \$settings['mail']['from_name'] ?? '',

                // Payment Credentials";

$content = str_replace($mountSearch, $mountReplace, $content);

// 2. Add mail settings to save() method
$saveSearch = "        \$settings['social'] = [
            'facebook' => \$data['social_facebook'] ?? '',
            'instagram' => \$data['social_instagram'] ?? '',
            'twitter' => \$data['social_twitter'] ?? '',
            'youtube' => \$data['social_youtube'] ?? '',
            'tiktok' => \$data['social_tiktok'] ?? '',
            'linkedin' => \$data['social_linkedin'] ?? '',
        ];

        // Update payment credentials";

$saveReplace = "        \$settings['social'] = [
            'facebook' => \$data['social_facebook'] ?? '',
            'instagram' => \$data['social_instagram'] ?? '',
            'twitter' => \$data['social_twitter'] ?? '',
            'youtube' => \$data['social_youtube'] ?? '',
            'tiktok' => \$data['social_tiktok'] ?? '',
            'linkedin' => \$data['social_linkedin'] ?? '',
        ];

        // Update mail settings
        \$mailSettings = \$settings['mail'] ?? [];
        if (!empty(\$data['mail_driver'])) {
            \$mailSettings['driver'] = \$data['mail_driver'];
        }
        if (!empty(\$data['mail_host'])) {
            \$mailSettings['host'] = \$data['mail_host'];
        }
        if (!empty(\$data['mail_port'])) {
            \$mailSettings['port'] = \$data['mail_port'];
        }
        if (!empty(\$data['mail_username'])) {
            \$mailSettings['username'] = \$data['mail_username'];
        }
        if (!empty(\$data['mail_password'])) {
            \$mailSettings['password'] = encrypt(\$data['mail_password']);
        }
        if (isset(\$data['mail_encryption'])) {
            \$mailSettings['encryption'] = \$data['mail_encryption'];
        }
        if (!empty(\$data['mail_from_address'])) {
            \$mailSettings['from_address'] = \$data['mail_from_address'];
        }
        if (!empty(\$data['mail_from_name'])) {
            \$mailSettings['from_name'] = \$data['mail_from_name'];
        }
        \$settings['mail'] = \$mailSettings;

        // Update payment credentials";

$content = str_replace($saveSearch, $saveReplace, $content);

file_put_contents($filePath, $content);
echo "✅ Successfully updated mount() and save() methods with mail settings\n";
