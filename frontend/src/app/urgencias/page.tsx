import { AlertTriangle, Clock, PhoneCall, Stethoscope } from "lucide-react";
import { CtaLink } from "@/components/marketing/cta-link";
import { MarketingLayout } from "@/components/marketing/marketing-layout";
import { PhotoFeatureStack } from "@/components/marketing/photo-feature-stack";
import { PriorityBanner } from "@/components/marketing/priority-banner";
import { Section, SectionHeading } from "@/components/marketing/marketing-ui";
import { Reveal } from "@/components/marketing/reveal";
import { SplitHero } from "@/components/marketing/split-hero";
import { WHATSAPP_URL } from "@/components/marketing/whatsapp-link";

const signs = [
  "Dificultad para respirar, dolor torácico o cianosis",
  "Vómito o diarrea persistente con signos de deshidratación",
  "Convulsiones, alteración del estado de conciencia o síncope",
  "Traumatismo severo, caída o accidente de tránsito",
  "Sospecha de intoxicación o envenenamiento",
  "Sangrado activo que no cede o herida profunda",
  "Dolor abdominal agudo o severo",
  "Complicaciones en estado de gestación o parto",
];

const steps = [
  { icon: PhoneCall, title: "Avisanos antes de venir", text: "Escribinos por WhatsApp o llamá al centro con el caso. El equipo de triaje se prepara mientras estás en camino." },
  { icon: Stethoscope, title: "Estabilización inmediata", text: "Al llegar, la prioridad es la valoración de triaje y estabilizar: dolor, respiración, signos vitales." },
  { icon: Clock, title: "Seguimiento hasta el alta", text: "Si el paciente requiere observación o remisión a salas, mantenemos informado al núcleo familiar." },
];

export default function UrgenciasPage() {
  return (
    <MarketingLayout>
      {/* Hero: misma familia visual que Servicios/Productos/Equipo/Nosotros/Blog */}
      <SplitHero
        eyebrow="Urgencias Prioritarias"
        title="Cuando no puede esperar, actuamos con rapidez y rigor médico"
        lead="Ante un accidente, un cuadro agudo o dolor intenso, escribinos o llamá para que nuestro personal asistencial esté listo a tu llegada."
        image="/gallery/illustrations/illustration-8.png"
        imageAlt="Médico de urgencias en atención médica"
        actions={
          <CtaLink href={WHATSAPP_URL} variant="cta">
            <PhoneCall className="size-4" />
            Línea Prioritaria por WhatsApp
          </CtaLink>
        }
      />
      <div className="relative z-10 mx-auto -mt-8 max-w-5xl px-4 sm:px-6 lg:px-8">
        <PriorityBanner label="Línea directa 24/7" detail="+57 601 555 0188" />
      </div>

      <Section>
        <Reveal>
          <SectionHeading eyebrow="¿Cuándo es una urgencia?" title="Señales que requieren atención médica inmediata" center={false} />
        </Reveal>
        <div className="mt-10 rounded-[2rem] bg-card p-6 shadow-elevation-4 sm:p-10">
          <div className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
            {signs.map((sign, i) => (
              <Reveal key={sign} delay={(i % 4) * 0.05}>
                <div className="flex gap-3 text-sm leading-6">
                  <AlertTriangle className="mt-0.5 size-4.5 shrink-0 text-warning" />
                  <span>{sign}</span>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
        <p className="mt-6 text-sm text-muted-foreground">
          Ante la duda, escribinos: nuestro equipo de triaje valorará la prioridad de tu atención médica.
        </p>
      </Section>

      <Section className="bg-section-cream">
        <PhotoFeatureStack
          image="/gallery/ips/urgencias_prioritaria.jpg"
          imageAlt="Procedimiento médico de urgencia prioritaria"
          reverse
          features={[
            { title: "Valoración de Triaje", text: "Una urgencia clasificada en triaje prioritario recibe atención de inmediato sin esperas innecesarias." },
            { title: "Atención ágil", text: "Escribinos o llamá directo — coordinamos la recepción asistencial a tu llegada." },
          ]}
        />
      </Section>

      <Section dark>
        <Reveal>
          <SectionHeading eyebrow="Cómo funciona" title="Qué pasa cuando llegás con una urgencia" dark />
        </Reveal>
        <div className="mt-14 grid gap-8 sm:grid-cols-3">
          {steps.map((step, i) => {
            const Icon = step.icon;
            return (
              <Reveal key={step.title} delay={i * 0.08}>
                <span className="grid size-11 place-items-center rounded-xl bg-white/10 text-chart-3">
                  <Icon className="size-5" />
                </span>
                <h3 className="mt-5 text-base font-bold">{step.title}</h3>
                <p className="mt-2 text-sm leading-6 text-white/70">{step.text}</p>
              </Reveal>
            );
          })}
        </div>
      </Section>
    </MarketingLayout>
  );
}
