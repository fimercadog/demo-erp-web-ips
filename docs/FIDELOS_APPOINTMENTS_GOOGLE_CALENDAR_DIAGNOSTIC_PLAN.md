# FIDELOS CORE — DIAGNÓSTICO Y PLAN DE IMPLEMENTACIÓN DEL MOTOR TRANSVERSAL DE CITAS Y SINCRONIZACIÓN CON GOOGLE CALENDAR

**Fecha:** 27 de Septiembre de 2026  
**Rama activa:** `fidelos-web-erp-clinica-odontologica`  
**Estado:** Diagnóstico y Arquitectura — En espera de aprobación explícita del usuario  

---

## 1. RESUMEN EJECUTIVO Y OBJETIVOS

El objetivo de esta especificación es diseñar e implementar un **Motor Transversal de Agendamiento de Citas Públicas con Sincronización Bidireccional en Tiempo Real con Google Calendar** incorporado directamente en el **FidelOS Core**, habilitando su consumo directo en las 5 verticales de salud y servicio con agendamiento público:

1. `fidelos-web-erp-ips` (IPS / Salud Humana)
2. `fidelos-web-erp-clinica-estetica` (Medicina Estética & Spa)
3. `fidelos-web-erp-veterinaria` (Clínica Veterinaria & Mascotas)
4. `fidelos-web-erp-clinica-odontologica` (Clínica Odontológica & Salud Oral)
5. `fidelos-web-erp-sueroterapia-domicilio` (Sueroterapia & Enfermería a Domicilio)

### Principio Arquitectónico Fundacional
> **"Core = Lógica Transversal + BD Transversal + UI Transversal. Vertical = Configuración + Branding + Flujo Específico de Industria."**

No se duplicará el motor de agenda ni la sincronización con Google Calendar en cada vertical. El motor reside en el Core (`backend/app/Services/SchedulingService.php` y `backend/app/Services/GoogleCalendarService.php`), opera de forma multi-tenant aislada por `company_id`, y expone componentes UI reutilizables en el Frontend Next.js.

---

## 2. DIAGNÓSTICO DE ARQUITECTURA Y ESTADO ACTUAL DE LA BASE DE DATOS

### 2.1 Tablas Existentes en FidelOS Core
1. **`appointments`**:
   - `id`, `company_id`, `branch_id`, `patient_id`, `client_id`, `service_id`, `practitioner_id` (FK a `users`).
   - `starts_at` (dateTime), `ends_at` (dateTime), `duration_minutes` (default 30).
   - `resource` (string, consultorio/box/sillón), `reason`, `status` (`scheduled`, `confirmed`, `attended`, `no_show`, `cancelled`).
   - `price`, `payment_status`, `reminder_sent`, `cancellation_reason`, `notes`.
   - `is_domiciliary`, `address`, `city`, `neighborhood`, `address_reference`, `dispatch_status`.

2. **`services`**:
   - `id`, `company_id`, `name`, `code`, `description`, `price`, `duration_minutes`, `is_active`.

3. **`users` (Practitioners / Profesionales)**:
   - Contiene los médicos, odontólogos, cosmetólogas, veterinarios y enfermeros.
   - Campos de horario/disponibilidad laboral asignable.

4. **`branches` (Sedes / Ubicaciones)**:
   - Multi-sede por `company_id`.

---

## 3. CATÁLOGO DE NUEVOS ESQUEMAS Y MIGRACIONES EN CORE

Para soportar Google Calendar 2-Way Sync, añadiremos 2 nuevas migraciones en el Core de Laravel:

### 3.1 Migración 1: `2026_09_27_000015_create_google_calendar_integrations_table.php`

```php
Schema::create('google_calendar_integrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete(); // Profesional/Practitioner asociado
    $table->string('google_email');
    $table->string('google_calendar_id')->default('primary');
    $table->text('access_token'); // Encriptado vía Laravel Crypt
    $table->text('refresh_token')->nullable(); // Encriptado vía Laravel Crypt
    $table->timestamp('expires_at')->nullable();
    $table->boolean('sync_enabled')->default(true);
    $table->string('sync_direction')->default('two_way'); // two_way, export_only, import_only
    $table->timestamp('last_synced_at')->nullable();
    $table->text('sync_error')->nullable();
    $table->timestamps();

    $table->index(['company_id', 'user_id']);
});
```

### 3.2 Migración 2: `2026_09_27_000016_add_google_calendar_fields_to_appointments_table.php`

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->string('google_calendar_id')->nullable()->after('dispatch_status');
    $table->string('google_event_id')->nullable()->after('google_calendar_id');
    $table->string('sync_status')->default('pending')->after('google_event_id'); // synced, pending, error
    $table->timestamp('last_synced_at')->nullable()->after('sync_status');
    $table->text('sync_error')->nullable()->after('last_synced_at');

    $table->index(['company_id', 'google_event_id']);
});
```

---

## 4. ARQUITECTURA DEL BACKEND (SERVICIOS Y CONTROLADORES EN LARAVEL)

```
backend/app/
├── Models/
│   ├── Appointment.php (Extend con relaciones y accesores Google)
│   └── GoogleCalendarIntegration.php (Modelo Eloquent con tokens encriptados)
├── Services/
│   ├── SchedulingService.php (Cálculo de disponibilidad combinada FidelOS + Google FreeBusy)
│   └── GoogleCalendarService.php (OAuth2, Sync 2-Way, FreeBusy, Webhook handling)
└── Http/Controllers/Api/
    ├── SchedulingController.php (Endpoints públicos y privados de agendamiento)
    ├── GoogleCalendarOAuthController.php (Flujo OAuth2 de autorización por empresa/usuario)
    └── GoogleCalendarWebhookController.php (Recepción de push notifications de Google)
