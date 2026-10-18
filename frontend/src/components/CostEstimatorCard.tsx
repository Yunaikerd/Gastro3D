"use client";

import React, { useEffect, useState, useCallback } from "react";
import { GenerationMode, TextureQuality, CostEstimateResponse } from "@/types/dish";

interface CostEstimatorCardProps {
  mode: GenerationMode;
  modelVersion: string;
  textureQuality: TextureQuality;
  onTextureQualityChange: (quality: TextureQuality) => void;
  userBalance: number | null;
  apiUrl: string;
}

export default function CostEstimatorCard({
  mode,
  modelVersion,
  textureQuality,
  onTextureQualityChange,
  userBalance,
  apiUrl,
}: CostEstimatorCardProps) {
  const [estimate, setEstimate] = useState<CostEstimateResponse | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchEstimate = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`${apiUrl}/dishes/cost-estimate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          mode,
          model_version: modelVersion,
          texture_quality: textureQuality,
        }),
      });

      if (res.ok) {
        const data: CostEstimateResponse = await res.json();
        if (data.success) {
          setEstimate(data);
        }
      }
    } catch (err) {
      console.error("Error fetching cost estimate:", err);
    } finally {
      setLoading(false);
    }
  }, [apiUrl, mode, modelVersion, textureQuality]);

  useEffect(() => {
    fetchEstimate();
  }, [fetchEstimate]);

  const requiredCredits =
    estimate?.required_credits ??
    (mode === "multiview" && textureQuality === "detailed" ? 40 : 30);
  const currentBalance = userBalance ?? estimate?.available_credits ?? 0;
  const canAfford = currentBalance >= requiredCredits;
  const remainingAfter = currentBalance - requiredCredits;

  return (
    <div className="cost-estimator-card">
      <div className="estimator-header">
        <div className="estimator-title-group">
          <span className="estimator-icon">💎</span>
          <div>
            <h3 className="estimator-title">Presupuesto y Control de Costes</h3>
            <span className="estimator-subtitle">
              Cálculo en tiempo real con la API oficial de Tripo V3
            </span>
          </div>
        </div>

        <span className={`estimator-pill ${canAfford ? "affordable" : "insufficient"}`}>
          {loading ? "Calculando..." : canAfford ? "Saldo Suficiente ✓" : "Saldo Insuficiente ⚠️"}
        </span>
      </div>

      {/* Texture Quality Selector (Only applicable for multiview) */}
      {mode === "multiview" && (
        <div className="texture-quality-section">
          <label className="section-label">Calidad de Texturizado y Acabado PBR</label>
          <div className="texture-options-grid">
            <div
              className={`texture-card ${textureQuality === "standard" ? "selected" : ""}`}
              onClick={() => onTextureQualityChange("standard")}
            >
              <div className="texture-header">
                <strong>Textura Estándar</strong>
                <span className="texture-cost">30 CR</span>
              </div>
              <p className="texture-desc">
                Mapeo difuso básico. Rápido y económico para platos sencillos o pruebas iniciales.
              </p>
            </div>

            <div
              className={`texture-card ${textureQuality === "detailed" ? "selected" : ""}`}
              onClick={() => onTextureQualityChange("detailed")}
            >
              <div className="texture-header">
                <div style={{ display: "flex", alignItems: "center", gap: "0.4rem" }}>
                  <strong>Textura Detallada PBR</strong>
                  <span className="badge-gastronomy">Recomendado Gastro</span>
                </div>
                <span className="texture-cost highlight">40 CR</span>
              </div>
              <p className="texture-desc">
                Mapas de rugosidad, profundidad y especularidad. Máximo realismo en salsas, carnes y vajilla.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Cost & Balance breakdown ledger */}
      <div className="estimator-ledger">
        <div className="ledger-row">
          <span className="ledger-label">Saldo disponible en Wallet:</span>
          <span className="ledger-value balance">{currentBalance.toFixed(2)} créditos</span>
        </div>

        <div className="ledger-row">
          <span className="ledger-label">
            Coste estimado ({mode === "multiview" ? "Multivista" : "Estándar"} · {textureQuality === "detailed" ? "PBR Detallada" : "Estándar"}):
          </span>
          <span className="ledger-value cost">-{requiredCredits} créditos</span>
        </div>

        <div className="ledger-row total">
          <span className="ledger-label">Saldo proyectado tras generación:</span>
          <span className={`ledger-value ${canAfford ? "positive" : "negative"}`}>
            {canAfford ? `${remainingAfter.toFixed(2)} créditos` : "Insuficiente"}
          </span>
        </div>
      </div>
    </div>
  );
}
