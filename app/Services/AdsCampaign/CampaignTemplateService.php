<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignCreative;
use App\Models\AdsCampaign\AdsCampaignTargeting;
use App\Models\AdsCampaign\AdsServiceRequest;
use App\Models\Event;

/**
 * Pre-built campaign templates optimized per event type.
 *
 * Each template provides:
 * - Suggested targeting (demographics, interests, placements)
 * - Ad copy templates with dynamic placeholders
 * - Recommended budget allocation across platforms
 * - CTA and creative guidance
 * - A/B test suggestions
 */
class CampaignTemplateService
{
    /**
     * Available event type templates.
     */
    public const TEMPLATES = [
        'concert' => 'Concert / Live Music',
        'festival' => 'Festival / Multi-Day',
        'theater' => 'Theater / Performing Arts',
        'sports' => 'Sports Event',
        'comedy' => 'Comedy / Stand-Up',
        'conference' => 'Conference / Business',
        'nightlife' => 'Nightlife / Club Event',
        'family' => 'Family / Kids Event',
        'exhibition' => 'Exhibition / Art',
        'default' => 'General Event',
    ];

    /**
     * Apply a template to a campaign based on event type.
     */
    public function applyTemplate(AdsCampaign $campaign, string $templateKey, ?Event $event = null): AdsCampaign
    {
        $template = $this->getTemplate($templateKey);

        // Apply campaign-level settings
        $campaign->update([
            'objective' => $template['objective'],
            'ab_testing_enabled' => true,
            'ab_test_variable' => $template['ab_test_variable'],
            'ab_test_split_percentage' => 50,
            'auto_optimize' => true,
            'optimization_rules' => $template['optimization_rules'],
            'retargeting_config' => $template['retargeting_config'],
        ]);

        // Create targeting
        $this->createTargeting($campaign, $template, $event);

        // Create creative templates
        $this->createCreativeTemplates($campaign, $template, $event);

        return $campaign->fresh();
    }

    /**
     * Get a template configuration.
     */
    public function getTemplate(string $key): array
    {
        return match ($key) {
            'concert' => $this->concertTemplate(),
            'festival' => $this->festivalTemplate(),
            'theater' => $this->theaterTemplate(),
            'sports' => $this->sportsTemplate(),
            'comedy' => $this->comedyTemplate(),
            'conference' => $this->conferenceTemplate(),
            'nightlife' => $this->nightlifeTemplate(),
            'family' => $this->familyTemplate(),
            'exhibition' => $this->exhibitionTemplate(),
            default => $this->defaultTemplate(),
        };
    }

    /**
     * Suggest the best template for an event based on its properties.
     */
    public function suggestTemplate(Event $event): string
    {
        $title = strtolower($event->getTranslation('title', 'en') ?? $event->getTranslation('title', 'ro') ?? '');
        $description = strtolower($event->getTranslation('description', 'en') ?? $event->getTranslation('description', 'ro') ?? '');
        $combined = $title . ' ' . $description;

        $scores = [
            'concert' => $this->keywordScore($combined, ['concert', 'live music', 'gig', 'band', 'singer', 'tour', 'acoustic', 'recital']),
            'festival' => $this->keywordScore($combined, ['festival', 'fest', 'multi-day', 'camping', 'lineup', 'stages']),
            'theater' => $this->keywordScore($combined, ['theater', 'theatre', 'play', 'musical', 'opera', 'ballet', 'drama', 'performing arts']),
            'sports' => $this->keywordScore($combined, ['match', 'game', 'tournament', 'championship', 'league', 'sport', 'football', 'basketball', 'tennis', 'marathon', 'race']),
            'comedy' => $this->keywordScore($combined, ['comedy', 'stand-up', 'standup', 'comedian', 'improv', 'roast', 'humor']),
            'conference' => $this->keywordScore($combined, ['conference', 'summit', 'seminar', 'workshop', 'webinar', 'business', 'networking', 'tech', 'keynote']),
            'nightlife' => $this->keywordScore($combined, ['club', 'nightclub', 'dj', 'party', 'rave', 'electronic', 'techno', 'house music', 'afterparty']),
            'family' => $this->keywordScore($combined, ['family', 'kids', 'children', 'circus', 'puppet', 'animation', 'educational', 'zoo']),
            'exhibition' => $this->keywordScore($combined, ['exhibition', 'exhibit', 'gallery', 'art', 'museum', 'installation', 'photography', 'sculpture']),
        ];

        $best = array_keys($scores, max($scores))[0];

        return max($scores) > 0 ? $best : 'default';
    }

