# CSV Import/Export Examples

This folder contains example CSV files for importing data into the EventPilot system.

## Taxonomies

### Available Taxonomy Types

- `event-types` - Event types (with parent-child hierarchy)
- `event-genres` - Event genres (with parent-child hierarchy)
- `event-tags` - Event tags
- `artist-types` - Artist types
- `artist-genres` - Artist genres

### CSV Format for Taxonomies

All taxonomy CSVs must have these columns:

- `name` - **Required** - Display name
- `slug` - Optional - URL-friendly identifier (auto-generated from name if empty)
- `description` - Optional - Description text
- `parent_slug` - Optional - Slug of parent item (for hierarchical taxonomies)

### Example: event-types-example.csv

```csv
name,slug,description,parent_slug
Concert,concert,Live music performances,
Festival,festival,Multi-day music and arts events,
Rock Concert,rock-concert,Rock music concerts,concert
Pop Concert,pop-concert,Pop music concerts,concert
```

**Note:** Parent items must be defined BEFORE child items in the CSV.

## Export Commands

### Export all taxonomies of a type

```bash
# Export to default location (storage/app/exports/)
php artisan export:taxonomies event-types

# Export to custom file
php artisan export:taxonomies event-genres --file=/path/to/file.csv
```

### Available export types

```bash
php artisan export:taxonomies event-types
php artisan export:taxonomies event-genres
php artisan export:taxonomies event-tags
php artisan export:taxonomies artist-types
php artisan export:taxonomies artist-genres
```

## Import Commands

### Import taxonomies from CSV

```bash
php artisan import:taxonomies /path/to/file.csv event-types
php artisan import:taxonomies /path/to/file.csv event-genres
```

### Import process

1. **First pass**: Creates all items without parent relationships
2. **Second pass**: Updates parent_id based on parent_slug
3. **Duplicate handling**: Skips items with existing slugs

### Error handling

- Missing parent_slug: Item created without parent
- Invalid parent_slug: Warning logged, item created without parent
- Duplicate slug: Item skipped, warning logged
- Empty name: Row skipped

## Migration workflow (localhost to production)

### Method 1: Export from localhost, import to production

**On localhost:**
```bash
php artisan export:taxonomies event-types
# File created at: storage/app/exports/event-types-2024-11-19.csv
```

**On production:**
```bash
# Upload the CSV file to server, then:
php artisan import:taxonomies /path/to/event-types-2024-11-19.csv event-types
```

### Method 2: Direct database copy (NOT RECOMMENDED)

If you have both databases accessible, you can export from one and import to another, but CSV method is safer and more controlled.

## Tips

1. **Always backup** before importing
2. **Test with small files** first
3. **Use example files** as templates
4. **Check for duplicates** - import command will skip them
5. **Verify parent relationships** after import
6. **Use slugs consistently** - they are used for parent-child matching

## Venues

### CSV Format for Venues

**Required:** `name`

**Optional:** slug, address, city, state, country, website_url, phone, email, facebook_url, instagram_url, capacity, capacity_total, capacity_standing, capacity_seated, lat, lng, description

### Import Venues
```bash
php artisan import:venues /path/to/venues.csv
```

See `venues-example.csv` for format with Romanian venues.

## Artists

### CSV Format for Artists

**Required:** `name`

**Optional:** slug, email, phone, website_url, facebook_url, instagram_url, tiktok_url, spotify_url, youtube_url, country, city, bio, formed_at (YYYY-MM-DD)

### Import Artists
```bash
php artisan import:artists /path/to/artists.csv
```

See `artists-example.csv` for format with Romanian artists.

## Future enhancements

- Export commands for Venues and Artists
- Events import/export (complex, with relations)
