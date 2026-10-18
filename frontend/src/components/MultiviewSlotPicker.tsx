"use client";

import React, { useRef } from "react";
import { ViewAngle } from "@/types/dish";

export interface SlotFileMap {
  front: File | null;
  left: File | null;
  right: File | null;
  back: File | null;
}

export interface SlotPreviewMap {
  front: string | null;
  left: string | null;
  right: string | null;
  back: string | null;
}

interface MultiviewSlotPickerProps {
  files: SlotFileMap;
  previews: SlotPreviewMap;
  onSlotChange: (angle: ViewAngle, file: File | null) => void;
  disabled?: boolean;
}

interface SlotConfig {
  angle: ViewAngle;
  title: string;
  subtitle: string;
  icon: string;
  required: boolean;
}

const SLOTS: SlotConfig[] = [
  {
    angle: "front",
    title: "Vista Frontal",
    subtitle: "Ángulo 0° · Principal",
    icon: "🍽️",
    required: true,
  },
  {
    angle: "left",
    title: "Vista Izquierda",
    subtitle: "Ángulo 90° · Lateral",
    icon: "⬅️",
    required: false,
  },
  {
    angle: "right",
    title: "Vista Derecha",
    subtitle: "Ángulo 270° · Lateral",
    icon: "➡️",
    required: false,
  },
  {
    angle: "back",
    title: "Vista Trasera",
    subtitle: "Ángulo 180° · Posterior",
    icon: "🔄",
    required: false,
  },
];

export default function MultiviewSlotPicker({
  files,
  previews,
  onSlotChange,
  disabled = false,
}: MultiviewSlotPickerProps) {
  const inputRefs = {
    front: useRef<HTMLInputElement | null>(null),
    left: useRef<HTMLInputElement | null>(null),
    right: useRef<HTMLInputElement | null>(null),
    back: useRef<HTMLInputElement | null>(null),
  };

  const handleFileSelected = (angle: ViewAngle, fileList: FileList | null) => {
    if (!fileList || fileList.length === 0) return;
    const file = fileList[0];
    if (!file.type.startsWith("image/")) {
      alert("Por favor selecciona un archivo de imagen (JPEG, PNG o WebP).");
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      alert("La fotografía no debe exceder los 10 MB.");
      return;
    }
    onSlotChange(angle, file);
  };

  const handleDrop = (angle: ViewAngle, e: React.DragEvent) => {
    e.preventDefault();
    if (disabled) return;
    handleFileSelected(angle, e.dataTransfer.files);
  };

  const totalLoaded = Object.values(files).filter(Boolean).length;
  const hasFront = Boolean(files.front);
  const isValid = hasFront && totalLoaded >= 2;

  return (
    <div className="multiview-slot-picker">
      <div className="slots-header">
        <div className="slots-counter-group">
          <span className="slots-count-badge">
            {totalLoaded} de 4 fotografías
          </span>
          <span className="slots-hint">
            Mínimo 2 fotos (Frontal obligatoria + al menos 1 complementaria).
          </span>
        </div>

        <div className="slots-validation-status">
          {!hasFront ? (
            <span className="validation-pill error">⚠️ Falta la foto Frontal</span>
          ) : totalLoaded < 2 ? (
            <span className="validation-pill warning">⚠️ Agrega al menos 1 ángulo más</span>
          ) : (
            <span className="validation-pill success">
              ✓ Listo para reconstrucción 3D ({totalLoaded} ángulos)
            </span>
          )}
        </div>
      </div>

      <div className="slots-grid">
        {SLOTS.map((slot) => {
          const file = files[slot.angle];
          const preview = previews[slot.angle];
          const isFront = slot.angle === "front";

          return (
            <div
              key={slot.angle}
              className={`slot-card ${file ? "has-file" : "empty"} ${
                isFront ? "slot-primary" : ""
              } ${disabled ? "disabled" : ""}`}
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => handleDrop(slot.angle, e)}
            >
              <input
                type="file"
                ref={inputRefs[slot.angle]}
                onChange={(e) => handleFileSelected(slot.angle, e.target.files)}
                accept="image/jpeg,image/png,image/webp"
                style={{ display: "none" }}
                disabled={disabled}
              />

              {preview && file ? (
                <div className="slot-preview-wrapper">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={preview}
                    alt={slot.title}
                    className="slot-preview-img"
                  />
                  <div className="slot-badge-angle">
                    <span>{slot.icon}</span>
                    <span>{slot.title}</span>
                  </div>

                  <div className="slot-overlay-actions">
                    <button
                      type="button"
                      className="slot-action-btn replace"
                      onClick={() => inputRefs[slot.angle].current?.click()}
                      disabled={disabled}
                      title="Reemplazar esta fotografía"
                    >
                      Reemplazar
                    </button>
                    <button
                      type="button"
                      className="slot-action-btn remove"
                      onClick={() => onSlotChange(slot.angle, null)}
                      disabled={disabled}
                      title="Quitar foto"
                    >
                      ✕
                    </button>
                  </div>

                  <div className="slot-file-meta">
                    <span className="slot-meta-name" title={file.name}>
                      {file.name}
                    </span>
                    <span className="slot-meta-size">
                      {(file.size / 1024).toFixed(0)} KB
                    </span>
                  </div>
                </div>
              ) : (
                <div
                  className="slot-empty-dropzone"
                  onClick={() => !disabled && inputRefs[slot.angle].current?.click()}
                >
                  <div className="slot-icon-box">
                    <span className="slot-icon">{slot.icon}</span>
                    {slot.required && (
                      <span className="slot-req-badge">Obligatoria</span>
                    )}
                  </div>
                  <strong className="slot-title">{slot.title}</strong>
                  <span className="slot-subtitle">{slot.subtitle}</span>
                  <span className="slot-cta">Haz clic o arrastra foto</span>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
