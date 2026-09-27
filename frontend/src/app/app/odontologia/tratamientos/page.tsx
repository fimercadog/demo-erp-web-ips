"use client";

import { CrudField } from "@/components/crud/crud-modal";
import { ModuleTablePage } from "@/components/module-table-page";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppColumnDef } from "@/lib/table-types";
import { Button } from "@/components/ui/button";
import { fetchApi } from "@/lib/api";
import { FileCheck, DollarSign } from "lucide-react";

interface TreatmentPlan {
  id: number;
  title: string;
  patient?: { name: string };
  dentist?: { name: string };
  total_amount: number;
  discount_amount: number;
  final_amount: number;
  status: "draft" | "approved" | "in_progress" | "completed" | "cancelled";
  invoice_id?: number | null;
  created_at: string;
}

const columns: AppColumnDef<TreatmentPlan>[] = [
  {
    header: "Plan de Tratamiento",
    cell: ({ row }) => (
      <span className="font-semibold text-foreground">{row.original.title}</span>
    ),
  },
  {
    header: "Paciente",
    cell: ({ row }) => row.original.patient?.name || "—",
  },
  {
    header: "Odontólogo",
    cell: ({ row }) => row.original.dentist?.name || "—",
  },
  {
    header: "Valor Total",
    cell: ({ row }) => (
      <span className="font-mono text-xs font-semibold">
        ${Number(row.original.final_amount || 0).toLocaleString("es-CO")}
      </span>
    ),
  },
  {
    header: "Estado",
    cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status.toUpperCase()} />,
  },
  {
    header: "Facturación ERP",
    cell: ({ row }) => {
      if (row.original.invoice_id) {
        return <span className="text-xs font-medium text-emerald-500 flex items-center gap-1"><FileCheck className="h-3.5 w-3.5" /> Facturado #{row.original.invoice_id}</span>;
      }
      return (
        <Button
          size="sm"
          variant="outline"
          className="h-7 text-xs gap-1 text-primary hover:text-primary"
          onClick={async () => {
            try {
              await fetchApi(`/treatment-plans/${row.original.id}/convert-to-invoice`, { method: "POST" });
              window.location.reload();
            } catch {
              alert("No se pudo convertir el plan a factura.");
            }
          }}
        >
          <DollarSign className="h-3 w-3" /> Facturar
        </Button>
      );
    },
  },
];

const fields: CrudField[] = [
  { name: "patient_id", label: "ID Paciente", required: true },
  { name: "title", label: "Título del Plan", required: true },
  { name: "discount_amount", label: "Descuento ($)", type: "number" },
  { name: "notes", label: "Observaciones", type: "textarea" },
];

export default function TreatmentPlansPage() {
  return (
    <ModuleTablePage<TreatmentPlan>
      title="Planes de Tratamiento & Presupuestos"
      description="Estructuración de presupuestos odontológicos por piezas dentales y facturación directa en ERP."
      resource="treatment-plans"
      columns={columns}
      fields={fields}
    />
  );
}
