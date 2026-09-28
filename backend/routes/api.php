<?php

use App\Http\Controllers\Api\GoogleCalendarOAuthController;
use App\Http\Controllers\Api\GoogleCalendarWebhookController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DomiciliaryAppointmentController;
use App\Http\Controllers\Api\PublicDomiciliarySchedulingController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\ThreeWayMatchingController;
use App\Http\Controllers\Api\AccountPayableController;
use App\Http\Controllers\Api\AccountReceivableController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DentalConsentController;
use App\Http\Controllers\Api\DentalEvolutionController;
use App\Http\Controllers\Api\OdontogramController;
use App\Http\Controllers\Api\PublicDentalSchedulingController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\BreedController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\CashRegisterController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientNoteController;
use App\Http\Controllers\Api\ClinicalApplicationController;
use App\Http\Controllers\Api\ClinicalReportController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContingencyController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\DebitNoteController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PortalAppointmentController;
use App\Http\Controllers\Api\PortalAuthController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\ProductBatchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicAppointmentController;
use App\Http\Controllers\Api\PublicCatalogController;
use App\Http\Controllers\Api\PublicSchedulingController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SegmentController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SpeciesController;
use App\Http\Controllers\Api\StockAlertController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas sin sesion: throttle por IP para frenar fuerza bruta / enumeracion.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

// Webhook público de Google Calendar Push Notifications (Idempotente)
Route::post('/webhooks/google-calendar', [GoogleCalendarWebhookController::class, 'handle']);

// Formularios publicos del sitio de marketing (demo / contacto).
Route::get('/public/company/info', [CompanyController::class, 'publicInfo'])->middleware('throttle:catalog-read');
Route::post('/public/leads', [LeadController::class, 'store'])->middleware('throttle:5,1');

// Portal publico "Solicita tu cita": genera un Lead (source=appointment).
Route::post('/public/appointments', [PublicAppointmentController::class, 'store'])->middleware('throttle:appointment-request');

// Catalogo publico: navegable por visitantes anonimos. La solicitud de
// cotizacion entra al CRM como Cliente + Quote en borrador.
Route::prefix('public/catalog')->group(function (): void {
    // Limiters con nombre (contador propio, ver AppServiceProvider): navegar el
    // catalogo no consume la cuota de "solicitar cotizacion".
    Route::middleware('throttle:catalog-read')->group(function (): void {
        Route::get('/products', [PublicCatalogController::class, 'products']);
        Route::get('/products/{id}', [PublicCatalogController::class, 'product'])->whereNumber('id');
        Route::get('/categories', [PublicCatalogController::class, 'categories']);
    });
    Route::post('/quote-requests', [PublicCatalogController::class, 'storeQuoteRequest'])->middleware('throttle:catalog-quote');
});

// Portal publico "Agendar cita" (S13): disponibilidad real + cita
// auto-confirmada. Complementa /public/appointments (solo Lead) de arriba.
Route::prefix('public/appointments')->group(function (): void {
    Route::middleware('throttle:catalog-read')->group(function (): void {
        Route::get('/services', [PublicSchedulingController::class, 'services']);
        Route::get('/species', [PublicSchedulingController::class, 'species']);
        Route::get('/species/{id}/breeds', [PublicSchedulingController::class, 'breeds'])->whereNumber('id');
        Route::get('/availability', [PublicSchedulingController::class, 'availability']);
    });
    Route::post('/book', [PublicSchedulingController::class, 'book'])->middleware('throttle:appointment-booking');
});

Route::prefix('public/domiciliary')->group(function (): void {
    Route::get('/services', [PublicDomiciliarySchedulingController::class, 'services'])->middleware('throttle:catalog-read');
    Route::post('/book', [PublicDomiciliarySchedulingController::class, 'book'])->middleware('throttle:appointment-booking');
});

Route::prefix('public/dental')->group(function (): void {
    Route::get('/services', [PublicDentalSchedulingController::class, 'services'])->middleware('throttle:catalog-read');
    Route::post('/book', [PublicDentalSchedulingController::class, 'book'])->middleware('throttle:appointment-booking');
});

