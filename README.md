# SmartPOS Product Service (:8003)

The **SmartPOS Product Service** is a high-performance microservice that manages product master data, categories, brands, measurement units, product variants, dynamic barcodes & QR codes, prices, product images, and label printing.

## 🚀 Key Features

1. **Domain-Driven Tables**:
   - `categories`: Hierarchical category trees with soft deletes.
   - `brands`: Brand registry.
   - `units`: Measurement units with symbol and precision.
   - `products`: Product master table (isolated from stock and barcode columns).
   - `product_variants`: Size, flavor, and pack variants.
   - `product_codes`: Flexible Barcode & QR Code storage (`code_option: none | barcode | qrcode | both`).
   - `product_prices`: Effective multi-currency pricing windows.
   - `product_images`: Multi-image gallery with sort orders and primary tags.
   - `label_templates`: Printable sticker label dimensions and toggle configurations.
   - `product_label_print_logs`: Audit tracking of printed labels.

2. **Availability Date Engine**:
   - Supports `available_from` and `available_until` window validation.
   - Filter query `available_only=1` for active POS sales listings.

3. **Built-in Barcode & QR Engine**:
   - `CODE128`, `EAN13`, `EAN8`, `UPC-A`, `CODE39` barcode rendering.
   - Standard QR code generation with `SP:PROD:<uuid>` and `SP:VAR:<uuid>` format.

4. **Security & Multi-Tenancy**:
   - Strict `business_uuid` isolation.
   - JWT authentication decoding with cross-service compatibility.
   - Granular RBAC permissions (`products.view`, `products.create`, `labels.print`, etc.).

---

### Docker Setup (Recommended)
```bash
# Run from repository root:
docker compose up -d --build product-service
```
- **Service Container**: `smartpos-product-service-1` (`:8003` -> internal `:8000`)
- **MySQL Container**: `smartpos-product-mysql-1` (`:3309` -> internal `:3306`)
- **Redis Container**: `smartpos-product-redis-1` (`:6382` -> internal `:6379`)
- **phpMyAdmin**: `http://localhost:8083`
- **API Documentation**: `http://localhost:8003/docs/products` or `http://localhost:8000/docs/products`

---

### Local Setup (Without Docker)
```bash
cd product-service
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve --port=8003
```

### Running Tests
```bash
# Inside docker
docker exec smartpos-product-service-1 php artisan test

# Locally
php artisan test
```

---

## 📖 API Documentation
For endpoint references and sample payloads, refer to [docs/API_REFERENCE.md](file:///Users/macbookpro/Projects/smartpos/product-service/docs/API_REFERENCE.md).
