# Blog & Articles

## Short Presentation

Tell your story with Blog & Articles. Great events deserve great content. This comprehensive content management system transforms your platform into a publishing powerhouse, helping you connect with your audience before, during, and after events.

Create compelling articles about upcoming events, artist interviews, behind-the-scenes glimpses, and event recaps. The rich text editor makes writing a pleasure, with easy media embedding for photos, videos, and social content that brings your stories to life.

Organize content your way with categories, tags, and series. Build anticipation with a series of articles leading up to your flagship event. Group festival coverage into a dedicated series that fans can follow from day one to the final encore.

The SEO toolkit ensures your content gets discovered. Custom meta titles, descriptions, and keywords help search engines understand and rank your content. Featured images catch eyes in social shares.

Multi-author support lets your whole team contribute. Track who wrote what, build author profiles, and showcase your content creators. The revision history means you never lose a draft, and the publishing workflow keeps everything organized from first idea to published piece.

Keep readers engaged with a comment system complete with moderation tools. Track article views and build your newsletter subscriber list directly from your blog. Turn readers into ticket buyers with strategically placed calls-to-action.

Your events have stories. It's time to tell them.

---

## Detailed Description

Blog & Articles is a full-featured content management system designed for event organizers who want to build deeper connections with their audience through content marketing. It provides all the tools needed to create, organize, and distribute compelling content.

### Content Creation

The rich text editor supports:
- Formatted text with headings, lists, and quotes
- Image embedding with captions and galleries
- Video embedding from YouTube, Vimeo, and more
- Social media embeds
- Custom HTML blocks for advanced layouts

### Content Organization

Multiple organizational tools keep your content structured:
- **Categories**: Broad topics like "News", "Reviews", "Interviews"
- **Tags**: Specific keywords for granular organization
- **Series**: Group related articles into sequential collections
- **Authors**: Multi-author support with profiles

### Publishing Workflow

Articles move through a clear workflow:
1. **Draft**: Work in progress, not visible to public
2. **Scheduled**: Ready to publish at a future date/time
3. **Published**: Live and visible to readers
4. **Private/Password Protected**: Restricted access

### SEO Optimization

Built-in SEO tools include:
- Custom meta titles and descriptions
- Keyword targeting
- Automatic slugs with customization
- Open Graph tags for social sharing
- Reading time calculation

### Engagement Features

- Comment system with moderation queue
- View tracking and analytics
- Newsletter subscription forms
- Related article suggestions
- Social sharing buttons

---

## Features

### Content Management
- Rich text editor with media embedding
- Draft, scheduled, and published states
- Content revision history
- Featured articles and images
- Automatic reading time calculation
- Public, private, and password-protected visibility

### Organization
- Category and tag management
- Multiple author support with profiles
- Article series organization
- Slug customization
- Related articles suggestions

### SEO & Discovery
- SEO metadata (title, description, keywords)
- Social sharing integration
- Automatic reading time
- Sitemap generation
- Search-friendly URLs

### Engagement
- Comment system with moderation
- View tracking and analytics
- Newsletter subscriptions
- Related articles
- Social sharing buttons

---

## Use Cases

### Event Promotion
Build excitement for upcoming events with feature articles, artist spotlights, and preview content that drives ticket sales.

### Artist & Performer Features
Interview artists and performers, sharing their stories and creating deeper connections with your audience.

### Event Recaps
Publish photo galleries, video highlights, and written recaps that extend the event experience and keep attendees engaged.

### Behind the Scenes
Share the making of your events - venue preparation, team introductions, and the stories that make your events special.

### Industry News
Position yourself as an industry authority by covering relevant news, trends, and insights in your event space.

### Customer Stories
Feature testimonials, attendee experiences, and community stories that build social proof and trust.

---

## Technical Documentation

### Overview

The Blog & Articles microservice provides a complete CMS for creating, organizing, and publishing content. It supports multi-author workflows, SEO optimization, and engagement tracking.

