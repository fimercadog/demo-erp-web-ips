"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  Activity,
  AlertTriangle,
  ArrowLeftRight,
  BarChart3,
  BookOpen,
  Bot,
  Building2,
  CalendarClock,
  CalendarDays,
  ClipboardList,
  Clock,
  Contact2,
  DollarSign,
  FileCheck,
  FileText,
  Handshake,
  HeartPulse,
  Hospital,
  Inbox,
  LayoutDashboard,
  ListChecks,
  Lock,
  LogOut,
  Menu,
  Moon,
  Package,
  Pill,
  Receipt,
  Repeat,
  Ruler,
  Settings,
  Shield,
  ShieldAlert,
  Siren,
  Stethoscope,
  Sun,
  Syringe,
  Tag,
  Tags,
  TrendingUp,
  Truck,
  UserCheck,
  UserCircle,
  Users,
  Wallet,
  Warehouse,
  WifiOff,
  X,
  type LucideIcon,
} from "lucide-react";
import { LogoMark } from "@/components/brand/logo";
import { BetaNotice } from "@/components/layout/beta-notice";
import { ContingencyBanner } from "@/components/layout/contingency-banner";
import { useContingency } from "@/lib/contingency/context";
import { planHidesRoute, planLocksAsPremium } from "@/lib/plan";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { useTheme } from "@/components/theme-provider";
import { api } from "@/lib/api";
import {
  AUTH_EXPIRED_EVENT,
  AuthUser,
  clearAuthSession,
  getStoredUser,
  hasAnyPermission,
  updateStoredUser,
} from "@/lib/auth";
import { cn } from "@/lib/utils";

let meRequest: Promise<{ data: { user: AuthUser } }> | null = null;
function fetchMe() {
  meRequest ??= api
    .get<{ user: AuthUser }>("/auth/me")
    .finally(() => {
      meRequest = null;
    });
  return meRequest;
}

type NavItem = {
  href: string;
  label: string;
  icon: LucideIcon;
  permissions?: string[];
  premium?: boolean;
  alert?: boolean;
};

type NavGroup = { label: string; items: NavItem[] };

/**
 * Reorganización del Sidebar Admin ERP para Vertical IPS:
 * Separación rigurosa de Historias Clínicas, Facturación Electrónica, RIPS y Cuentas Médicas.
 */
