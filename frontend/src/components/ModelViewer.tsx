"use client";

import React, { useEffect, useRef, useState } from "react";

interface ModelViewerProps {
  src: string;
  alt?: string;
  poster?: string;
  dishTitle?: string;
}

export default function ModelViewer({
  src,
  alt = "Modelo 3D de Comida",
  poster,
  dishTitle,
}: ModelViewerProps) {
  const [loaded, setLoaded] = useState(false);
  const [autoRotate, setAutoRotate] = useState(true);
  const viewerRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    // Import @google/model-viewer on client side
    import("@google/model-viewer")
      .then(() => setLoaded(true))
      .catch((err) => console.error("Error loading model-viewer:", err));
  }, []);

  const handleResetCamera = () => {
    if (viewerRef.current && "cameraOrbit" in viewerRef.current) {
      (viewerRef.current as any).cameraOrbit = "0deg 75deg 105%";
      (viewerRef.current as any).jumpCameraToGoal();
    }
  };

  const toggleRotate = () => {
    setAutoRotate(!autoRotate);
  };

  if (!loaded) {
    return (
      <div className="viewer-placeholder">
        <div className="viewer-spinner" />
        <p style={{ marginTop: "1rem", color: "var(--text-muted)", fontSize: "0.9rem" }}>
          Iniciando visualizador 3D...
        </p>
      </div>
    );
  }

  return (
    <div className="viewer-wrapper">
      <div className="viewer-header">
        <div className="viewer-title-group">
          <span className="viewer-badge">GLB 3D</span>
          <span className="viewer-dish-name">{dishTitle || "Plato 3D"}</span>
        </div>
        <div className="viewer-controls">
          <button
            type="button"
            className="viewer-btn"
            onClick={toggleRotate}
            title={autoRotate ? "Pausar rotación automática" : "Activar rotación automática"}
          >
            {autoRotate ? "⏸ Rotación" : "▶ Rotar"}
          </button>
          <button
            type="button"
            className="viewer-btn"
            onClick={handleResetCamera}
            title="Reiniciar vista"
          >
            ↺ Vista
          </button>
          <a
            href={src}
            download={`${dishTitle || "plato"}.glb`}
            className="viewer-btn viewer-btn-download"
            title="Descargar archivo GLB"
          >
            ⬇ Descargar GLB
          </a>
        </div>
      </div>

      <div className="viewer-canvas-container">
        <model-viewer
          ref={viewerRef}
          src={src}
          alt={alt}
          poster={poster}
          auto-rotate={autoRotate ? "" : undefined}
          camera-controls=""
          shadow-intensity="1.5"
          exposure="1.1"
          touch-action="pan-y"
          style={{
            width: "100%",
            height: "100%",
            backgroundColor: "#06080e",
            borderRadius: "14px",
            outline: "none",
          }}
        />
        <div className="viewer-hint">
          Arrastra para rotar &middot; Rueda / pellizca para zoom &middot; Clic derecho para desplazar
        </div>
      </div>
    </div>
  );
}
