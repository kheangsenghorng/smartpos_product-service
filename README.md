# SmartPOS Product Service (`:8003`)

The **SmartPOS Product Service** is a high-performance microservice that manages product master data, categories, brands, measurement units, product variants, dynamic barcodes & QR codes, multi-currency pricing, image galleries, sticker label templates, and print audit logging.

---

## 🚀 Key Capabilities & Modules

### 1. Catalog & Master Data Management
- **Hierarchical Categories**: Parent-child category tree with soft delete support.
- **Brands & Units**: Master brand registries and measurement units (symbols, precision).
- **Product Master Records**: Core product data decoupled from inventory/stock.
- **Product Variants**: Multi-attribute variants (Size, Color, Flavor) with individual SKUs and prices.
- **Image Gallery**: Multi-image attachments with primary thumbnail tags and display ordering.

### 2. Multi-Currency Pricing Engine
- Supports flexible pricing windows (`effective_from`, `effective_until`).
- Currency-aware pricing per product and per variant.

### 3. Barcode & QR Code Engine
- **Supported Barcode Symbologies**: `CODE128`, `EAN-13`, `EAN-8`, `UPC-A`, `CODE-39`.
- **Human-Readable Text Support**: Renders centered numeric barcode text dynamically beneath the bars:
  ```
   ███ ██ █ ████ ███
       8850000000010
  ```
- **2D QR Code Generation**: Standard vector SVGs (`SP:PROD:{uuid}`, `SP:VAR:{uuid}`).
- **Automatic Code Generation**: Auto-derives barcode values from SKUs when creating items.

### 4. Sticker Label Printing & Audit Logging
- **Customizable Dimensions**: Define custom label width and height in millimeters (e.g., `40x30mm`, `50x25mm`).
- **Preview Endpoint**: Returns SVG snippets and label metadata for client-side rendering.
- **Print Endpoint**: Dispatches print jobs and persists audit logs (`product_label_print_logs`).

### 5. Availability Date Windows
- Products support scheduling dates (`available_from`, `available_until`).
- Query parameter `?available_only=1` returns active items for POS sales listings.

---

## 🛡️ Security & Multi-Tenancy Hardening

- **Multi-Tenant Isolation**: Every database query is strictly scoped by `business_uuid`.
- **JWT Authentication**: [JwtAuthMiddleware](app/Http/Middleware/JwtAuthMiddleware.php) with constant-time HMAC-SHA256 signature and expiration verification.
- **Role-Based Access Control (RBAC)**: Granular permissions (`products.view`, `products.create`, `labels.print`, etc.).
- **Rate Limiting**:
  - `throttle:api`: Global protection of **120 requests/minute** per user/IP.
  - `throttle:heavy-ops`: Resource protection of **30 requests/minute** for label printing and barcode generation.
- **Input Sanitization & Security Headers**: Strips XSS script tags and injects strict security headers (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, strict CSP).

---

## 🗺️ API Route Overview (`/api/v1`)

| Module | Method | Route | Description |
| :--- | :--- | :--- | :--- |
| **Health** | `GET` | `/api/health` | Service health status check |
| **Products** | `GET / POST` | `/api/v1/products` | List and create products |
| | `GET / PUT / DELETE` | `/api/v1/products/{id}` | Show, update, or delete a product |
| **Variants** | `GET / POST` | `/api/v1/products/{id}/variants` | List or create variants |
| **Codes** | `GET / POST` | `/api/v1/products/{id}/codes` | Manage barcodes and QR codes |
| | `POST` | `/api/v1/products/{id}/codes/generate` | Auto-generate barcode/QR codes |
| **Prices** | `GET / POST` | `/api/v1/products/{id}/prices` | Manage product price points |
| **Images** | `GET / POST` | `/api/v1/products/{id}/images` | Upload and order product images |
| **Labels** | `POST` | `/api/v1/products/{id}/labels/preview` | Preview sticker layout and SVG |
| | `POST` | `/api/v1/products/{id}/labels/print` | Dispatch print job and create audit log |
| **Templates** | `GET / POST / PUT` | `/api/v1/label-templates` | CRUD for sticker templates |
| **Categories**| `GET / POST / PUT` | `/api/v1/categories` | Hierarchical category management |
| **Brands & Units** | `GET / POST / PUT` | `/api/v1/brands`, `/api/v1/units` | Brand and Unit registry |

---

## 🖨️ Label Printing API Example

### Request
```bash
POST /api/v1/products/1/labels/print
Authorization: Bearer <JWT_TOKEN>
Content-Type: application/json

{
  "label_template_id": 1,
  "product_variant_id": 1,
  "quantity": 10
}
```

### Response (`201 Created`)
```json
{
  "success": true,
  "message": "Label print job dispatched for 10 item(s).",
  "data": {
    "log": {
      "id": 1,
      "product_id": 1,
      "product_variant_id": 1,
      "label_template_id": 1,
      "quantity_printed": 10,
      "printed_at": "2026-08-24T09:05:00.000000Z"
    },
    "preview": {
      "template": {
        "id": 1,
        "name": "Standard Sticker 40x30",
        "width_mm": 40.0,
        "height_mm": 30.0
      },
      "content": {
        "product_name": "Green Tea 500ml",
        "variant_name": "Bottle",
        "sku": "TEA-500-BOT",
        "price": "USD 2.50",
        "barcode_value": "8850000000010"
      },
      "rendered": {
        "barcode_svg": "<svg ...>...</svg>",
        "qrcode_svg": null
      }
    }
  }
}
```

---

## 🐳 Docker Setup

```bash
# Start Product Service and database containers
docker compose up -d --build product-service
```

### Container Port Mapping:
- **Product API Service**: `http://localhost:8003`
- **Interactive OpenAPI Docs (Scramble)**: `http://localhost:8003/docs/products`
- **MySQL Database**: Port `3309` (internal `3306`), database `smartpos_product`
- **Redis Cache**: Port `6382` (internal `6379`)
- **phpMyAdmin**: `http://localhost:8083`

---

## 💻 Local Setup (Without Docker)

```bash
# 1. Install dependencies
composer install

# 2. Environment configuration
cp .env.example .env
php artisan key:generate

# 3. Database migrations
php artisan migrate

# 4. Start local development server
php artisan serve --port=8003
```

---

## 🧪 Running Automated Tests

```bash
# Run all unit and feature tests with in-memory SQLite
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit

# Run with human-readable testdox output
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --testdox

# Inside Docker container
docker exec smartpos-product-service-1 php artisan test
```

**Current Test Status**: 🟢 **68 tests, 253 assertions, 0 failures, 100% pass rate**.

For the full security and penetration test report, see [SECURITY_TESTING_REPORT.md](SECURITY_TESTING_REPORT.md).