### Database Schema

| Table | Description |
|-------|-------------|
| `blog_categories` | Content categories |
| `blog_tags` | Content tags |
| `blog_authors` | Author profiles |
| `blog_series` | Article series/collections |
| `blog_articles` | Article content |
| `blog_article_tag` | Article-tag relationships |
| `blog_article_revisions` | Version history |
| `blog_article_views` | View analytics |
| `blog_comments` | Reader comments |
| `blog_subscriptions` | Newsletter subscribers |

### API Endpoints

#### List Articles
```
GET /api/blog/articles
```
List published articles with pagination and filters.

#### Get Article
```
GET /api/blog/articles/{slug}
```
Retrieve article by slug.

#### Create Article
```
POST /api/blog/articles
```
Create new article (admin).

#### Update Article
```
PUT /api/blog/articles/{id}
```
Update article content.

#### Publish Article
```
POST /api/blog/articles/{id}/publish
```
Move article to published state.

#### List Categories
```
GET /api/blog/categories
```
List all categories.

#### List Tags
```
GET /api/blog/tags
```
List all tags.

#### List Authors
```
GET /api/blog/authors
```
List author profiles.

#### List Series
```
GET /api/blog/series
```
List article series.

#### Get Comments
```
GET /api/blog/articles/{id}/comments
```
Get article comments.

#### Post Comment
```
POST /api/blog/articles/{id}/comments
```
Submit reader comment.

#### Subscribe
```
POST /api/blog/subscribe
```
Subscribe to newsletter.

#### Get Stats
```
GET /api/blog/stats
```
Blog analytics overview.

### Article Structure

```json
{
  "id": 1,
  "title": "Summer Festival 2025 Lineup Announced",
  "slug": "summer-festival-2025-lineup",
  "excerpt": "Get ready for the biggest festival of the year...",
  "content": "<p>We're thrilled to announce...</p>",
  "featured_image": "/images/festival-lineup.jpg",
  "author": {
    "id": 1,
    "name": "John Editor",
    "avatar": "/avatars/john.jpg",
    "bio": "Music journalist and festival enthusiast"
  },
  "category": {
    "id": 1,
    "name": "News",
    "slug": "news"
  },
  "tags": ["festival", "lineup", "summer"],
  "series": {
    "id": 1,
    "name": "Festival Countdown",
    "position": 3
  },
  "seo": {
    "meta_title": "Summer Festival 2025 Lineup | Event Blog",
    "meta_description": "Check out the complete lineup...",
    "keywords": ["summer festival", "music festival", "2025"]
  },
  "status": "published",
  "visibility": "public",
  "reading_time": 5,
  "views": 1250,
  "comments_count": 23,
  "published_at": "2025-01-15T10:00:00Z",
  "created_at": "2025-01-14T15:30:00Z",
  "updated_at": "2025-01-15T09:45:00Z"
}
```

### Configuration

```php
'blog' => [
    'max_articles' => 'unlimited',
    'max_categories' => 100,
    'max_authors' => 50,
    'comments' => [
        'enabled' => true,
        'moderation' => true,
        'require_approval' => true,
    ],
    'seo' => [
        'auto_generate' => true,
        'max_title_length' => 60,
        'max_description_length' => 160,
    ],
    'reading_time' => [
        'words_per_minute' => 200,
    ],
]
```

### Integration Example

```php
use App\Services\Blog\BlogService;

$blog = app(BlogService::class);

// Create article
$article = $blog->createArticle([
    'title' => 'New Event Announcement',
    'content' => '<p>Exciting news...</p>',
    'category_id' => 1,
    'author_id' => 1,
    'status' => 'draft',
]);

// Publish article
$blog->publish($article->id);

// Get popular articles
$popular = $blog->getPopular(limit: 5);

// Track view
$blog->trackView($article->id, $request);
```
