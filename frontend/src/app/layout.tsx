import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Gastro3D — Plataforma SaaS de Menús 3D con IA",
  description: "Transforma fotografías de platos gastronómicos en modelos 3D interactivos hiperrealistas con IA.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es">
      <body>
        <div className="bg-ambient">
          <div className="glow-orb-1" />
          <div className="glow-orb-2" />
          <div className="glow-orb-3" />
        </div>
        <div className="grid-overlay" />
        {children}
      </body>
    </html>
  );
}