const navGroups: NavGroup[] = [
  {
    label: "",
    items: [{ href: "/app/dashboard", label: "Dashboard IPS", icon: LayoutDashboard, permissions: ["dashboard.view"] }],
  },
  {
    label: "Clínica",
    items: [
      { href: "/app/agenda", label: "Citas del Día", icon: CalendarDays, permissions: ["appointments.manage"] },
      { href: "/app/citas", label: "Agenda & Consultas", icon: CalendarClock, permissions: ["appointments.manage"] },
      { href: "/app/pacientes", label: "Directorio de Pacientes", icon: Users, permissions: ["patients.manage"] },
      { href: "/app/consultas", label: "Historias Clínicas", icon: Stethoscope, permissions: ["medical_records.manage"] },
      { href: "/app/procedimientos", label: "Procedimientos", icon: Activity, permissions: ["procedures.manage"] },
      { href: "/app/urgencias", label: "Triage / Consulta Prioritaria", icon: Siren, permissions: ["medical_records.manage"] },
    ],
  },
  {
    label: "Odontología",
    items: [
      { href: "/app/odontologia/dashboard", label: "Dashboard Odontología", icon: Activity, permissions: ["medical_records.manage"] },
      { href: "/app/odontologia/tratamientos", label: "Planes & Presupuestos", icon: ClipboardList, permissions: ["medical_records.manage"] },
      { href: "/app/odontologia/evoluciones", label: "Evolución Dental", icon: FileText, permissions: ["medical_records.manage"] },
      { href: "/app/odontologia/consentimientos", label: "Consentimientos Dentales", icon: FileCheck, permissions: ["medical_records.manage"] },
      { href: "/app/odontologia/procedimientos", label: "Tarifas & Procedimientos", icon: ListChecks, permissions: ["services.manage"] },
    ],
  },
  {
    label: "Asistencial",
    items: [
      { href: "/app/equipo", label: "Médicos & Especialistas", icon: UserCheck, permissions: ["users.manage"] },
      { href: "/app/recetas", label: "Órdenes & Prescripciones", icon: FileText, permissions: ["prescriptions.manage"] },
      { href: "/app/diagnosticos", label: "Diagnósticos CIE-10", icon: ListChecks, permissions: ["medical_records.manage"] },
      { href: "/app/servicios", label: "Portafolio de Servicios", icon: Hospital, permissions: ["services.manage"] },
    ],
  },
  {
    label: "Comercial & Convenios",
    items: [
      { href: "/app/leads", label: "Solicitudes & Convenios", icon: Inbox, permissions: ["leads.view"] },
      { href: "/app/clientes", label: "Afiliados & Entidades", icon: Building2, permissions: ["clients.manage"] },
      { href: "/app/cotizaciones", label: "Cotizaciones & Presupuestos", icon: FileText, permissions: ["deals.manage"] },
    ],
  },
  {
    label: "Facturación & RIPS",
    items: [
      { href: "/app/facturas", label: "Facturación Electrónica", icon: Receipt, permissions: ["invoices.manage"] },
      { href: "/app/reportes-comerciales", label: "Proceso RIPS", icon: FileCheck, permissions: ["reports.view"] },
      { href: "/app/cuentas-por-cobrar", label: "Cuentas Médicas", icon: DollarSign, permissions: ["accounts_receivable.view"] },
      { href: "/app/cuentas-por-pagar", label: "Cuentas por Pagar (Proveedores)", icon: FileText, permissions: ["accounts_payable.view"] },
      { href: "/app/contabilidad", label: "Contabilidad Básica", icon: BookOpen },
      { href: "/app/finanzas/conciliacion", label: "Conciliación Bancaria (Nivel 1)", icon: ArrowLeftRight },
    ],
  },
  {
    label: "Operaciones & Farmacia",
    items: [
      { href: "/app/productos", label: "Farmacia & Insumos Hospitalarios", icon: Pill, permissions: ["products.manage"] },
      { href: "/app/bodegas", label: "Bodegas Hospitalarias", icon: Warehouse, permissions: ["warehouses.manage"] },
      { href: "/app/movimientos-inventario", label: "Kardex de Farmacia", icon: ArrowLeftRight, permissions: ["stock.manage"] },
      { href: "/app/alertas-stock", label: "Alertas de Dispositivos", icon: AlertTriangle, permissions: ["products.manage"] },
      { href: "/app/cajas", label: "Caja & Copagos", icon: Wallet, permissions: ["cash.manage"] },
      { href: "/app/sesiones-caja", label: "Turnos de Caja", icon: ClipboardList, permissions: ["cash.manage"] },
      { href: "/app/sueroterapia", label: "Sueroterapia a Domicilio", icon: Syringe, permissions: ["appointments.manage"] },
    ],
  },
  {
    label: "Administración IPS",
    items: [
      { href: "/app/reportes", label: "Reportes & KPIs IPS", icon: BarChart3, permissions: ["reports.view"] },
      { href: "/app/auditoria", label: "Auditoría de Historias Clínicas", icon: Shield, permissions: ["audit.view"] },
      { href: "/app/usuarios", label: "Usuarios & Roles Médicos", icon: UserCircle, permissions: ["users.manage"] },
      { href: "/app/rrhh/asistencia", label: "Asistencia RRHH", icon: Clock, permissions: ["users.manage"] },
      { href: "/app/configuracion", label: "Configuración IPS & Habilitación", icon: Settings, permissions: ["settings.manage"] },
    ],
  },
  {
    label: "Herramientas IPS",
    items: [
      { href: "/app/contingencia", label: "Modo Contingencia Asistencial", icon: WifiOff, alert: true },
      { href: "/app/ia", label: "Asistente Médico IA (Demo)", icon: Bot, premium: true },
    ],
  },
];

const allNavItems: NavItem[] = navGroups.flatMap((g) => g.items);

function PremiumBadge() {
  return (
    <span className="ml-auto shrink-0 flex items-center gap-1 rounded bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-600 dark:bg-sky-500/20 dark:text-sky-400">
      <Lock className="h-3 w-3 shrink-0" /> Premium
    </span>
  );
}

const PREMIUM_INFO: Record<string, { title: string; body: React.ReactNode }> = {
  "/app/contingencia": {
    title: "Modo Contingencia Asistencial (Continuidad sin Conexión)",
    body: (
      <>
        <p>
          El modo contingencia permite{" "}
          <strong className="font-semibold text-foreground">registrar admisiones e historias clínicas localmente</strong> cuando hay contingencias de red.
        </p>
        <p>
          Al restablecerse la conectividad, las atenciones encoladas se{" "}
          <strong className="font-semibold text-foreground">sincronizan automáticamente con el ERP de la IPS</strong> resguardando la trazabilidad.
        </p>
      </>
    ),
  },
  "/app/ia": {
    title: "Asistente Clínico de Inteligencia Artificial IPS",
    body: (
      <>
        <p>
          Herramienta de apoyo asistencial para{" "}
          <strong className="font-semibold text-foreground">
            consulta de catálogo CIE-10, resúmenes de atenciones e indicadores de farmacia
          </strong>
          .
        </p>
      </>
    ),
  },
};

