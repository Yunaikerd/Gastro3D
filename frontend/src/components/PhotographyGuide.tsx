"use client";

import React, { useState } from "react";

export default function PhotographyGuide() {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="photography-guide-container">
      <button
        type="button"
        className="guide-toggle-btn"
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
      >
        <span className="guide-icon">📸</span>
        <span className="guide-title-text">
          Guía Rápida de Fotografía Gastronómica 3D
        </span>
        <span className="guide-arrow">{isOpen ? "▲ Ocultar" : "▼ Ver consejos"}</span>
      </button>

      {isOpen && (
        <div className="guide-body animate-fade-in">
          <p className="guide-intro">
            Para obtener el modelo 3D más realista de tu plato con Tripo V3, sigue estas 4 recomendaciones de captura:
          </p>

          <div className="guide-grid">
            <div className="guide-card">
              <div className="guide-card-icon">📐</div>
              <h4 className="guide-card-title">1. Ángulo Comensal (45°)</h4>
              <p className="guide-card-text">
                Fotografía el plato a 45 grados de inclinación. Permite captar tanto la superficie y textura del alimento como el volumen y la altura del plato.
              </p>
            </div>

            <div className="guide-card">
              <div className="guide-card-icon">💡</div>
              <h4 className="guide-card-title">2. Iluminación Uniforme</h4>
              <p className="guide-card-text">
                Utiliza luz suave y difusa. Evita el flash directo del móvil para no generar brillos agresivos en salsas, aceites o vajilla cerámica.
              </p>
            </div>

            <div className="guide-card">
              <div className="guide-card-icon">🔄</div>
              <h4 className="guide-card-title">3. Giro en 4 Cuadrantes</h4>
              <p className="guide-card-text">
                Mantén la misma altura y distancia mientras caminas o giras el plato en 4 ángulos: Frontal (0°), Izquierda (90°), Trasera (180°) y Derecha (270°).
              </p>
            </div>

            <div className="guide-card">
              <div className="guide-card-icon">🍽️</div>
              <h4 className="guide-card-title">4. Fondo y Encuadre Limpio</h4>
              <p className="guide-card-text">
                Ubica el plato sobre una mesa o mantel de tono neutro sin cubiertos cruzados ni objetos distractores. Deja un pequeño margen alrededor.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
