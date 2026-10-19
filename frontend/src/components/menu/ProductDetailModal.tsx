"use client";

import React, { useEffect } from "react";
import { MenuProductData } from "@/types/menu";
import styles from "@/app/menu/[slug]/menu.module.css";

interface ProductDetailModalProps {
  product: MenuProductData | null;
  currency: string;
  onClose: () => void;
}

export default function ProductDetailModal({
  product,
  currency,
  onClose,
}: ProductDetailModalProps) {
  useEffect(() => {
    if (!product) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onClose();
      }
    };

    document.addEventListener("keydown", handleKeyDown);
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      document.body.style.overflow = "unset";
    };
  }, [product, onClose]);

  if (!product) return null;

  const fallbackImage = "/images/placeholders/food-placeholder.svg";

  const handleImageError = (e: React.SyntheticEvent<HTMLImageElement, Event>) => {
    const target = e.currentTarget;
    if (target.src !== fallbackImage) {
      target.src = fallbackImage;
    }
  };

  const formatPrice = (amount: number, curr: string) => {
    try {
      return new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: curr || "COP",
        maximumFractionDigits: 0,
      }).format(amount);
    } catch {
      return `$${amount.toLocaleString()} ${curr}`;
    }
  };

  return (
    <div
      className={styles.modalOverlay}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-product-title"
    >
      <div
        className={styles.modalContent}
        onClick={(e) => e.stopPropagation()}
      >
        <button
          className={styles.modalCloseBtn}
          onClick={onClose}
          aria-label="Cerrar detalle"
        >
          ✕
        </button>

        <div className={styles.modalImageWrapper}>
          <img
            src={product.photo_url || fallbackImage}
            alt={product.name}
            className={styles.modalImage}
            onError={handleImageError}
          />
        </div>

        <div className={styles.modalBody}>
          <div className={styles.modalHeader}>
            <h2 id="modal-product-title" className={styles.modalTitle}>
              {product.name}
            </h2>
            <div className={styles.modalPrice}>
              {formatPrice(product.price, currency)}
            </div>
          </div>

          {product.description && (
            <p className={styles.modalDesc}>{product.description}</p>
          )}

          {/* Prep time & Tags */}
          <div className={styles.modalTagsRow}>
            {product.prep_time_minutes && (
              <span className={styles.badge}>
                <span>⏱</span>
                <span>{product.prep_time_minutes} min preparación</span>
              </span>
            )}

            {product.dietary_tags && product.dietary_tags.map((tag) => (
              <span key={tag} className={styles.dietaryPill}>
                ✓ {tag}
              </span>
            ))}

            {product.allergens && product.allergens.map((allergen) => (
              <span key={allergen} className={styles.allergenPill}>
                ⚠ Contiene: {allergen}
              </span>
            ))}
          </div>

          {/* 3D Preparation Notice (Rule: only if has_3d is true) */}
          {product.has_3d ? (
            <div className={styles.threeDCardNotice}>
              <span className={styles.threeDIcon}>🧊</span>
              <div>
                <div className={styles.threeDTitle}>Digitalización 3D Activa</div>
                <div className={styles.threeDSub}>
                  Este plato cuenta con modelo tridimensional. La experiencia interactiva 360° y Realidad Aumentada (&quot;Ver en mi mesa&quot;) estará habilitada en la Etapa 4.
                </div>
              </div>
            </div>
          ) : (
            <div style={{ marginTop: "14px" }} />
          )}

          {/* Demo Notice */}
          <div className={styles.demoPriceNoticeBox}>
            <strong>Nota Demostrativa:</strong> Los precios, fotos y especificaciones corresponden a datos de prueba del prototipo comercial Gastro3D y no a la carta oficial de Coffelia.
          </div>
        </div>

        <div className={styles.modalFooter}>
          <button
            type="button"
            className={styles.backBtn}
            onClick={onClose}
          >
            Volver a la carta
          </button>
        </div>
      </div>
    </div>
  );
}
