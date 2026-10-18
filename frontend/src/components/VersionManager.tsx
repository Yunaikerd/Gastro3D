"use client";

import React from "react";
import { DishGenerationData, GenerationStatus } from "@/types/dish";

interface VersionManagerProps {
  versions: DishGenerationData[];
  selectedVersionId: string | null;
  onSelectVersion: (version: DishGenerationData) => void;
  onActivateVersion: (versionId: string) => Promise<void>;
  onAddNewVersion: () => void;
  activating: boolean;
}

export default function VersionManager({
  versions,
  selectedVersionId,
  onSelectVersion,
  onActivateVersion,
  onAddNewVersion,
  activating,
}: VersionManagerProps) {
  if (versions.length === 0) {
    return null;
  }

  const getStatusBadge = (status: GenerationStatus) => {
    switch (status) {
      case "success":
        return <span className="version-status-pill success">● 3D Listo</span>;
      case "running":
        return <span className="version-status-pill running">● Procesando IA</span>;
      case "queued":
        return <span className="version-status-pill queued">● En Cola</span>;
      case "failed":
        return <span className="version-status-pill failed">● Fallido</span>;
      default:
        return <span className="version-status-pill pending">● Pendiente</span>;
    }
  };

  const selectedGen = versions.find((v) => v.id === selectedVersionId) || versions[0];

  return (
    <div className="version-manager-container">
      <div className="version-manager-header">
        <div className="version-title-group">
          <span className="version-icon">🧬</span>
          <div>
            <h4 className="version-title">Versiones 3D del Plato ({versions.length})</h4>
            <span className="version-subtitle">
              Genera nuevas versiones sin sobrescribir las anteriores
            </span>
          </div>
        </div>

        <button
          type="button"
          className="btn-add-version"
          onClick={onAddNewVersion}
          title="Subir nuevas fotos para crear otra versión 3D de este plato"
        >
          <span>＋ Nueva Versión</span>
        </button>
      </div>

      <div className="version-tabs-scroll">
        {versions.map((ver) => {
          const isSelected = ver.id === selectedGen.id;
          return (
            <div
              key={ver.id}
              className={`version-tab-card ${isSelected ? "selected" : ""} ${
                ver.is_active ? "is-active" : ""
              }`}
              onClick={() => onSelectVersion(ver)}
            >
              <div className="version-tab-top">
                <span className="version-number-badge">
                  v{ver.version_number}
                </span>
                {ver.is_active && (
                  <span className="version-active-tag">⭐ Activa</span>
                )}
                {getStatusBadge(ver.status)}
              </div>

              <div className="version-tab-details">
                <div className="version-mode-label">
                  {ver.mode === "multiview" ? "Multivista" : "Estándar"}
                  {ver.texture_quality === "detailed" ? " · PBR" : ""}
                </div>
                <div className="version-views-count">
                  {ver.views?.length || 1} {ver.views?.length === 1 ? "foto" : "ángulos"}
                  {ver.credits_consumed ? ` · ${ver.credits_consumed} CR` : ""}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {selectedGen && !selectedGen.is_active && selectedGen.status === "success" && (
        <div className="version-activate-bar">
          <span className="activate-bar-text">
            Esta versión <strong>#{selectedGen.version_number}</strong> no está activa en tu menú.
          </span>
          <button
            type="button"
            className="btn-activate-version"
            onClick={() => onActivateVersion(selectedGen.id)}
            disabled={activating}
          >
            {activating ? "Activando..." : "⭐ Activar como Versión Principal"}
          </button>
        </div>
      )}
    </div>
  );
}
