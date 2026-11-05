<?php

return [
    /*
     * Marketplace-uri la care macheta orizontală (hero_image_url / cover_image) încărcată din contul
     * organizatorului trebuie să fie pe raportul coverului de eveniment Facebook (1920×1005, ~1,91:1).
     * Implicit doar Ambilet (id 1); bilete.online și viaqui folosesc același endpoint fără regula asta.
     */
    'facebook_cover_marketplaces' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('FACEBOOK_COVER_RATIO_MARKETPLACES', '1'))
    ))),

    'facebook_cover' => [
        'width' => 1920,
        'height' => 1005,
        'min_width' => 1200,
        'min_height' => 628,
        // Abatere acceptată față de raportul 1920/1005 (2%).
        'ratio_tolerance' => 0.02,
    ],
];
