import { AppointmentCta } from "@/components/marketing/appointment-cta";
import { CircularPhotoAbout } from "@/components/marketing/circular-photo-about";
import { CtaLink } from "@/components/marketing/cta-link";
import { FaqColumns } from "@/components/marketing/faq-columns";
import { FloatingContactCard } from "@/components/marketing/floating-contact-card";
import { IconFeatureFloatCard } from "@/components/marketing/icon-feature-float-card";
import { MarketingLayout } from "@/components/marketing/marketing-layout";
import { faqs, services, testimonials } from "@/components/marketing/marketing-data";
import { Section, SectionHeading } from "@/components/marketing/marketing-ui";
import { PhotoOverlayLinks } from "@/components/marketing/photo-overlay-links";
import { PriorityBanner } from "@/components/marketing/priority-banner";
import { Reveal } from "@/components/marketing/reveal";
import { SERVICE_ICON } from "@/components/marketing/service-card";
import { SplitHero } from "@/components/marketing/split-hero";
import { TestimonialGrid } from "@/components/marketing/testimonial-card";

export default function ServiciosPage() {
  return (
    <MarketingLayout>
      <SplitHero
        eyebrow="Servicios"
        title="Portafolio de servicios médicos e IPS especializada"
        lead="Consulta médica general, especialidades, vacunación e inmunización, laboratorio clínico y procedimientos ambulatorios — con historia clínica digital por paciente."
        image="/gallery/illustrations/illustration-7.png"
        imageAlt="Médico en valoración clínica"
        actions={
          <>
            <CtaLink href="/agendar-cita" variant="cta">
              Agendar cita médica
            </CtaLink>
            <CtaLink href="#todos-los-servicios" variant="outline">
              Ver todos los servicios
            </CtaLink>
          </>
        }
      />

      <Section className="pt-0">
        <CircularPhotoAbout
          image="/gallery/ips/urgencias_prioritaria.jpg"
          imageAlt="Atención médica prioritaria"
          eyebrow="Atención Prioritaria"
          title="Prioridad inmediata cuando requiere valoración urgente"
        >
          <p>
            Ante un cuadro agudo, fiebre persistente o dolor intenso, la prioridad es estabilizar y valorar.
            Escribinos antes de venir para que nuestro equipo asistencial esté listo a tu llegada.
          </p>
        </CircularPhotoAbout>
        <div className="mt-10">
          <PriorityBanner label="Atención prioritaria, escribinos" detail="+57 601 555 0188" />
        </div>
      </Section>

      <div id="todos-los-servicios">
        <IconFeatureFloatCard
          items={services.map((s) => ({
            icon: SERVICE_ICON[s.slug],
            title: s.title,
            text: s.short,
            href: `/servicios/${s.slug}`,
          }))}
        />
      </div>

      <PhotoOverlayLinks
        title="Todos los servicios de un vistazo"
        image="/gallery/ips/consulta_medica.jpg"
        imageAlt="Procedimiento médico ambulatorio"
        items={services.map((s) => ({ label: s.title, href: `/servicios/${s.slug}` }))}
      />

      <div className="relative z-10 mx-auto -mt-16 max-w-5xl px-4 sm:px-6 lg:px-8">
        <FloatingContactCard title="Escribinos cuando quieras" />
      </div>

      <Section>
        <Reveal>
          <SectionHeading eyebrow="FAQ" title="Preguntas frecuentes" center={false} />
        </Reveal>
        <div className="mt-12">
          <FaqColumns faqs={faqs.slice(0, 6)} />
        </div>
      </Section>

      <Section className="bg-section-cream">
        <Reveal>
          <SectionHeading eyebrow="Testimonios" title="Lo que cuentan nuestros pacientes" />
        </Reveal>
        <div className="mt-12">
          <TestimonialGrid testimonials={testimonials} limit={2} />
        </div>
      </Section>

      <AppointmentCta />
    </MarketingLayout>
  );
}
