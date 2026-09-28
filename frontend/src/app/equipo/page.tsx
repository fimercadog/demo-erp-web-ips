import { AppointmentCta } from "@/components/marketing/appointment-cta";
import { CtaLink } from "@/components/marketing/cta-link";
import { MarketingLayout } from "@/components/marketing/marketing-layout";
import { team } from "@/components/marketing/marketing-data";
import { Section } from "@/components/marketing/marketing-ui";
import { PhotoFeatureStack } from "@/components/marketing/photo-feature-stack";
import { SplitHero } from "@/components/marketing/split-hero";
import { TeamProfileList } from "@/components/marketing/team-profile-row";

export default function EquipoPage() {
  return (
    <MarketingLayout>
      {/* Hero: misma familia visual que Servicios/Nosotros. */}
      <SplitHero
        eyebrow="Equipo"
        title="El equipo médico que te acompaña en cada consulta"
        lead="Médicos de planta, especialistas y personal asistencial que coordinan tu atención, citas y seguimiento médico."
        image="/gallery/illustrations/illustration-4.png"
        imageAlt="Médico del equipo en atención médica"
        actions={
          <CtaLink href="/agendar-cita" variant="cta">
            Agendar cita médica
          </CtaLink>
        }
      />

      <Section className="pt-0">
        <PhotoFeatureStack
          image="/gallery/ips/doctor_alejandro_morales.jpg"
          imageAlt="Médico especialista realizando una valoración en consulta"
          reverse
          features={[
            { title: "Continuidad y rigor profesional", text: "Médicos tratantes que conocen tu historia clínica y brindan seguimiento continuo en cada visita." },
            { title: "Atención multidisciplinaria", text: "Especialistas en consulta general, medicina interna, pediatría y prevención trabajando de forma coordinada." },
          ]}
        />
      </Section>

      {/* Fila apilada foto+card -- patron "Highly Trained Veterinarians" de
          About, en vez de un grid de tarjetas parejas. */}
      <Section className="bg-section-cream">
        <TeamProfileList team={team} />
      </Section>

      <AppointmentCta />
    </MarketingLayout>
  );
}