    /**
     * Get recommended budget split across platforms for a template.
     */
    public function getRecommendedBudgetSplit(string $templateKey): array
    {
        $template = $this->getTemplate($templateKey);
        return $template['budget_split'];
    }

    protected function concertTemplate(): array
    {
        return [
            'name' => 'Concert / Live Music',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.35, 'instagram' => 0.45, 'google' => 0.20],
            'optimization_rules' => [
                'max_cpc' => 2.50,
                'min_ctr' => 0.8,
                'min_roas' => 2.0,
                'max_frequency' => 4,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 14,
                'audiences' => ['event_page_visitors', 'video_viewers_75', 'past_ticket_buyers'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 18,
                'age_max' => 45,
                'interests' => [
                    ['name' => 'Live music', 'category' => 'entertainment'],
                    ['name' => 'Concerts', 'category' => 'entertainment'],
                    ['name' => 'Music festivals', 'category' => 'entertainment'],
                    ['name' => 'Nightlife', 'category' => 'lifestyle'],
                ],
                'placements' => ['feed', 'stories', 'reels', 'explore', 'youtube_instream'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'video',
                    'variant' => 'A',
                    'headline_template' => '🎵 {artist_name} LIVE in {city}!',
                    'primary_text_template' => "Don't miss {artist_name} performing live at {venue}! {event_date}.\n\n🎫 Tickets starting from {min_price}\n⏰ Limited availability - grab yours now!",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Use 15-30s performance clip or artist highlight reel. Vertical 9:16 for Stories/Reels, 1:1 for Feed.',
                ],
                [
                    'type' => 'image',
                    'variant' => 'B',
                    'headline_template' => '{artist_name} | {event_date} | {venue}',
                    'primary_text_template' => "🔥 {artist_name} is coming to {city}!\n\nJoin thousands of fans for an unforgettable night of live music.\n\n📍 {venue}\n📅 {event_date}\n🎫 Tickets from {min_price}",
                    'cta' => 'BUY_TICKETS',
                    'guidance' => 'Use high-energy artist photo or event poster. Minimal text overlay (<20%). Bold colors.',
                ],
                [
                    'type' => 'carousel',
                    'variant' => 'A',
                    'headline_template' => 'See {artist_name} Live',
                    'primary_text_template' => "Your next unforgettable concert experience awaits 🎶\n\nSwipe to see what's in store →",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Slide 1: Artist photo. Slide 2: Venue atmosphere. Slide 3: Ticket tiers/prices. Slide 4: Social proof (past event photos).',
                ],
            ],
        ];
    }

