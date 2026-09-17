<?php

namespace App\Services\Partners;

use App\Models\MarketplacePartner;
use App\Models\PartnerArticle;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Stores articles a media partner sends (single PUT or batch).
 */
class PartnerArticleWriter
{
    public const MAX_BATCH = 100;

    private const TITLE_LENGTH = 255;
    private const EXCERPT_LENGTH = 500;

    /**
     * Create or update the article; a hidden article stays hidden.
     *
     * @return array{status: string, article: PartnerArticle, unknown_artist_ids: array<int>}
     *
     * @throws PartnerApiException
     */
    public function upsert(MarketplacePartner $partner, string $sourceId, array $input): array
    {
        if (isset($input['source']) && (!is_scalar($input['source']) || (string) $input['source'] !== $partner->slug)) {
            throw new PartnerApiException('source_mismatch', "source must be {$partner->slug}.");
        }

        if (isset($input['source_id']) && (!is_scalar($input['source_id']) || (string) $input['source_id'] !== $sourceId)) {
            throw new PartnerApiException('source_id_mismatch', 'source_id in the body does not match the URL.');
        }

        $validator = Validator::make($input, [
            'artist_ids' => ['required', 'array', 'min:1', 'max:50'],
            'artist_ids.*' => ['integer'],
            'type' => ['nullable', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:2000'],
            'excerpt' => ['nullable', 'string', 'max:10000'],
            'url' => ['required', 'string', 'url', 'max:2048'],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'author' => ['nullable', 'string', 'max:191'],
            'published_at' => ['required', 'date'],
            'updated_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw new PartnerApiException(
                'invalid_article',
                $validator->errors()->first(),
                422,
                $validator->errors()->toArray()
            );
        }

        if (!$this->isHttpUrl($input['url']) || !$partner->allowsArticleUrl($input['url'])) {
            throw new PartnerApiException('invalid_url', 'url must be a link to the partner site.');
        }

        if (!empty($input['image_url']) && !$this->isHttpUrl($input['image_url'])) {
            throw new PartnerApiException('invalid_image_url', 'image_url must be an http(s) link.');
        }

        $title = $this->plainText($input['title'], self::TITLE_LENGTH);
        if ($title === '') {
            throw new PartnerApiException('invalid_article', 'title is empty once HTML is removed.');
        }

        $requestedIds = array_values(array_unique(array_map('intval', $input['artist_ids'])));
        // Only artists this marketplace shows to the partner (as on GET /artists).
        $knownIds = PartnerArtistScope::query($partner->marketplaceClient)
            ->whereIn('artists.id', $requestedIds)
            ->pluck('artists.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!$knownIds) {
            throw new PartnerApiException('artist_not_found', 'None of the artist_ids exist.', 404);
        }

        // Dates without an offset are the marketplace's local time.
        $timezone = MarketplaceTz::tz($partner->marketplaceClient);

        $attributes = [
            'marketplace_client_id' => $partner->marketplace_client_id,
            'type' => $input['type'] ?? null,
            'title' => $title,
            'excerpt' => isset($input['excerpt']) ? ($this->plainText($input['excerpt'], self::EXCERPT_LENGTH) ?: null) : null,
            'url' => $input['url'],
            'image_url' => $input['image_url'] ?? null,
            'author' => isset($input['author']) ? ($this->plainText($input['author'], 191) ?: null) : null,
            'published_at' => Carbon::parse($input['published_at'], $timezone)->utc(),
            'source_updated_at' => !empty($input['updated_at']) ? Carbon::parse($input['updated_at'], $timezone)->utc() : null,
        ];

        try {
            [$article, $created] = $this->save($partner, $sourceId, $attributes, $knownIds);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created the same article; this one updates it.
            [$article, $created] = $this->save($partner, $sourceId, $attributes, $knownIds);
        }

        return [
            'status' => $created ? 'created' : 'updated',
            'article' => $article,
            'unknown_artist_ids' => array_values(array_diff($requestedIds, $knownIds)),
        ];
    }

    public function delete(MarketplacePartner $partner, string $sourceId): bool
    {
        return (bool) PartnerArticle::where('marketplace_partner_id', $partner->id)
            ->where('source_id', $sourceId)
            ->delete();
    }

    private function save(MarketplacePartner $partner, string $sourceId, array $attributes, array $artistIds): array
    {
        return DB::transaction(function () use ($partner, $sourceId, $attributes, $artistIds) {
            $article = PartnerArticle::firstOrNew([
                'marketplace_partner_id' => $partner->id,
                'source_id' => $sourceId,
            ]);
            $created = !$article->exists;

            $article->fill($attributes)->save();
            $article->artists()->sync($artistIds);

            return [$article, $created];
        });
    }

    private function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Plain text: tags removed, entities decoded (WordPress sends &#8211; and the like),
     * whitespace collapsed, cut to $length characters.
     */
    private function plainText(mixed $value, int $length): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }
}