```

### 4.1 Lógica de Disponibilidad Combinada (`SchedulingService.php`)
1. Obtiene los slots teóricos de atención del profesional (ej: 08:00 - 17:00).
2. Consulta citas existentes en FidelOS para esa fecha (`status != cancelled`).
3. Si el profesional tiene activa la integración con Google Calendar:
   - Invoca `GoogleCalendarService::getFreeBusySlots(...)`.
   - Fusiona los bloques ocupados de Google Calendar con los de FidelOS.
4. Devuelve únicamente los intervalos de tiempo donde el profesional está 100% libre en ambos sistemas.

### 4.2 Sincronización Bidireccional (`GoogleCalendarService.php`)
- **FidelOS ➔ Google Calendar**:
  - Al agendar, editar o cancelar una cita en FidelOS, se crea/actualiza/elimina inmediatamente el evento en Google Calendar usando `google_event_id`.
  - Incluye detalles contextuales: Nombre del Paciente/Cliente, Servicio, Modalidad (Consultorio o Domicilio), Dirección.
- **Google Calendar ➔ FidelOS**:
  - Endpoint webhook (`/api/v1/webhooks/google-calendar`) o polling de sync que actualiza disponibilidad e impide doble agendamiento cuando el profesional crea un evento personal en Google Calendar.

---

## 5. ARQUITECTURA DEL FRONTEND (NEXT.JS)

### 5.1 Componente Motor de Agendamiento Reutilizable
- `frontend/src/components/public/public-booking-engine.tsx`
  - Reutilizable para las 5 verticales.
  - Wizard interactivo de 4 pasos:
    1. **Paso 1: Selección de Servicio / Tratamiento**: Muestra duración, precio y descripción.
    2. **Paso 2: Selección de Profesional / Especialista y Sede**: Muestra agenda y modalidad (presencial o domicilio).
    3. **Paso 3: Selector de Fecha y Hora**: Consulta la disponibilidad combinada en tiempo real (FidelOS DB + Google Calendar FreeBusy).
    4. **Paso 4: Datos del Paciente/Cliente y Confirmación**: Emite confirmación inmediata y enlace directo "Agregar a mi Google Calendar".

### 5.2 Adaptación de las 5 Páginas Públicas de Agendamiento
| Vertical | Ruta Pública | Configuración del `<PublicBookingEngine />` |
|---|---|---|
| **IPS** | `/agendar-cita` | `vertical="ips"`, especialidades médicas, médicos, consultorios. |
| **Clínica Estética** | `/agendar-cita-estetica` | `vertical="estetica"`, tratamientos faciales/corporales, especialistas. |
| **Veterinaria** | `/agendar-cita-veterinaria` | `vertical="veterinaria"`, servicios vet, tipo de mascota (perro/gato/etc.). |
| **Odontología** | `/agendar-cita-odontologica` | `vertical="odontologia"`, valoraciones, tratamientos dentales, odontólogos. |
| **Sueroterapia** | `/agendar-cita-sueroterapia` | `vertical="sueroterapia"`, sueros, enfermero/médico a domicilio + dirección. |

### 5.3 Módulo Administrativo ERP (UI de Gestión)
- `/app/agenda`: Vista de calendario interactivo (Día, Semana, Mes) con estados de cita y badge de sincronización con Google Calendar.
- `/app/configuracion/integraciones/google-calendar`: Pantalla de administración multi-tenant donde cada empresa o profesional vincula su cuenta de Google Calendar de forma segura.

---

## 6. SEGURIDAD, MULTI-TENANT Y CERO HARDCODING

1. **Aislamiento Multi-Tenant**: Cada integración y cita está estrictamente filtrada por `company_id`.
2. **Encriptación de Tokens**: `access_token` y `refresh_token` se almacenan encriptados en MariaDB usando `Crypt::encryptString`.
3. **Cero Datos Empresariales Hardcodeados**: No se usan correos, IDs ni credenciales escritas directamente en el código; la configuración OAuth se lee desde `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).

---

## 7. PLAN DE PRUEBAS Y CERTIFICACIÓN

1. **Migraciones & Seeds**: `php artisan migrate:fresh --seed`
2. **Pruebas Unitarias & Integración (PHPUnit)**:
   - `GoogleCalendarServiceTest.php`
   - `UnifiedSchedulingTest.php`
   - Pruebas de regresión del suite completo (**>316 pruebas**).
3. **Build de Producción Next.js**: `npm run build` sin errores de compilación TypeScript.
4. **Reporte de Certificación**: Documentación detallada en `docs/FIDELOS_APPOINTMENTS_GOOGLE_CALENDAR_CERTIFICATION.md`.

---

## 8. SOLICITUD DE APROBACIÓN

> **Se requiere confirmación explícita del usuario para iniciar la fase de codificación y ejecución del plan.**
