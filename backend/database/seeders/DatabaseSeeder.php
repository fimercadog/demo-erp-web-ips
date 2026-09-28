<?php

namespace Database\Seeders;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\ClinicalApplication;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Diagnosis;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Segment;
use App\Models\Service;
use App\Models\Species;
use App\Models\Breed;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dataset demo de la vertical IPS — "NOVA IPS S.A.S.".
 *
 * Contenido 100% asistencial y coherente:
 * Pacientes, médicos especialistas, citas, historias clínicas, diagnósticos CIE-10,
 * recetas médicas, procedimientos ambulatorios, facturación electrónica asistencial,
 * cuentas médicas por cobrar, cuentas por pagar a proveedores y turnos de caja.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $company = Company::firstOrCreate([
            'name' => 'NOVA IPS S.A.S.',
        ], [
            'nit' => '901.245.880-3',
            'email' => 'recepcion@novaips.test',
            'phone' => '+57 601 555 0188',
            'address' => 'Calle 93 #14-20, Bogotá',
            'timezone' => 'America/Bogota',
            'locale' => 'es',
        ]);

        [$users, $vets] = $this->seedRolesAndUsers($company);
        $admin = $users['admin@novaips.test'];
        $reception = $users['recepcion@novaips.test'];

        $warehouses = $this->seedWarehouses($company);
        $mainWarehouse = $warehouses['Farmacia / Vitrina'];

        [$products, $publicProducts] = $this->seedInventoryCatalog($company);
        $suppliers = $this->seedSuppliers($company);
        $this->seedCashRegisters($company);
        $segments = $this->seedSegments($company);
        $species = $this->seedSpeciesAndBreeds($company);
        $services = $this->seedServices($company);

        $clients = $this->seedOwners($company, $segments);
        $this->seedContacts($company, $clients);
        $patients = $this->seedPatients($company, $clients, $species);

        $this->seedStock($company, $products, $suppliers, $mainWarehouse, $warehouses);

        $appointments = $this->seedAppointments($company, $patients, $services, $vets);
        $consultations = $this->seedConsultations($company, $patients, $appointments, $vets);
        $diagnoses = $this->seedDiagnoses($company);
        $this->attachDiagnoses($consultations, $diagnoses);
        $this->seedClinicalApplications($company, $patients, $products, $vets, $mainWarehouse, $consultations);
        $this->seedPrescriptions($company, $consultations, $products, $vets);
        $this->seedProcedures($company, $patients, $services, $vets);

        $this->seedLeads($company);
        $this->seedProductSales($company, $clients, $publicProducts, $mainWarehouse, $reception);
        $this->seedSurgeryQuotes($company, $clients, $services);
        $this->seedWellnessDeals($company, $clients, $admin, $users['ventas@novaips.test']);
        $this->seedClientNotesAndTasks($company, $clients, $patients, $admin, $reception);
        $this->seedAuditLog($company, $admin, $clients, $patients);

        // Nuevos seeders asistenciales y financieros requeridos
        $this->seedInvoicesAndAccounts($company, $clients, $products, $reception, $mainWarehouse);
        $this->seedAccountsPayable($company, $suppliers);
        $this->seedCashSessions($company, $reception);
        $this->call(DentalSeeder::class);
    }

    // ---------------------------------------------------------------- usuarios

    /** @return array{0: array<string,User>, 1: array<int,User>} */
    private function seedRolesAndUsers(Company $company): array
    {
        $permissionNames = [
            'dashboard.view', 'leads.view', 'clients.manage', 'clients.delete', 'deals.manage', 'activities.manage',
            'products.manage', 'warehouses.manage', 'stock.manage', 'suppliers.manage', 'purchase_orders.manage',
            'purchase_receipts.manage', 'orders.manage', 'invoices.manage', 'accounts_receivable.view',
            'accounts_payable.view', 'payments.manage', 'cash.manage', 'reports.view',
            'users.manage', 'roles.manage', 'audit.view', 'settings.manage',
            'services.manage', 'patients.manage', 'appointments.manage', 'medical_records.manage',
            'vaccinations.manage', 'prescriptions.manage', 'procedures.manage', 'clinical_reports.view',
        ];

        $clinical = [
            'services.manage', 'patients.manage', 'appointments.manage', 'medical_records.manage',
            'vaccinations.manage', 'prescriptions.manage', 'procedures.manage', 'clinical_reports.view',
        ];

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'Super Admin' => $permissionNames,
            'Administrador de empresa' => $permissionNames,
            'Médico Especialista' => array_merge(['dashboard.view', 'clients.manage', 'orders.manage', 'reports.view'], $clinical),
            'Recepción' => [
                'dashboard.view', 'leads.view', 'clients.manage', 'patients.manage', 'services.manage',
                'appointments.manage', 'orders.manage', 'invoices.manage', 'accounts_receivable.view',
                'payments.manage', 'cash.manage', 'reports.view',
            ],
            'Ventas' => [
                'dashboard.view', 'leads.view', 'clients.manage', 'deals.manage', 'activities.manage',
                'orders.manage', 'invoices.manage', 'accounts_receivable.view', 'payments.manage', 'cash.manage', 'reports.view',
            ],
            'Inventario' => [
                'dashboard.view', 'products.manage', 'warehouses.manage', 'stock.manage', 'suppliers.manage',
                'purchase_orders.manage', 'purchase_receipts.manage', 'accounts_payable.view', 'payments.manage',
                'cash.manage', 'orders.manage', 'reports.view',
            ],
            'Usuario' => ['dashboard.view'],
        ];
        foreach ($roles as $roleName => $rolePermissions) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])->syncPermissions($rolePermissions);
        }

        $demo = [
            ['superadmin@novaips.test', 'Sofía Mercado', 'Super Admin'],
            ['admin@novaips.test', 'Camila Rojas', 'Administrador de empresa'],
            ['medico@novaips.test', 'Dr. Alejandro Morales', 'Médico Especialista'],
            ['medico2@novaips.test', 'Dra. Natalia Cárdenas', 'Médico Especialista'],
            ['recepcion@novaips.test', 'Marcela Duarte', 'Recepción'],
            ['inventario@novaips.test', 'Valentina Castro', 'Inventario'],
            ['ventas@novaips.test', 'Sebastián Moreno', 'Ventas'],
        ];

        $users = [];
        foreach ($demo as [$email, $name, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['company_id' => $company->id, 'name' => $name, 'password' => Hash::make('password'), 'status' => 'active'],
            );
            $user->syncRoles([$role]);
            $users[$email] = $user;
        }

        $vets = [$users['medico@novaips.test'], $users['medico2@novaips.test']];

        return [$users, $vets];
    }

    // -------------------------------------------------------------- inventario

    /** @return array<string,Warehouse> */
    private function seedWarehouses(Company $company): array
    {
        return collect([
            ['name' => 'Farmacia / Vitrina', 'location' => 'Recepción, planta baja'],
            ['name' => 'Depósito', 'location' => 'Bodega interna, segundo piso'],
        ])->mapWithKeys(fn ($data) => [
            $data['name'] => Warehouse::firstOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                $data + ['status' => 'active'],
            ),
        ])->all();
    }

    /** @return array{0: Collection<int,Product>, 1: Collection<int,Product>} */
    private function seedInventoryCatalog(Company $company): array
    {
        $categories = collect([
            ['Biológicos', 'ips'],
            ['Antiparasitarios', 'ips'],
            ['Farmacia', 'ips'],
            ['Alimento médico', 'ips'],
            ['Accesorios', 'ips'],
            ['Insumos médicos', 'ips'],
            ['Dermocosmética', 'estetica'],
            ['Higiene Oral', 'odontologia'],
            ['Suplementación', 'sueroterapia'],
            ['Veterinaria General', 'veterinaria'],
        ])->mapWithKeys(fn ($d) => [$d[0] => Category::firstOrCreate(
            ['company_id' => $company->id, 'name' => $d[0]],
            ['status' => 'active', 'vertical' => $d[1], 'is_public' => true]
        )]);

        $brands = collect(['Zoetis', 'MSD Salud', 'Virbac', 'Elanco', 'Royal Canin', "Hill's", 'Genérico', 'ISDIN', 'Curaprox'])
            ->mapWithKeys(fn ($name) => [$name => Brand::firstOrCreate(['company_id' => $company->id, 'name' => $name], ['status' => 'active'])]);
        $units = collect([
            ['name' => 'Unidad', 'abbreviation' => 'un'],
            ['name' => 'Frasco', 'abbreviation' => 'fco'],
            ['name' => 'Dosis', 'abbreviation' => 'dosis'],
            ['name' => 'Tableta', 'abbreviation' => 'tab'],
            ['name' => 'Bolsa', 'abbreviation' => 'bls'],
        ])->mapWithKeys(fn ($d) => [$d['name'] => Unit::firstOrCreate(['company_id' => $company->id, 'name' => $d['name']], $d + ['status' => 'active'])]);

        $rows = [
            ['VAC-DHPPI', 'Vacuna polivalente (DHPPi)', 'Biológicos', 'Zoetis', 'Dosis', 22000, 45000, 15, false, 'Vacuna múltiple asistencial.', 'ips'],
            ['VAC-RABIA', 'Vacuna antirrábica', 'Biológicos', 'MSD Salud', 'Dosis', 12000, 30000, 20, false, 'Antirrábica obligatoria.', 'ips'],
            ['VAC-TRIPLE-F', 'Vacuna triple asistencial', 'Biológicos', 'MSD Salud', 'Dosis', 24000, 48000, 10, false, 'Inmunización asistencial.', 'ips'],
            ['VAC-TOS', 'Vacuna estacional', 'Biológicos', 'Zoetis', 'Dosis', 20000, 42000, 8, false, 'Vacuna de refuerzo estacional.', 'ips'],
            ['ANTI-INT', 'Desparasitante amplio espectro', 'Antiparasitarios', 'Virbac', 'Tableta', 3500, 9000, 40, false, 'Tratamiento antiparasitario.', 'ips'],
            ['ANTI-EXT', 'Antiparasitario oral', 'Antiparasitarios', 'Elanco', 'Tableta', 28000, 55000, 25, false, 'Protección mensual oral.', 'ips'],
            ['ANTI-PIPE', 'Solución tópica antiséptica', 'Antiparasitarios', 'Virbac', 'Unidad', 18000, 38000, 20, false, 'Aplicación tópica asistencial.', 'ips'],
            ['FARM-AMOXI', 'Amoxicilina 250 mg', 'Farmacia', 'Genérico', 'Tableta', 900, 2500, 60, false, 'Antibiótico betalactámico.', 'ips'],
            ['FARM-MELOX', 'Meloxicam 1,5 mg/ml suspensión', 'Farmacia', 'Genérico', 'Frasco', 22000, 45000, 10, false, 'Antiinflamatorio no esteroideo.', 'ips'],
            ['FARM-SUERO', 'Suero fisiológico 500 ml', 'Farmacia', 'Genérico', 'Frasco', 4500, 9000, 30, false, 'Solución salina 0,9% para fluidoterapia.', 'ips'],
            ['FARM-GABA', 'Gabapentina 100 mg', 'Farmacia', 'Genérico', 'Tableta', 1200, 3200, 40, false, 'Analgésico neuropático.', 'ips'],
            ['ALIM-GASTRO', 'Suplemento nutricional 2 kg', 'Alimento médico', 'Royal Canin', 'Bolsa', 78000, 128000, 8, true, 'Suplemento nutricional clínico.', 'ips'],
            ['ALIM-RENAL', 'Fórmula de soporte renal 2 kg', 'Alimento médico', "Hill's", 'Bolsa', 92000, 148000, 6, true, 'Dieta de soporte asistencial.', 'ips'],
            ['ALIM-RECOV', 'Fórmula de recuperación', 'Alimento médico', 'Royal Canin', 'Unidad', 9000, 16000, 24, true, 'Alta energía para pacientes convalecientes.', 'ips'],
            ['ACC-COLLAR', 'Inmovilizador asistencial talla M', 'Accesorios', 'Genérico', 'Unidad', 8000, 18000, 15, true, 'Protector post-procedimiento.', 'ips'],
            ['ACC-SHAMP', 'Solución dermo-limpiadora 250 ml', 'Accesorios', 'Virbac', 'Frasco', 19000, 36000, 20, true, 'Uso dermo-asistencial.', 'ips'],
            ['ACC-DENTAL', 'Kit de higiene oral', 'Accesorios', 'Genérico', 'Unidad', 12000, 24000, 18, true, 'Kit de higiene oral asistencial.', 'ips'],
            ['INS-JERINGA', 'Jeringa 3 ml con aguja', 'Insumos médicos', 'Genérico', 'Unidad', 400, 0, 200, false, 'Insumo de uso interno.', 'ips'],
            ['INS-GUANTE', 'Guantes de examen (caja x100)', 'Insumos médicos', 'Genérico', 'Unidad', 22000, 0, 20, false, 'Nitrilo sin polvo.', 'ips'],
            // Productos Estética
            ['EST-SERUM-C', 'Serum Vitamina C Antioxidante 30ml', 'Dermocosmética', 'ISDIN', 'Frasco', 65000, 145000, 10, true, 'Sérum concentrado con Vitamina C pura y ácido hialurónico.', 'estetica'],
            ['EST-BLOQ-SOL', 'Fotoprotector Solar SPF 50+ 50ml', 'Dermocosmética', 'ISDIN', 'Frasco', 45000, 98000, 15, true, 'Fotoprotector ultraligero de fase acuosa.', 'estetica'],
            // Productos Odontología
            ['ODON-PASTA', 'Crema Dental Enzimática 75ml', 'Higiene Oral', 'Curaprox', 'Unidad', 18000, 42000, 20, true, 'Crema dental suave con enzimas naturales.', 'odontologia'],
            ['ODON-CEPILLO', 'Cepillo Dental Ultrasuave 5460', 'Higiene Oral', 'Curaprox', 'Unidad', 12000, 28000, 30, true, 'Filamentos CUREN de alta calidad para encías sensibles.', 'odontologia'],
            // Productos Sueroterapia
            ['SUERO-KIT-IMM', 'Kit Infusión Inmunológica Oral 10 Ampollas', 'Suplementación', 'Genérico', 'Unidad', 55000, 120000, 10, true, 'Suplemento líquido liposomado rico en Zinc y Vitamina C.', 'sueroterapia'],
        ];

        $products = collect($rows)->map(function (array $r) use ($company, $categories, $brands, $units) {
            [$sku, $name, $cat, $brand, $unit, $cost, $price, $reorder, $public, $desc, $vertical] = $r;

            return Product::firstOrCreate(
                ['company_id' => $company->id, 'sku' => $sku],
                [
                    'name' => $name,
                    'description' => $desc,
                    'category_id' => $categories[$cat]->id ?? null,
                    'brand_id' => $brands[$brand]->id ?? null,
                    'unit_id' => $units[$unit]->id ?? $units['Unidad']->id,
                    'cost_price' => $cost,
                    'unit_price' => $price,
                    'reorder_level' => $reorder,
                    'status' => 'active',
                    'is_public' => $public,
                    'vertical' => $vertical,
                ],
            );
        });

        $publicProducts = $products->filter(fn (Product $p) => $p->is_public)->values();

        return [$products, $publicProducts];
    }

    /** @return Collection<int,Supplier> */
    private function seedSuppliers(Company $company): Collection
    {
        return collect([
            ['name' => 'Distribuciones Médicas del Norte', 'contact_name' => 'Jorge Niño', 'email' => 'pedidos@distrimednorte.example'],
            ['name' => 'Promedical Colombia SAS', 'contact_name' => 'Marcela Durán', 'email' => 'ventas@promedical.example'],
            ['name' => 'Insumos Hospitalarios Mayorista', 'contact_name' => 'Ricardo Peña', 'email' => 'mayoristas@insumoshospitalarios.example'],
        ])->map(fn ($d) => Supplier::firstOrCreate(['company_id' => $company->id, 'name' => $d['name']], $d + ['status' => 'active']));
    }

    private function seedCashRegisters(Company $company): void
    {
        CashRegister::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Caja principal IPS'],
            ['status' => 'active'],
        );
    }

    /** @return array<string,Segment> */
    private function seedSegments(Company $company): array
    {
        return collect(['Particular', 'Convenio empresarial', 'Entidad de Salud', 'Seguro Médico'])
            ->mapWithKeys(fn ($name) => [$name => Segment::firstOrCreate(['company_id' => $company->id, 'name' => $name], ['status' => 'active'])])
            ->all();
    }

    // ---------------------------------------------------------- clínica: base

    /** @return array<string,Species> */
    private function seedSpeciesAndBreeds(Company $company): array
    {
        $species = Species::firstOrCreate(['company_id' => $company->id, 'name' => 'Humana'], ['status' => 'active']);
        Breed::firstOrCreate(['company_id' => $company->id, 'species_id' => $species->id, 'name' => 'General'], ['status' => 'active']);
        return ['Humana' => $species];
    }

    /** @return array<string,Service> */
    private function seedServices(Company $company): array
    {
        return collect([
            ['Consulta general', 'consulta', 30, 55000, 'ips', true],
            ['Consulta especializada', 'consulta', 45, 95000, 'ips', true],
            ['Consulta a domicilio', 'consulta', 60, 130000, 'ips', true],
            ['Vacunación asistencial', 'vacunacion', 15, 40000, 'ips', true],
            ['Desparasitación asistencial', 'vacunacion', 15, 28000, 'ips', true],
            ['Cirugía ambulatoria', 'cirugia', 120, 420000, 'ips', true],
            ['Procedimiento menor', 'cirugia', 90, 280000, 'ips', true],
            ['Lavado / Limpieza asistencial', 'cirugia', 75, 240000, 'ips', true],
            ['Curación / manejo de heridas', 'curacion', 20, 35000, 'ips', true],
            ['Observación asistencial (día)', 'hospitalizacion', null, 140000, 'ips', true],
            ['Toma de muestras / laboratorio', 'otro', 30, 45000, 'ips', true],
            ['Valoración asistencial integral', 'otro', 45, 180000, 'ips', true],
            // Estética
            ['Limpieza Facial Profunda', 'procedimiento', 60, 150000, 'estetica', true],
            ['Armonización Facial con Ácido Hialurónico', 'procedimiento', 45, 450000, 'estetica', true],
            ['Hidratación Labial & Perfilado', 'procedimiento', 45, 380000, 'estetica', true],
            ['Peeling Químico Médico', 'procedimiento', 30, 220000, 'estetica', true],
            // Veterinaria
            ['Consulta Veterinaria Canina/Felina', 'consulta', 30, 60000, 'veterinaria', true],
            ['Vacunación Séxtuple Canina', 'vacunacion', 20, 45000, 'veterinaria', true],
            ['Profilaxis Dental Veterinaria', 'procedimiento', 60, 220000, 'veterinaria', true],
            // Sueroterapia
            ['Suero Multivitamínico e Inmunológico', 'sueroterapia', 60, 180000, 'sueroterapia', true],
            ['Suero Detox & Antioxidante Hígado', 'sueroterapia', 60, 210000, 'sueroterapia', true],
            ['Suero Energizante & Recuperación deportiva', 'sueroterapia', 60, 195000, 'sueroterapia', true],
        ])->mapWithKeys(fn ($d) => [
            $d[0] => Service::firstOrCreate(
                ['company_id' => $company->id, 'name' => $d[0]],
                [
                    'type' => $d[1],
                    'estimated_duration_minutes' => $d[2],
                    'price' => $d[3],
                    'vertical' => $d[4],
                    'is_public' => $d[5],
                    'status' => 'active',
                ],
            ),
        ])->all();
    }

    // ------------------------------------------------------- clientes / pacientes

    /** @return Collection<int,Client> */
    private function seedOwners(Company $company, array $segments): Collection
    {
        $rows = [
            ['Camila Herrera', null, 'camila.herrera@gmail.com', '+57 310 555 0101', 'Cra 15 #85-40, Bogotá', 'Particular'],
            ['Andrés Vargas', null, 'andres.vargas@gmail.com', '+57 311 555 0102', 'Calle 53 #24-18, Bogotá', 'Particular'],
            ['Marcela Ríos', null, 'marcela.rios@hotmail.com', '+57 312 555 0103', 'Cra 7 #127-33, Bogotá', 'Particular'],
            ['Felipe Castaño', null, 'felipe.castano@gmail.com', '+57 313 555 0104', 'Calle 100 #19-54, Bogotá', 'Particular'],
            ['Diana Torres', null, 'diana.torres@gmail.com', '+57 314 555 0105', 'Cra 50 #6-20, Bogotá', 'Particular'],
            ['Juan David Peláez', null, 'jd.pelaez@gmail.com', '+57 315 555 0106', 'Calle 72 #10-34, Bogotá', 'Particular'],
            ['Laura Gutiérrez', null, 'laura.gutierrez@gmail.com', '+57 316 555 0107', 'Cra 19 #45-12, Bogotá', 'Particular'],
            ['Santiago Rojas', null, 'santiago.rojas@gmail.com', '+57 317 555 0108', 'Calle 140 #7-90, Bogotá', 'Particular'],
            ['Natalia Ospina', null, 'natalia.ospina@gmail.com', '+57 318 555 0109', 'Cra 24 #63-11, Bogotá', 'Particular'],
            ['EPS Sanitas Convenio', 'EPS Sanitas S.A.', 'contacto@eps-sanitas.example', '+57 601 555 0210', 'Cra 30 #12-45, Bogotá', 'Entidad de Salud'],
            ['Seguros Bolívar Salud', 'Seguros Bolívar', 'info@bolivarsalud.example', '+57 320 555 0211', 'Calle 26 #59-51, Bogotá', 'Seguro Médico'],
            ['Bienestar - Nexa BPO', 'Nexa BPO', 'bienestar@nexabpo.example', '+57 601 555 0212', 'Av 68 #40-11, Bogotá', 'Convenio empresarial'],
        ];

        return collect($rows)->map(fn ($d) => Client::firstOrCreate(
            ['company_id' => $company->id, 'email' => $d[2]],
            [
                'name' => $d[0], 'company_name' => $d[1], 'phone' => $d[3], 'address' => $d[4],
                'segment_id' => $segments[$d[5]]->id, 'status' => 'active',
            ],
        ));
    }

    private function seedContacts(Company $company, Collection $clients): void
    {
        collect([
            [9, 'Paola Méndez', 'Coordinadora de cuentas médicas', 'cuentas@eps-sanitas.example', '+57 320 555 0310'],
            [10, 'Hernán Cortés', 'Auditor médico de convenios', 'auditoria@bolivarsalud.example', '+57 321 555 0311'],
            [11, 'Ana María Lima', 'Líder de salud ocupacional', 'ana.lima@nexabpo.example', '+57 322 555 0312'],
        ])->each(fn ($d) => Contact::firstOrCreate(
            ['company_id' => $company->id, 'name' => $d[1], 'client_id' => $clients[$d[0]]->id],
            ['role' => $d[2], 'email' => $d[3], 'phone' => $d[4], 'status' => 'active'],
        ));
    }

    /** @return Collection<int,Patient> */
    private function seedPatients(Company $company, Collection $clients, array $species): Collection
    {
        $speciesId = $species['Humana']->id ?? null;

        $rows = [
            [0, 'Carlos Andrés Mendoza', 'CC', '1018293847', 'Carlos Andrés', 'Mendoza', 'male', '1988-03-14', 'Sura', 'O+', '+57 310 555 0101', 'carlos.mendoza@example.com'],
            [0, 'Mariana Gómez Ortiz', 'CC', '1020394857', 'Mariana', 'Gómez Ortiz', 'female', '1992-07-02', 'Coosalud', 'A+', '+57 311 555 0102', 'mariana.gomez@example.com'],
            [1, 'Lucía Fernández', 'CC', '52839201', 'Lucía', 'Fernández', 'female', '1995-11-20', 'Compensar', 'O-', '+57 312 555 0103', 'lucia.fernandez@example.com'],
            [1, 'Santiago Morales', 'CC', '1032948201', 'Santiago', 'Morales', 'male', '1985-02-10', 'Salud Total', 'B+', '+57 313 555 0104', 'santiago.morales@example.com'],
            [2, 'Valentina Torres', 'CC', '1015839201', 'Valentina', 'Torres', 'female', '1998-01-05', 'Sura', 'A-', '+57 314 555 0105', 'valentina.torres@example.com'],
            [3, 'Mateo Benítez', 'TI', '1098293847', 'Mateo', 'Benítez', 'male', '2012-05-30', 'Coosalud', 'O+', '+57 315 555 0106', 'mateo.benitez@example.com'],
            [3, 'Sofia Benítez', 'RC', '1192837465', 'Sofia', 'Benítez', 'female', '2018-01-18', 'Coosalud', 'O+', '+57 315 555 0107', 'sofia.benitez@example.com'],
            [4, 'Gabriel Silva', 'CC', '80192837', 'Gabriel', 'Silva', 'male', '1976-09-12', 'Compensar', 'AB+', '+57 316 555 0108', 'gabriel.silva@example.com'],
            [5, 'Camila Vargas', 'CC', '1028394812', 'Camila', 'Vargas', 'female', '1990-12-01', 'Sura', 'O+', '+57 317 555 0109', 'camila.vargas@example.com'],
            [6, 'Daniela Ríos', 'CC', '52938471', 'Daniela', 'Ríos', 'female', '1984-06-25', 'Salud Total', 'A+', '+57 318 555 0110', 'daniela.rios@example.com'],
            [7, 'Alejandro Castro', 'CC', '1019283746', 'Alejandro', 'Castro', 'male', '1989-08-08', 'Compensar', 'O+', '+57 319 555 0111', 'alejandro.castro@example.com'],
            [8, 'Isabela Restrepo', 'TI', '1082736451', 'Isabela', 'Restrepo', 'female', '2010-02-14', 'Sura', 'B-', '+57 320 555 0112', 'isabela.restrepo@example.com'],
            [9, 'Felipe Osorio', 'CC', '1027384950', 'Felipe', 'Osorio', 'male', '1993-04-03', 'Coosalud', 'O+', '+57 321 555 0113', 'felipe.osorio@example.com'],
            [10, 'Nicolás Suárez', 'CC', '1038472910', 'Nicolás', 'Suárez', 'male', '2000-06-11', 'Sura', 'A+', '+57 322 555 0114', 'nicolas.suarez@example.com'],
        ];

        return collect($rows)->map(fn ($d) => Patient::firstOrCreate(
            ['company_id' => $company->id, 'document_number' => $d[3]],
            [
                'client_id' => $clients[$d[0]]->id ?? null,
                'species_id' => $speciesId,
                'name' => $d[1],
                'document_type' => $d[2],
                'document_number' => $d[3],
                'first_name' => $d[4],
                'last_name' => $d[5],
                'sex' => $d[6],
                'birth_date' => $d[7],
                'eps' => $d[8],
                'blood_type' => $d[9],
                'phone' => $d[10],
                'email' => $d[11],
                'status' => 'active',
            ],
        ));
    }

    // ------------------------------------------------------------------ stock

    private function seedStock(
        Company $company,
        Collection $products,
        Collection $suppliers,
        Warehouse $mainWarehouse,
        array $warehouses,
    ): void {
        $po = PurchaseOrder::firstOrCreate(
            ['company_id' => $company->id, 'supplier_id' => $suppliers[0]->id, 'warehouse_id' => $mainWarehouse->id],
            ['status' => 'draft', 'order_date' => Carbon::today()->subDays(18), 'expected_date' => Carbon::today()->subDays(11), 'total' => 0],
        );
        if ($po->items()->count() === 0) {
            $lines = [
                [$products->firstWhere('sku', 'VAC-DHPPI'), 40],
                [$products->firstWhere('sku', 'VAC-RABIA'), 50],
                [$products->firstWhere('sku', 'ANTI-EXT'), 30],
                [$products->firstWhere('sku', 'FARM-AMOXI'), 120],
                [$products->firstWhere('sku', 'ALIM-GASTRO'), 12],
            ];
            $total = 0;
            foreach ($lines as [$product, $qty]) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id, 'product_id' => $product->id, 'product_name' => $product->name,
                    'sku' => $product->sku, 'quantity' => $qty, 'unit_cost' => $product->cost_price,
                ]);
                $total += $qty * $product->cost_price;
                StockMovement::create([
                    'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $mainWarehouse->id,
                    'type' => 'in', 'quantity' => $qty, 'reason' => 'Recepción de orden de compra hospitalaria',
                    'reference' => 'purchase_order:'.$po->id,
                ]);
            }
            $po->update(['status' => 'received', 'total' => $total]);
        }

        foreach ($products as $product) {
            if (StockMovement::where('product_id', $product->id)->doesntExist()) {
                StockMovement::create([
                    'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $mainWarehouse->id,
                    'type' => 'in', 'quantity' => max(6, $product->reorder_level * 3), 'reason' => 'Inventario inicial hospitalario',
                ]);
            }
        }

        foreach (['VAC-TOS', 'FARM-MELOX'] as $sku) {
            $product = $products->firstWhere('sku', $sku);
            if ($product && StockMovement::where('product_id', $product->id)->where('reason', 'Salida por consumo asistencial')->doesntExist()) {
                $onHand = (int) StockMovement::where('product_id', $product->id)->sum('quantity');
                $out = max(1, $onHand - (int) floor($product->reorder_level / 2));
                StockMovement::create([
                    'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $mainWarehouse->id,
                    'type' => 'out', 'quantity' => -$out, 'reason' => 'Salida por consumo asistencial',
                ]);
            }
        }

        PurchaseOrder::firstOrCreate(
            ['company_id' => $company->id, 'supplier_id' => $suppliers[1]->id, 'warehouse_id' => $mainWarehouse->id],
            ['status' => 'draft', 'order_date' => Carbon::today(), 'expected_date' => Carbon::today()->addDays(7), 'total' => 0],
        );

        if (StockTransfer::where('company_id', $company->id)->doesntExist()) {
            $product = $products->firstWhere('sku', 'FARM-SUERO');
            $transfer = StockTransfer::create([
                'company_id' => $company->id, 'product_id' => $product->id,
                'from_warehouse_id' => $mainWarehouse->id, 'to_warehouse_id' => $warehouses['Depósito']->id,
                'quantity' => 10, 'reference' => 'TR-0001', 'notes' => 'Reserva de suero para área asistencial.',
                'status' => 'completed',
            ]);
            foreach ([[$mainWarehouse->id, -10], [$warehouses['Depósito']->id, 10]] as [$wid, $qty]) {
                StockMovement::create([
                    'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $wid,
                    'type' => 'adjustment', 'quantity' => $qty, 'reason' => 'Transferencia entre bodegas',
                    'reference' => 'transfer:'.$transfer->id,
                ]);
            }
        }
    }

    // -------------------------------------------------------------- agenda

    /** @return Collection<int,Appointment> */
    private function seedAppointments(
        Company $company,
        Collection $patients,
        array $services,
        array $vets,
    ): Collection {
        $general = $services['Consulta general'] ?? $services[array_key_first($services)];
        $vac = $services['Vacunación asistencial'] ?? $general;
        $espec = $services['Consulta especializada'] ?? $general;
        $dental = $services['Curación / manejo de heridas'] ?? $general;
        $ester = $services['Cirugía ambulatoria'] ?? $general;

        $plan = [
            [0, $general, now()->subDays(24)->setTime(9, 0), 'attended', 'Control medico preventivo', 'Consultorio 101', 0],
            [4, $general, now()->subDays(18)->setTime(10, 30), 'attended', 'Chequeo por malestar digestivo', 'Consultorio 102', 1],
            [7, $dental, now()->subDays(12)->setTime(8, 0), 'attended', 'Curación ambulatoria de herida', 'Sala de Procedimientos', 0],
            [2, $vac, now()->subDays(9)->setTime(11, 0), 'attended', 'Refuerzo de esquema de vacunación', 'Consultorio 101', 1],
            [9, $general, now()->subDays(6)->setTime(15, 30), 'attended', 'Dolor en articulación de rodilla', 'Consultorio 102', 0],
            [11, $general, now()->subDays(5)->setTime(16, 0), 'no_show', 'Control de presión arterial', 'Consultorio 101', 1],
            [3, $vac, now()->subDays(3)->setTime(9, 30), 'attended', 'Inmunización esquema estacional', 'Consultorio 101', 0],
            [10, $general, now()->subDays(2)->setTime(14, 0), 'cancelled', 'Valoración dermatológica', 'Consultorio 102', 1],
            [1, $general, now()->setTime(8, 30), 'attended', 'Revisión y retirar puntos de sutura', 'Consultorio 101', 0],
            [5, $vac, now()->setTime(9, 30), 'confirmed', 'Vacuna de refuerzo', 'Consultorio 101', 0],
            [8, $espec, now()->setTime(10, 30), 'confirmed', 'Consulta prioritaria por migraña', 'Consultorio 102', 1],
            [13, $general, now()->setTime(11, 30), 'scheduled', 'Valoración ocupacional de ingreso', 'Consultorio 101', 0],
            [6, $general, now()->setTime(15, 0), 'scheduled', 'Congestión nasal y fiebre', 'Consultorio 102', 1],
            [12, $general, now()->addDay()->setTime(9, 0), 'scheduled', 'Consulta médica general', 'Consultorio 101', 1],
            [11, $vac, now()->addDay()->setTime(10, 0), 'scheduled', 'Segunda dosis de inmunización', 'Consultorio 101', 0],
            [7, $ester, now()->addDays(2)->setTime(7, 30), 'confirmed', 'Procedimiento menor programado', 'Sala de Procedimientos', 0],
            [0, $general, now()->addDays(3)->setTime(16, 0), 'scheduled', 'Control de laboratorio clínico', 'Consultorio 102', 1],
            [10, $general, now()->addDays(4)->setTime(11, 0), 'scheduled', 'Chequeo preventivo', 'Consultorio 101', 0],
        ];

        $out = collect();
        foreach ($plan as [$idx, $service, $start, $status, $reason, $room, $vetIdx]) {
            $patient = $patients->get($idx % $patients->count());
            $minutes = $service->estimated_duration_minutes ?: 30;
            $out->push(Appointment::firstOrCreate(
                ['company_id' => $company->id, 'patient_id' => $patient->id, 'starts_at' => $start],
                [
                    'service_id' => $service->id,
                    'practitioner_id' => $vets[$vetIdx]->id,
                    'ends_at' => $start->copy()->addMinutes($minutes),
                    'duration_minutes' => $minutes,
                    'resource' => $room,
                    'reason' => $reason,
                    'status' => $status,
                ],
            ));
        }

        return $out;
    }

    // ------------------------------------------------------- historia clínica

    /** @return Collection<int,Consultation> */
    private function seedConsultations(
        Company $company,
        Collection $patients,
        Collection $appointments,
        array $vets,
    ): Collection {
        $rows = [
            [0, 24, 'Control médico preventivo anual', 72.4, 36.6,
                'Paciente acude a chequeo general. Refiere buen estado general, hábito intestinal y sueño normales. Sin dolor.',
                'TA 120/80 mmHg, FC 72 lpm, FR 16 rpm, SpO2 98%. Auscultación cardiopulmonar limpia. Abdomen blando no doloroso.',
                'Paciente adulto sano. Evaluación de riesgo cardiovascular bajo.',
                'Continuar estilo de vida saludable. Solicitud de laboratorio básico de rutina. Próximo control en 12 meses.', 0],
            [4, 18, 'Consulta por malestar gastrointestinal', 65.6, 37.1,
                'Paciente refiere náuseas y episodios eméticos x3 de 24 horas de evolución tras consumo de alimento en la calle.',
                'TA 115/75 mmHg, FC 78 lpm. Mucosas hidratadas. Abdomen blando, depresible, dolor leve a la palpación en epigastrio.',
                'Gastroenteritis aguda sin deshidratación severa.',
                'Reposo alimentario inicial, hidratación oral con sales, suero oral. Antiemético por 3 días. Control en 48 horas.', 1],
            [7, 12, 'Dolor lumbar y esguince moderado', 68.9, 36.4,
                'Paciente refiere dolor lumbar de 5 días de evolución tras esfuerzo físico al levantar carga pesada.',
                'Dolor a la palpación de paravertebrales lumbares L4-L5. Lasegue negativo. Marcha conservada.',
                'Lumbago mecánico agudo.',
                'Analgésico y antiinflamatorio por 5 días. Reposo relativo, compresas húmedo-calientes. Control en 1 semana.', 0],
            [9, 6, 'Gripa e infección respiratoria alta', 74.7, 37.2,
                'Paciente consulta por congestión nasal, odinofagia y tos seca de 3 días de evolución.',
                'Orofaringe hiperémica sin exudados amigdalinos. Otoscopia bilateral normal. Campos pulmonares bien ventilados.',
                'Infección agudo de vías respiratorias superiores (Rinofaringitis aguda).',
                'Tratamiento sintomático: analgésico/antipirético, abundantes líquidos, lavados nasales con solución salina.', 0],
            [1, 0, 'Revisión y curación de herida quirúrgica', 63.1, 36.5,
                'Paciente acude para revisión de sutura de herida limpia en antebrazo derecho realizada hace 5 días.',
                'Herida quirúrgica de 3 cm con afrontamiento adecuado de bordes, sin eritema ni secreción purulenta.',
                'Herida limpia en fase adecuada de cicatrización.',
                'Curación local diaria con solución antiséptica. Retiro de puntos de sutura en 3 días.', 0],
            [2, 3, 'Control posoperatorio y evaluación de cicatrización', 58.4, 36.6,
                'Paciente acude a cita de control post-procedimiento ambulatorio sin complicaciones.',
                'Buen estado general, constantes vitales estables, herida quirúrgica con adecuada cicatrización.',
                'Evolución clínica satisfactoria post-procedimiento.',
                'Se dan indicaciones de cuidado domiciliario y signos de alarma.', 0],
        ];

        $out = collect();
        foreach ($rows as [$pIdx, $daysAgo, $reason, $w, $t, $s, $o, $a, $p, $vetIdx]) {
            $patient = $patients->get($pIdx % $patients->count());
            $date = now()->subDays($daysAgo)->toDateString();
            $appt = $appointments->first(fn (Appointment $ap) => $ap->patient_id === $patient->id
                && $ap->starts_at->toDateString() === $date);

            $out->push(Consultation::firstOrCreate(
                ['company_id' => $company->id, 'patient_id' => $patient->id, 'date' => $date],
                [
                    'appointment_id' => $appt?->id,
                    'vet_id' => $vets[$vetIdx]->id,
                    'reason' => $reason,
                    'weight' => $w,
                    'temperature' => $t,
                    'subjective' => $s,
                    'objective' => $o,
                    'assessment' => $a,
                    'plan' => $p,
                ],
            ));
        }

        return $out;
    }

    /** @return array<string,Diagnosis> */
    private function seedDiagnoses(Company $company): array
    {
        return collect([
            ['I10', 'Hipertensión esencial (primaria)'],
            ['E11.9', 'Diabetes mellitus tipo 2 sin complicaciones'],
            ['J06.9', 'Infección aguda de las vías respiratorias superiores, no especificada (IRA)'],
            ['J00', 'Rinofaringitis aguda [resfriado común]'],
            ['J02.9', 'Faringitis aguda, no especificada'],
            ['J03.9', 'Amigdalitis aguda, no especificada'],
            ['J20.9', 'Bronquitis aguda, no especificada'],
            ['J45.9', 'Asma, no especificado'],
            ['J18.9', 'Neumonía, no especificada'],
            ['J30.4', 'Rinitis alérgica, no especificada'],
            ['A09.0', 'Gastroenteritis y colitis de origen infeccioso'],
            ['A09.9', 'Gastroenteritis y colitis de origen no especificado'],
            ['K29.7', 'Gastritis, no especificada'],
            ['K21.9', 'Enfermedad del reflujo gastroesofágico sin esofagitis (ERGE)'],
            ['K58.9', 'Síndrome del colon irritable sin diarrea'],
            ['B82.9', 'Parasitosis intestinal, sin otra especificación'],
            ['N39.0', 'Infección de vías urinarias, sitio no especificado (IVU)'],
            ['N18.9', 'Enfermedad renal crónica, no especificada'],
            ['M54.5', 'Lumbago no especificado / Lumbalgia mecánica'],
            ['M54.2', 'Cervicalgia'],
            ['M25.5', 'Dolor articular (artralgia)'],
            ['M79.1', 'Mialgia'],
            ['M17.9', 'Gonartrosis [artrosis de la rodilla], no especificada'],
            ['S83.6', 'Esguince y torcedura de la rodilla'],
            ['S93.4', 'Esguince y torcedura del tobillo'],
            ['S63.5', 'Esguince y torcedura de la muñeca'],
            ['T14.1', 'Herida de región no especificada del cuerpo'],
            ['L20.9', 'Dermatitis atópica, no especificada'],
            ['L23.9', 'Dermatitis de contacto alérgica, no especificada'],
            ['L70.0', 'Acné vulgar'],
            ['L30.9', 'Dermatitis, no especificada'],
            ['L03.9', 'Celulitis de sitio no especificado'],
            ['H60.9', 'Otitis externa, no especificada'],
            ['H66.9', 'Otitis media, no especificada'],
            ['H10.9', 'Conjuntivitis, no especificada'],
            ['E66.0', 'Obesidad debida a exceso de calorías'],
            ['E66.9', 'Obesidad, no especificada'],
            ['E03.9', 'Hipotiroidismo, no especificado'],
            ['E78.5', 'Hiperlipidemia, no especificada / Dislipidemia'],
            ['R51', 'Cefalea / Dolor de cabeza'],
            ['R50.9', 'Fiebre, no especificada'],
            ['R10.4', 'Otros dolores abdominales y los no especificados'],
            ['R05', 'Tos'],
            ['G43.9', 'Migraña, no especificada'],
            ['F41.1', 'Trastorno de ansiedad generalizada'],
            ['F32.9', 'Episodio depresivo, no especificado'],
            ['Z00.0', 'Examen médico general / Chequeo preventivo de rutina'],
            ['Z01.4', 'Examen ginecológico general de rutina'],
            ['Z02.1', 'Examen médico ocupacional de ingreso / preempleo'],
            ['Z30.0', 'Consejo y asesoramiento general sobre la anticoncepción'],
        ])->mapWithKeys(fn ($d) => [
            $d[0] => Diagnosis::firstOrCreate(
                ['company_id' => $company->id, 'code' => $d[0]],
                ['name' => $d[1], 'status' => 'active'],
            ),
        ])->all();
    }

    private function attachDiagnoses(Collection $consultations, array $diagnoses): void
    {
        $byReason = [
            'Consulta por malestar gastrointestinal' => ['A09.9', 'B82.9'],
            'Gripa e infección respiratoria alta' => ['J06.9', 'J00'],
            'Dolor lumbar y esguince moderado' => ['M54.5', 'S83.6'],
        ];
        foreach ($consultations as $consultation) {
            $codes = $byReason[$consultation->reason] ?? [];
            if ($codes && $consultation->diagnoses()->count() === 0) {
                $consultation->diagnoses()->sync(collect($codes)->map(fn ($c) => $diagnoses[$c]->id)->all());
            }
        }
    }

    // --------------------------------------------------- vacunas / recetas / procedimientos

    private function seedClinicalApplications(
        Company $company,
        Collection $patients,
        Collection $products,
        array $vets,
        Warehouse $warehouse,
        Collection $consultations,
    ): void {
        $dhppi = $products->firstWhere('sku', 'VAC-DHPPI');
        $rabia = $products->firstWhere('sku', 'VAC-RABIA');
        $tripleF = $products->firstWhere('sku', 'VAC-TRIPLE-F');
        $antiInt = $products->firstWhere('sku', 'ANTI-INT');
        $antiExt = $products->firstWhere('sku', 'ANTI-EXT');

        $rows = [
            [0, 'vaccine', 'Vacuna polivalente (DHPPi)', $dhppi, 330, 35, 0],
            [0, 'vaccine', 'Vacuna antirrábica', $rabia, 330, 35, 0],
            [0, 'deworming', 'Desparasitación amplia', $antiInt, 95, -5, 1],
            [1, 'vaccine', 'Vacuna antirrábica', $rabia, 300, 65, 0],
            [2, 'vaccine', 'Vacuna asistencial recomendada', $tripleF, 9, 356, 1],
            [3, 'vaccine', 'Vacuna polivalente — 1ra dosis', $dhppi, 3, 18, 0],
            [4, 'deworming', 'Antiparasitario oral', $antiExt, 40, -10, 1],
            [5, 'vaccine', 'Vacuna antirrábica', $rabia, 350, 12, 0],
            [7, 'vaccine', 'Vacuna polivalente', $dhppi, 200, 165, 0],
            [9, 'deworming', 'Desparasitación amplia', $antiInt, 20, 70, 0],
            [11, 'vaccine', 'Vacuna polivalente — 1ra dosis', $dhppi, 3, 18, 0],
        ];

        foreach ($rows as [$pIdx, $type, $name, $product, $daysAgo, $dueInDays, $vetIdx]) {
            $patient = $patients->get($pIdx % $patients->count());
            $appliedAt = now()->subDays($daysAgo)->toDateString();

            $existing = ClinicalApplication::where([
                'company_id' => $company->id, 'patient_id' => $patient->id, 'name' => $name, 'applied_at' => $appliedAt,
            ])->first();
            if ($existing) {
                continue;
            }

            $stockMovementId = null;
            if ($product) {
                $movement = StockMovement::create([
                    'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
                    'type' => 'out', 'quantity' => -1, 'reason' => 'Aplicación clínica asistencial',
                    'reference' => 'seed:clinical_application',
                ]);
                $stockMovementId = $movement->id;
            }

            $consultation = $consultations->firstWhere('patient_id', $patient->id);

            ClinicalApplication::create([
                'company_id' => $company->id,
                'type' => $type,
                'patient_id' => $patient->id,
                'product_id' => $product?->id,
                'consultation_id' => $consultation?->id,
                'vet_id' => $vets[$vetIdx]->id,
                'stock_movement_id' => $stockMovementId,
                'name' => $name,
                'applied_at' => $appliedAt,
                'lot' => 'L'.random_int(10000, 99999),
                'expires_at' => now()->addMonths(random_int(8, 20))->toDateString(),
                'next_due_at' => now()->addDays($dueInDays)->toDateString(),
            ]);
        }
    }

    private function seedPrescriptions(
        Company $company,
        Collection $consultations,
        Collection $products,
        array $vets,
    ): void {
        $amoxi = $products->firstWhere('sku', 'FARM-AMOXI');
        $melox = $products->firstWhere('sku', 'FARM-MELOX');
        $gaba = $products->firstWhere('sku', 'FARM-GABA');

        $plans = [
            ['Control médico preventivo anual', 'Continuar hábitos de vida saludables.', [
                [$amoxi, 'Amoxicilina 250 mg', '1 cápsula', 'Cada 12 horas', '5 días'],
            ]],
            ['Consulta por malestar gastrointestinal', 'Administrar con suero oral. Reposo digestivo.', [
                [$amoxi, 'Amoxicilina 250 mg', '1 cápsula', 'Cada 8 horas', '7 días'],
                [$melox, 'Meloxicam 1,5 mg/ml', '10 ml', 'Cada 24 horas', '3 días'],
            ]],
            ['Dolor lumbar y esguince moderado', 'Reposo relativo y aplicación de calor local.', [
                [$melox, 'Meloxicam 1,5 mg/ml', '15 ml', 'Cada 24 horas', '5 días'],
                [$gaba, 'Gabapentina 100 mg', '1 tableta', 'Cada 8 horas', '7 días'],
            ]],
            ['Gripa e infección respiratoria alta', 'Abundante hidratación y lavados nasales.', [
                [$amoxi, 'Amoxicilina 250 mg', '1 cápsula', 'Cada 8 horas', '7 días'],
            ]],
            ['Revisión y curación de herida quirúrgica', 'Curación diaria de herida quirúrgica.', [
                [$amoxi, 'Amoxicilina 250 mg', '1 cápsula', 'Cada 12 horas', '5 días'],
            ]],
            ['Control posoperatorio y evaluación de cicatrización', 'Mantener área limpia y seca.', [
                [$gaba, 'Gabapentina 100 mg', '1 tableta', 'Cada 12 horas', '5 días'],
            ]],
        ];

        foreach ($plans as [$reason, $notes, $items]) {
            $consultation = $consultations->firstWhere('reason', $reason);
            if (! $consultation) {
                continue;
            }
            $prescription = Prescription::firstOrCreate(
                ['company_id' => $company->id, 'consultation_id' => $consultation->id],
                [
                    'patient_id' => $consultation->patient_id,
                    'vet_id' => $consultation->vet_id ?? $vets[0]->id,
                    'notes' => $notes,
                ]
            );
            if ($prescription->wasRecentlyCreated && $prescription->items()->count() === 0) {
                foreach ($items as [$product, $name, $dosage, $frequency, $duration]) {
                    $prescription->items()->create([
                        'product_id' => $product?->id,
                        'medication_name' => $name,
                        'sku' => $product?->sku,
                        'dosage' => $dosage,
                        'frequency' => $frequency,
                        'duration' => $duration,
                    ]);
                }
            }
        }
    }

    private function seedProcedures(
        Company $company,
        Collection $patients,
        array $services,
        array $vets,
    ): void {
        $procSvc = $services['Curación / manejo de heridas'] ?? $services['Consulta general'] ?? reset($services);

        $rows = [
            [7, 'Curación y lavado de herida asistencial', $procSvc, 12,
                'Procedimiento ambulatorio bajo técnica aséptica. Lavado con solución salina 0.9% y aplicación de apósito estéril.', 0],
            [4, 'Sutura de herida en miembro superior', $procSvc, 6,
                'Afrontamiento de bordes con nylon 3-0 bajo anestesia local con lidocaína. Evolución y hemostasia adecuadas.', 1],
            [8, 'Retiro de puntos de sutura post-quirúrgico', $procSvc, 40,
                'Retiro de material de sutura en región abdominal sin complicaciones. Cicatrización de primera intención.', 0],
            [0, 'Toma de electrocardiograma y valoración', $procSvc, 60,
                'Electrocardiograma de 12 derivaciones en reposo. Ritmo sinusal regular sin alteraciones agudas del segmento ST.', 1],
        ];

        foreach ($rows as [$pIdx, $type, $service, $daysAgo, $notes, $vetIdx]) {
            $patient = $patients->get($pIdx % $patients->count());
            Procedure::firstOrCreate(
                ['company_id' => $company->id, 'patient_id' => $patient->id, 'type' => $type],
                [
                    'service_id' => $service->id,
                    'vet_id' => $vets[$vetIdx]->id,
                    'performed_at' => now()->subDays($daysAgo)->toDateString(),
                    'notes' => $notes,
                ],
            );
        }
    }

    // ------------------------------------------------------------ portal / ventas

    private function seedLeads(Company $company): void
    {
        $rows = [
            ['Andrea Salcedo', 'andrea.salcedo@gmail.com', '+57 300 555 0401', 'appointment', 'new',
                'Paciente: Andrea Salcedo. Motivo: Consulta de medicina general y chequeo preventivo. Fecha preferida: sábado en la mañana.'],
            ['Miguel Ángel Ruiz', 'miguel.ruiz@gmail.com', '+57 301 555 0402', 'appointment', 'new',
                'Paciente: Miguel Ruiz. Motivo: Valoración médica por cuadro gripal persistente. Fecha preferida: entre semana en la tarde.'],
            ['Carolina Méndez', 'carolina.mendez@hotmail.com', '+57 302 555 0403', 'appointment', 'contacted',
                'Paciente: Carolina Méndez. Motivo: Consulta especializada dermatológica. Fecha preferida: lunes.'],
            ['Julián Pardo', 'julian.pardo@gmail.com', '+57 303 555 0404', 'appointment', 'new',
                'Paciente: Julián Pardo. Motivo: Examen médico ocupacional de ingreso.'],
            ['Verónica Lozano', 'veronica.lozano@nexabpo.example', '+57 304 555 0405', 'contact', 'new',
                'Consulta por convenio empresarial de salud ocupacional para colaboradores.'],
            ['Tomás Salazar', 'tomas.salazar@gmail.com', '+57 305 555 0406', 'contact', 'discarded',
                'Preguntó por horarios de atención y convenios de salud; no volvió a responder.'],
        ];

        foreach ($rows as [$name, $email, $phone, $source, $status, $message]) {
            Lead::firstOrCreate(
                ['company_id' => $company->id, 'email' => $email, 'source' => $source],
                [
                    'name' => $name, 'phone' => $phone, 'message' => $message, 'status' => $status,
                    'ip_address' => '190.85.'.random_int(1, 254).'.'.random_int(1, 254),
                ],
            );
        }
    }

    private function seedProductSales(
        Company $company,
        Collection $clients,
        Collection $publicProducts,
        Warehouse $warehouse,
        User $reception,
    ): void {
        $gastro = $publicProducts->firstWhere('sku', 'ALIM-GASTRO');
        $recov = $publicProducts->firstWhere('sku', 'ALIM-RECOV');
        $collar = $publicProducts->firstWhere('sku', 'ACC-COLLAR');
        $shampoo = $publicProducts->firstWhere('sku', 'ACC-SHAMP');

        $plans = [
            ['client' => $clients[1], 'confirmed' => true, 'items' => [[$gastro, 1], [$recov, 6]]],
            ['client' => $clients[3], 'confirmed' => true, 'items' => [[$collar, 1], [$shampoo, 1]]],
            ['client' => $clients[6], 'confirmed' => false, 'items' => [[$shampoo, 2]]],
        ];

        foreach ($plans as $plan) {
            $order = Order::firstOrCreate(
                ['company_id' => $company->id, 'client_id' => $plan['client']->id, 'warehouse_id' => $warehouse->id],
                ['owner_id' => $reception->id, 'status' => 'draft', 'total' => 0],
            );
            if ($order->items()->count() > 0) {
                continue;
            }
            $total = 0;
            foreach ($plan['items'] as [$product, $qty]) {
                OrderItem::create([
                    'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
                    'sku' => $product->sku, 'quantity' => $qty, 'unit_price' => $product->unit_price,
                ]);
                $total += $qty * $product->unit_price;
            }
            $order->update(['total' => $total]);
            if ($plan['confirmed']) {
                foreach ($plan['items'] as [$product, $qty]) {
                    StockMovement::create([
                        'company_id' => $company->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
                        'type' => 'out', 'quantity' => -$qty, 'reason' => 'Venta asistencial', 'reference' => 'order:'.$order->id,
                    ]);
                }
                $order->update(['status' => 'confirmed']);
            }
        }
    }

    private function seedSurgeryQuotes(Company $company, Collection $clients, array $services): void
    {
        $plans = [
            ['client' => $clients[3], 'status' => 'sent', 'title' => 'Presupuesto procedimiento asistencial',
                'items' => [[$services['Cirugía ambulatoria'], 1], [$services['Consulta general'], 1]]],
            ['client' => $clients[7], 'status' => 'accepted', 'title' => 'Presupuesto tratamiento ambulatorio',
                'items' => [[$services['Procedimiento menor'], 1], [$services['Observación asistencial (día)'], 1]]],
            ['client' => $clients[9], 'status' => 'draft', 'title' => 'Presupuesto atención especializada',
                'items' => [[$services['Cirugía ambulatoria'], 1], [$services['Observación asistencial (día)'], 2]]],
        ];

        foreach ($plans as $plan) {
            $quote = Quote::firstOrCreate(
                ['company_id' => $company->id, 'client_id' => $plan['client']->id, 'title' => $plan['title']],
                ['status' => 'draft', 'valid_until' => Carbon::today()->addDays(20), 'total' => 0],
            );
            if ($quote->items()->count() > 0) {
                continue;
            }
            $total = 0;
            foreach ($plan['items'] as [$service, $qty]) {
                QuoteItem::create([
                    'quote_id' => $quote->id, 'product_id' => null, 'product_name' => $service->name,
                    'sku' => null, 'quantity' => $qty, 'unit_price' => $service->price,
                ]);
                $total += $qty * $service->price;
            }
            $quote->update(['total' => $total, 'status' => $plan['status']]);
        }
    }

    private function seedWellnessDeals(Company $company, Collection $clients, User $admin, User $sales): void
    {
        $stages = ['prospecting', 'qualification', 'proposal', 'negotiation', 'won', 'lost'];
        $titles = [
            'Plan asistencial anual — Familia Herrera',
            'Convenio de salud ocupacional — Nexa BPO',
            'Plan preventivo empresa — EPS Sanitas Convenio',
            'Paquete chequeo corporativo — Seguros Bolívar Salud',
            'Plan especial asistencial — Carlos Mendoza',
        ];
        foreach ($titles as $i => $title) {
            $client = $clients[[0, 11, 10, 9, 4][$i]];
            Deal::firstOrCreate(
                ['company_id' => $company->id, 'client_id' => $client->id, 'title' => $title],
                [
                    'owner_id' => $i % 2 === 0 ? $admin->id : $sales->id,
                    'amount' => 350000 + $i * 220000,
                    'stage' => $stages[$i % count($stages)],
                    'expected_close_date' => Carbon::today()->addDays(7 + $i * 6),
                ],
            );
        }
    }

    private function seedClientNotesAndTasks(
        Company $company,
        Collection $clients,
        Collection $patients,
        User $admin,
        User $reception,
    ): void {
        collect([
            [0, 'Carlos Mendoza: paciente muy puntual con controles. Prefiere cita a primera hora.'],
            [4, 'Valentina pidió recordatorio por WhatsApp para su próximo control asistencial.'],
            [9, 'EPS Sanitas: facturación mensual consolidada de servicios asistenciales.'],
            [10, 'Seguros Bolívar: coordinar plan asistencial corporativo para grupo de afiliados.'],
        ])->each(fn ($d) => ClientNote::firstOrCreate(
            ['company_id' => $company->id, 'client_id' => $clients[$d[0]]->id, 'body' => $d[1]],
            ['user_id' => $admin->id],
        ));

        collect([
            ['task', 'Llamar a Marcela Ríos para agendar control medico', 2, 1, false],
            ['task', 'Confirmar ayuno para procedimiento programado', null, 1, false],
            ['followup', 'Recordatorio de refuerzo asistencial — Carlos Mendoza', 0, 3, false],
            ['followup', 'Seguimiento posoperatorio — Gabriel Silva', 7, -1, true],
            ['task', 'Cotizar plan asistencial — EPS Sanitas Convenio', 10, 4, false],
        ])->each(fn ($d) => Activity::firstOrCreate(
            ['company_id' => $company->id, 'subject' => $d[1]],
            [
                'client_id' => $d[2] === null ? null : $clients[$d[2]]->id,
                'type' => $d[0],
                'due_date' => Carbon::today()->addDays($d[3]),
                'completed' => $d[4],
            ],
        ));
    }

    private function seedAuditLog(
        Company $company,
        User $admin,
        Collection $clients,
        Collection $patients,
    ): void {
        $rows = [
            ['login', 'auth', User::class, $admin->id, 0],
            ['patient.created', 'pacientes', Patient::class, $patients[0]->id, 12],
            ['appointment.updated', 'citas', Appointment::class, null, 1],
            ['consultation.created', 'historia-clinica', Consultation::class, null, 6],
            ['clinical_application.created', 'vacunas', ClinicalApplication::class, null, 3],
            ['prescription.created', 'recetas', Prescription::class, null, 6],
            ['order.confirmed', 'pedidos', Order::class, null, 2],
            ['purchase_order.received', 'compras', PurchaseOrder::class, null, 11],
            ['settings.updated', 'configuracion', Company::class, $company->id, 20],
        ];

        foreach ($rows as [$action, $module, $entity, $entityId, $daysAgo]) {
            $log = AuditLog::firstOrCreate(
                ['company_id' => $company->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $entityId],
                [
                    'user_id' => $admin->id,
                    'module' => $module,
                    'ip_address' => '190.85.'.random_int(1, 254).'.'.random_int(1, 254),
                    'new_values' => ['message' => 'Acción registrada por el dataset de demostración asistencial'],
                ],
            );

            if ($log->wasRecentlyCreated) {
                $at = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 8));
                $log->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            }
        }
    }

    private function seedInvoicesAndAccounts(
        Company $company,
        Collection $clients,
        Collection $products,
        User $reception,
        Warehouse $warehouse
    ): void {
        $rows = [
            ['client' => $clients[0], 'number' => 'FE-1001', 'status' => 'paid', 'days_ago' => 15, 'items' => [[$products->firstWhere('sku', 'VAC-DHPPI'), 1, 45000], [$products->firstWhere('sku', 'FARM-AMOXI'), 2, 2500]]],
            ['client' => $clients[1], 'number' => 'FE-1002', 'status' => 'issued', 'days_ago' => 10, 'items' => [[$products->firstWhere('sku', 'ALIM-GASTRO'), 1, 128000]]],
            ['client' => $clients[2], 'number' => 'FE-1003', 'status' => 'overdue', 'days_ago' => 35, 'items' => [[$products->firstWhere('sku', 'FARM-MELOX'), 1, 45000], [$products->firstWhere('sku', 'ACC-SHAMP'), 1, 36000]]],
            ['client' => $clients[3], 'number' => 'FE-1004', 'status' => 'issued', 'days_ago' => 5, 'items' => [[$products->firstWhere('sku', 'ACC-DENTAL'), 1, 24000]]],
            ['client' => $clients[4], 'number' => 'FE-1005', 'status' => 'paid', 'days_ago' => 20, 'items' => [[$products->firstWhere('sku', 'ANTI-EXT'), 1, 55000]]],
        ];

        foreach ($rows as $r) {
            $subtotal = array_reduce($r['items'], fn($acc, $it) => $acc + ($it[1] * $it[2]), 0);
            $tax = round($subtotal * 0.19, 2);
            $total = $subtotal + $tax;
            $issueDate = Carbon::today()->subDays($r['days_ago']);
            $dueDate = $issueDate->copy()->addDays(30);

            $invoice = Invoice::firstOrCreate(
                ['company_id' => $company->id, 'number' => $r['number']],
                [
                    'client_id' => $r['client']->id,
                    'warehouse_id' => $warehouse->id,
                    'user_id' => $reception->id,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'status' => $r['status'],
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax' => $tax,
                    'total' => $total,
                    'notes' => 'Factura electrónica asistencial de prueba',
                ]
            );

            if ($invoice->wasRecentlyCreated) {
                foreach ($r['items'] as [$prod, $qty, $price]) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $prod?->id,
                        'product_name' => $prod?->name ?? 'Servicio asistencial',
                        'sku' => $prod?->sku,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount' => 0,
                        'tax' => round($qty * $price * 0.19, 2),
                        'line_total' => round($qty * $price * 1.19, 2),
                    ]);
                }

                AccountReceivable::create([
                    'company_id' => $company->id,
                    'client_id' => $r['client']->id,
                    'invoice_id' => $invoice->id,
                    'original_amount' => $total,
                    'paid_amount' => $r['status'] === 'paid' ? $total : 0,
                    'balance' => $r['status'] === 'paid' ? 0 : $total,
                    'due_date' => $dueDate,
                    'status' => $r['status'] === 'paid' ? 'paid' : ($r['days_ago'] > 30 ? 'overdue' : 'pending'),
                ]);
            }
        }
    }

    private function seedAccountsPayable(Company $company, Collection $suppliers): void
    {
        $rows = [
            ['supplier' => $suppliers[0], 'amount' => 1250000, 'paid' => 1250000, 'status' => 'paid', 'due_days' => -10],
            ['supplier' => $suppliers[1], 'amount' => 840000, 'paid' => 0, 'status' => 'pending', 'due_days' => 15],
            ['supplier' => $suppliers[2], 'amount' => 450000, 'paid' => 0, 'status' => 'overdue', 'due_days' => -5],
        ];

        foreach ($rows as $r) {
            AccountPayable::firstOrCreate(
                ['company_id' => $company->id, 'supplier_id' => $r['supplier']->id, 'original_amount' => $r['amount']],
                [
                    'paid_amount' => $r['paid'],
                    'balance' => $r['amount'] - $r['paid'],
                    'due_date' => Carbon::today()->addDays($r['due_days']),
                    'status' => $r['status'],
                ]
            );
        }
    }

    private function seedCashSessions(Company $company, User $reception): void
    {
        $register = CashRegister::where('company_id', $company->id)->first();
        if (!$register) return;

        $s1 = CashSession::firstOrCreate(
            ['company_id' => $company->id, 'cash_register_id' => $register->id, 'opened_at' => Carbon::yesterday()->setTime(8, 0)],
            [
                'opened_by' => $reception->id,
                'closed_by' => $reception->id,
                'closed_at' => Carbon::yesterday()->setTime(18, 0),
                'opening_amount' => 200000,
                'expected_amount' => 650000,
                'closing_amount' => 650000,
                'difference' => 0,
                'status' => 'closed',
                'notes' => 'Cierre de turno asistencial sin novedades',
            ]
        );
        if ($s1->wasRecentlyCreated) {
            CashMovement::create([
                'company_id' => $company->id,
                'cash_session_id' => $s1->id,
                'user_id' => $reception->id,
                'type' => 'in',
                'amount' => 450000,
                'method' => 'cash',
                'source_type' => 'manual',
                'source_id' => 1,
                'notes' => 'Ingreso por copagos y servicios asistenciales',
            ]);
        }

        CashSession::firstOrCreate(
            ['company_id' => $company->id, 'cash_register_id' => $register->id, 'opened_at' => Carbon::today()->setTime(8, 0)],
            [
                'opened_by' => $reception->id,
                'opening_amount' => 200000,
                'expected_amount' => 380000,
                'closing_amount' => 0,
                'difference' => 0,
                'status' => 'open',
                'notes' => 'Turno activo asistencial del día',
            ]
        );
    }
}
