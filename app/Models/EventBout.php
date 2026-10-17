<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un meci din fight card-ul unei gale. Luptătorii sunt TenantArtist (rol „fighter”,
 * profilul sportiv în meta: nickname, country, record, weight_class …).
 */
class EventBout extends Model
{
    public const SECTIONS = [
        'main_event' => 'Main event',
        'co_main' => 'Co-main event',
        'main_card' => 'Main card',
        'prelims' => 'Preliminarii',
        'early_prelims' => 'Preliminarii timpurii',
    ];

    public const DISCIPLINES = [
        'mma' => 'MMA',
        'kickboxing' => 'Kickbox',
        'boxing' => 'Box',
        'muay_thai' => 'Muay Thai',
        'grappling' => 'Grappling',
    ];

    protected $fillable = [
        'tenant_id', 'event_id', 'card_section', 'sort_order',
        'fighter_a_id', 'fighter_b_id', 'fighter_a_name', 'fighter_b_name',
        'weight_class', 'discipline', 'rounds', 'round_minutes', 'is_title_fight', 'title_name',
        'status', 'winner', 'method', 'result_round', 'result_time', 'meta',
    ];

    protected $casts = [
        'is_title_fight' => 'boolean',
        'meta' => 'array',
        'rounds' => 'integer',
        'round_minutes' => 'integer',
        'result_round' => 'integer',
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fighterA(): BelongsTo
    {
        return $this->belongsTo(TenantArtist::class, 'fighter_a_id');
    }

    public function fighterB(): BelongsTo
    {
        return $this->belongsTo(TenantArtist::class, 'fighter_b_id');
    }

    /** Ordinea secțiunilor pe afiș (main event primul). */
    public static function sectionRank(string $section): int
    {
        $i = array_search($section, array_keys(self::SECTIONS), true);

        return $i === false ? 99 : $i;
    }
}
