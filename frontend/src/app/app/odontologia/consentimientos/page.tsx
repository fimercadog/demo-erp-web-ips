"use client";

import { CrudField } from "@/components/crud/crud-modal";
import { ModuleTablePage } from "@/components/module-table-page";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppColumnDef } from "@/lib/table-types";

interface DentalConsent {
  id: number;
  title: string;
  procedure_type: string;
  patient?: { name: string };
  signed_at?: string | null;
  created_at: string;
}

const columns: AppColumnDef<DentalConsent>[] = [
  {
    header: "Documento Consentimiento",
    cell: ({ row }) => <span className="font-semibold text-foreground">{row.original.title}</span>,
  },
  {
    header: "Tipo de Procedimiento",
    cell: ({ row }) => (
      <span className="capitalize font-mono text-xs text-primary font-medium">
        {row.original.procedure_type}
      </span>
    ),
  },
  {
    header: "Paciente",
    cell: ({ row }) => row.original.patient?.name || "—",
  },
  {
    header: "Estado Firma",
    cell: ({ row }) => (
      <StatusBadge
        status={row.original.signed_at ? "active" : "pending"}
        label={row.original.signed_at ? "Firmado Digitalmente" : "Pendiente de Firma"}
      />
    ),
  },
  {
    header: "Fecha Firma",
    cell: ({ row }) =>
      row.original.signed_at ? new Date(row.original.signed_at).toLocaleDateString("es-CO") : "—",
  },
];

const fields: CrudField[] = [
  { name: "patient_id", label: "ID Paciente", required: true },
  {
    name: "procedure_type",
    label: "Tipo de Procedimiento",
    type: "select",
    options: [
      { label: "Endodoncia", value: "endodontics" },
      { label: "Ortodoncia", value: "orthodontics" },
      { label: "Cirugía Oral / Extracción", value: "surgery" },
      { label: "Implante Dental", value: "implantology" },
      { label: "Procedimiento General", value: "general" },
    ],
    required: true,
  },
  { name: "title", label: "Título del Documento", required: true },
  { name: "content", label: "Texto del Consentimiento", type: "textarea", required: true },
];

export default function DentalConsentsPage() {
  return (
    <ModuleTablePage<DentalConsent>
      title="Consentimientos Informados Odontológicos"
      description="Registro legal y firmas digitales de consentimientos informados por especialidad."
      resource="dental-consents"
      columns={columns}
      fields={fields}
    />
  );
}
