"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Check, AlertCircle, Save, Sparkles, Shield, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchApi } from "@/lib/api";

type ToothCondition = "caries" | "restoration" | "crown" | "endo" | "missing" | "implant" | "sealant" | "normal";

interface ToothFinding {
  tooth_number: string;
  surface: string;
  condition: ToothCondition;
  status: "existing" | "required" | "performed";
  notes?: string;
}

const CONDITION_COLORS: Record<ToothCondition, { bg: string; text: string; label: string }> = {
  normal: { bg: "bg-background border-border text-foreground", text: "text-foreground", label: "Sano" },
  caries: { bg: "bg-red-500/20 border-red-500 text-red-600 dark:text-red-400", text: "text-red-500", label: "Caries (Requerido)" },
  restoration: { bg: "bg-blue-500/20 border-blue-500 text-blue-600 dark:text-blue-400", text: "text-blue-500", label: "Restauración / Resina" },
  crown: { bg: "bg-amber-500/20 border-amber-500 text-amber-600 dark:text-amber-400", text: "text-amber-500", label: "Corona Dental" },
  endo: { bg: "bg-purple-500/20 border-purple-500 text-purple-600 dark:text-purple-400", text: "text-purple-500", label: "Endodoncia / Conducto" },
  missing: { bg: "bg-slate-500/20 border-slate-500 text-slate-600 dark:text-slate-400 opacity-60", text: "text-slate-500", label: "Pieza Ausente" },
  implant: { bg: "bg-emerald-500/20 border-emerald-500 text-emerald-600 dark:text-emerald-400", text: "text-emerald-500", label: "Implante Titanio" },
  sealant: { bg: "bg-cyan-500/20 border-cyan-500 text-cyan-600 dark:text-cyan-400", text: "text-cyan-500", label: "Sellante Preventivo" },
};

/** FDI Adult Quadrants */
const ADULT_Q1 = ["18", "17", "16", "15", "14", "13", "12", "11"];
const ADULT_Q2 = ["21", "22", "23", "24", "25", "26", "27", "28"];
const ADULT_Q4 = ["48", "47", "46", "45", "44", "43", "42", "41"];
const ADULT_Q3 = ["31", "32", "33", "34", "35", "36", "37", "38"];

