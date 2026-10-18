"use client";

import React, { useState, useEffect } from "react";
import { DishViewData } from "@/types/dish";

interface PhotoComparisonProps {
  views: DishViewData[];
  fallbackUrl?: string;
  dishTitle?: string;
}

export default function PhotoComparison({
  views,
  fallbackUrl,
  dishTitle,
}: PhotoComparisonProps) {
  const [selectedAngle, setSelectedAngle] = useState<string>("front");
  const [isZoomed, setIsZoomed] = useState(false);
  const [imageLoading, setImageLoading] = useState(false);
  const [imageError, setImageError] = useState(false);

  const angleLabels: Record<string, { label: string; icon: string }> = {
    front: { label: "Frontal (0°)", icon: "🍽️" },
    left: { label: "Izquierda (90°)", icon: "⬅️" },
    right: { label: "Derecha (270°)", icon: "➡️" },
    back: { label: "Trasera (180°)", icon: "🔄" },
  };

  // Synchronize selected angle whenever views prop changes
  useEffect(() => {
    if (views && views.length > 0) {
      const exists = views.some((v) => v.angle === selectedAngle);
      if (!exists) {
        setSelectedAngle(views[0].angle);
      }
    }
    setImageError(false);
  }, [views, selectedAngle]);

  const currentView = views.find((v) => v.angle === selectedAngle) || views[0];
  const photoUrl = currentView?.image_url || fallbackUrl;

  const handleAngleChange = (angle: string) => {
    setSelectedAngle(angle);
    setImageLoading(true);
    setImageError(false);
  };

  if (!photoUrl) {
    return null;
  }

  const activeInfo = angleLabels[currentView?.angle || "front"] || {
    label: currentView?.angle || "Foto de Referencia",
    icon: "📷",
  };

  return (
    <div className="photo-comparison-container">
      <div className="photo-comparison-header">
        <div className="comparison-title-group">
          <span className="comparison-icon">📷</span>
          <div>
            <h4 className="comparison-title">Fotografías Originales</h4>
            <span className="comparison-subtitle">
              Compara la captura real con la geometría 3D generada
            </span>
          </div>
        </div>

        {views.length > 1 && (
          <div className="comparison-tabs">
            {views.map((v) => {
              const info = angleLabels[v.angle] || { label: v.angle, icon: "📷" };
              const isSelected = v.angle === (currentView?.angle || "front");
              return (
                <button
                  key={v.angle}
                  type="button"
                  className={`comparison-tab-btn ${isSelected ? "active" : ""}`}
                  onClick={() => handleAngleChange(v.angle)}
                  title={`Ver captura ${info.label}`}
                >
                  <span>{info.icon}</span>
                  <span>{info.label}</span>
                </button>
              );
            })}
          </div>
        )}
      </div>

      <div className="photo-display-frame">
        {imageLoading && (
          <div
            style={{
              position: "absolute",
              inset: 0,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              background: "rgba(6, 8, 14, 0.6)",
              zIndex: 2,
            }}
          >
            <div className="viewer-spinner" style={{ width: "24px", height: "24px" }} />
          </div>
        )}

        {imageError ? (
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              alignItems: "center",
              justifyContent: "center",
              height: "100%",
              color: "var(--text-muted)",
              padding: "1rem",
              textAlign: "center",
            }}
          >
            <span style={{ fontSize: "2rem", marginBottom: "0.5rem" }}>⚠️</span>
            <p style={{ fontSize: "0.85rem", marginBottom: "0.5rem" }}>
              No se pudo cargar la vista {activeInfo.label}
            </p>
            {fallbackUrl && fallbackUrl !== photoUrl && (
              <button
                type="button"
                className="btn-secondary"
                style={{ padding: "0.3rem 0.6rem", fontSize: "0.75rem" }}
                onClick={() => handleAngleChange("front")}
              >
                Volver a Frontal
              </button>
            )}
          </div>
        ) : (
          /* eslint-disable-next-line @next/next/no-img-element */
          <img
            key={`${currentView?.angle || "front"}_${photoUrl}`}
            src={photoUrl}
            alt={`${dishTitle || "Plato"} - Vista ${activeInfo.label}`}
            className="photo-display-img"
            onLoad={() => setImageLoading(false)}
            onError={() => {
              setImageLoading(false);
              setImageError(true);
            }}
            onClick={() => setIsZoomed(true)}
            title="Haz clic para ver en tamaño completo"
          />
        )}

        <div className="photo-display-overlay">
          <span className="photo-angle-pill">
            {activeInfo.icon} {activeInfo.label}
          </span>
          <button
            type="button"
            className="photo-zoom-btn"
            onClick={() => setIsZoomed(true)}
          >
            🔍 Zoom
          </button>
        </div>
      </div>

      {isZoomed && (
        <div className="photo-modal-lightbox" onClick={() => setIsZoomed(false)}>
          <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              key={`lightbox_${currentView?.angle || "front"}_${photoUrl}`}
              src={photoUrl}
              alt={`${dishTitle || "Plato"} - ${activeInfo.label}`}
              className="lightbox-img"
            />
            <button
              type="button"
              className="lightbox-close-btn"
              onClick={() => setIsZoomed(false)}
              aria-label="Cerrar vista completa"
            >
              ✕
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

