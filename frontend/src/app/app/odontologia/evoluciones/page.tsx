"use client";

import { CrudField } from "@/components/crud/crud-modal";
import { ModuleTablePage } from "@/components/module-table-page";
import { AppColumnDef } from "@/lib/table-types";

interface DentalEvolution {
  id: number;
  tooth_number?: string;
  procedure_done: string;
  observations?: string;
  patient?: { name: string };
  dentist?: { name: string };
  created_at: string;
}

const columns: AppColumnDef<DentalEvolution>[] = [
  {
    header: "Pieza",
    cell: ({ row }) => (
      <span className="font-mono text-xs font-bold text-primary">
        #{row.original.tooth_number || "Gral"}
      </span>
    ),
  },
  {
    header: "Procedimiento Realizado",
    cell: ({ row }) => <span className="font-medium text-foreground">{row.original.procedure_done}</span>,
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
    header: "Observaciones",
    cell: ({ row }) => <span className="text-xs text-muted-foreground">{row.original.observations || "—"}</span>,
  },
  {
    header: "Fecha",
    cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString("es-CO"),
  },
];

const fields: CrudField[] = [
  { name: "patient_id", label: "ID Paciente", required: true },
  { name: "tooth_number", label: "Pieza Dental (FDI)", placeholder: "Ej. 16" },
  { name: "procedure_done", label: "Procedimiento Realizado", type: "textarea", required: true },
  { name: "observations", label: "Observaciones / Indicaciones", type: "textarea" },
];

export default function DentalEvolutionsPage() {
  return (
    <ModuleTablePage<DentalEvolution>
      title="Evolución Clínica Odontológica"
      description="Notas cronológicas de evolución por cita, odontólogo y pieza dental tratada."
      resource="dental-evolutions"
      columns={columns}
      fields={fields}
    />
  );
}
