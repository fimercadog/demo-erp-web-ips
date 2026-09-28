import Image from "next/image";
import { AppointmentCta } from "@/components/marketing/appointment-cta";
import { container } from "@/components/marketing/page-hero";
import { CtaLink } from "@/components/marketing/cta-link";
import { MarketingLayout } from "@/components/marketing/marketing-layout";
import { stats, team } from "@/components/marketing/marketing-data";
import { Section, SectionHeading } from "@/components/marketing/marketing-ui";
import { OffsetBlobBlock } from "@/components/marketing/offset-blob-block";
import { PhotoFeatureStack } from "@/components/marketing/photo-feature-stack";
import { Reveal } from "@/components/marketing/reveal";
import { SplitHero } from "@/components/marketing/split-hero";
import { StatsSection } from "@/components/marketing/stats-section";
import { TeamProfileList } from "@/components/marketing/team-profile-row";
import { WHATSAPP_URL } from "@/components/marketing/whatsapp-link";

const values = [
  { icon: "/gallery/icons/icon-9.png", title: "Medicina preventiva real", text: "No esperamos a que algo duela: vacunación y chequeos programados desde la primera visita." },
  { icon: "/gallery/icons/icon-14.png", title: "Quirófano propio", text: "Cirugías de rutina y de tejidos blandos sin derivar el caso a otra clínica." },
  { icon: "/gallery/icons/icon-13.png", title: "Diagnóstico el mismo día", text: "Laboratorio propio para no hacerte esperar un resultado externo." },
];

export default function AboutPage() {
  return (
    <MarketingLayout>
      {/* Hero: misma familia visual que Servicios -- eyebrow, titulo grande,
          texto, CTAs, ilustracion protagonista a la derecha sobre blob organico. */}
      <SplitHero
        eyebrow="Nosotros"
        title="Un centro médico de vanguardia con atención cercana y humana"
        lead="NOVA IPS nació para que cada paciente y su familia cuenten con un equipo médico especializado que los acompañe en cada etapa de su vida."
        image="/gallery/illustrations/illustration-2.png"
        imageAlt="Médico especialista en consulta médica"
        actions={
          <>
            <CtaLink href="/equipo" variant="cta">
              Conocer al equipo médico
            </CtaLink>
            <CtaLink href={WHATSAPP_URL} variant="outline">
              Escribinos por WhatsApp
            </CtaLink>
          </>
        }
      />

      {/* Tira de 3 iconos plana, sin tarjeta flotante */}
      <Section className="pb-0">
        <div className="grid gap-x-10 gap-y-10 sm:grid-cols-3">
          {values.map((item, i) => (
            <Reveal key={item.title} delay={i * 0.08}>
              <Image src={item.icon} alt="" width={56} height={56} className="size-14" />
              <p className="mt-4 font-heading text-sm font-extrabold uppercase tracking-[0.14em] text-chart-4">{item.title}</p>
              <p className="mt-2 text-sm leading-6 text-muted-foreground">{item.text}</p>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section>
        <PhotoFeatureStack
          image="/gallery/ips/doctor_alejandro_morales.jpg"
          imageAlt="Médico del equipo en consulta médica"
          features={[
            { title: "Más de una década", text: "Iniciamos como un consultorio médico de atención general; hoy contamos con instalaciones modernas, laboratorio clínico y salas de procedimientos ambulatorios." },
            { title: "Atención personalizada", text: "Cada consulta se agenda con el tiempo adecuado para una valoración integral e historia clínica completa." },
            { title: "El mismo equipo siempre", text: "Médicos especialistas de planta que conocen la historia clínica de cada paciente y su núcleo familiar." },
          ]}
        />
      </Section>

      <OffsetBlobBlock
        title="Nuestra misión y valores"
        image="/gallery/ips/consulta_medica.jpg"
        imageAlt="Procedimiento médico asistencial"
        actions={
          <CtaLink href="/servicios" variant="cta">
            Ver servicios médicos
          </CtaLink>
        }
      >
        <ul className="mt-2 space-y-3 text-sm leading-6">
          <li>
            <strong className="font-bold">Trato humano.</strong> Explicamos cada diagnóstico y tratamiento con claridad y empatía.
          </li>
          <li>
            <strong className="font-bold">Medicina basada en la evidencia.</strong> Diagnósticos rigurosos, guías de práctica clínica y seguimiento continuo.
          </li>
          <li>
            <strong className="font-bold">Innovación digital.</strong> Historia clínica digital integrada y resultados de laboratorio en línea.
          </li>
        </ul>
      </OffsetBlobBlock>

      {/* Parrafo ancho de storytelling */}
      <Section className="pt-0">
        <Reveal>
          <div className={`${container} max-w-3xl space-y-5 text-base leading-8 text-muted-foreground`}>
            <p>
              NOVA IPS nació con el propósito de brindar una atención médica integral, oportuna y personalizada.
              Más de una década después, contamos con consultorios especializados, laboratorio clínico certificado y salas
              de procedimientos ambulatorios, manteniendo nuestro compromiso con la salud y bienestar de cada paciente.
            </p>
            <p>
              Trabajamos con profesionales de la salud capacitados y con historia clínica digital unificada, permitiendo que
              cada consulta — ya sea de prevención, control o atención prioritaria — se construya sobre el historial clínico del paciente.
            </p>
          </div>
        </Reveal>
      </Section>

      {/* Equipo -- mismo patron "Highly Trained Veterinarians" de About: fila
          apilada foto+card, no un grid de tarjetas parejas (ver Equipo para el
          listado completo con bios). */}
      <Section className="bg-section-cream">
        <Reveal>
          <SectionHeading eyebrow="Profesionales" title="El equipo detrás de cada consulta" center={false} />
        </Reveal>
        <div className="mt-14">
          <TeamProfileList team={team} />
        </div>
      </Section>

      <Section dark>
        <StatsSection stats={stats} dark />
      </Section>

      <AppointmentCta />
    </MarketingLayout>
  );
}
