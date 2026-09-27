"use client";

import { CrudField } from "@/components/crud/crud-modal";
import { ModuleTablePage } from "@/components/module-table-page";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppColumnDef } from "@/lib/table-types";
import { Service } from "@/lib/types";

const columns: AppColumnDef<Service>[] = [
  {
    header: "Procedimiento Dental",
    cell: ({ row }) => <span className="font-semibold text-foreground">{row.original.name}</span>,
  },
  {
    header: "Descripción",
    cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.description || "—"}</span>,
  },
  {
    header: "Duración Est.",
    cell: ({ row }) => <span className="font-mono text-xs">{row.original.estimated_duration_minutes || 30} min</span>,
  },
  {
    header: "Tarifa Base",
    cell: ({ row }) => (
      <span className="font-mono text-xs font-semibold text-primary">
        ${Number(row.original.price || 0).toLocaleString("es-CO")}
      </span>
    ),
  },
  {
    header: "Estado",
    cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status === "active" ? "Activo" : "Inactivo"} />,
  },
];

const fields: CrudField[] = [
  { name: "name", label: "Nombre del Procedimiento", required: true },
  { name: "description", label: "Descripción / Detalles", type: "textarea" },
  { name: "price", label: "Tarifa ($)", type: "number", required: true },
  { name: "estimated_duration_minutes", label: "Duración estimada (min)", type: "number" },
];

export default function DentalProceduresPage() {
  return (
    <ModuleTablePage<Service>
      title="Tarifas & Portafolio de Procedimientos Dentales"
      description="Catálogo de servicios odontológicos, costos y tiempos estimados de atención."
      resource="services"
      columns={columns}
      fields={fields}
    />
  );
}
