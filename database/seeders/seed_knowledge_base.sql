-- Knowledge Base Microservice Seeder
-- Run this SQL directly on the database to add the Knowledge Base microservice

INSERT INTO microservices (slug, name, description, short_description, price, currency, billing_cycle, pricing_model, features, category, status, metadata, created_at, updated_at)
VALUES (
    'knowledge-base',
    '{"en": "Knowledge Base", "ro": "Baza de Cunoștințe"}',
    '{"en": "Complete knowledge base and help center solution for your marketplace. Create articles and FAQs, organize content into categories, track helpfulness votes, and provide self-service support to your customers. Includes search functionality, popular topics, and analytics.", "ro": "Soluție completă de bază de cunoștințe și centru de ajutor pentru marketplace-ul tău. Creează articole și FAQ-uri, organizează conținutul în categorii, urmărește voturile de utilitate și oferă suport self-service clienților. Include funcționalitate de căutare, subiecte populare și analiză."}',
    '{"en": "Help center with articles, FAQs and self-service support", "ro": "Centru de ajutor cu articole, FAQ-uri și suport self-service"}',
    10.00,
    'EUR',
    'monthly',
    'recurring',
    '{"en": ["Rich text editor for articles", "FAQ question-answer format", "Category organization with icons and colors", "Popular topics widget", "Search functionality", "Article view tracking", "Helpfulness voting (Was this helpful?)", "Featured articles", "SEO metadata support", "Multi-language content", "Article tagging", "Related articles", "Contact section integration", "Analytics dashboard"], "ro": ["Editor text bogat pentru articole", "Format FAQ întrebare-răspuns", "Organizare categorii cu iconițe și culori", "Widget subiecte populare", "Funcționalitate căutare", "Urmărire vizualizări articole", "Votare utilitate (A fost util?)", "Articole featured", "Suport metadate SEO", "Conținut multi-limbă", "Etichetare articole", "Articole conexe", "Integrare secțiune contact", "Dashboard analiză"]}',
    'support',
    'active',
    '{"endpoints": ["GET /api/kb/categories", "GET /api/kb/categories/{slug}", "GET /api/kb/articles", "GET /api/kb/articles/{slug}", "GET /api/kb/articles/search", "GET /api/kb/articles/popular", "GET /api/kb/articles/featured", "GET /api/kb/faqs", "POST /api/kb/articles/{id}/vote", "POST /api/kb/articles/{id}/view"], "database_tables": ["kb_categories", "kb_articles", "kb_popular_topics"], "max_articles": "unlimited", "max_categories": 50}',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    short_description = VALUES(short_description),
    price = VALUES(price),
    features = VALUES(features),
    metadata = VALUES(metadata),
    updated_at = NOW();
