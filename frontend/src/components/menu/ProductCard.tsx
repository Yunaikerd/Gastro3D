"use client";

import React from "react";
import { MenuProductData } from "@/types/menu";
import styles from "@/app/menu/[slug]/menu.module.css";

interface ProductCardProps {
  product: MenuProductData;
  currency: string;
  onSelect: (product: MenuProductData) => void;
}

export default function ProductCard({
  product,
  currency,
  onSelect,
}: ProductCardProps) {
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
    <article
      className={styles.card}
      onClick={() => onSelect(product)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onSelect(product);
        }
      }}
      aria-label={`Ver detalle de ${product.name}`}
    >
      <div className={styles.imageContainer}>
        <img
          src={product.photo_url || fallbackImage}
          alt={product.name}
          className={styles.productImage}
          onError={handleImageError}
          loading="lazy"
        />

        <div className={styles.cardTopBadges}>
          {product.is_featured ? (
            <span className={styles.featuredBadge}>Destacado</span>
          ) : (
            <span />
          )}

          {product.has_3d && (
            <span className={styles.threeDBadge} title="Modelo 3D disponible">
              <span>🧊</span>
              <span>3D</span>
            </span>
          )}
        </div>
      </div>

      <div className={styles.cardBody}>
        <h3 className={styles.cardTitle}>{product.name}</h3>

        {product.description && (
          <p className={styles.cardDesc}>{product.description}</p>
        )}

        <div className={styles.cardFooter}>
          <div>
            <span className={styles.priceTag}>
              {formatPrice(product.price, currency)}
            </span>
            <span className={styles.priceDemoNotice}>*Precio demostrativo</span>
          </div>

          <button
            type="button"
            className={styles.viewDetailBtn}
            onClick={(e) => {
              e.stopPropagation();
              onSelect(product);
            }}
          >
            Ver Detalle
          </button>
        </div>
      </div>
    </article>
  );
}
