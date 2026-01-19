<?php

$file = 'app/Http/Controllers/Api/TenantClient/AccountController.php';
$content = file_get_contents($file);

// Add validation for new fields
$content = str_replace(
    "            'phone' => 'nullable|string|max:50',",
    "            'phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',",
    $content
);

// Add saving of new fields
$search = "if (isset(\$validated['phone'])) {
            \$customer->phone = \$validated['phone'];
        }";

$replace = "if (isset(\$validated['phone'])) {
            \$customer->phone = \$validated['phone'];
        }

        if (isset(\$validated['city'])) {
            \$customer->city = \$validated['city'];
        }

        if (isset(\$validated['country'])) {
            \$customer->country = \$validated['country'];
        }

        if (isset(\$validated['date_of_birth'])) {
            \$customer->date_of_birth = \$validated['date_of_birth'];
            // Calculate age from date of birth
            \$dob = new \DateTime(\$validated['date_of_birth']);
            \$now = new \DateTime();
            \$customer->age = \$dob->diff(\$now)->y;
        }";

$content = str_replace($search, $replace, $content);

// Update profile response to include new fields in both getProfile and updateProfile
$content = str_replace(
    "                'phone' => \$customer->phone,
            ],
        ]);
    }",
    "                'phone' => \$customer->phone,
                'city' => \$customer->city,
                'country' => \$customer->country,
                'date_of_birth' => \$customer->date_of_birth?->format('Y-m-d'),
                'age' => \$customer->age,
            ],
        ]);
    }",
    $content
);

file_put_contents($file, $content);
echo "✓ Updated AccountController with new profile fields\n";