function NavLink({ item }: { item: NavItem }) {
  const pathname = usePathname();
  const Icon = item.icon;
  const { isActive: contingencyActive, pendingCount } = useContingency();
  const premiumLocked = item.premium || planLocksAsPremium(item.href);

  if (item.alert && !premiumLocked) {
    const active = pathname === item.href;
    return (
      <Link
        href={item.href}
        className={cn(
          "flex min-h-[36px] h-auto items-center gap-2.5 rounded-md px-3 py-1.5 text-xs sm:text-sm font-medium text-amber-600 transition-colors hover:bg-amber-500/10 dark:text-amber-400",
          active && "bg-amber-500/10",
        )}
      >
        <Icon className="h-4 w-4 shrink-0" />
        <span className="min-w-0 flex-1 leading-tight">{item.label}</span>
        {contingencyActive ? (
          <span className="ml-auto shrink-0 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
            Activo{pendingCount ? ` · ${pendingCount}` : ""}
          </span>
        ) : null}
      </Link>
    );
  }

  if (premiumLocked) {
    const info = PREMIUM_INFO[item.href] ?? PREMIUM_INFO["/app/ia"];
    return (
      <Dialog>
        <DialogTrigger asChild>
          <button
            type="button"
            className="flex min-h-[36px] h-auto w-full items-center gap-2.5 rounded-md px-3 py-1.5 text-xs sm:text-sm text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
          >
            <Icon className="h-4 w-4 shrink-0" />
            <span className="min-w-0 flex-1 text-left leading-tight">{item.label}</span>
            <PremiumBadge />
          </button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{info.title}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 text-sm text-muted-foreground">{info.body}</div>
        </DialogContent>
      </Dialog>
    );
  }

  const active = pathname === item.href;

  return (
    <Link
      href={item.href}
      className={cn(
        "flex min-h-[36px] h-auto items-center gap-2.5 rounded-md px-3 py-1.5 text-xs sm:text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100",
        active && "bg-sky-50 font-semibold text-sky-700 dark:bg-sky-950/60 dark:text-sky-300",
      )}
    >
      <Icon className="h-4 w-4 shrink-0" />
      <span className="min-w-0 flex-1 leading-tight">{item.label}</span>
    </Link>
  );
}

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { theme, setTheme } = useTheme();
  const router = useRouter();
  const pathname = usePathname();
  const [user, setUser] = React.useState<AuthUser | null>(null);
  const [checkingSession, setCheckingSession] = React.useState(true);
  const [mobileNavOpen, setMobileNavOpen] = React.useState(false);

  React.useEffect(() => {
    queueMicrotask(() => setMobileNavOpen(false));
  }, [pathname]);

  const logout = React.useCallback(async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      // Session local cleanup
    } finally {
      clearAuthSession();
      router.replace("/login");
    }
  }, [router]);

  React.useEffect(() => {
    const cached = getStoredUser();
    if (cached) {
      queueMicrotask(() => {
        setUser(cached);
        setCheckingSession(false);
      });
    }

    fetchMe()
      .then((response) => {
        updateStoredUser(response.data.user);
        setUser(response.data.user);
      })
      .catch(() => {
        clearAuthSession();
        router.replace("/login");
      })
      .finally(() => setCheckingSession(false));
  }, [router]);

  React.useEffect(() => {
    function handleExpiredSession() {
      clearAuthSession();
      router.replace("/login");
    }

    window.addEventListener(AUTH_EXPIRED_EVENT, handleExpiredSession);
    return () => window.removeEventListener(AUTH_EXPIRED_EVENT, handleExpiredSession);
  }, [router]);

  const visibleGroups = navGroups
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) => !planHidesRoute(item.href) && hasAnyPermission(user, item.permissions),
      ),
    }))
    .filter((group) => group.items.length > 0);

  const activeNav = allNavItems
    .filter((item) => pathname === item.href || pathname.startsWith(`${item.href}/`))
    .sort((a, b) => b.href.length - a.href.length)[0];
  const authorized =
    !planHidesRoute(activeNav?.href) &&
    !planLocksAsPremium(activeNav?.href) &&
    (!user || !activeNav || hasAnyPermission(user, activeNav.permissions));

  if (checkingSession && !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-900 text-slate-100">
        <div className="text-center">
          <p className="text-sm font-semibold tracking-wide">Validando sesión...</p>
          <p className="mt-1 text-xs text-slate-400">FidelOS · ERP Multi-Empresa</p>
        </div>
      </div>
    );
  }

  const sidebarHeader = (
    <div className="flex h-16 items-center gap-3 border-b border-slate-200 bg-slate-900 px-5 dark:border-slate-800">
      <LogoMark size="sm" />
      <div>
        <p className="flex items-center gap-1.5 text-sm font-bold tracking-tight text-white">
          FidelOS<span className="text-sky-400">·</span>ERP
          <span className="rounded bg-sky-500/20 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-sky-300">
            PRO
          </span>
        </p>
        <p className="text-[11px] text-slate-400">Plataforma de Gestión</p>
      </div>
    </div>
  );

  const navBody = (
    <nav className="flex-1 space-y-4 overflow-y-auto p-4">
      {visibleGroups.map((group) => (
        <div key={group.label || "general"} className="space-y-1">
          {group.label ? (
            <p className="mb-1 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
              {group.label}
            </p>
          ) : null}
          {group.items.map((item) => (
            <NavLink key={item.href} item={item} />
          ))}
        </div>
      ))}
    </nav>
  );

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-slate-200 bg-white lg:flex lg:flex-col dark:border-slate-800 dark:bg-slate-900">
        {sidebarHeader}
        {navBody}
      </aside>

      {mobileNavOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div
            className="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
            onClick={() => setMobileNavOpen(false)}
            aria-hidden
          />
          <div className="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div className="relative">
              {sidebarHeader}
              <Button
                variant="outline"
                size="icon"
                aria-label="Cerrar menú"
                title="Cerrar menú"
                onClick={() => setMobileNavOpen(false)}
                className="absolute right-3 top-1/2 -translate-y-1/2"
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
            {navBody}
          </div>
        </div>
      ) : null}

      <div className="lg:pl-72">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between gap-2 border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:px-6 dark:border-slate-800 dark:bg-slate-900/95">
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="icon"
              className="lg:hidden"
              aria-label="Abrir menú"
              title="Abrir menú"
              aria-expanded={mobileNavOpen}
              onClick={() => setMobileNavOpen(true)}
            >
              <Menu className="h-4 w-4" />
            </Button>
            <div className="min-w-0 max-w-36 sm:max-w-none">
              <p className="truncate text-sm font-bold text-slate-900 dark:text-slate-100">
                {user?.company?.name ?? "FidelOS"}
              </p>
              <p className="hidden truncate text-xs text-slate-500 sm:block dark:text-slate-400">
                Gestión Empresarial Operativa
              </p>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-1 sm:gap-2">
            <div className="hidden items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 sm:flex dark:border-slate-800 dark:bg-slate-800/60">
              <UserCircle className="h-4 w-4 text-sky-600 dark:text-sky-400" />
              <div className="min-w-0">
                <p className="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">{user?.name ?? "Usuario en sesión"}</p>
                <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">{user?.roles?.[0] ?? "Administrador"}</p>
              </div>
            </div>
            <BetaNotice />
            <Button variant="outline" size="icon" aria-label="Cerrar sesión" title="Cerrar sesión" onClick={logout}>
              <LogOut className="h-4 w-4" />
            </Button>
          </div>
        </header>
        <ContingencyBanner />
        <main className="mx-auto w-full max-w-7xl px-4 py-6 lg:px-6">
          {authorized ? (
            children
          ) : (
            <div className="flex min-h-[60vh] flex-col items-center justify-center text-center">
              <ShieldAlert className="h-10 w-10 text-slate-400" />
              <h1 className="mt-4 text-lg font-semibold">No tienes acceso a esta sección médica</h1>
              <p className="mt-1 max-w-sm text-sm text-slate-500">
                Tu perfil de usuario no cuenta con los permisos asistenciales requeridos.
              </p>
              <Link href="/app/dashboard" className="mt-6 inline-flex h-9 items-center rounded-md bg-sky-600 px-4 text-sm font-medium text-white hover:bg-sky-700">
                Ir al Dashboard IPS
              </Link>
            </div>
          )}
        </main>
      </div>
    </div>
  );
}
