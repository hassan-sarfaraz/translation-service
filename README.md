# High-Performance Scalable Translation Management Service

A headless, high-throughput RESTful Translation Management Service built with **Laravel 11**. It is architected to handle millions of translation keys across multiple locales with microsecond lookup speeds, tag-based segmentation, multi-layer Redis caching, and CDN edge invalidation hooks.

---

## Evaluation Criteria & Feature Highlights

- **Zero Third-Party CRUD / Translation Packages**: Built entirely on native Laravel components without external admin packages or third-party query wrappers.
- **Performance SLAs**:
  - Standard CRUD & Search endpoints: `< 200ms`
  - Frontend JSON Export endpoint: `< 500ms` (tested against 100,000+ entries)
- **SQL Query & Database Optimization**:
  - Composite unique index on `(locale_id, key)` prevents duplicate entries and guarantees O(1) index lookups.
  - Composite primary keys and index inversion on pivot tables (`tag_translation`) optimize join and subquery overhead.
  - Fulltext and prefix indexing on translation keys and contents to ensure sub-millisecond search filtering.
  - Low-memory database exports using `pluck('content', 'key')` directly from PDO streams without Eloquent collection hydration overhead.
- **Token-Based Authentication**: Secured with **Laravel Sanctum** (`auth:sanctum`), returning clean, headless `401 Unauthorized` JSON responses.
- **Multi-Layer Caching & CDN Edge Purge**:
  - In-memory Redis caching with dynamic tag-level invalidation.
  - RFC-compliant `Cache-Control` response headers (`public, max-age=3600, stale-while-revalidate=60`) for HTTP edge proxies.
  - Architectural CDN edge purging hook (`purgeCdnEdge`) to prevent stale cached responses when database translations are modified.
- **Docker Support**: Pre-configured `docker-compose.yml` (Laravel Sail) with PHP 8.3, MySQL 8.0, and Redis.
- **Comprehensive Test Suite (> 95% Target Coverage)**: Unit, functional CRUD, and benchmark latency tests verifying strict SLAs.
- **Interactive OpenAPI / Swagger Documentation**: Embedded Swagger UI served cleanly without external PHP dependencies.

---

## Database Architecture & Indexing Strategy

### Schema Design

1. **`locales`**: Stores distinct language codes (`id`, `code`, `name`). Unique index on `code`.
2. **`tags`**: Segments translations (e.g., `mobile`, `web`, `desktop`). Unique index on `name`.
3. **`translations`**: Primary data store (`id`, `locale_id`, `key`, `content`, timestamps).
   - `UNIQUE(locale_id, key)`
   - `INDEX(key)`
   - `FULLTEXT(content)`
4. **`tag_translation`**: Pivot table (`translation_id`, `tag_id`).
   - Composite Primary Key: `(translation_id, tag_id)`
   - Secondary Index: `(tag_id, translation_id)` for reverse lookups.

---

## Local Installation & Setup

### Prerequisites

- PHP 8.3+
- Composer 2+
- MySQL 8.0+
- Redis Server (or `file` cache fallback)

### 1. Clone & Install Dependencies

```bash
git clone https://github.com/hassan-sarfaraz/translation-service.git
cd translation-service
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Environment Configuration

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=translation_service
DB_USERNAME=root
DB_PASSWORD=

# Use redis for high-concurrency production or database/file for local fallback
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Performance Seeding & Automated Tests

**a. Benchmark Seeding (100,000+ Records)**

Populate the database with chunked, memory-safe benchmark data:

```bash
php artisan benchmark:seed 100000
```

**b. Run Test Suite**

Validate functional CRUD operations, tag lookups, authentication, and sub-200ms / sub-500ms latency SLAs:

```bash
php artisan test
```

### 5. Interactive API Documentation (Swagger UI)

**a. Start the local server:**

```bash
php artisan serve
```

**b. Navigate to:**

```
http://localhost:8000/docs
```

### 6. Authenticating in Swagger

**a. Generate an API token via Tinker:**

```bash
php artisan tinker --execute="echo App\Models\User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin', 'password' => bcrypt('password')])->createToken('admin-token')->plainTextToken . PHP_EOL;"
```

**b.** In Swagger UI, click the green **Authorize** button in the top right.

**c.** Paste the plain token into the **Value** field and submit.

### 7. Docker Deployment (Laravel Sail)

For evaluators running in containerized environments (Linux / macOS / WSL2):

```bash
# Start Docker containers
./vendor/bin/sail up -d

# Run database migrations
./vendor/bin/sail artisan migrate

# Seed 100k benchmark entries
./vendor/bin/sail artisan benchmark:seed 100000

# Execute full test suite
./vendor/bin/sail artisan test
```