# API Documentation (OpenAPI/Swagger) Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Developers integrating with the platform lack:
1. **No API reference**: Must read source code to understand endpoints
2. **No testing tools**: Can't easily test API calls
3. **No SDK guidance**: No code examples for different languages
4. **Inconsistent responses**: No standardized documentation of responses

### What This Feature Does
- Generate OpenAPI 3.0 specification automatically
- Provide interactive Swagger UI for testing
- Document all endpoints with examples
- Generate SDKs for popular languages
- Version API documentation

---

## Technical Implementation

### 1. Package Installation

```bash
composer require darkaonline/l5-swagger
```

### 2. Configuration

```php
// config/l5-swagger.php
return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'EPAS/Tixello API',
            ],
            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'docs',
            ],
            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [
                    base_path('app/Http/Controllers/Api'),
                    base_path('app/OpenApi'),
                ],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
        ],
        'paths' => [
            'docs' => storage_path('api-docs'),
            'views' => base_path('resources/views/vendor/l5-swagger'),
            'base' => env('L5_SWAGGER_BASE_PATH', null),
            'excludes' => [],
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => 'Enter your bearer token',
                ],
                'apiKey' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-API-Key',
                    'description' => 'Tenant API Key',
                ],
            ],
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy' => false,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'additional_config_url' => null,
        'validator_url' => null,
    ],
];
```

### 3. Base API Info

Create `app/OpenApi/OpenApiSpec.php`:

```php
<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="EPAS/Tixello API",
 *     description="Event ticketing and management platform API",
 *     @OA\Contact(
 *         email="api@tixello.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="Proprietary",
 *         url="https://tixello.com/terms"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/v1",
 *     description="Production API Server"
 * )
 *
 * @OA\Server(
 *     url="/api/tenant-client",
 *     description="Tenant Client API (requires X-API-Key header)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Sanctum token"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="apiKey",
 *     type="apiKey",
 *     in="header",
 *     name="X-API-Key",
 *     description="Tenant API Key for client-facing endpoints"
 * )
 *
 * @OA\Tag(name="Events", description="Event management endpoints")
 * @OA\Tag(name="Tickets", description="Ticket operations")
 * @OA\Tag(name="Orders", description="Order management")
 * @OA\Tag(name="Customers", description="Customer operations")
 * @OA\Tag(name="Authentication", description="Auth endpoints")
 * @OA\Tag(name="Payments", description="Payment processing")
 */
class OpenApiSpec
{
}
```

### 4. Schema Definitions

Create `app/OpenApi/Schemas/EventSchema.php`:

```php
<?php

namespace App\OpenApi\Schemas;

/**
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     title="Event",
 *     description="Event model",
 *     required={"id", "name", "start_date"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Summer Music Festival"),
 *     @OA\Property(property="slug", type="string", example="summer-music-festival"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="short_description", type="string"),
 *     @OA\Property(property="start_date", type="string", format="date-time"),
 *     @OA\Property(property="end_date", type="string", format="date-time"),
 *     @OA\Property(property="venue", ref="#/components/schemas/Venue"),
 *     @OA\Property(property="ticket_types", type="array", @OA\Items(ref="#/components/schemas/TicketType")),
 *     @OA\Property(property="status", type="string", enum={"draft", "published", "cancelled"}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="EventList",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Event")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Venue",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="city", type="string"),
 *     @OA\Property(property="country", type="string"),
 *     @OA\Property(property="latitude", type="number"),
 *     @OA\Property(property="longitude", type="number")
 * )
 */

/**
 * @OA\Schema(
 *     schema="TicketType",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string", example="General Admission"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="price", type="number", format="float", example=49.99),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="quantity_available", type="integer"),
 *     @OA\Property(property="max_per_order", type="integer")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="order_number", type="string", example="ORD-ABC123"),
 *     @OA\Property(property="status", type="string", enum={"pending", "paid", "cancelled", "refunded"}),
 *     @OA\Property(property="total", type="number", example=99.99),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="customer", ref="#/components/schemas/Customer"),
 *     @OA\Property(property="tickets", type="array", @OA\Items(ref="#/components/schemas/Ticket")),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Customer",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="first_name", type="string"),
 *     @OA\Property(property="last_name", type="string"),
 *     @OA\Property(property="phone", type="string")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Ticket",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="ticket_number", type="string"),
 *     @OA\Property(property="ticket_type", ref="#/components/schemas/TicketType"),
 *     @OA\Property(property="qr_code", type="string"),
 *     @OA\Property(property="status", type="string")
 * )
 */

/**
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="last_page", type="integer"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="total", type="integer")
 * )
 */

/**
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     type="object",
 *     @OA\Property(property="first", type="string"),
 *     @OA\Property(property="last", type="string"),
 *     @OA\Property(property="prev", type="string", nullable=true),
 *     @OA\Property(property="next", type="string", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="errors", type="object")
 * )
 */

class Schemas
{
}
```

### 5. Controller Annotations

Example for `app/Http/Controllers/Api/TenantClient/EventController.php`:

```php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;

class EventController extends Controller
{
    /**
     * @OA\Get(
     *     path="/events",
     *     operationId="listEvents",
     *     tags={"Events"},
     *     summary="List all events",
     *     description="Returns a paginated list of published events for the tenant",
     *     security={{"apiKey": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category slug",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter events starting from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/EventList")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Implementation
    }

    /**
     * @OA\Get(
     *     path="/events/{slug}",
     *     operationId="getEvent",
     *     tags={"Events"},
     *     summary="Get event details",
     *     security={{"apiKey": {}}},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Event slug",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Event")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Event not found"
     *     )
     * )
     */
    public function show(string $slug)
    {
        // Implementation
    }
}
```

### 6. Generate Documentation Command

```php
// app/Console/Commands/GenerateApiDocs.php
class GenerateApiDocs extends Command
{
    protected $signature = 'api:docs {--format=json}';
    protected $description = 'Generate API documentation';

    public function handle(): int
    {
        $this->call('l5-swagger:generate');
        $this->info('API documentation generated successfully!');

        return Command::SUCCESS;
    }
}
```

### 7. Routes

```php
// routes/web.php
Route::get('/api/documentation', function () {
    return view('vendor.l5-swagger.index');
})->name('api.docs');

Route::get('/api/docs.json', function () {
    return response()->file(storage_path('api-docs/api-docs.json'));
});
```

### 8. Custom Documentation Page

Create `resources/views/api-docs/index.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation - Tixello</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "/api/docs.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "BaseLayout",
                persistAuthorization: true,
            });
        };
    </script>
</body>
</html>
```

---

## Testing Checklist

1. [ ] OpenAPI spec generates without errors
2. [ ] Swagger UI loads correctly
3. [ ] All endpoints are documented
4. [ ] Authentication works in Swagger UI
5. [ ] Request/response schemas are accurate
6. [ ] Examples are provided for complex endpoints
7. [ ] Error responses are documented
8. [ ] Pagination is documented
9. [ ] API versioning is reflected
10. [ ] Download spec as JSON/YAML works
