"use client";

import React, { useEffect, useRef, useState, useCallback } from "react";
import ModelViewer from "@/components/ModelViewer";

interface TripoModelInfo {
  id: string;
  name: string;
  description: string;
  credits: number;
  recommended: boolean;
}

interface DishModelData {
  id: string;
  title: string | null;
  prompt: string | null;
  original_filename: string | null;
  image_path: string;
  image_url: string;
  mime_type: string | null;
  file_size_bytes: number | null;
  tripo_task_id: string | null;
  model_version: string;
  status: "pending" | "uploading" | "queued" | "running" | "success" | "failed" | "cancelled";
  progress: number;
  glb_path: string | null;
  glb_url: string | null;
  thumbnail_url: string | null;
  error_message: string | null;
  created_at: string;
}

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
  const [activeTab, setActiveTab] = useState<"generator" | "gallery" | "infrastructure">("generator");

  // Form & Upload state
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [dishTitle, setDishTitle] = useState("");
  const [dishPrompt, setDishPrompt] = useState("");
  const [selectedModel, setSelectedModel] = useState<string>("v3.1-20260211");
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  // Active Dish state
  const [activeDish, setActiveDish] = useState<DishModelData | null>(null);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [generating, setGenerating] = useState(false);

  // Gallery state
  const [dishes, setDishes] = useState<DishModelData[]>([]);
  const [loadingDishes, setLoadingDishes] = useState(false);

  // Tripo V3 Balance and Models
  const [balance, setBalance] = useState<number | null>(null);
  const [frozenCredits, setFrozenCredits] = useState<number>(0);
  const [availableModels, setAvailableModels] = useState<TripoModelInfo[]>([]);
  const [balanceLoading, setBalanceLoading] = useState(false);

  // Infrastructure health state
  const [health, setHealth] = useState<HealthData | null>(null);
  const [healthLoading, setHealthLoading] = useState(false);

  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

  // Check Tripo V3 Balance
  const fetchBalance = useCallback(async () => {
    setBalanceLoading(true);
    try {
      const res = await fetch(`${apiUrl}/tripo/balance`, { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          setBalance(data.balance);
          setFrozenCredits(data.frozen ?? 0);
          if (Array.isArray(data.models) && data.models.length > 0) {
            setAvailableModels(data.models);
          }
          if (data.default_model && !selectedModel) {
            setSelectedModel(data.default_model);
          }
        }
      }
    } catch (err) {
      console.error("Error fetching Tripo V3 balance:", err);
    } finally {
      setBalanceLoading(false);
    }
  }, [apiUrl, selectedModel]);

  // Check Health
  const checkHealth = useCallback(async () => {
    setHealthLoading(true);
    try {
      const res = await fetch(`${apiUrl}/health`, { cache: "no-store" });
      if (res.ok) {
        const data: HealthData = await res.json();
        setHealth(data);
      }
    } catch {
      setHealth(null);
    } finally {
      setHealthLoading(false);
    }
  }, [apiUrl]);

  // Fetch Dishes List
  const fetchDishes = useCallback(async () => {
    setLoadingDishes(true);
    try {
      const res = await fetch(`${apiUrl}/dishes`, { cache: "no-store" });
      if (res.ok) {
        const result = await res.json();
        if (result.success && Array.isArray(result.data)) {
          setDishes(result.data);
        }
      }
    } catch (err) {
      console.error("Error fetching dishes:", err);
    } finally {
      setLoadingDishes(false);
    }
  }, [apiUrl]);

  useEffect(() => {
    checkHealth();
    fetchBalance();
    fetchDishes();
  }, [checkHealth, fetchBalance, fetchDishes]);

  // Polling for active dish progress if it's currently running in Tripo AI V3
  useEffect(() => {
    if (!activeDish) return;
    if (!["uploading", "queued", "running"].includes(activeDish.status)) return;

    const interval = setInterval(async () => {
      try {
        const res = await fetch(`${apiUrl}/dishes/${activeDish.id}/status`, { cache: "no-store" });
        if (res.ok) {
          const result = await res.json();
          if (result.success && result.data) {
            setActiveDish(result.data);
            if (["success", "failed", "cancelled"].includes(result.data.status)) {
              fetchDishes();
              fetchBalance();
            }
          }
        }
      } catch (err) {
        console.error("Polling error:", err);
      }
    }, 3500);

    return () => clearInterval(interval);
  }, [activeDish, apiUrl, fetchDishes, fetchBalance]);

  // Handle file picker
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 10 * 1024 * 1024) {
        setUploadError("La imagen no debe superar los 10 MB.");
        return;
      }
      setSelectedFile(file);
      setPreviewUrl(URL.createObjectURL(file));
      setUploadError(null);
      if (!dishTitle) {
        setDishTitle(file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file && file.type.startsWith("image/")) {
      if (file.size > 10 * 1024 * 1024) {
        setUploadError("La imagen no debe superar los 10 MB.");
        return;
      }
      setSelectedFile(file);
      setPreviewUrl(URL.createObjectURL(file));
      setUploadError(null);
      if (!dishTitle) {
        setDishTitle(file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const clearSelectedFile = () => {
    setSelectedFile(null);
    setPreviewUrl(null);
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  // Upload photo to Laravel backend (0 credits consumed)
  const handleUploadPhoto = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedFile) {
      setUploadError("Por favor selecciona una fotografía de un plato.");
      return;
    }

    setUploading(true);
    setUploadError(null);

    const formData = new FormData();
    formData.append("image", selectedFile);
    if (dishTitle.trim()) formData.append("title", dishTitle.trim());
    if (dishPrompt.trim()) formData.append("prompt", dishPrompt.trim());
    formData.append("model_version", selectedModel);

    try {
      const res = await fetch(`${apiUrl}/dishes/upload`, {
        method: "POST",
        body: formData,
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.message || "Error al subir la fotografía.");
      }

      setActiveDish(result.data);
      clearSelectedFile();
      setDishPrompt("");
      fetchDishes();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Error de conexión al subir la imagen";
      setUploadError(msg);
    } finally {
      setUploading(false);
    }
  };

  // User confirmed generation with Tripo AI V3
  const confirmAndStartTripoGeneration = async () => {
    if (!activeDish) return;
    setShowConfirmModal(false);
    setGenerating(true);

    try {
      const res = await fetch(`${apiUrl}/dishes/${activeDish.id}/generate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ model_version: selectedModel }),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.message || "Error al iniciar la generación en Tripo AI V3.");
      }

      setActiveDish(result.data);
      fetchDishes();
      fetchBalance();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Error al comunicarse con Tripo AI V3";
      setActiveDish((prev) => (prev ? { ...prev, status: "failed", error_message: msg } : null));
    } finally {
      setGenerating(false);
    }
  };

  const getStatusBadge = (status: DishModelData["status"]) => {
    switch (status) {
      case "success":
        return <span className="status-badge success">● Modelo 3D Listo</span>;
      case "failed":
        return <span className="status-badge failed">● Fallido</span>;
      case "running":
        return <span className="status-badge running">● Procesando con IA V3</span>;
      case "queued":
        return <span className="status-badge queued">● En Cola V3</span>;
      case "uploading":
        return <span className="status-badge uploading">● Subiendo a Tripo V3</span>;
      case "pending":
      default:
        return <span className="status-badge pending">● Pendiente de Generar</span>;
    }
  };

  // Calculate required credits for selected model
  const currentModelObj = availableModels.find((m) => m.id === selectedModel) || {
    id: "v3.1-20260211",
    name: "Tripo v3.1 (Máxima Calidad)",
    credits: 30,
  };
  const requiredCredits = currentModelObj.credits;
  const hasSufficientBalance = balance !== null && balance >= requiredCredits;

  return (
    <main className="container">
      {/* Header */}
      <header className="header">
        <div className="brand">
          <div className="brand-icon">🍽️</div>
          <div>
            <h1 className="brand-title">Gastro3D</h1>
            <p style={{ fontSize: "0.8rem", color: "var(--text-muted)" }}>
              SaaS Menús Gastronómicos 3D &middot; Tripo AI V3 OpenAPI
            </p>
          </div>
        </div>

        {/* Balance Badge and Tabs */}
        <div style={{ display: "flex", alignItems: "center", gap: "1rem", flexWrap: "wrap" }}>
          {/* Tripo V3 Balance Badge */}
          <div
            className={`balance-badge ${
              balance === null
                ? ""
                : balance >= 30
                ? "sufficient"
                : balance > 0
                ? "low"
                : "empty"
            }`}
            onClick={fetchBalance}
            style={{ cursor: "pointer" }}
            title="Haz clic para actualizar saldo en tiempo real"
          >
            <span>💳 Saldo Tripo V3:</span>
            <span className="balance-val">
              {balanceLoading
                ? "..."
                : balance !== null
                ? `${balance.toFixed(2)} créditos`
                : "Sin verificar"}
            </span>
            {frozenCredits > 0 && (
              <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>
                ({frozenCredits} congelados)
              </span>
            )}
            <span style={{ fontSize: "0.75rem", opacity: 0.7 }}>↺</span>
          </div>

          {/* Navigation Tabs */}
          <nav className="nav-tabs">
            <button
              className={`nav-tab ${activeTab === "generator" ? "active" : ""}`}
              onClick={() => setActiveTab("generator")}
            >
              Generador 3D
            </button>
            <button
              className={`nav-tab ${activeTab === "gallery" ? "active" : ""}`}
              onClick={() => {
                setActiveTab("gallery");
                fetchDishes();
              }}
            >
              Platos ({dishes.length})
            </button>
            <button
              className={`nav-tab ${activeTab === "infrastructure" ? "active" : ""}`}
              onClick={() => {
                setActiveTab("infrastructure");
                checkHealth();
                fetchBalance();
              }}
            >
              Infraestructura V3
            </button>
          </nav>
        </div>
      </header>

      {/* TAB 1: GENERADOR 3D */}
      {activeTab === "generator" && (
        <section className="generator-layout">
          {/* Col 1: Formulario de Subida y Control */}
          <div className="glass-card">
            <h2 className="card-title-main">1. Fotografía y Modelo de IA</h2>
            <p className="card-subtitle">
              Sube la fotografía de tu plato y selecciona el motor de IA Tripo V3 según calidad y consumo.
            </p>

            <form onSubmit={handleUploadPhoto}>
              {/* Dropzone */}
              {!previewUrl ? (
                <div
                  className="dropzone"
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={handleDrop}
                  onClick={() => fileInputRef.current?.click()}
                >
                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileChange}
                    accept="image/jpeg,image/png,image/webp"
                    style={{ display: "none" }}
                  />
                  <div className="dropzone-icon">📷</div>
                  <p className="dropzone-text">Arrastra una fotografía aquí o haz clic para explorar</p>
                  <span className="dropzone-hint">Formatos: JPG, PNG, WEBP (Máx. 10MB)</span>
                </div>
              ) : (
                <div className="preview-container">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={previewUrl} alt="Vista previa de plato" className="preview-img" />
                  <div className="preview-overlay">
                    <button type="button" className="remove-btn" onClick={clearSelectedFile}>
                      Cambiar foto
                    </button>
                  </div>
                </div>
              )}

              {/* Form fields */}
              <div className="form-group">
                <label className="form-label" htmlFor="dish-title">
                  Nombre del Plato
                </label>
                <input
                  id="dish-title"
                  type="text"
                  className="form-input"
                  placeholder="Ej. Ceviche Clásico Peruano"
                  value={dishTitle}
                  onChange={(e) => setDishTitle(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label" htmlFor="dish-prompt">
                  Descripción o Notas Culinarias (Opcional)
                </label>
                <input
                  id="dish-prompt"
                  type="text"
                  className="form-input"
                  placeholder="Ej. Pescado fresco, maíz choclo y camote en plato blanco"
                  value={dishPrompt}
                  onChange={(e) => setDishPrompt(e.target.value)}
                />
              </div>

              {/* Model Version Selector */}
              <div className="model-selector-group">
                <label className="form-label">
                  Versión del Motor de IA (Tripo V3) &middot; Calidad vs Coste
                </label>
                <div style={{ display: "flex", flexDirection: "column", gap: "0.5rem" }}>
                  {availableModels.map((m) => (
                    <div
                      key={m.id}
                      className={`model-option-card ${selectedModel === m.id ? "selected" : ""}`}
                      onClick={() => setSelectedModel(m.id)}
                    >
                      <div>
                        <div className="model-name">
                          {m.name}
                          {m.recommended && <span className="model-badge-rec">Recomendado</span>}
                        </div>
                        <div className="model-desc">{m.description}</div>
                      </div>
                      <span className="model-cost-tag">{m.credits} créditos</span>
                    </div>
                  ))}
                </div>
              </div>

              {uploadError && (
                <div className="error-banner">
                  <span>{uploadError}</span>
                  <button type="button" onClick={() => setUploadError(null)} style={{ background: "none", border: "none", color: "white", cursor: "pointer" }}>✕</button>
                </div>
              )}

              <button
                type="submit"
                className="btn-primary"
                disabled={uploading || !selectedFile}
              >
                {uploading ? (
                  <>
                    <span className="viewer-spinner" style={{ width: "16px", height: "16px", borderWidth: "2px" }} />
                    <span>Guardando fotografía (Sin coste)...</span>
                  </>
                ) : (
                  <>
                    <span>📤 Guardar Plato en Gastro3D (0 créditos)</span>
                  </>
                )}
              </button>
            </form>

            {/* Current Active Dish status box */}
            {activeDish && (
              <div className="progress-widget" style={{ marginTop: "1.75rem" }}>
                <div className="progress-header">
                  <span className="progress-label">
                    <span>Plato Activo:</span>
                    <strong>{activeDish.title || "Sin título"}</strong>
                  </span>
                  {getStatusBadge(activeDish.status)}
                </div>

                {/* Progress bar */}
                <div className="progress-bar-bg">
                  <div
                    className="progress-bar-fill"
                    style={{ width: `${Math.max(activeDish.progress, 5)}%` }}
                  />
                </div>

                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginTop: "0.5rem" }}>
                  <span className="progress-desc">
                    {activeDish.status === "pending" && "Foto guardada. Revisa los créditos necesarios antes de generar."}
                    {activeDish.status === "uploading" && "Subiendo fotografía a Tripo V3 (/v3/files)..."}
                    {activeDish.status === "queued" && "En cola de renderizado en Tripo V3..."}
                    {activeDish.status === "running" && `Generando malla y texturas PBR (${activeDish.progress}%)...`}
                    {activeDish.status === "success" && "¡Generación completada! Modelo GLB almacenado localmente."}
                    {activeDish.status === "failed" && `Error: ${activeDish.error_message || "Fallo en generación"}`}
                  </span>
                  <span className="progress-pct">{activeDish.progress}%</span>
                </div>

                {/* Action Button: Trigger Tripo AI Generation */}
                {activeDish.status === "pending" && (
                  <button
                    type="button"
                    className="btn-primary btn-generate"
                    style={{ marginTop: "1rem" }}
                    onClick={() => setShowConfirmModal(true)}
                    disabled={generating}
                  >
                    <span>🚀 Iniciar Generación 3D (Tripo V3)</span>
                  </button>
                )}

                {activeDish.status === "failed" && (
                  <button
                    type="button"
                    className="btn-primary"
                    style={{ marginTop: "1rem", background: "rgba(239, 68, 68, 0.2)", border: "1px solid rgba(239, 68, 68, 0.4)" }}
                    onClick={() => setShowConfirmModal(true)}
                    disabled={generating}
                  >
                    <span>↺ Reintentar Generación</span>
                  </button>
                )}
              </div>
            )}
          </div>

          {/* Col 2: Visualizador 3D Interactivo */}
          <div className="glass-card" style={{ display: "flex", flexDirection: "column" }}>
            <h2 className="card-title-main">2. Visualizador 3D Interactivo</h2>
            <p className="card-subtitle">
              Inspección en tiempo real con rotación 360°, zoom e iluminación interactiva con Google model-viewer.
            </p>

            {activeDish?.status === "success" && activeDish.glb_url ? (
              <ModelViewer
                src={`${apiUrl}/dishes/${activeDish.id}/model`}
                dishTitle={activeDish.title || "Plato 3D"}
                poster={activeDish.thumbnail_url || activeDish.image_url}
              />
            ) : (
              <div className="viewer-placeholder">
                <div style={{ fontSize: "3rem", marginBottom: "0.5rem" }}>🧊</div>
                <h3 style={{ fontSize: "1.1rem", fontWeight: 700, marginBottom: "0.4rem" }}>
                  {activeDish
                    ? activeDish.status === "running" || activeDish.status === "queued" || activeDish.status === "uploading"
                      ? "Generando modelo 3D con Tripo V3..."
                      : "Esperando inicio de generación..."
                    : "No hay ningún modelo 3D activo"}
                </h3>
                <p style={{ color: "var(--text-muted)", fontSize: "0.85rem", maxWidth: "380px" }}>
                  {activeDish
                    ? activeDish.status === "running"
                      ? "Tripo AI V3 está sintetizando la geometría y mapas PBR. El visor cargará automáticamente al completarse."
                      : "Presiona 'Iniciar Generación 3D' para enviar la imagen a Tripo V3."
                    : "Sube una fotografía de un plato para visualizar su modelo 3D aquí con rotación orbital y zoom."}
                </p>
                {activeDish && (activeDish.status === "running" || activeDish.status === "queued") && (
                  <div className="viewer-spinner" style={{ marginTop: "1.5rem" }} />
                )}
              </div>
            )}
          </div>
        </section>
      )}

      {/* TAB 2: GALERÍA DE PLATOS */}
      {activeTab === "gallery" && (
        <section className="glass-card">
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1.5rem" }}>
            <div>
              <h2 className="card-title-main">Galería de Platos Registrados</h2>
              <p className="card-subtitle" style={{ marginBottom: 0 }}>
                Trabajos persistidos en la base de datos PostgreSQL de Gastro3D.
              </p>
            </div>
            <button
              className="viewer-btn"
              onClick={fetchDishes}
              disabled={loadingDishes}
            >
              <span>{loadingDishes ? "Actualizando..." : "↺ Actualizar"}</span>
            </button>
          </div>

          {dishes.length === 0 ? (
            <div className="viewer-placeholder" style={{ height: "260px" }}>
              <p style={{ color: "var(--text-muted)" }}>Aún no has subido fotografías de platos.</p>
            </div>
          ) : (
            <div className="gallery-grid">
              {dishes.map((dish) => (
                <div
                  key={dish.id}
                  className="gallery-card"
                  onClick={() => {
                    setActiveDish(dish);
                    setActiveTab("generator");
                  }}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={dish.thumbnail_url || dish.image_url}
                    alt={dish.title || "Plato"}
                    className="gallery-card-img"
                  />
                  <div className="gallery-card-body">
                    <div className="gallery-card-title">{dish.title || "Sin título"}</div>
                    <div className="gallery-card-meta">
                      {getStatusBadge(dish.status)}
                      <span>{new Date(dish.created_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* TAB 3: INFRAESTRUCTURA & DIAGNÓSTICO V3 */}
      {activeTab === "infrastructure" && (
        <section style={{ display: "flex", flexDirection: "column", gap: "1.5rem" }}>
          <div className="cards-grid">
            {/* Frontend Card */}
            <div className="glass-card">
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
                <strong>Next.js Frontend</strong>
                <span className="status-badge online">● En Línea</span>
              </div>
              <p style={{ fontSize: "0.85rem", color: "var(--text-secondary)" }}>
                React 19 &middot; TypeScript &middot; Google Model Viewer
              </p>
            </div>

            {/* Backend Card */}
            <div className="glass-card">
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
                <strong>Laravel Backend API</strong>
                {health?.status === "healthy" ? (
                  <span className="status-badge online">● En Línea</span>
                ) : (
                  <span className="status-badge offline">● Sin Conexión</span>
                )}
              </div>
              <p style={{ fontSize: "0.85rem", color: "var(--text-secondary)" }}>
                PHP 8.4 CLI &middot; TripoService V3 &middot; OpenAPI v3 Endpoints
              </p>
            </div>

            {/* PostgreSQL Card */}
            <div className="glass-card">
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
                <strong>PostgreSQL Database</strong>
                {health?.database?.status === "connected" ? (
                  <span className="status-badge online">● Conectada</span>
                ) : (
                  <span className="status-badge offline">● Desconectada</span>
                )}
              </div>
              <p style={{ fontSize: "0.85rem", color: "var(--text-secondary)" }}>
                Base de datos: gastro3d &middot; Tabla: dish_models
              </p>
            </div>

            {/* Tripo V3 Account Card */}
            <div className="glass-card">
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
                <strong>Cuenta Tripo AI (V3)</strong>
                <span className={`status-badge ${balance !== null && balance > 0 ? "online" : "checking"}`}>
                  ● {balance !== null ? `${balance.toFixed(2)} créditos` : "Consultando..."}
                </span>
              </div>
              <p style={{ fontSize: "0.85rem", color: "var(--text-secondary)" }}>
                Base: https://openapi.tripo3d.ai/v3 &middot; Congelados: {frozenCredits}
              </p>
            </div>
          </div>

          <div style={{ textAlign: "center" }}>
            <button className="viewer-btn" onClick={() => { checkHealth(); fetchBalance(); }} disabled={healthLoading || balanceLoading}>
              <span>{healthLoading || balanceLoading ? "Verificando..." : "↺ Recomprobar Estado y Saldo"}</span>
            </button>
          </div>
        </section>
      )}

      {/* MODAL: Explicit Authorization & Credit Protection */}
      {showConfirmModal && activeDish && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div className="modal-header">
              <div className="modal-icon">{hasSufficientBalance ? "⚡" : "⚠️"}</div>
              <div>
                <h3 className="modal-title">
                  {hasSufficientBalance ? "Confirmar Generación 3D (V3)" : "Saldo Insuficiente en Tripo AI"}
                </h3>
                <span style={{ fontSize: "0.78rem", color: "var(--text-muted)" }}>
                  Motor: {currentModelObj.name}
                </span>
              </div>
            </div>

            <p className="modal-body">
              Estás a punto de procesar el plato <strong>&quot;{activeDish.title}&quot;</strong> con la API V3 de Tripo AI.
            </p>

            {/* Credit Breakdown Table */}
            <div style={{ background: "rgba(0,0,0,0.3)", padding: "0.9rem", borderRadius: "10px", display: "flex", flexDirection: "column", gap: "0.4rem", fontSize: "0.84rem" }}>
              <div style={{ display: "flex", justifyContent: "space-between" }}>
                <span style={{ color: "var(--text-muted)" }}>Saldo disponible en tu cuenta:</span>
                <strong style={{ fontFamily: "var(--font-mono)" }}>
                  {balance !== null ? `${balance.toFixed(2)} créditos` : "Consultando..."}
                </strong>
              </div>
              <div style={{ display: "flex", justifyContent: "space-between" }}>
                <span style={{ color: "var(--text-muted)" }}>Coste del modelo ({selectedModel}):</span>
                <strong style={{ fontFamily: "var(--font-mono)", color: "var(--accent-orange)" }}>
                  {requiredCredits} créditos
                </strong>
              </div>
              <div style={{ display: "flex", justifyContent: "space-between", borderTop: "1px solid rgba(255,255,255,0.08)", paddingTop: "0.4rem" }}>
                <span style={{ color: "var(--text-muted)" }}>Saldo estimado posterior:</span>
                <strong style={{ fontFamily: "var(--font-mono)", color: hasSufficientBalance ? "#34d399" : "#f87171" }}>
                  {balance !== null ? `${(balance - requiredCredits).toFixed(2)} créditos` : "N/A"}
                </strong>
              </div>
            </div>

            {/* Warning when balance is insufficient */}
            {!hasSufficientBalance && (
              <div className="insufficient-box">
                <strong>🚫 Generación Bloqueada por Seguridad:</strong>
                <span>
                  No dispones de saldo suficiente en tu cuenta de Tripo AI (Saldo actual: <strong>{balance?.toFixed(2) ?? "0.00"} créditos</strong>; necesarios: <strong>{requiredCredits} créditos</strong>).
                </span>
                <span>
                  Para evitar errores de cobro o llamadas fallidas, el botón de envío ha sido deshabilitado.
                </span>
                <a
                  href="https://developers.tripo3d.ai"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="link-recharge"
                >
                  Abrir Consola de Tripo AI para recargar créditos ↗
                </a>
              </div>
            )}

            {/* Safe Confirmation when balance is sufficient */}
            {hasSufficientBalance && (
              <div className="modal-alert">
                <strong>Aviso de Cobro:</strong> Al confirmar, se deducirán {requiredCredits} créditos de tu cuenta de Tripo AI y comenzará el renderizado 3D de alta precisión.
              </div>
            )}

            <div className="modal-actions">
              <button
                type="button"
                className="modal-btn-cancel"
                onClick={() => setShowConfirmModal(false)}
                disabled={generating}
              >
                Cerrar
              </button>
              <button
                type="button"
                className="modal-btn-confirm"
                onClick={confirmAndStartTripoGeneration}
                disabled={generating || !hasSufficientBalance}
                style={{
                  opacity: hasSufficientBalance ? 1 : 0.4,
                  cursor: hasSufficientBalance ? "pointer" : "not-allowed",
                  filter: hasSufficientBalance ? "none" : "grayscale(100%)",
                }}
              >
                {generating
                  ? "Iniciando..."
                  : hasSufficientBalance
                  ? `Sí, Proceder (${requiredCredits} créditos)`
                  : "Saldo Insuficiente"}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
