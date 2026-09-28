# FIDELOS CORE — AUDITORÍA Y PROPUESTA DE ARQUITECTURA DEL CATÁLOGO DINÁMICO TRANSVERSAL

**Fecha:** 28 de Septiembre de 2026  
**Rama activa:** `fidelos-web-erp-clinica-odontologica`  
**Estado:** AUDITORÍA Y PROPUESTA ARQUITECTÓNICA — En espera de aprobación  

---

## 1. DIAGNÓSTICO DEL ESTADO ACTUAL Y VACÍOS DETECTADOS

### 1.1 Diagnóstico de Base de Datos
* **Tabla `products`**:
  * Posee `company_id`, `name`, `description`, `category_id`, `brand_id`, `unit_id`, `unit_price`, `cost_price`, `status`, `is_public`, `image_url`.
  * **Faltantes**: No cuenta con `display_order` (orden de visualización), `compare_price` (precio anterior de oferta) ni `vertical` (para clasificación por vertical).
* **Tabla `services`**:
  * Posee `company_id`, `name`, `description`, `type`, `estimated_duration_minutes`, `price`, `status`.
  * **Faltantes**: Le falta `is_public` (boolean), `image_url` (imagen del servicio), `category_id` / `vertical` (categorización), `display_order` (orden) y `compare_price`.
* **Tabla `categories`**:
  * Posee `company_id`, `name`, `status`.
  * **Faltantes**: Le falta `vertical` (string), `is_public` y `display_order`.

### 1.2 Diagnóstico de Frontend Público (Next.js)
* **Páginas de Odontología (`/odontologia`), Sueroterapia (`/sueroterapia`), IPS (`/servicios`), Estética y Veterinaria**:
  * Presentan especialidades o servicios renderizados parcialmente mediante tarjetas con datos harcodeados o fallbacks locales en el componente.
  * **Requisito Obligatorio**: Eliminar 100% los arrays o JSONs harcodeados en componentes y conectar las páginas web públicas al API del backend.

---

## 2. PROPUESTA DE MIGRACIONES Y ESQUEMA DB DE CORE (ADITIVAS)

### 2.1 Migración 1: `2026_09_28_000017_add_catalog_fields_to_services_table.php`
```php
Schema::table('services', function (Blueprint $table) {
    $table->foreignId('category_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
    $table->string('vertical')->nullable()->after('category_id')->index(); // ips | estetica | veterinaria | odontologia | sueroterapia
    $table->string('image_url', 500)->nullable()->after('description');
    $table->decimal('compare_price', 12, 2)->nullable()->after('price');
    $table->boolean('is_public')->default(true)->index()->after('status');
    $table->integer('display_order')->default(0)->after('is_public');
});
```

### 2.2 Migración 2: `2026_09_28_000018_add_catalog_fields_to_products_table.php`
```php
Schema::table('products', function (Blueprint $table) {
    $table->string('vertical')->nullable()->after('category_id')->index();
    $table->decimal('compare_price', 12, 2)->nullable()->after('unit_price');
    $table->integer('display_order')->default(0)->after('is_public');
});
```

### 2.3 Migración 3: `2026_09_28_000019_add_vertical_to_categories_table.php`
```php
Schema::table('categories', function (Blueprint $table) {
    $table->string('vertical')->nullable()->after('company_id')->index();
    $table->boolean('is_public')->default(true)->after('status');
    $table->integer('display_order')->default(0)->after('is_public');
});
```

---

## 3. PROPUESTA DE BACKEND Y API PÚBLICA DE CATÁLOGO

```text
                                FIDELOS CORE
                                      │
          ┌───────────────────────────┴───────────────────────────┐
          ▼                                                       ▼
  CRUD Administrativo                                     PublicCatalogController
  (/app/servicios, /app/productos)                         (/api/public/catalog/*)
          │                                                       │
          └───────────────────────────┬───────────────────────────┘
                                      ▼
                            Base de Datos MariaDB
                    (products, services, categories)
```

### Endpoints Públicos de Catálogo Transversal
1. `GET /api/public/catalog/services?vertical={vertical}&category_id={id}`
   * Retorna los servicios activos (`status = active`) y públicos (`is_public = true`) filtrados por `company_id` y `vertical`, ordenados por `display_order` y `name`.
2. `GET /api/public/catalog/products?vertical={vertical}&category_id={id}`
   * Retorna los productos activos y públicos con precios, fotos y categorías.
3. `GET /api/public/catalog/categories?vertical={vertical}`
   * Retorna las categorías que contienen ítems públicos activos.
4. `POST /api/services/{id}/image` y `POST /api/products/{id}/image`
   * Permiten al administrador subir o reemplazar la imagen directamente en almacenamiento de FidelOS.

---

## 4. COBERTURA DE LAS 5 VERTICALES EN PÁGINAS PÚBLICAS

| Vertical | Ruta Pública | Fuente Única de Verdad (Backend API) |
|---|---|---|
| **IPS** | `/servicios`, `/catalogo` | `GET /api/public/catalog/services?vertical=ips` |
| **Estética** | `/servicios`, `/estetica` | `GET /api/public/catalog/services?vertical=estetica` |
| **Veterinaria** | `/servicios`, `/catalogo` | `GET /api/public/catalog/services?vertical=veterinaria` |
| **Odontología** | `/odontologia`, `/agendar-cita-odontologica` | `GET /api/public/catalog/services?vertical=odontologia` |
| **Sueroterapia** | `/sueroterapia` | `GET /api/public/catalog/services?vertical=sueroterapia` |

---

## 5. PLAN DE EJECUCIÓN Y VALIDACIÓN

1. **Crear Migraciones Aditivas en Backend Core**.
2. **Actualizar Eloquent Models (`Service`, `Product`, `Category`)** con nuevos atributos `$fillable`, accesores y relaciones.
3. **Actualizar `ServiceController` y `ProductController`** para soportar `category_id`, `vertical`, `is_public`, `display_order`, `compare_price` y subida de imágenes.
4. **Actualizar `PublicCatalogController`** para exponer servicios y productos públicos filtrados por vertical.
5. **Poblar Seeders Coherentes (`DatabaseSeeder`, `DentalSeeder`, etc.)** con imágenes e ítems propios de cada vertical (IPS, Estética, Vet, Odontología, Sueroterapia).
6. **Conectar Páginas Públicas en Next.js** para consumir dinámicamente la API de catálogo.
7. **Ejecutar Pruebas Automatizadas**:
   * `php artisan migrate:fresh --seed`
   * `php artisan test` (PHPUnit suite)
   * `npm run build` (Build Next.js)
8. **Entregar Matriz Final de Certificación**.
