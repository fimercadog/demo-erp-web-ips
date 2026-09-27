"use client";

import Link from "next/link";
import { Activity, Calendar, ClipboardList, FileCheck, Plus, Sparkles, UserCheck, Users } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";

export default function DentalDashboardPage() {
  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground">
            Dashboard Odontológico
          </h1>
          <p className="text-sm text-muted-foreground">
            Control de citas, odontogramas, presupuestos y evolución clínica dental.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Link href="/app/odontologia/tratamientos">
            <Button size="sm" className="gap-2">
              <Plus className="h-4 w-4" />
              Nuevo Plan de Tratamiento
            </Button>
          </Link>
          <Link href="/agendar-cita-odontologica" target="_blank">
            <Button variant="outline" size="sm" className="gap-2">
              <Calendar className="h-4 w-4" />
              Portal de Agendamiento
            </Button>
          </Link>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Citas Dentales Hoy
            </CardTitle>
            <Calendar className="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-foreground">8 Citas</div>
            <p className="text-xs text-muted-foreground mt-1">
              5 Confirmadas • 3 Atendidas
            </p>
          </CardContent>
        </Card>

        <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Presupuestos Aprobados
            </CardTitle>
            <ClipboardList className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-foreground">$14.2M</div>
            <p className="text-xs text-muted-foreground mt-1">
              12 planes convertidos a Factura ERP
            </p>
          </CardContent>
        </Card>

        <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Tratamientos Activos
            </CardTitle>
            <Activity className="h-4 w-4 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-foreground">24 Tratamientos</div>
            <p className="text-xs text-muted-foreground mt-1">
              Ortodoncia, Endodoncia e Implantes
            </p>
          </CardContent>
        </Card>

        <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Consentimientos Firmados
            </CardTitle>
            <FileCheck className="h-4 w-4 text-indigo-500" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-foreground">100% Al día</div>
            <p className="text-xs text-muted-foreground mt-1">
              Consentimientos digitalizados
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Accesos Rápidos & Módulos */}
      <div className="grid gap-6 md:grid-cols-3">
        <Card className="md:col-span-2 border-border/60">
          <CardHeader>
            <CardTitle className="text-lg font-semibold flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-primary" />
              Módulos Especializados de la Vertical Odontológica
            </CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <Link
              href="/app/pacientes"
              className="p-4 rounded-xl border border-border/60 hover:border-primary/50 transition-all bg-card/40 hover:bg-card/80 group"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-all">
                  <Users className="h-5 w-5" />
                </div>
                <h3 className="font-semibold text-foreground">Odontograma Interactivo</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                Selecciona un paciente y abre la dentición FDI adulta/pediátrica para registrar piezas y hallazgos.
              </p>
            </Link>

            <Link
              href="/app/odontologia/tratamientos"
              className="p-4 rounded-xl border border-border/60 hover:border-emerald-500/50 transition-all bg-card/40 hover:bg-card/80 group"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 rounded-lg bg-emerald-500/10 text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                  <ClipboardList className="h-5 w-5" />
                </div>
                <h3 className="font-semibold text-foreground">Planes de Tratamiento</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                Estructura presupuestos odontológicos por piezas dentales y conviértelos a Facturas del ERP Core.
              </p>
            </Link>

            <Link
              href="/app/odontologia/evoluciones"
              className="p-4 rounded-xl border border-border/60 hover:border-blue-500/50 transition-all bg-card/40 hover:bg-card/80 group"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 rounded-lg bg-blue-500/10 text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-all">
                  <Activity className="h-5 w-5" />
                </div>
                <h3 className="font-semibold text-foreground">Evolución Clínica</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                Notas cronológicas de evolución por cita, odontólogo y pieza dental tratada.
              </p>
            </Link>

            <Link
              href="/app/odontologia/consentimientos"
              className="p-4 rounded-xl border border-border/60 hover:border-indigo-500/50 transition-all bg-card/40 hover:bg-card/80 group"
            >
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 rounded-lg bg-indigo-500/10 text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                  <FileCheck className="h-5 w-5" />
                </div>
                <h3 className="font-semibold text-foreground">Consentimientos Informados</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                Gestión de documentos legales firmados para Cirugía, Endodoncia, Ortodoncia e Implantes.
              </p>
            </Link>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-lg font-semibold flex items-center gap-2">
              <UserCheck className="h-5 w-5 text-primary" />
              Especialidades Odontológicas
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {[
              { name: "Ortodoncia & Ortopedia Maxilar", active: "14 Pacientes" },
              { name: "Endodoncia Biomecánica", active: "8 Pacientes" },
              { name: "Implantología & Cirugía Oral", active: "6 Pacientes" },
              { name: "Periodoncia & Salud Gingival", active: "10 Pacientes" },
              { name: "Odontopediatría & Prevención", active: "12 Pacientes" },
            ].map((esp, i) => (
              <div key={i} className="flex items-center justify-between p-2.5 rounded-lg border border-border/40 bg-card/30">
                <span className="text-sm font-medium text-foreground">{esp.name}</span>
                <StatusBadge status="active" label={esp.active} />
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
