import { use } from "react";
import { OdontogramView } from "./odontogram-view";

export function generateStaticParams() {
  return Array.from({ length: 50 }, (_, i) => ({ id: String(i + 1) }));
}

export default function OdontogramPage({ params }: { params: Promise<{ id: string }> }) {
  const resolvedParams = use(params);
  return <OdontogramView patientId={resolvedParams.id} />;
}
