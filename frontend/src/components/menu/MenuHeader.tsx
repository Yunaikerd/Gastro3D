"use client";

import React from "react";
import { EstablishmentMenuData } from "@/types/menu";
import styles from "@/app/menu/[slug]/menu.module.css";

interface MenuHeaderProps {
  establishment: EstablishmentMenuData;
  themeMode: "dark" | "light";
  onToggleTheme: () => void;
}

export default function MenuHeader({
  establishment,
  themeMode,
  onToggleTheme,
}: MenuHeaderProps) {
  const fallbackLogo = "/images/placeholders/coffelia-logo-demo.svg";
  const fallbackCover = "/images/placeholders/coffelia-cover-demo.svg";

  const handleImageError = (e: React.SyntheticEvent<HTMLImageElement, Event>, fallback: string) => {
    const target = e.currentTarget;
    if (target.src !== fallback) {
      target.src = fallback;
    }
  };

  return (
    <header>
      {/* Top Utility Bar */}
      <div className={styles.topUtilityBar}>
        <div className={styles.demoPill}>
          <span>⚡</span>
          <span>Catálogo Demostrativo</span>
        </div>
        <button
          className={styles.themeToggleBtn}
          onClick={onToggleTheme}
          aria-label="Cambiar tema claro u oscuro"
        >
          <span>{themeMode === "dark" ? "☀️ Modo Claro" : "🌙 Modo Oscuro"}</span>
        </button>
      </div>

      {/* Hero Section */}
      <div className={styles.heroSection}>
        <div className={styles.coverCard}>
          <img
            src={establishment.cover_url || fallbackCover}
            alt={`Portada de ${establishment.name}`}
            className={styles.coverImage}
            onError={(e) => handleImageError(e, fallbackCover)}
          />
          <div className={styles.coverOverlay} />
        </div>

        <div className={styles.establishmentProfile}>
          <div className={styles.logoWrapper}>
            <img
              src={establishment.logo_url || fallbackLogo}
              alt={`Logo de ${establishment.name}`}
              className={styles.logoImage}
              onError={(e) => handleImageError(e, fallbackLogo)}
            />
          </div>

          <h1 className={styles.establishmentName}>{establishment.name}</h1>

          {establishment.tagline && (
            <p className={styles.tagline}>{establishment.tagline}</p>
          )}

          <div className={styles.metaBadges}>
            {establishment.business_type && (
              <span className={styles.badge}>
                <span>☕</span>
                <span style={{ textTransform: "capitalize" }}>
                  {establishment.business_type}
                </span>
              </span>
            )}
            {establishment.contact.city && (
              <span className={styles.badge}>
                <span>📍</span>
                <span>{establishment.contact.city}, {establishment.contact.country || "CO"}</span>
              </span>
            )}
            <span className={styles.badge}>
              <span>💳</span>
              <span>{establishment.currency || "COP"}</span>
            </span>
            {establishment.contact.whatsapp && (
              <a
                href={`https://wa.me/${establishment.contact.whatsapp.replace(/\D/g, "")}?text=Hola%2C%20quisiera%20consultar%20sobre%20su%20men%C3%BA%20digital`}
                target="_blank"
                rel="noopener noreferrer"
                className={styles.whatsappLink}
                title="Contactar por WhatsApp"
              >
                <span>💬 WhatsApp</span>
              </a>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
