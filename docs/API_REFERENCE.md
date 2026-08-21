# SmartPOS Product Service API Reference (:8003)

This document provides technical documentation for the **SmartPOS Product Service**, responsible for managing product master data, categories, brands, measurement units, product variants, barcodes, QR codes, prices, images, and label printing.

## Base URL
```
http://localhost:8003/api/v1
```

## Authentication
All API requests (except `/api/health`) require a JWT Bearer token issued by Identity Service.

```http
Authorization: Bearer <jwt_token>
```

---

## 1. Categories

### List Categories
`GET /api/v1/categories`
- Query Params:
  - `search`: Filter by name or code
  - `is_active`: `true` | `false`
  - `tree`: `1` (returns nested category tree with `children`)
  - `per_page`: Number of results (default: `20`)

### Create Category
`POST /api/v1/categories`
```json
{
  "name": "Soft Drinks",
  "code": "SOFT-DRK",
  "parent_id": 1,
  "description": "Carbonated drinks",
  "sort_order": 1,
  "is_active": true
}
```

### Get / Update / Delete Category
- `GET /api/v1/categories/{id}`
- `PUT /api/v1/categories/{id}`
- `DELETE /api/v1/categories/{id}`

---

## 2. Brands

### List Brands
`GET /api/v1/brands`

### Create Brand
`POST /api/v1/brands`
```json
{
  "name": "Coca-Cola",
  "code": "COKE",
  "description": "The Coca-Cola Company",
  "logo_path": "brands/coke.png",
  "is_active": true
}
```

### Get / Update / Delete Brand
- `GET /api/v1/brands/{id}`
- `PUT /api/v1/brands/{id}`
- `DELETE /api/v1/brands/{id}`

---

## 3. Units

### List Units
`GET /api/v1/units`

### Create Unit
`POST /api/v1/units`
```json
{
  "name": "Piece",
  "code": "PCS",
  "symbol": "pcs",
  "precision": 0,
  "is_active": true
}
```

### Get / Update / Delete Unit
- `GET /api/v1/units/{id}`
- `PUT /api/v1/units/{id}`
- `DELETE /api/v1/units/{id}` *(Restricted if assigned to products)*

---

## 4. Products

### List Products
`GET /api/v1/products`
- Query Params:
  - `search`: Filter by name, SKU, or barcode/QR code value
  - `sku`: Exact SKU search
  - `category_id`: Category filter
  - `brand_id`: Brand filter
  - `available_only`: `1` (only products valid today according to `available_from` / `available_until`)

### Create Product
`POST /api/v1/products`
```json
{
  "name": "Coca-Cola 330ml Can",
  "sku": "COKE-330",
  "category_id": 2,
  "brand_id": 1,
  "unit_id": 1,
  "track_inventory": true,
  "allow_negative_stock": false,
  "is_taxable": true,
  "is_active": true,
  "available_from": "2026-03-01",
  "available_until": "2027-03-01",
  "code_option": "both",
  "selling_price": 0.75,
  "cost_price": 0.45,
  "currency_code": "USD"
}
```

### Code Option Details
- `none`: No code record generated
- `barcode`: Auto-generates CODE128 barcode
- `qrcode`: Auto-generates QR code (`SP:PROD:<uuid>`)
- `both`: Generates both barcode and QR code

### Get / Update / Delete Product
- `GET /api/v1/products/{id}`
- `PUT /api/v1/products/{id}`
- `DELETE /api/v1/products/{id}`

---

## 5. Product Variants

- `GET /api/v1/products/{product_id}/variants`
- `POST /api/v1/products/{product_id}/variants`
```json
{
  "name": "1.5L Bottle",
  "sku": "COKE-1500",
  "sort_order": 2,
  "is_default": false,
  "selling_price": 1.99,
  "code_option": "barcode"
}
```
- `PUT /api/v1/product-variants/{variant_id}`
- `DELETE /api/v1/product-variants/{variant_id}`

---

## 6. Product Codes (Barcode & QR Code)

- `GET /api/v1/products/{product_id}/codes`
- `POST /api/v1/products/{product_id}/codes` (Manual add)
- `POST /api/v1/products/{product_id}/codes/generate`
```json
{
  "code_option": "both",
  "product_variant_id": null
}
```
- `DELETE /api/v1/product-codes/{code_id}`

---

## 7. Product Prices

- `GET /api/v1/products/{product_id}/prices`
- `POST /api/v1/products/{product_id}/prices`
```json
{
  "currency_code": "USD",
  "selling_price": 0.99,
  "cost_price": 0.50,
  "minimum_price": 0.80,
  "starts_at": "2026-06-01 00:00:00",
  "ends_at": "2026-12-31 23:59:59"
}
```
- `PUT /api/v1/product-prices/{price_id}`
- `DELETE /api/v1/product-prices/{price_id}`

---

## 8. Product Images

- `GET /api/v1/products/{product_id}/images`
- `POST /api/v1/products/{product_id}/images`
```json
{
  "image_path": "products/coke/front.png",
  "alt_text": "Coca-Cola front view",
  "sort_order": 0,
  "is_primary": true
}
```
- `DELETE /api/v1/product-images/{image_id}`

---

## 9. Label Templates & Sticker Printing

### List Templates
`GET /api/v1/label-templates`

### Create Template
`POST /api/v1/label-templates`
```json
{
  "name": "Standard Shelf Sticker (50x30mm)",
  "width_mm": 50,
  "height_mm": 30,
  "show_product_name": true,
  "show_variant_name": true,
  "show_price": true,
  "show_sku": true,
  "show_barcode": true,
  "show_qrcode": true,
  "is_default": true,
  "is_active": true
}
```

### Preview Product Label
`POST /api/v1/products/{product_id}/labels/preview`
```json
{
  "label_template_id": 1,
  "product_variant_id": null
}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "template": {
      "id": 1,
      "name": "Standard Shelf Sticker (50x30mm)",
      "width_mm": 50,
      "height_mm": 30
    },
    "content": {
      "product_name": "Coca-Cola 330ml Can",
      "sku": "COKE-330",
      "price": "USD 0.75",
      "barcode_value": "COKE-330",
      "qrcode_value": "SP:PROD:uuid-string"
    },
    "rendered": {
      "barcode_svg": "<svg ...></svg>",
      "qrcode_svg": "<svg ...></svg>"
    }
  }
}
```

### Print Product Label
`POST /api/v1/products/{product_id}/labels/print`
```json
{
  "label_template_id": 1,
  "product_variant_id": null,
  "quantity": 10
}
```
*Dispatches print job and records audit record in `product_label_print_logs`.*
