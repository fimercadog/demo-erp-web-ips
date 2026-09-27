"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, Calendar, Check, Clock, Sparkles, User, UserCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchApi } from "@/lib/api";

interface DentalServiceItem {
  id: number;
  name: string;
  price: number;
  estimated_duration_minutes: number;
}

export default function PublicDentalBookingPage() {
  const [services, setServices] = useState<DentalServiceItem[]>([]);
  const [selectedServiceId, setSelectedServiceId] = useState<number | null>(null);
  const [patientName, setPatientName] = useState("");
  const [clientEmail, setClientEmail] = useState("");
  const [clientPhone, setClientPhone] = useState("");
  const [date, setDate] = useState("");
  const [time, setTime] = useState("09:00");
  const [notes, setNotes] = useState("");

  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchApi<{ data: DentalServiceItem[] }>("/public/dental/services")
      .then((res) => {
        if (res.data) setServices(res.data);
      })
      .catch(() => {
        // Demo fallback services
        setServices([
          { id: 1, name: "Profilaxis y Detartraje Ultrasónico", price: 120000, estimated_duration_minutes: 45 },
          { id: 2, name: "Resina Estética Fotocurable", price: 180000, estimated_duration_minutes: 45 },
          { id: 3, name: "Valoración & Diagnóstico Odontológico", price: 80000, estimated_duration_minutes: 30 },
          { id: 4, name: "Tratamiento de Conducto (Endodoncia)", price: 450000, estimated_duration_minutes: 60 },
        ]);
      });
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedServiceId || !patientName || !clientEmail || !clientPhone || !date) {
      setError("Por favor completa los campos obligatorios.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await fetchApi("/public/dental/book", {
        method: "POST",
        body: JSON.stringify({
          service_id: selectedServiceId,
          starts_at: `${date} ${time}:00`,
          client_name: patientName,
          client_email: clientEmail,
          client_phone: clientPhone,
          patient_name: patientName,
          notes,
        }),
      });

      setSuccess(true);
    } catch {
      setError("No se pudo confirmar la cita. Revisa el horario o intenta de nuevo.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background py-12 px-4">
      <div className="max-w-3xl mx-auto space-y-6">
        <Link href="/odontologia" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="h-4 w-4" /> Volver a la Clínica Odontológica
        </Link>

        <div className="text-center space-y-2">
          <h1 className="text-3xl font-extrabold tracking-tight text-foreground flex items-center justify-center gap-2">
            <Sparkles className="h-7 w-7 text-primary" />
            Agendamiento de Cita Odontológica
          </h1>
          <p className="text-sm text-muted-foreground">
            Reserva tu turno de atención odontológica en tiempo real de forma segura.
          </p>
        </div>

        {success ? (
          <Card className="border-emerald-500/50 bg-emerald-500/10 p-8 text-center space-y-4">
            <div className="w-12 h-12 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto">
              <Check className="h-6 w-6" />
            </div>
            <h2 className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
              ¡Cita Odontológica Agendada Exitosamente!
            </h2>
            <p className="text-sm text-muted-foreground max-w-md mx-auto">
              Hemos registrado tu reserva para el <strong>{date} a las {time}</strong>. Te enviaremos recordatorio a {clientEmail}.
            </p>
            <Button onClick={() => window.location.reload()} className="mt-4">
              Agendar Otra Cita
            </Button>
          </Card>
        ) : (
          <Card className="border-border/60 bg-card/60 backdrop-blur-md">
            <CardHeader>
              <CardTitle className="text-lg font-semibold">Formulario de Reserva</CardTitle>
            </CardHeader>

            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">
                {error && (
                  <div className="p-3 rounded-lg border border-red-500/30 bg-red-500/10 text-red-500 text-sm">
                    {error}
                  </div>
                )}

                <div>
                  <label className="text-xs font-semibold text-muted-foreground block mb-2">
                    1. Selecciona el Procedimiento / Servicio Odontológico *
                  </label>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {services.map((srv) => (
                      <div
                        key={srv.id}
                        onClick={() => setSelectedServiceId(srv.id)}
                        className={`p-3.5 rounded-xl border cursor-pointer transition-all ${
                          selectedServiceId === srv.id
                            ? "border-primary bg-primary/10 ring-2 ring-primary"
                            : "border-border/60 hover:bg-card"
                        }`}
                      >
                        <div className="font-semibold text-sm text-foreground">{srv.name}</div>
                        <div className="flex items-center justify-between text-xs text-muted-foreground mt-1">
                          <span>{srv.estimated_duration_minutes} min</span>
                          <span className="font-mono font-bold text-primary">
                            ${Number(srv.price).toLocaleString("es-CO")}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className="text-xs font-semibold text-muted-foreground block mb-1">
                      Nombre completo del paciente *
                    </label>
                    <input
                      type="text"
                      required
                      value={patientName}
                      onChange={(e) => setPatientName(e.target.value)}
                      placeholder="Ej. Camila Ramírez"
                      className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                  </div>

                  <div>
                    <label className="text-xs font-semibold text-muted-foreground block mb-1">
                      Teléfono / WhatsApp *
                    </label>
                    <input
                      type="tel"
                      required
                      value={clientPhone}
                      onChange={(e) => setClientPhone(e.target.value)}
                      placeholder="+57 300 123 4567"
                      className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                  </div>
                </div>

                <div>
                  <label className="text-xs font-semibold text-muted-foreground block mb-1">
                    Correo electrónico *
                  </label>
                  <input
                    type="email"
                    required
                    value={clientEmail}
                    onChange={(e) => setClientEmail(e.target.value)}
                    placeholder="paciente@correo.com"
                    className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                  />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className="text-xs font-semibold text-muted-foreground block mb-1">
                      Fecha preferida *
                    </label>
                    <input
                      type="date"
                      required
                      value={date}
                      onChange={(e) => setDate(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                  </div>

                  <div>
                    <label className="text-xs font-semibold text-muted-foreground block mb-1">
                      Hora de atención *
                    </label>
                    <select
                      value={time}
                      onChange={(e) => setTime(e.target.value)}
                      className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                      <option value="08:00">08:00 AM</option>
                      <option value="09:00">09:00 AM</option>
                      <option value="10:00">10:00 AM</option>
                      <option value="11:00">11:00 AM</option>
                      <option value="14:00">02:00 PM</option>
                      <option value="15:00">03:00 PM</option>
                      <option value="16:00">04:00 PM</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="text-xs font-semibold text-muted-foreground block mb-1">
                    Molestia o motivo de consulta
                  </label>
                  <textarea
                    rows={3}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Describa si tiene dolor, sensibilidad o motivo especial de valoración..."
                    className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                  />
                </div>

                <Button type="submit" disabled={loading} className="w-full size-lg gap-2 text-base">
                  {loading ? "Confirmando Cita..." : "Confirmar Cita Odontológica"}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
