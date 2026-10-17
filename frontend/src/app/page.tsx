"use client";

import { useEffect, useState } from "react";

interface HealthData {
  status: string;
  service: string;
  timestamp: string;
  database: {
    status: string;
    driver: string;
    error: string | null;
  };
}

export default function Home() {
  const [health, setHealth] = useState<HealthData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const checkHealth = async () => {
    setLoading(true);
    setError(null);
    try {
      const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";
      const res = await fetch(`${apiUrl}/health`, {
        cache: "no-store",
      });

      if (!res.ok) {
        throw new Error(`API returned HTTP ${res.status}`);
      }

      const data: HealthData = await res.json();
      setHealth(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Error de conexión";
      setError(message);
      setHealth(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    checkHealth();
  }, []);

  const isDbConnected = health?.database?.status === "connected";
  const isApiHealthy = health?.status === "healthy";

  return (
    <main className="container">
      {/* Header */}
      <header className="header">
        <div className="brand">
          <div className="brand-icon">🍽️</div>
          <div>
            <h1 className="brand-title">Gastro3D</h1>
            <p style={{ fontSize: "0.8rem", color: "var(--text-muted)" }}>
              SaaS Menús 3D &middot; Fase 1
            </p>
          </div>
        </div>
        <span className="brand-badge">Ambiente Aislado</span>
      </header>

      {/* Hero */}
      <section className="hero">
        <div className="hero-tag">
          <span className="hero-tag-dot" />
          <span>Fase 1: Generación de Comida en 3D</span>
        </div>
        <h2 className="hero-title">
          Arquitectura <span className="gradient-text">Full-Stack Gastro3D</span>
        </h2>
        <p className="hero-desc">
          Infraestructura modular e independiente con Next.js, Laravel API, PostgreSQL y generación 3D con Tripo AI.
        </p>
      </section>

      {/* Health Status Cards */}
      <section className="cards-grid">
        {/* Frontend Card */}
        <div className="glass-card">
          <div className="card-header">
            <div className="card-title-group">
              <div className="card-icon">⚡</div>
              <div>
                <h3 className="card-title">Frontend Next.js</h3>
                <span style={{ fontSize: "0.75rem", color: "var(--text-muted)" }}>
                  React 19 &middot; TypeScript
                </span>
              </div>
            </div>
            <span className="status-badge online">● En Línea</span>
          </div>
          <div className="card-details">
            <div className="detail-row">
              <span className="detail-label">Puerto Local</span>
              <span className="detail-value">localhost:3000</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Servicio</span>
              <span className="detail-value">Next.js App Router</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Visualizador 3D</span>
              <span className="detail-value">Three.js / GLTF (Listo)</span>
            </div>
          </div>
        </div>

        {/* Backend Card */}
        <div className="glass-card">
          <div className="card-header">
            <div className="card-title-group">
              <div className="card-icon">🚀</div>
              <div>
                <h3 className="card-title">Backend Laravel</h3>
                <span style={{ fontSize: "0.75rem", color: "var(--text-muted)" }}>
                  PHP 8.3 &middot; REST API
                </span>
              </div>
            </div>
            {loading ? (
              <span className="status-badge checking">Verificando...</span>
            ) : isApiHealthy ? (
              <span className="status-badge online">● En Línea</span>
            ) : (
              <span className="status-badge offline">● Sin Conexión</span>
            )}
          </div>
          <div className="card-details">
            <div className="detail-row">
              <span className="detail-label">Endpoint Health</span>
              <span className="detail-value">localhost:8000/api/health</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Estado API</span>
              <span className="detail-value">
                {loading ? "Comprobando..." : isApiHealthy ? "Activo (200 OK)" : error || "Error"}
              </span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Integración Tripo AI</span>
              <span className="detail-value">Fase 1 (En curso)</span>
            </div>
          </div>
        </div>

        {/* PostgreSQL Card */}
        <div className="glass-card">
          <div className="card-header">
            <div className="card-title-group">
              <div className="card-icon">🐘</div>
              <div>
                <h3 className="card-title">Base de Datos</h3>
                <span style={{ fontSize: "0.75rem", color: "var(--text-muted)" }}>
                  PostgreSQL 16
                </span>
              </div>
            </div>
            {loading ? (
              <span className="status-badge checking">Verificando...</span>
            ) : isDbConnected ? (
              <span className="status-badge online">● Conectada</span>
            ) : (
              <span className="status-badge offline">● Desconectada</span>
            )}
          </div>
          <div className="card-details">
            <div className="detail-row">
              <span className="detail-label">Base de Datos</span>
              <span className="detail-value">gastro3d</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Puerto Host / Docker</span>
              <span className="detail-value">5433 : 5432</span>
            </div>
            <div className="detail-row">
              <span className="detail-label">Contenedor Dedicado</span>
              <span className="detail-value">gastro3d_postgres</span>
            </div>
          </div>
        </div>
      </section>

      {/* Action / Refresh Bar */}
      <div style={{ display: "flex", justifyContent: "center", marginTop: "0.5rem" }}>
        <button id="refresh-health-btn" className="refresh-button" onClick={checkHealth} disabled={loading}>
          <span>{loading ? "Verificando..." : "↺ Recomprobar Estado de Salud"}</span>
        </button>
      </div>

      {/* Feature Preview Box */}
      <section className="preview-box">
        <span className="preview-badge">Siguiente Módulo &middot; Fase 1</span>
        <h3 className="preview-title">Pipeline de Generación 3D con Tripo AI</h3>
        <p className="preview-text">
          Subida de fotos de platos en alta resolución, orquestación de jobs asíncronos en Laravel, persistencia en PostgreSQL y renderizado interactivo en tiempo real con descarga y almacenamiento de archivos GLB.
        </p>
      </section>
    </main>
  );
}