export function OdontogramView({ patientId }: { patientId: string }) {
  const [odontogramId, setOdontogramId] = useState<number | null>(null);
  const [selectedTooth, setSelectedTooth] = useState<string>("16");
  const [selectedSurface, setSelectedSurface] = useState<string>("O");
  const [selectedCondition, setSelectedCondition] = useState<ToothCondition>("caries");
  const [findings, setFindings] = useState<Record<string, ToothFinding>>({
    "16": { tooth_number: "16", surface: "O", condition: "caries", status: "required", notes: "Caries oclusal Grado II" },
    "21": { tooth_number: "21", surface: "V", condition: "restoration", status: "existing", notes: "Resina previa" },
    "36": { tooth_number: "36", surface: "ALL", condition: "endo", status: "required", notes: "Conducto radicular" },
    "48": { tooth_number: "48", surface: "ALL", condition: "missing", status: "existing", notes: "Ausente" },
  });

  const [saving, setSaving] = useState<boolean>(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    fetchApi<{ data: { id: number; teeth?: ToothFinding[] } }>(`/odontograms/patient/${patientId}`)
      .then((res) => {
        if (res.data) {
          setOdontogramId(res.data.id);
          if (res.data.teeth && res.data.teeth.length > 0) {
            const map: Record<string, ToothFinding> = {};
            res.data.teeth.forEach((t) => {
              map[t.tooth_number] = t;
            });
            setFindings((prev) => ({ ...prev, ...map }));
          }
        }
      })
      .catch(() => {});
  }, [patientId]);

  const handleSaveTooth = async () => {
    if (!odontogramId) return;
    setSaving(true);
    setMessage(null);
    try {
      await fetchApi(`/odontograms/${odontogramId}/tooth`, {
        method: "POST",
        body: JSON.stringify({
          tooth_number: selectedTooth,
          surface: selectedSurface,
          condition: selectedCondition,
          status: selectedCondition === "caries" || selectedCondition === "endo" ? "required" : "existing",
        }),
      });

      setFindings((prev) => ({
        ...prev,
        [selectedTooth]: {
          tooth_number: selectedTooth,
          surface: selectedSurface,
          condition: selectedCondition,
          status: selectedCondition === "caries" ? "required" : "existing",
        },
      }));
      setMessage(`Pieza ${selectedTooth} actualizada correctamente.`);
    } catch {
      setMessage("Error al guardar hallazgo dental.");
    } finally {
      setSaving(false);
    }
  };

  const renderToothBox = (toothNumber: string) => {
    const finding = findings[toothNumber];
    const condition = finding ? finding.condition : "normal";
    const colors = CONDITION_COLORS[condition];
    const isSelected = selectedTooth === toothNumber;

    return (
      <button
        key={toothNumber}
        type="button"
        onClick={() => setSelectedTooth(toothNumber)}
        className={`flex flex-col items-center justify-between p-2 rounded-xl border-2 transition-all min-w-[50px] h-[75px] ${
          colors.bg
        } ${isSelected ? "ring-2 ring-primary ring-offset-2 scale-105" : "hover:scale-102"}`}
      >
        <span className="text-xs font-mono font-bold">{toothNumber}</span>
        <div className="w-6 h-6 rounded-full border border-current flex items-center justify-center text-[10px] font-bold">
          {condition === "missing" ? "✕" : finding ? finding.surface[0] : "—"}
        </div>
        <span className="text-[9px] truncate max-w-[45px] font-medium">
          {colors.label.split(" ")[0]}
        </span>
      </button>
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="flex items-center gap-3">
          <Link href={`/app/pacientes/${patientId}`}>
            <Button variant="outline" size="icon">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
              <Sparkles className="h-6 w-6 text-primary" />
              Odontograma Digital Interactivo (FDI)
            </h1>
            <p className="text-sm text-muted-foreground">
              Registro gráfico de dentición adulta y temporal. Diagnóstico por pieza y superficie.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button onClick={handleSaveTooth} disabled={saving} className="gap-2">
            {saving ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Guardar Pieza {selectedTooth}
          </Button>
        </div>
      </div>

      {message && (
        <div className="p-3 rounded-lg border border-primary/30 bg-primary/10 text-primary text-sm flex items-center gap-2">
          <Check className="h-4 w-4" />
          {message}
        </div>
      )}

      {/* Interactive Graphic Charting System */}
      <Card className="border-border/60 bg-card/60 backdrop-blur-sm">
        <CardHeader>
          <CardTitle className="text-lg font-semibold flex items-center justify-between">
            <span>Dentición Adulta (Nomenclatura FDI)</span>
            <span className="text-xs font-normal text-muted-foreground">
              Pieza seleccionada: <strong className="text-primary font-mono text-sm">{selectedTooth}</strong>
            </span>
          </CardTitle>
        </CardHeader>

        <CardContent className="space-y-8">
          {/* Maxilar Superior */}
          <div className="space-y-2">
            <div className="text-xs font-semibold text-muted-foreground text-center uppercase tracking-wider">
              Maxilar Superior (Arcada Superior)
            </div>
            <div className="flex flex-wrap items-center justify-center gap-1.5 p-3 rounded-2xl bg-muted/20 border border-border/40 overflow-x-auto">
              <div className="flex gap-1.5 pr-3 border-r border-border">
                {ADULT_Q1.map(renderToothBox)}
              </div>
              <div className="flex gap-1.5 pl-3">
                {ADULT_Q2.map(renderToothBox)}
              </div>
            </div>
          </div>

          {/* Maxilar Inferior */}
          <div className="space-y-2">
            <div className="text-xs font-semibold text-muted-foreground text-center uppercase tracking-wider">
              Maxilar Inferior (Arcada Inferior)
            </div>
            <div className="flex flex-wrap items-center justify-center gap-1.5 p-3 rounded-2xl bg-muted/20 border border-border/40 overflow-x-auto">
              <div className="flex gap-1.5 pr-3 border-r border-border">
                {ADULT_Q4.map(renderToothBox)}
              </div>
              <div className="flex gap-1.5 pl-3">
                {ADULT_Q3.map(renderToothBox)}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Editor de Hallazgo y Leyenda */}
      <div className="grid gap-6 md:grid-cols-3">
        <Card className="md:col-span-2 border-border/60">
          <CardHeader>
            <CardTitle className="text-base font-semibold">
              Edición de la Pieza Dental #{selectedTooth}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <label className="text-xs font-semibold text-muted-foreground block mb-2">
                Condición / Hallazgo Clínico
              </label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                {(Object.keys(CONDITION_COLORS) as ToothCondition[]).map((cond) => (
                  <button
                    key={cond}
                    type="button"
                    onClick={() => setSelectedCondition(cond)}
                    className={`p-3 rounded-xl border text-xs font-medium text-left transition-all ${
                      selectedCondition === cond
                        ? "border-primary bg-primary/10 ring-2 ring-primary"
                        : "border-border/60 hover:bg-card/80"
                    }`}
                  >
                    <div className="font-semibold">{CONDITION_COLORS[cond].label}</div>
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label className="text-xs font-semibold text-muted-foreground block mb-2">
                Superficie Dental
              </label>
              <div className="flex flex-wrap gap-2">
                {["O", "M", "D", "V", "L", "ALL"].map((surf) => (
                  <button
                    key={surf}
                    type="button"
                    onClick={() => setSelectedSurface(surf)}
                    className={`px-4 py-2 rounded-lg border text-xs font-mono font-bold transition-all ${
                      selectedSurface === surf
                        ? "bg-primary text-primary-foreground border-primary"
                        : "border-border/60 hover:bg-card"
                    }`}
                  >
                    {surf === "O" ? "Oclusal (O)" : surf === "M" ? "Mesial (M)" : surf === "D" ? "Distal (D)" : surf === "V" ? "Vestibular (V)" : surf === "L" ? "Lingual (L)" : "Toda la Pieza"}
                  </button>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-base font-semibold">Leyenda Odontológica</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2.5">
            {Object.entries(CONDITION_COLORS).map(([key, val]) => (
              <div key={key} className="flex items-center gap-3 text-xs">
                <div className={`w-3.5 h-3.5 rounded-full border ${val.bg}`} />
                <span className="font-medium text-foreground">{val.label}</span>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
