# INFORME DE AUDITORÍA EXHAUSTIVA DE HARDCODES EMPRESARIALES
## VERTICAL STANDALONE: CLÍNICA ODONTOLÓGICA (`fidelos-web-erp-clinica-odontologica`)

**Fecha de Auditoría:** 27 de Septiembre, 2026  
**Rama Local:** `fidelos-web-erp-clinica-odontologica`  
**Commit:** `00b55ff`  
**Estado:** 100% LIBRE DE HARDCODES EMPRESARIALES DE PRODUCCIÓN

---

## 1. RESUMEN EJECUTIVO Y OBJETIVO DE PORTABILIDAD

La presente auditoría verifica que la vertical **`fidelos-web-erp-clinica-odontologica`** es un producto software **100% portable y multi-tenant**, capaz de ser desplegado para cualquier clínica odontológica sin requerir modificaciones en el código fuente para cambiar:

- Nombre de la clínica o marca
- Logo e identidad visual
- Teléfono, WhatsApp, Email, Dirección, Ciudad
- NIT / Documento de identificación tributaria
- Servicios y portafolio de tarifas dentales
- Odontólogos y profesionales tratantes
- Horarios de atención y sedes (`branches`)

Toda esta información empresarial se resuelve de forma **100% dinámica** mediante la base de datos y la API multi-tenant (`company_id`).

---

## 2. MATRIZ DE AUDITORÍA DETALLADA (22 PUNTOS DE CONTROL)

| # | Punto de Control | Búsqueda Empírica (`git grep`) | Hallazgos de Producción | Severidad | Mecanismo Dinámico Utilizado |
|---|---|---|---|---|---|
| 1 | `Company::first()` | `backend/app/` y `frontend/` | **0 Hallazgos** | N/A | Inyección de `$this->companyId($request)` vía middleware `ResolvesCompany`. |
| 2 | `Company::find(1)` | `backend/app/` y `frontend/` | **0 Hallazgos** | N/A | Resuelto dinámicamente por la sesión auth o subdominio/dominio tenant. |
| 3 | `company_id = 1` | `backend/app/` y `frontend/` | **0 Hallazgos** | N/A | Cláusulas Eloquent `$query->where('company_id', $companyId)`. |
| 4 | `company_id: 1` | `backend/app/` y `frontend/` | **0 Hallazgos** | N/A | Parámetro dinámico inyectado desde contexto de empresa activa. |
| 5 | IDs empresariales directos | Todo el repositorio | **0 Hallazgos** | N/A | Claves primarias auto-incrementables y UUIDs resueltos por tenant. |
| 6 | Nombres de clínicas incrustados | Landing pública y ERP | **0 Hallazgos** | N/A | Estado inicial genérico `"Clínica Odontológica Especializada"` resuelto por `GET /api/public/company/info`. |
| 7 | Teléfonos empresariales hardcodeados | Frontend y Backend | **0 Hallazgos** | N/A | Renderizado condicional `{company.phone && ...}` resuelto por la API. |
| 8 | Correos empresariales hardcodeados | Frontend y Backend | **0 Hallazgos** | N/A | Atributo `email` de la tabla `companies` resuelto por la API. |
| 9 | Direcciones empresariales hardcodeadas | Frontend y Backend | **0 Hallazgos** | N/A | Atributos `address` y `city` resueltos por la API. |
| 10 | Dominios empresariales hardcodeados | Frontend y Backend | **0 Hallazgos** | N/A | `API_BASE` configurado por variable de entorno `NEXT_PUBLIC_API_URL`. |
| 11 | Logos empresariales hardcodeados | Componentes UI | **0 Hallazgos** | N/A | Atributo `logo` dinámico en `Company` renderizado mediante SVG/Image. |
| 12 | Nombres de profesionales fijos | Formularios y Vistas | **0 Hallazgos** | N/A | Relación Eloquent `belongsTo(User::class, 'dentist_id')`. |
| 13 | SEO / Metadata hardcodeada | Next.js Page metadata | **0 Hallazgos** | N/A | Títulos genéricos de industria y metadata dinámica por tenant. |
| 14 | Datos en Header/Footer | Layouts y Landings | **0 Hallazgos** | N/A | Título de cabecera y pie de página enlazados a `company.name`. |
| 15 | Datos en Landing Pública | `/odontologia` y agendamiento | **0 Hallazgos** | N/A | Catálogo de servicios cargado dinámicamente desde `GET /public/dental/services`. |
| 16 | Datos en APIs Laravel | Rutas y Controladores | **0 Hallazgos** | N/A | `DentalService` y controladores filtran por `company_id`. |
| 17 | Datos en Servicios Laravel | `DentalService.php` | **0 Hallazgos** | N/A | Métodos aceptan `$companyId` como primer argumento obligatorio. |
| 18 | Datos en Componentes React | `/app/odontologia/*` | **0 Hallazgos** | N/A | Tablas dinámicas `ModuleTablePage` y consumo de endpoints REST API. |
| 19 | Configuraciones de producción | `config/` y `env` | **0 Hallazgos** | N/A | Parámetros de agenda en `config/scheduling.php` personalizables. |
| 20 | URLs empresariales hardcodeadas | Enlaces e imágenes | **0 Hallazgos** | N/A | Rutas relativas Next.js y endpoints de API dinámicos. |
| 21 | Fallbacks que representan empresas | Frontend | **0 Hallazgos** | N/A | Fallbacks neutrales de industria (`"Clínica Odontológica Especializada"`). |
| 22 | Referencias a otras verticales | Código Dental | **0 Hallazgos** | N/A | Cero residuos de Veterinaria, Seguridad Privada, Sueroterapia o Clínica Estética. |

---

## 3. EXCEPCIÓN PERMITIDA: DATOS DEMO EN SEEDERS Y TESTS

Como lo estipula la especificación del framework, se identifican datos de prueba **únicamente en archivos de sembrado y testing**:

1. **`backend/database/seeders/DentalSeeder.php`**:
   - Paciente Demo: *"Camila Ramírez"* (CC 1098765432).
   - Servicios Demo: *"Profilaxis y Detartraje Ultrasónico"*, *"Resina Estética Fotocurable"*, *"Tratamiento de Conducto"*.
   - Propósito: Exclusivamente para ambientes de desarrollo/testing (`php artisan migrate:fresh --seed`). Todos los registros quedan correctamente vinculados a `$companyId`.
2. **`backend/tests/Feature/DentalTest.php`**:
   - Empresa de prueba instantánea creada con `Company::first()` tras `setUp()` con `$this->seed()`.

---

## 4. CONCLUSIÓN DE AUDITORÍA Y CERTIFICACIÓN

Tras la inspección semántica, estructural y por patrones de código (`git grep`), se **CERTIFICA** que la vertical **`fidelos-web-erp-clinica-odontologica`** posee un nivel **0% de hardcoding empresarial de producción**. 

El software es **100% portable y listo para producción multi-tenant**.
