"use client";

import React, { useState } from "react";
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

  const angleLabels: Record<string, { label: string; icon: string }> = {
    front: { label: "Frontal (0°)", icon: "🍽️" },
    left: { label: "Izquierda (90°)", icon: "⬅️" },
    right: { label: "Derecha (270°)", icon: "➡️" },
    back: { label: "Trasera (180°)", icon: "🔄" },
  };

  const currentView = views.find((v) => v.angle === selectedAngle) || views[0];
  const photoUrl = currentView?.image_url || fallbackUrl;

  if (!photoUrl) {
    return null;
  }

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
                  onClick={() => setSelectedAngle(v.angle)}
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
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={photoUrl}
          alt={`${dishTitle || "Plato"} - Vista ${currentView?.angle || "principal"}`}
          className="photo-display-img"
          onClick={() => setIsZoomed(true)}
          title="Haz clic para ver en tamaño completo"
        />

        <div className="photo-display-overlay">
          <span className="photo-angle-pill">
            {angleLabels[currentView?.angle || "front"]?.icon}{" "}
            {angleLabels[currentView?.angle || "front"]?.label || "Foto de Referencia"}
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
              src={photoUrl}
              alt="Fotografía original de referencia"
              className="lightbox-img"
            />
            <button
              type="button"
              className="lightbox-close-btn"
              onClick={() => setIsZoomed(false)}
            >
              ✕
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