// Portal del dueño (S14): login sin password por enlace mágico + CRUD de sus
// propias citas. Guard `client`, separado del panel de staff.
Route::post('/portal/login', [PortalAuthController::class, 'requestLink'])->middleware('throttle:portal-login');
Route::get('/portal/consume/{client}', [PortalAuthController::class, 'consume'])
    ->name('portal.consume')
    ->whereNumber('client')
    ->middleware(['signed', 'throttle:portal-consume']);

Route::middleware('auth:client')->prefix('portal')->group(function (): void {
    Route::get('/me', [PortalAuthController::class, 'me']);
    Route::post('/logout', [PortalAuthController::class, 'logout']);
    Route::get('/appointments', [PortalAppointmentController::class, 'index']);
    Route::patch('/appointments/{id}/reschedule', [PortalAppointmentController::class, 'reschedule'])->whereNumber('id');
    Route::post('/appointments/{id}/cancel', [PortalAppointmentController::class, 'cancel'])->whereNumber('id');
});

Route::middleware('auth:sanctum')->group(function (): void {
    // Sin permiso: cualquier usuario autenticado.
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Integración Google Calendar
    Route::get('/integrations/google-calendar/connect', [GoogleCalendarOAuthController::class, 'redirect']);
    Route::get('/integrations/google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback']);
    Route::get('/integrations/google-calendar/status', [GoogleCalendarOAuthController::class, 'status']);
    Route::delete('/integrations/google-calendar/disconnect', [GoogleCalendarOAuthController::class, 'disconnect']);

    // Cada recurso exige el permiso Spatie correspondiente (mismo mapa que el
    // menu del frontend). `can:` responde 403 si el usuario no lo tiene.
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view');
    Route::get('/reports', ReportController::class)->middleware('can:reports.view');
    Route::get('/reports/commercial', [ReportController::class, 'commercial'])->middleware('can:reports.view');
    Route::get('/reports/clinical', ClinicalReportController::class)->middleware('can:clinical_reports.view');

    // Modo contingencia: el estado lo lee cualquier usuario (para renderizar el
    // banner y el modo solo-lectura); activar/desactivar exige settings.manage.
    Route::get('/contingency/status', [ContingencyController::class, 'status']);
    Route::post('/contingency/activate', [ContingencyController::class, 'activate'])->middleware('can:settings.manage');
    Route::post('/contingency/deactivate', [ContingencyController::class, 'deactivate'])->middleware('can:settings.manage');

    Route::get('/leads', [LeadController::class, 'index'])->middleware('can:leads.view');
    Route::post('/leads', [LeadController::class, 'storeManual'])->middleware('can:leads.view');
    Route::match(['put', 'patch'], '/leads/{lead}', [LeadController::class, 'update'])->middleware('can:leads.view');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->middleware('can:leads.view');

    Route::get('/company', [CompanyController::class, 'show'])->middleware('can:settings.manage');
    Route::put('/company', [CompanyController::class, 'update'])->middleware('can:settings.manage');

    Route::post('/branches/{id}/assign-user', [BranchController::class, 'assignUser'])->middleware('can:settings.manage')->whereNumber('id');
    Route::apiResource('branches', BranchController::class)->middleware('can:settings.manage');

    // CRM
    // El borrado permanente de clientes exige su propio permiso: Ventas crea y
    // edita (clients.manage) pero no hace hard-delete (solo roles administrativos
    // tienen clients.delete). Sin historial -> se borra; con historial la FK
    // RESTRICT del BaseCrudController responde 422 ("marcalo como inactivo").
    Route::apiResource('clients', ClientController::class)->except('destroy')->middleware('can:clients.manage');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->middleware('can:clients.delete');
    Route::get('/clients/{client}/history', [ClientController::class, 'history'])->middleware('can:clients.manage');
    Route::apiResource('contacts', ContactController::class)->middleware('can:clients.manage');
    Route::apiResource('segments', SegmentController::class)->middleware('can:clients.manage');
    Route::apiResource('client-notes', ClientNoteController::class)->only(['index', 'store', 'destroy'])->middleware('can:clients.manage');
    Route::apiResource('deals', DealController::class)->middleware('can:deals.manage');
    Route::apiResource('activities', ActivityController::class)->middleware('can:activities.manage');

    Route::apiResource('quotes', QuoteController::class)->middleware('can:deals.manage');
    Route::post('/quotes/{quote}/items', [QuoteController::class, 'addItem'])->middleware('can:deals.manage');
    Route::delete('/quotes/{quote}/items/{item}', [QuoteController::class, 'removeItem'])->middleware('can:deals.manage');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->middleware('can:deals.manage');
    Route::post('/quotes/{quote}/respond', [QuoteController::class, 'respond'])->middleware('can:deals.manage');
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert'])->middleware('can:deals.manage');

    // Inventario
    Route::get('/product-batches/expiring-report', [ProductBatchController::class, 'expiringReport'])->middleware('can:products.manage');
    Route::get('/product-batches/{id}/traceability', [ProductBatchController::class, 'traceability'])->middleware('can:products.manage')->whereNumber('id');
    Route::apiResource('product-batches', ProductBatchController::class)->only(['index', 'show', 'store'])->middleware('can:products.manage');
    Route::apiResource('products', ProductController::class)->middleware('can:products.manage');
    Route::post('/products/{id}/image', [ProductController::class, 'image'])->middleware('can:products.manage')->whereNumber('id');
    Route::apiResource('categories', CategoryController::class)->middleware('can:products.manage');
    Route::apiResource('brands', BrandController::class)->middleware('can:products.manage');
    Route::apiResource('units', UnitController::class)->middleware('can:products.manage');
    Route::apiResource('warehouses', WarehouseController::class)->middleware('can:warehouses.manage');
    Route::apiResource('suppliers', SupplierController::class)->middleware('can:suppliers.manage');
    Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'store'])->middleware('can:stock.manage');
    Route::apiResource('stock-transfers', StockTransferController::class)->only(['index', 'store'])->middleware('can:stock.manage');
    Route::get('/stock-alerts', StockAlertController::class)->middleware('can:products.manage');

    Route::apiResource('purchase-orders', PurchaseOrderController::class)->middleware('can:purchase_orders.manage');
    Route::post('/purchase-orders/{purchase_order}/items', [PurchaseOrderController::class, 'addItem'])->middleware('can:purchase_orders.manage');
    Route::delete('/purchase-orders/{purchase_order}/items/{item}', [PurchaseOrderController::class, 'removeItem'])->middleware('can:purchase_orders.manage');
    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->middleware('can:purchase_orders.manage');
    Route::apiResource('purchase-receipts', PurchaseReceiptController::class)->only(['index', 'show', 'store'])->middleware('can:purchase_receipts.manage');

    // Puente CRM <-> Inventario: un pedido consume stock al confirmarse.
    Route::apiResource('orders', OrderController::class)->middleware('can:orders.manage');
    Route::post('/orders/{order}/items', [OrderController::class, 'addItem'])->middleware('can:orders.manage');
    Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem'])->middleware('can:orders.manage');
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm'])->middleware('can:orders.manage');

    // ERP Pyme V1: facturacion interna, cartera, pagos y caja.
    Route::apiResource('invoices', InvoiceController::class)->middleware('can:invoices.manage');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('can:invoices.manage');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('can:invoices.manage');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->middleware('can:invoices.manage');
    Route::apiResource('credit-notes', CreditNoteController::class)->only(['index', 'show', 'store'])->middleware('can:invoices.manage');
    Route::apiResource('debit-notes', DebitNoteController::class)->only(['index', 'show', 'store'])->middleware('can:invoices.manage');
    Route::apiResource('sales-returns', SalesReturnController::class)->only(['index', 'show', 'store'])->middleware('can:invoices.manage');
    Route::apiResource('subscription-plans', SubscriptionPlanController::class)->middleware('can:invoices.manage');
    Route::post('/subscriptions/process-billing', [SubscriptionController::class, 'processBilling'])->middleware('can:invoices.manage');
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause'])->middleware('can:invoices.manage');
    Route::post('/subscriptions/{id}/resume', [SubscriptionController::class, 'resume'])->middleware('can:invoices.manage');
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])->middleware('can:invoices.manage');
    Route::apiResource('subscriptions', SubscriptionController::class)->middleware('can:invoices.manage');
    Route::apiResource('purchase-returns', PurchaseReturnController::class)->only(['index', 'show', 'store'])->middleware('can:purchase_orders.manage');
    Route::get('/accounts-receivable/aging', [AccountReceivableController::class, 'aging'])->middleware('can:accounts_receivable.view');
    Route::get('/accounts-receivable/summary', [AccountReceivableController::class, 'summary'])->middleware('can:accounts_receivable.view');
    Route::apiResource('accounts-receivable', AccountReceivableController::class)->only(['index', 'show'])->middleware('can:accounts_receivable.view');
    Route::get('/accounts-payable/aging', [AccountPayableController::class, 'aging'])->middleware('can:accounts_payable.view');
    Route::get('/accounts-payable/summary', [AccountPayableController::class, 'summary'])->middleware('can:accounts_payable.view');
    Route::apiResource('accounts-payable', AccountPayableController::class)->only(['index', 'show'])->middleware('can:accounts_payable.view');
    Route::get('/accounting/chart', [AccountingController::class, 'getChart']);
    Route::post('/accounting/chart', [AccountingController::class, 'storeAccount']);
    Route::get('/accounting/entries', [AccountingController::class, 'getEntries']);
    Route::post('/accounting/entries', [AccountingController::class, 'storeEntry']);
    Route::get('/accounting/trial-balance', [AccountingController::class, 'getTrialBalance']);
    Route::get('/accounting/general-ledger', [AccountingController::class, 'getGeneralLedger']);

    Route::get('/finance/bank-statements', [BankReconciliationController::class, 'index']);
    Route::post('/finance/bank-statements/import', [BankReconciliationController::class, 'import']);
    Route::get('/finance/bank-statements/{id}', [BankReconciliationController::class, 'show']);
    Route::post('/finance/bank-statements/{id}/auto-match', [BankReconciliationController::class, 'autoMatch']);
    Route::get('/finance/bank-statements/{id}/report', [BankReconciliationController::class, 'report']);
    Route::post('/finance/bank-reconciliations/manual', [BankReconciliationController::class, 'manualMatch']);
    Route::post('/finance/bank-reconciliations/{id}/unmatch', [BankReconciliationController::class, 'unmatch']);

    Route::get('/purchases/{id}/three-way-match', [ThreeWayMatchingController::class, 'matchAnalysis'])->middleware('can:purchase_orders.manage');
    Route::post('/purchases/{id}/three-way-match/evaluate', [ThreeWayMatchingController::class, 'evaluate'])->middleware('can:purchase_orders.manage');
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store'])->middleware('can:payments.manage');
    Route::apiResource('cash-registers', CashRegisterController::class)->middleware('can:cash.manage');
    Route::apiResource('cash-sessions', CashSessionController::class)->only(['index', 'show', 'store'])->middleware('can:cash.manage');
    Route::post('/cash-sessions/{cash_session}/close', [CashSessionController::class, 'close'])->middleware('can:cash.manage');
    Route::apiResource('cash-movements', CashMovementController::class)->only(['index', 'show'])->middleware('can:cash.manage');

    // RRHH - Asistencia Basica
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendance/novedad', [AttendanceController::class, 'registerNovedad']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/status', [AttendanceController::class, 'currentStatus']);
    Route::get('/attendance/summary', [AttendanceController::class, 'summary']);

    // Vertical Sueroterapia a Domicilio
    Route::get('/domiciliary-appointments', [DomiciliaryAppointmentController::class, 'index'])->middleware('can:appointments.manage');
    Route::post('/domiciliary-appointments', [DomiciliaryAppointmentController::class, 'store'])->middleware('can:appointments.manage');
    Route::post('/domiciliary-appointments/{id}/dispatch', [DomiciliaryAppointmentController::class, 'updateDispatch'])->middleware('can:appointments.manage');
    Route::post('/domiciliary-appointments/{id}/execute', [DomiciliaryAppointmentController::class, 'executeTherapy'])->middleware('can:appointments.manage');

    // --- Clínica veterinaria ---
    Route::apiResource('services', ServiceController::class)->middleware('can:services.manage');
    Route::apiResource('species', SpeciesController::class)->middleware('can:patients.manage');
    Route::apiResource('breeds', BreedController::class)->middleware('can:patients.manage');
    Route::apiResource('patients', PatientController::class)->middleware('can:patients.manage');
    Route::post('/patients/{id}/photo', [PatientController::class, 'photo'])->middleware('can:patients.manage')->whereNumber('id');
    Route::post('/patients/{id}/restore', [PatientController::class, 'restore'])->middleware('can:patients.manage')->whereNumber('id');

    Route::apiResource('consultations', ConsultationController::class)->middleware('can:medical_records.manage');
    Route::post('/consultations/{id}/restore', [ConsultationController::class, 'restore'])->middleware('can:medical_records.manage')->whereNumber('id');

    Route::get('/clinical-applications/due', [ClinicalApplicationController::class, 'due'])->middleware('can:vaccinations.manage');
    Route::apiResource('clinical-applications', ClinicalApplicationController::class)->middleware('can:vaccinations.manage');
    Route::post('/clinical-applications/{id}/restore', [ClinicalApplicationController::class, 'restore'])->middleware('can:vaccinations.manage')->whereNumber('id');

    Route::apiResource('diagnoses', DiagnosisController::class)->middleware('can:medical_records.manage');

    Route::get('/prescriptions/{id}/pdf', [PrescriptionController::class, 'pdf'])->middleware('can:prescriptions.manage')->whereNumber('id');
    Route::apiResource('prescriptions', PrescriptionController::class)->only(['index', 'show', 'store', 'destroy'])->middleware('can:prescriptions.manage');

    Route::apiResource('procedures', ProcedureController::class)->middleware('can:procedures.manage');
    Route::post('/procedures/{id}/consent', [ProcedureController::class, 'consent'])->middleware('can:procedures.manage')->whereNumber('id');
    Route::get('/procedures/{id}/consent-document', [ProcedureController::class, 'consentDocument'])->middleware('can:procedures.manage')->whereNumber('id');
    Route::post('/procedures/{id}/restore', [ProcedureController::class, 'restore'])->middleware('can:procedures.manage')->whereNumber('id');

    Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->middleware('can:appointments.manage');
    Route::apiResource('appointments', AppointmentController::class)->middleware('can:appointments.manage');
    Route::post('/appointments/{id}/confirm', [AppointmentController::class, 'confirm'])->middleware('can:appointments.manage')->whereNumber('id');
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->middleware('can:appointments.manage')->whereNumber('id');
    Route::post('/appointments/{id}/attended', [AppointmentController::class, 'markAttended'])->middleware('can:appointments.manage')->whereNumber('id');
    Route::post('/appointments/{id}/no-show', [AppointmentController::class, 'markNoShow'])->middleware('can:appointments.manage')->whereNumber('id');
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->middleware('can:appointments.manage')->whereNumber('id');

    // Odontología Vertical
    Route::get('/odontograms/patient/{patientId}', [OdontogramController::class, 'showByPatient'])->whereNumber('patientId');
    Route::post('/odontograms/{id}/tooth', [OdontogramController::class, 'saveTooth'])->whereNumber('id');
    Route::apiResource('odontograms', OdontogramController::class);

    Route::post('/treatment-plans/{id}/convert-to-invoice', [TreatmentPlanController::class, 'convertToInvoice'])->whereNumber('id');
    Route::apiResource('treatment-plans', TreatmentPlanController::class);

    Route::apiResource('dental-evolutions', DentalEvolutionController::class);
    Route::apiResource('dental-consents', DentalConsentController::class);

    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show'])->middleware('can:audit.view');
    Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('can:roles.manage');
    Route::apiResource('roles', RoleController::class)->only(['index', 'show', 'store', 'update'])->middleware('can:roles.manage');
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update'])->middleware('can:users.manage');

    // El permiso por recurso se valida dentro del controlador.
    Route::get('/exports/{resource}.{format}', ExportController::class)
        ->whereIn('resource', ['clients', 'deals', 'products', 'suppliers', 'stock-movements', 'purchase-orders', 'orders', 'invoices', 'accounts-receivable', 'accounts-payable', 'payments', 'cash-movements', 'audit-logs'])
        ->whereIn('format', ['csv', 'pdf']);
});

if (app()->environment('testing')) {
    Route::get('/test-403', fn () => abort(403, 'Acceso denegado simulado'));
    Route::get('/test-500', fn () => throw new \RuntimeException('Error simulado en backend'));
}

