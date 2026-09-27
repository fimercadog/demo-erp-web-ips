"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Activity, Calendar, Check, Clock, HeartPulse, Phone, ShieldCheck, Sparkles, Star, UserCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchApi } from "@/lib/api";

interface PublicCompanyInfo {
  name: string;
  phone?: string;
  email?: string;
  address?: string;
}

export default function PublicDentalLandingPage() {
  const [company, setCompany] = useState<PublicCompanyInfo>({
    name: "Clínica Odontológica Especializada",
  });

  useEffect(() => {
    fetchApi<{ data: PublicCompanyInfo }>("/public/company/info")
      .then((res) => {
        if (res.data) setCompany(res.data);
      })
      .catch(() => {});
  }, []);

  return (
    <div className="min-h-screen bg-background text-foreground">
      {/* Header / Nav */}
      <header className="sticky top-0 z-50 border-b border-border/40 bg-background/80 backdrop-blur-md">
        <div className="container mx-auto flex h-16 items-center justify-between px-4">
          <div className="flex items-center gap-2 font-bold text-xl text-primary">
            <Sparkles className="h-6 w-6" />
            <span>{company.name}</span>
          </div>

          <div className="flex items-center gap-3">
            <Link href="/agendar-cita-odontologica">
              <Button size="sm" className="gap-2">
                <Calendar className="h-4 w-4" />
                Agendar Cita
              </Button>
            </Link>
            <Link href="/login">
              <Button variant="outline" size="sm">
                Portal Odontológico
              </Button>
            </Link>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section className="relative overflow-hidden py-20 lg:py-28 bg-gradient-to-b from-primary/10 via-background to-background">
        <div className="container mx-auto px-4 text-center">
          <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-xs font-semibold text-primary mb-6">
            <ShieldCheck className="h-4 w-4" />
            Atención Odontológica Especializada & Odontograma Digital
          </div>

          <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl md:text-6xl text-foreground max-w-3xl mx-auto">
            Salud Oral Avanzada & <span className="text-primary">Diseño de Sonrisa</span>
          </h1>

          <p className="mt-6 text-lg text-muted-foreground max-w-2xl mx-auto">
            Bienvenido a {company.name}. Ofrecemos diagnóstico con odontograma digital interactivo, tecnología láser, endodoncia, ortodoncia invisible e implantes de alta precisión.
          </p>

          <div className="mt-8 flex flex-wrap justify-center gap-4">
            <Link href="/agendar-cita-odontologica">
              <Button className="h-11 px-8 gap-2 text-base">
                <Calendar className="h-5 w-5" />
                Reserva tu Valoración Dental
              </Button>
            </Link>
            {company.phone && (
              <a href={`tel:${company.phone}`}>
                <Button variant="outline" className="h-11 px-8 gap-2 text-base">
                  <Phone className="h-5 w-5" />
                  {company.phone}
                </Button>
              </a>
            )}
          </div>
        </div>
      </section>

      {/* Especialidades */}
      <section className="py-16 container mx-auto px-4">
        <div className="text-center max-w-2xl mx-auto mb-12">
          <h2 className="text-3xl font-bold tracking-tight text-foreground">
            Especialidades Clínicas Odontológicas
          </h2>
          <p className="text-sm text-muted-foreground mt-2">
            Tratamientos integrales con profesionales certificados y materiales de alta bio-compatibilidad.
          </p>
        </div>

        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {[
            {
              title: "Ortodoncia & Alineadores",
              desc: "Brackets estéticos cerámicos y alineadores invisibles para la alineación dental perfecta.",
            },
            {
              title: "Endodoncia Biomecánica",
              desc: "Tratamiento de conductos de alta precisión con localizador apical e instrumentación rotatoria.",
            },
            {
              title: "Implantología & Cirugía Oral",
              desc: "Rehabilitación fija con implantes de titanio grado médico para restaurar la masticación.",
            },
            {
              title: "Estética & Diseño de Sonrisa",
              desc: "Carillas cerámicas, resinas fotocuradas nanohíbridas y blanqueamiento LED avanzado.",
            },
            {
              title: "Periodoncia & Salud Gingival",
              desc: "Tratamiento especializado de encías, detartraje ultrasónico y prevención periodontal.",
            },
            {
              title: "Odontopediatría Prevención",
              desc: "Atención especializada para niños, sellantes de fosas y fisuras y aplicación de flúor.",
            },
          ].map((item, index) => (
            <Card key={index} className="border-border/60 bg-card/60 backdrop-blur-sm hover:border-primary/50 transition-all">
              <CardHeader>
                <CardTitle className="text-lg font-semibold flex items-center gap-2 text-foreground">
                  <Sparkles className="h-5 w-5 text-primary" />
                  {item.title}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-muted-foreground">{item.desc}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-border/40 py-8 text-center text-xs text-muted-foreground">
        <div className="container mx-auto px-4">
          <p>© {new Date().getFullYear()} {company.name}. Todos los derechos reservados. Plataforma FidelOS ERP.</p>
        </div>
      </footer>
    </div>
  );
}