    protected function festivalTemplate(): array
    {
        return [
            'name' => 'Festival / Multi-Day',
            'objective' => 'conversions',
            'ab_test_variable' => 'audience',
            'budget_split' => ['facebook' => 0.30, 'instagram' => 0.50, 'google' => 0.20],
            'optimization_rules' => [
                'max_cpc' => 3.00,
                'min_ctr' => 0.6,
                'min_roas' => 1.8,
                'max_frequency' => 5,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 30,
                'audiences' => ['event_page_visitors', 'video_viewers_50', 'past_attendees', 'lineup_page_visitors'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 18,
                'age_max' => 40,
                'interests' => [
                    ['name' => 'Music festivals', 'category' => 'entertainment'],
                    ['name' => 'Outdoor events', 'category' => 'lifestyle'],
                    ['name' => 'Travel', 'category' => 'lifestyle'],
                    ['name' => 'Camping', 'category' => 'lifestyle'],
                    ['name' => 'Live music', 'category' => 'entertainment'],
                ],
                'placements' => ['feed', 'stories', 'reels', 'explore', 'youtube_instream', 'youtube_discovery'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'video',
                    'variant' => 'A',
                    'headline_template' => '🎪 {event_name} {year} - The Experience Awaits!',
                    'primary_text_template' => "The ultimate {days}-day festival experience is back!\n\n🎵 {headliners}\n📍 {venue}, {city}\n📅 {event_dates}\n\n🎫 Early bird tickets available now!\nGroups of 4+ get 15% off 👯",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Aftermovie or highlight reel from previous edition. 30-60s. High energy. Show crowd, stages, atmosphere.',
                ],
                [
                    'type' => 'carousel',
                    'variant' => 'B',
                    'headline_template' => '{event_name} {year} Lineup',
                    'primary_text_template' => "Your festival checklist:\n✅ Amazing lineup\n✅ {days} days of music\n✅ Unforgettable memories\n\n❌ Tickets — get yours before they sell out!",
                    'cta' => 'BUY_TICKETS',
                    'guidance' => 'Each slide = one headliner/stage. Final slide = ticket CTA with pricing tiers.',
                ],
            ],
        ];
    }

    protected function theaterTemplate(): array
    {
        return [
            'name' => 'Theater / Performing Arts',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.45, 'instagram' => 0.30, 'google' => 0.25],
            'optimization_rules' => [
                'max_cpc' => 3.50,
                'min_ctr' => 0.5,
                'min_roas' => 1.5,
                'max_frequency' => 6,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 21,
                'audiences' => ['event_page_visitors', 'past_theater_goers'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 25,
                'age_max' => 65,
                'interests' => [
                    ['name' => 'Theater', 'category' => 'entertainment'],
                    ['name' => 'Performing arts', 'category' => 'entertainment'],
                    ['name' => 'Cultural events', 'category' => 'lifestyle'],
                    ['name' => 'Literature', 'category' => 'education'],
                ],
                'placements' => ['feed', 'stories', 'right_column', 'search', 'display'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'image',
                    'variant' => 'A',
                    'headline_template' => '🎭 {show_name} — Now Showing at {venue}',
                    'primary_text_template' => "Experience the magic of live theater.\n\n\"{review_quote}\" — {reviewer}\n\n📍 {venue}, {city}\n📅 {event_dates}\n🎫 Seats from {min_price}",
                    'cta' => 'BOOK_NOW',
                    'guidance' => 'Dramatic production photo or poster. Elegant, refined aesthetic. Include star ratings if available.',
                ],
                [
                    'type' => 'video',
                    'variant' => 'B',
                    'headline_template' => 'A Night at the Theater: {show_name}',
                    'primary_text_template' => "Step into a world of extraordinary storytelling.\n\n{show_name} brings {description_hook} to the stage at {venue}.\n\nBook your seats today — select performances are selling fast!",
                    'cta' => 'BOOK_NOW',
                    'guidance' => 'Trailer-style 15-30s clip. Moody lighting, dramatic moments. Subtle background score.',
                ],
            ],
        ];
    }

    protected function sportsTemplate(): array
    {
        return [
            'name' => 'Sports Event',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.35, 'instagram' => 0.30, 'google' => 0.35],
            'optimization_rules' => [
                'max_cpc' => 2.00,
                'min_ctr' => 1.0,
                'min_roas' => 2.5,
                'max_frequency' => 4,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 7,
                'audiences' => ['event_page_visitors', 'past_attendees', 'team_fans'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 16,
                'age_max' => 55,
                'interests' => [
                    ['name' => 'Sports', 'category' => 'sports'],
                    ['name' => 'Live sports events', 'category' => 'entertainment'],
                    ['name' => 'Sports fans', 'category' => 'lifestyle'],
                ],
                'placements' => ['feed', 'stories', 'reels', 'search', 'youtube_instream'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'video',
                    'variant' => 'A',
                    'headline_template' => '⚽ {team_a} vs {team_b} — Be There LIVE!',
                    'primary_text_template' => "Game day is coming! 🏟️\n\n{team_a} takes on {team_b} at {venue}.\n📅 {event_date}\n\n🎫 Tickets from {min_price}\nBring the energy. Be the 12th player! 💪",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Action highlights, crowd atmosphere, stadium shots. 15s max. High energy music.',
                ],
                [
                    'type' => 'image',
                    'variant' => 'B',
                    'headline_template' => 'Match Day: {team_a} vs {team_b}',
                    'primary_text_template' => "Don't watch from the couch — be part of the action!\n\n🏟️ {venue}\n📅 {event_date}\n🎫 From {min_price}\n\nGrab your tickets now before they're gone!",
                    'cta' => 'BUY_TICKETS',
                    'guidance' => 'Team matchup graphic or stadium photo. Bold team colors. Clear date and venue.',
                ],
            ],
        ];
    }

    protected function comedyTemplate(): array
    {
        return [
            'name' => 'Comedy / Stand-Up',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.40, 'instagram' => 0.40, 'google' => 0.20],
            'optimization_rules' => [
                'max_cpc' => 2.50,
                'min_ctr' => 0.7,
                'min_roas' => 2.0,
                'max_frequency' => 5,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 14,
                'audiences' => ['event_page_visitors', 'video_viewers_50', 'comedy_fans'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 20,
                'age_max' => 50,
                'interests' => [
                    ['name' => 'Comedy', 'category' => 'entertainment'],
                    ['name' => 'Stand-up comedy', 'category' => 'entertainment'],
                    ['name' => 'Netflix comedy specials', 'category' => 'entertainment'],
                    ['name' => 'Humor', 'category' => 'lifestyle'],
                ],
                'placements' => ['feed', 'stories', 'reels', 'explore'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'video',
                    'variant' => 'A',
                    'headline_template' => '😂 {comedian_name} LIVE — Prepare to Laugh!',
                    'primary_text_template' => "Your abs will hurt from laughing! 😂\n\n{comedian_name} brings their new show to {city}.\n\n📍 {venue}\n📅 {event_date}\n🎫 Tickets from {min_price}\n\nTag someone who needs a good laugh! 👇",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Short comedy clip (15-30s) — best joke or crowd reaction. Subtitles mandatory. Vertical for Stories/Reels.',
                ],
                [
                    'type' => 'image',
                    'variant' => 'B',
                    'headline_template' => '{comedian_name} Live in {city}',
                    'primary_text_template' => "Need a laugh? {comedian_name} has you covered.\n\nOne night only at {venue}. Limited seats.\n\n🎫 Book now before it sells out!",
                    'cta' => 'BUY_TICKETS',
                    'guidance' => 'Comedian photo with fun expression. Bright, warm colors. Quote from a famous bit optional.',
                ],
            ],
        ];
    }

    protected function conferenceTemplate(): array
    {
        return [
            'name' => 'Conference / Business',
            'objective' => 'leads',
            'ab_test_variable' => 'audience',
            'budget_split' => ['facebook' => 0.25, 'instagram' => 0.20, 'google' => 0.55],
            'optimization_rules' => [
                'max_cpc' => 5.00,
                'min_ctr' => 0.4,
                'min_roas' => 1.2,
                'max_frequency' => 7,
                'optimize_for' => 'registrations',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 30,
                'audiences' => ['registration_page_visitors', 'past_attendees', 'email_list'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 25,
                'age_max' => 55,
                'interests' => [
                    ['name' => 'Business', 'category' => 'business'],
                    ['name' => 'Professional development', 'category' => 'education'],
                    ['name' => 'Entrepreneurship', 'category' => 'business'],
                    ['name' => 'Technology', 'category' => 'technology'],
                ],
                'placements' => ['feed', 'search', 'display', 'youtube_instream'],
                'devices' => ['desktop', 'mobile'],
            ],
            'creatives' => [
                [
                    'type' => 'image',
                    'variant' => 'A',
                    'headline_template' => '{event_name} {year} — {tagline}',
                    'primary_text_template' => "Join {speaker_count}+ industry leaders at {event_name}.\n\n🎤 Keynotes from {speakers}\n📍 {venue}, {city}\n📅 {event_dates}\n\n🎫 Early bird pricing ends {early_bird_deadline}\n\nDon't miss the event {industry} professionals are talking about.",
                    'cta' => 'REGISTER_NOW',
                    'guidance' => 'Professional event branding. Speaker headshots. Clean, corporate design. Include company logos if sponsoring.',
                ],
                [
                    'type' => 'carousel',
                    'variant' => 'B',
                    'headline_template' => 'Why Attend {event_name}?',
                    'primary_text_template' => "4 reasons to attend {event_name} this year:\n\n👉 Swipe to discover what makes this the must-attend event of {year}.",
                    'cta' => 'LEARN_MORE',
                    'guidance' => 'Slide 1: Speaker lineup. Slide 2: Networking opportunities. Slide 3: Key topics/sessions. Slide 4: Testimonials from past attendees.',
                ],
            ],
        ];
    }

    protected function nightlifeTemplate(): array
    {
        return [
            'name' => 'Nightlife / Club Event',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.25, 'instagram' => 0.65, 'google' => 0.10],
            'optimization_rules' => [
                'max_cpc' => 1.50,
                'min_ctr' => 1.0,
                'min_roas' => 2.0,
                'max_frequency' => 3,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 7,
                'audiences' => ['event_page_visitors', 'video_viewers_25', 'past_club_goers'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 18,
                'age_max' => 35,
                'interests' => [
                    ['name' => 'Nightlife', 'category' => 'lifestyle'],
                    ['name' => 'Electronic music', 'category' => 'entertainment'],
                    ['name' => 'DJs', 'category' => 'entertainment'],
                    ['name' => 'Clubbing', 'category' => 'lifestyle'],
                ],
                'placements' => ['stories', 'reels', 'explore', 'feed'],
                'devices' => ['mobile'],
            ],
            'creatives' => [
                [
                    'type' => 'video',
                    'variant' => 'A',
                    'headline_template' => '🔊 {dj_name} at {venue} — {event_date}',
                    'primary_text_template' => "The night you've been waiting for 🌙\n\n{dj_name} takes over {venue}!\n📅 {event_date}\n\n🎫 Pre-sale tickets available\n⚡ Limited capacity — don't miss out!",
                    'cta' => 'GET_TICKETS',
                    'guidance' => '10-15s vertical video. Dark/neon aesthetic. DJ booth, crowd energy, light shows. Bass-heavy music.',
                ],
                [
                    'type' => 'image',
                    'variant' => 'B',
                    'headline_template' => '{dj_name} | {venue} | {event_date}',
                    'primary_text_template' => "This {day_of_week}. {venue}. {dj_name}.\n\nYou know you want to be there. 🔥\n\n🎫 Link in bio",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Event flyer style. Dark background, neon accents. DJ photo or abstract visuals. Minimal text.',
                ],
            ],
        ];
    }

    protected function familyTemplate(): array
    {
        return [
            'name' => 'Family / Kids Event',
            'objective' => 'conversions',
            'ab_test_variable' => 'audience',
            'budget_split' => ['facebook' => 0.55, 'instagram' => 0.30, 'google' => 0.15],
            'optimization_rules' => [
                'max_cpc' => 2.00,
                'min_ctr' => 0.6,
                'min_roas' => 1.5,
                'max_frequency' => 5,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 21,
                'audiences' => ['event_page_visitors', 'parent_audiences'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 25,
                'age_max' => 50,
                'interests' => [
                    ['name' => 'Family activities', 'category' => 'lifestyle'],
                    ['name' => 'Parenting', 'category' => 'lifestyle'],
                    ['name' => 'Kids entertainment', 'category' => 'entertainment'],
                    ['name' => 'Educational activities', 'category' => 'education'],
                ],
                'placements' => ['feed', 'stories', 'search', 'display'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'image',
                    'variant' => 'A',
                    'headline_template' => '👨‍👩‍👧‍👦 {event_name} — Fun for the Whole Family!',
                    'primary_text_template' => "Looking for a fun family outing?\n\n{event_name} is the perfect weekend adventure for kids of all ages!\n\n📍 {venue}, {city}\n📅 {event_dates}\n👶 Kids under 6: FREE\n🎫 Family tickets from {family_price}\n\nCreate memories that last a lifetime ❤️",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Bright, colorful imagery. Happy families/kids. Warm, inviting atmosphere. Safe & fun vibes.',
                ],
                [
                    'type' => 'carousel',
                    'variant' => 'B',
                    'headline_template' => 'What to Expect at {event_name}',
                    'primary_text_template' => "A day of wonder, learning, and fun! ✨\n\nSwipe to see what awaits your family →",
                    'cta' => 'BOOK_NOW',
                    'guidance' => 'Slide 1: Main attraction. Slide 2: Activities for kids. Slide 3: Food & refreshments. Slide 4: Ticket pricing with family deals.',
                ],
            ],
        ];
    }

    protected function exhibitionTemplate(): array
    {
        return [
            'name' => 'Exhibition / Art',
            'objective' => 'traffic',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.35, 'instagram' => 0.50, 'google' => 0.15],
            'optimization_rules' => [
                'max_cpc' => 2.50,
                'min_ctr' => 0.5,
                'min_roas' => 1.2,
                'max_frequency' => 6,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 30,
                'audiences' => ['event_page_visitors', 'art_enthusiasts'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 22,
                'age_max' => 60,
                'interests' => [
                    ['name' => 'Art', 'category' => 'entertainment'],
                    ['name' => 'Museums', 'category' => 'lifestyle'],
                    ['name' => 'Photography', 'category' => 'entertainment'],
                    ['name' => 'Culture', 'category' => 'lifestyle'],
                    ['name' => 'Design', 'category' => 'business'],
                ],
                'placements' => ['feed', 'stories', 'explore', 'display'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'carousel',
                    'variant' => 'A',
                    'headline_template' => '🎨 {exhibition_name} at {venue}',
                    'primary_text_template' => "Immerse yourself in {exhibition_name}.\n\n{artist_count}+ works by {artists}.\nA visual journey you won't forget.\n\n📍 {venue}\n📅 {event_dates}\n🎫 Tickets from {min_price}",
                    'cta' => 'BOOK_NOW',
                    'guidance' => 'Each slide = one artwork or installation. High-quality photos. Let the art speak. Minimal text overlay.',
                ],
                [
                    'type' => 'video',
                    'variant' => 'B',
                    'headline_template' => 'Step Into {exhibition_name}',
                    'primary_text_template' => "Art that moves you.\n\nVisit {exhibition_name} at {venue} before {end_date}.\n\nBook your time slot today.",
                    'cta' => 'BOOK_NOW',
                    'guidance' => 'Walk-through video of exhibition space. Slow, contemplative pace. Ambient music. 20-30s.',
                ],
            ],
        ];
    }

    protected function defaultTemplate(): array
    {
        return [
            'name' => 'General Event',
            'objective' => 'conversions',
            'ab_test_variable' => 'creative',
            'budget_split' => ['facebook' => 0.35, 'instagram' => 0.40, 'google' => 0.25],
            'optimization_rules' => [
                'max_cpc' => 3.00,
                'min_ctr' => 0.5,
                'min_roas' => 1.5,
                'max_frequency' => 5,
                'optimize_for' => 'ticket_sales',
            ],
            'retargeting_config' => [
                'enabled' => true,
                'window_days' => 14,
                'audiences' => ['event_page_visitors', 'past_attendees'],
                'exclude_purchasers' => true,
            ],
            'targeting' => [
                'age_min' => 18,
                'age_max' => 55,
                'interests' => [
                    ['name' => 'Events', 'category' => 'entertainment'],
                    ['name' => 'Things to do', 'category' => 'lifestyle'],
                    ['name' => 'Weekend activities', 'category' => 'lifestyle'],
                ],
                'placements' => ['feed', 'stories', 'reels', 'search', 'display'],
                'devices' => ['mobile', 'desktop'],
            ],
            'creatives' => [
                [
                    'type' => 'image',
                    'variant' => 'A',
                    'headline_template' => '{event_name} — {event_date} in {city}',
                    'primary_text_template' => "Something special is happening in {city}!\n\n{event_name} at {venue}\n📅 {event_date}\n🎫 Tickets from {min_price}\n\nDon't miss out — get your tickets now!",
                    'cta' => 'GET_TICKETS',
                    'guidance' => 'Event poster or key visual. Clear event name and date. Eye-catching colors.',
                ],
                [
                    'type' => 'video',
                    'variant' => 'B',
                    'headline_template' => 'Experience {event_name}',
                    'primary_text_template' => "This is your moment. Don't just watch — be there.\n\n{event_name}\n📍 {venue}\n📅 {event_date}\n\n🎫 Limited tickets available",
                    'cta' => 'BUY_TICKETS',
                    'guidance' => 'Highlight reel or teaser. 15-30s. Upbeat pacing. Show the experience attendees can expect.',
                ],
            ],
        ];
    }

    protected function createTargeting(AdsCampaign $campaign, array $template, ?Event $event): void
    {
        $targeting = $template['targeting'];
        $locations = [];

        // If event has a venue with location, add it as the primary targeting location
        if ($event && $event->venue_name) {
            $locations[] = [
                'type' => 'city',
                'name' => $event->venue_city ?? $event->venue_name,
                'radius' => $this->getRadiusForTemplate($template['name']),
            ];
        }

        AdsCampaignTargeting::create([
            'campaign_id' => $campaign->id,
            'name' => 'Template: ' . $template['name'],
            'age_min' => $targeting['age_min'],
            'age_max' => $targeting['age_max'],
            'genders' => ['all'],
            'locations' => $locations,
            'interests' => $targeting['interests'],
            'placements' => $targeting['placements'],
            'devices' => $targeting['devices'],
            'is_active' => true,
        ]);
    }

    protected function createCreativeTemplates(AdsCampaign $campaign, array $template, ?Event $event): void
    {
        foreach ($template['creatives'] as $creative) {
            $headline = $creative['headline_template'];
            $primaryText = $creative['primary_text_template'];

            // Replace known placeholders from the event
            if ($event) {
                $replacements = $this->buildEventReplacements($event);
                $headline = str_replace(array_keys($replacements), array_values($replacements), $headline);
                $primaryText = str_replace(array_keys($replacements), array_values($replacements), $primaryText);
            }

            AdsCampaignCreative::create([
                'campaign_id' => $campaign->id,
                'type' => $creative['type'],
                'headline' => $headline,
                'primary_text' => $primaryText,
                'cta' => $creative['cta'],
                'variant_label' => $creative['variant'] ?? null,
                'status' => 'draft',
                'platform_overrides' => [
                    'guidance' => $creative['guidance'],
                ],
            ]);
        }
    }

    protected function buildEventReplacements(Event $event): array
    {
        $title = $event->getTranslation('title', 'en') ?? $event->getTranslation('title', 'ro') ?? '';

        return [
            '{event_name}' => $title,
            '{event_date}' => $event->start_date?->format('M d, Y') ?? '',
            '{event_dates}' => $event->start_date ? ($event->start_date->format('M d') . ($event->end_date ? ' - ' . $event->end_date->format('M d, Y') : ', ' . $event->start_date->format('Y'))) : '',
            '{venue}' => $event->venue_name ?? '',
            '{city}' => $event->venue_city ?? $event->venue_name ?? '',
            '{year}' => $event->start_date?->format('Y') ?? date('Y'),
            '{min_price}' => '',
            '{artist_name}' => $title,
            '{show_name}' => $title,
            '{comedian_name}' => $title,
            '{dj_name}' => $title,
            '{exhibition_name}' => $title,
        ];
    }

    protected function getRadiusForTemplate(string $templateName): int
    {
        return match ($templateName) {
            'Festival / Multi-Day' => 100,
            'Conference / Business' => 80,
            'Concert / Live Music', 'Comedy / Stand-Up' => 50,
            'Nightlife / Club Event' => 25,
            default => 40,
        };
    }

    protected function keywordScore(string $text, array $keywords): int
    {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $score++;
            }
        }
        return $score;
    }
}
