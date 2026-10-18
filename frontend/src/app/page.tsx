"use client";

import React, { useEffect, useRef, useState, useCallback } from "react";
import ModelViewer from "@/components/ModelViewer";
import PhotographyGuide from "@/components/PhotographyGuide";
import MultiviewSlotPicker, { SlotFileMap, SlotPreviewMap } from "@/components/MultiviewSlotPicker";
import CostEstimatorCard from "@/components/CostEstimatorCard";
import VersionManager from "@/components/VersionManager";
import PhotoComparison from "@/components/PhotoComparison";
import {
  DishModelData,
  DishGenerationData,
  GenerationMode,
  TextureQuality,
  TripoModelInfo,
  HealthData,
  ViewAngle,
} from "@/types/dish";

export default function Home() {
  const [activeTab, setActiveTab] = useState<"generator" | "gallery" | "infrastructure">("generator");

  // Mode & Quality Selector
  const [generationMode, setGenerationMode] = useState<GenerationMode>("multiview");
  const [textureQuality, setTextureQuality] = useState<TextureQuality>("detailed");
  const [selectedModel, setSelectedModel] = useState<string>("v3.1-20260211");

  // New Version mode on existing dish
  const [isAddingNewVersion, setIsAddingNewVersion] = useState(false);

  // Form Fields
  const [dishTitle, setDishTitle] = useState("");
  const [dishPrompt, setDishPrompt] = useState("");
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  // Standard Mode single file state
  const [standardFile, setStandardFile] = useState<File | null>(null);
  const [standardPreview, setStandardPreview] = useState<string | null>(null);
  const standardInputRef = useRef<HTMLInputElement | null>(null);

  // Multiview Mode files state
  const [multiviewFiles, setMultiviewFiles] = useState<SlotFileMap>({
    front: null,
    left: null,
    right: null,
    back: null,
  });
  const [multiviewPreviews, setMultiviewPreviews] = useState<SlotPreviewMap>({
    front: null,
    left: null,
    right: null,
    back: null,
  });

  // Active Dish & Versions State
  const [activeDish, setActiveDish] = useState<DishModelData | null>(null);
  const [dishVersions, setDishVersions] = useState<DishGenerationData[]>([]);
  const [selectedVersion, setSelectedVersion] = useState<DishGenerationData | null>(null);
  const [activatingVersion, setActivatingVersion] = useState(false);

  // Generation Modal & Action State
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

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

  // Check Tripo V3 Balance
  const fetchBalance = useCallback(async () => {
    setBalanceLoading(true);
    try {
      const res = await fetch(`${apiUrl}/tripo/balance`, { cache: "no-store" });
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          const avail = data.available_credits ?? data.balance ?? 0;
          const froz = data.frozen_credits ?? data.frozen ?? 0;
          setBalance(Number(avail));
          setFrozenCredits(Number(froz));
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

  // Fetch Versions for a specific dish
  const fetchVersionsForDish = useCallback(
    async (dishId: string) => {
      try {
        const res = await fetch(`${apiUrl}/dishes/${dishId}/versions`, { cache: "no-store" });
        if (res.ok) {
          const json = await res.json();
          if (json.success && Array.isArray(json.data)) {
            setDishVersions(json.data);
            // Select active or latest version
            const activeVer = json.data.find((v: DishGenerationData) => v.is_active) || json.data[0];
            setSelectedVersion(activeVer || null);
          }
        }
      } catch (err) {
        console.error("Error fetching dish versions:", err);
      }
    },
    [apiUrl]
  );

  useEffect(() => {
    checkHealth();
    fetchBalance();
    fetchDishes();
  }, [checkHealth, fetchBalance, fetchDishes]);

  // Sequential Non-Overlapping Polling Loop
  useEffect(() => {
    if (!activeDish) return;

    // Determine target version or dish to poll
    const targetStatus = selectedVersion ? selectedVersion.status : activeDish.status;
    if (!["uploading", "queued", "running"].includes(targetStatus)) return;

    let isCancelled = false;
    let timerId: NodeJS.Timeout | null = null;
    const dishId = activeDish.id;
    const versionId = selectedVersion?.id;

    const pollStatus = async () => {
      if (isCancelled) return;

      try {
        const url = versionId
          ? `${apiUrl}/dishes/${dishId}/versions/${versionId}/status`
          : `${apiUrl}/dishes/${dishId}/status`;

        const res = await fetch(url, { cache: "no-store" });
        if (res.ok && !isCancelled) {
          const result = await res.json();
          if (result.success && result.data) {
            if (versionId) {
              const updatedGen = result.data as DishGenerationData;
              setSelectedVersion(updatedGen);
              setDishVersions((prev) =>
                prev.map((v) => (v.id === updatedGen.id ? updatedGen : v))
              );

              // Update activeDish parent fields if active
              if (updatedGen.is_active) {
                setActiveDish((prev) =>
                  prev
                    ? {
                        ...prev,
                        status: updatedGen.status,
                        progress: updatedGen.progress,
                        glb_path: updatedGen.glb_path,
                        glb_url: updatedGen.glb_url,
                        thumbnail_url: updatedGen.thumbnail_url,
                        tripo_task_id: updatedGen.tripo_task_id,
                      }
                    : null
                );
              }
            } else {
              const updatedDish = result.data as DishModelData;
              setActiveDish(updatedDish);
              setDishes((prev) => prev.map((d) => (d.id === updatedDish.id ? updatedDish : d)));
            }

            // Stop polling on terminal statuses
            if (["success", "failed", "cancelled"].includes(result.data.status)) {
              fetchDishes();
              fetchBalance();
              if (versionId) fetchVersionsForDish(dishId);
              return;
            }
          }
        }
      } catch (err) {
        console.error("Safe polling warning:", err);
      }

      // Schedule next poll strictly 4 seconds AFTER the previous one finished
      if (!isCancelled) {
        timerId = setTimeout(pollStatus, 4000);
      }
    };

    // First poll after 3.5 seconds
    timerId = setTimeout(pollStatus, 3500);

    return () => {
      isCancelled = true;
      if (timerId) clearTimeout(timerId);
    };
  }, [
    activeDish?.id,
    selectedVersion?.id,
    selectedVersion?.status,
    activeDish?.status,
    apiUrl,
    fetchDishes,
    fetchBalance,
    fetchVersionsForDish,
  ]);

  // Handle Standard File selection
  const handleStandardFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 10 * 1024 * 1024) {
        setUploadError("La imagen no debe superar los 10 MB.");
        return;
      }
      setStandardFile(file);
      setStandardPreview(URL.createObjectURL(file));
      setUploadError(null);
      if (!dishTitle) {
        setDishTitle(file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const handleStandardDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file && file.type.startsWith("image/")) {
      if (file.size > 10 * 1024 * 1024) {
        setUploadError("La imagen no debe superar los 10 MB.");
        return;
      }
      setStandardFile(file);
      setStandardPreview(URL.createObjectURL(file));
      setUploadError(null);
      if (!dishTitle) {
        setDishTitle(file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const clearStandardFile = () => {
    setStandardFile(null);
    setStandardPreview(null);
    if (standardInputRef.current) standardInputRef.current.value = "";
  };

  // Handle Multiview Slots
  const handleMultiviewSlotChange = (angle: ViewAngle, file: File | null) => {
    setMultiviewFiles((prev) => ({ ...prev, [angle]: file }));
    setMultiviewPreviews((prev) => ({
      ...prev,
      [angle]: file ? URL.createObjectURL(file) : null,
    }));
    setUploadError(null);
    if (file && angle === "front" && !dishTitle) {
      setDishTitle(file.name.replace(/\.[^/.]+$/, ""));
    }
  };

  // Submit Upload Form (0 credits consumed)
  const handleUploadDish = async (e: React.FormEvent) => {
    e.preventDefault();
    setUploadError(null);

    // Validation
    if (generationMode === "standard") {
      if (!standardFile) {
        setUploadError("Por favor selecciona una fotografía del plato.");
        return;
      }
    } else {
      if (!multiviewFiles.front) {
        setUploadError("La vista frontal es obligatoria para la generación multivista.");
        return;
      }
      const totalLoaded = Object.values(multiviewFiles).filter(Boolean).length;
      if (totalLoaded < 2) {
        setUploadError("Debes cargar al menos 2 fotografías (Frontal + al menos 1 lateral o trasera).");
        return;
      }
    }

    setUploading(true);

    try {
      let endpoint = `${apiUrl}/dishes/upload`;
      const formData = new FormData();

      if (isAddingNewVersion && activeDish) {
        // Adding a new version to an existing dish
        endpoint = `${apiUrl}/dishes/${activeDish.id}/versions`;
        formData.append("mode", generationMode);
        formData.append("model_version", selectedModel);
        formData.append("texture_quality", textureQuality);

        if (generationMode === "standard" && standardFile) {
          formData.append("images[front]", standardFile);
        } else {
          (["front", "left", "right", "back"] as ViewAngle[]).forEach((angle) => {
            const f = multiviewFiles[angle];
            if (f) formData.append(`images[${angle}]`, f);
          });
        }
      } else {
        // Creating a new Dish
        if (generationMode === "multiview") {
          endpoint = `${apiUrl}/dishes/multiview`;
          if (dishTitle.trim()) formData.append("name", dishTitle.trim());
          if (dishPrompt.trim()) formData.append("prompt", dishPrompt.trim());
          formData.append("model_version", selectedModel);
          formData.append("texture_quality", textureQuality);

          (["front", "left", "right", "back"] as ViewAngle[]).forEach((angle) => {
            const f = multiviewFiles[angle];
            if (f) formData.append(`images[${angle}]`, f);
          });
        } else {
          // Standard single photo
          endpoint = `${apiUrl}/dishes/upload`;
          if (standardFile) formData.append("image", standardFile);
          if (dishTitle.trim()) formData.append("title", dishTitle.trim());
          if (dishPrompt.trim()) formData.append("prompt", dishPrompt.trim());
          formData.append("model_version", selectedModel);
        }
      }

      const res = await fetch(endpoint, {
        method: "POST",
        body: formData,
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.message || "Error al registrar las fotografías del plato.");
      }

      if (isAddingNewVersion && activeDish) {
        // New version created
        const createdVer = result.data as DishGenerationData;
        setSelectedVersion(createdVer);
        await fetchVersionsForDish(activeDish.id);
        setIsAddingNewVersion(false);
      } else {
        // New dish created
        const createdDish = result.data as DishModelData;
        setActiveDish(createdDish);
        await fetchVersionsForDish(createdDish.id);
      }

      // Reset form states
      clearStandardFile();
      setMultiviewFiles({ front: null, left: null, right: null, back: null });
      setMultiviewPreviews({ front: null, left: null, right: null, back: null });
      setDishPrompt("");
      fetchDishes();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Error de conexión al guardar el plato.";
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
      let endpoint = `${apiUrl}/dishes/${activeDish.id}/generate`;
      let payload: Record<string, any> = { model_version: selectedModel };

      if (selectedVersion) {
        endpoint = `${apiUrl}/dishes/${activeDish.id}/versions/${selectedVersion.id}/generate`;
        payload = {
          texture_quality: selectedVersion.texture_quality || textureQuality,
        };
      }

      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.message || "Error al iniciar la generación en Tripo AI V3.");
      }

      if (selectedVersion && result.data?.version_number) {
        setSelectedVersion(result.data);
      } else {
        setActiveDish(result.data);
      }

      fetchDishes();
      fetchBalance();
      if (activeDish.id) fetchVersionsForDish(activeDish.id);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Error al comunicarse con Tripo AI V3";
      if (selectedVersion) {
        setSelectedVersion((prev) =>
          prev ? { ...prev, status: "failed", error_message: msg } : null
        );
      } else {
        setActiveDish((prev) =>
          prev ? { ...prev, status: "failed", error_message: msg } : null
        );
      }
    } finally {
      setGenerating(false);
    }
  };

  // Activate Version
  const handleActivateVersion = async (versionId: string) => {
    if (!activeDish) return;
    setActivatingVersion(true);
    try {
      const res = await fetch(
        `${apiUrl}/dishes/${activeDish.id}/versions/${versionId}/activate`,
        {
          method: "POST",
        }
      );
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          await fetchVersionsForDish(activeDish.id);
          // Refetch active dish
          const dishRes = await fetch(`${apiUrl}/dishes/${activeDish.id}`);
          if (dishRes.ok) {
            const dJson = await dishRes.json();
            if (dJson.success) setActiveDish(dJson.data);
          }
          fetchDishes();
        }
      }
    } catch (err) {
      console.error("Error activating version:", err);
    } finally {
      setActivatingVersion(false);
    }
  };

  // Select dish from gallery
  const handleSelectDishFromGallery = async (dish: DishModelData) => {
    setActiveDish(dish);
    setSelectedVersion(dish.active_generation || null);
    setActiveTab("generator");
    setIsAddingNewVersion(false);
    try {
      const res = await fetch(`${apiUrl}/dishes/${dish.id}`, { cache: "no-store" });
      if (res.ok) {
        const json = await res.json();
        if (json.success && json.data) {
          const fresh = json.data as DishModelData;
          setActiveDish(fresh);
          if (fresh.active_generation) {
            setSelectedVersion(fresh.active_generation);
          }
          setDishes((prev) => prev.map((d) => (d.id === fresh.id ? fresh : d)));
          await fetchVersionsForDish(fresh.id);
        }
      }
    } catch (err) {
      console.error("Error fetching fresh dish details:", err);
    }
  };

  const currentDisplayTarget = selectedVersion || activeDish;
  const currentStatus = currentDisplayTarget?.status || "pending";
  const currentProgress = currentDisplayTarget?.progress ?? 0;

  // Credit cost logic
  const requiredCredits =
    generationMode === "multiview" && textureQuality === "detailed" ? 40 : 30;
  const hasSufficientBalance = balance !== null && balance >= requiredCredits;

  // GLB Model URL for active view
  const currentGlbUrl =
    activeDish && selectedVersion?.status === "success"
      ? `${apiUrl}/dishes/${activeDish.id}/versions/${selectedVersion.id}/model`
      : activeDish?.status === "success"
      ? `${apiUrl}/dishes/${activeDish.id}/model`
      : null;

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
            title="Haz clic para actualizar saldo en tiempo real desde la API V3 de Tripo"
          >
            <span>💳 Saldo Disponible:</span>
            <span className="balance-val">
              {balanceLoading
                ? "..."
                : balance !== null
                ? `${balance.toFixed(2)} créditos`
                : "Sin verificar"}
            </span>
            {frozenCredits > 0 && (
              <span
                style={{
                  fontSize: "0.75rem",
                  color: "#fbbf24",
                  background: "rgba(251, 191, 36, 0.15)",
                  padding: "2px 6px",
                  borderRadius: "4px",
                }}
              >
                🔒 {frozenCredits.toFixed(0)} retenidos
              </span>
            )}
            <span style={{ fontSize: "0.75rem", opacity: 0.7 }}>↺</span>
          </div>

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
          {/* Col 1: Formulario y Versiones */}
          <div className="glass-card">
            {/* If adding new version banner */}
            {isAddingNewVersion && activeDish && (
              <div
                style={{
                  background: "rgba(249, 115, 22, 0.12)",
                  border: "1px solid rgba(249, 115, 22, 0.35)",
                  padding: "0.75rem 1rem",
                  borderRadius: "10px",
                  marginBottom: "1.25rem",
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                }}
              >
                <div>
                  <strong style={{ color: "#fdba74", fontSize: "0.88rem" }}>
                    Creando Nueva Versión para: &quot;{activeDish.title}&quot;
                  </strong>
                  <p style={{ fontSize: "0.74rem", color: "var(--text-muted)", margin: 0 }}>
                    La versión anterior no se perderá ni sobrescribirá.
                  </p>
                </div>
                <button
                  type="button"
                  className="viewer-btn"
                  onClick={() => setIsAddingNewVersion(false)}
                  style={{ fontSize: "0.75rem" }}
                >
                  Cancelar
                </button>
              </div>
            )}

            <h2 className="card-title-main">
              {isAddingNewVersion ? "Subir Fotos para Nueva Versión" : "1. Fotografías y Calidad 3D"}
            </h2>
            <p className="card-subtitle">
              Elige entre generación rápida estándar o alta fidelidad multivista con texturas PBR.
            </p>

            {/* Mode Switcher Segmented Control */}
            <div className="mode-segmented-control">
              <button
                type="button"
                className={`mode-segment-btn ${generationMode === "multiview" ? "active" : ""}`}
                onClick={() => setGenerationMode("multiview")}
              >
                <span>🌟 Alta Calidad Multivista</span>
                <span className="mode-badge-rec">Recomendado</span>
              </button>

              <button
                type="button"
                className={`mode-segment-btn ${generationMode === "standard" ? "active" : ""}`}
                onClick={() => setGenerationMode("standard")}
              >
                <span>📸 Estándar (1 Foto)</span>
              </button>
            </div>

            {/* Culinary Photography Guide */}
            <PhotographyGuide />

            <form onSubmit={handleUploadDish}>
              {/* Multiview Mode: 4 designated slots */}
              {generationMode === "multiview" ? (
                <MultiviewSlotPicker
                  files={multiviewFiles}
                  previews={multiviewPreviews}
                  onSlotChange={handleMultiviewSlotChange}
                  disabled={uploading}
                />
              ) : (
                /* Standard Mode: Single Dropzone */
                <div style={{ marginBottom: "1.25rem" }}>
                  {!standardPreview ? (
                    <div
                      className="dropzone"
                      onDragOver={(e) => e.preventDefault()}
                      onDrop={handleStandardDrop}
                      onClick={() => standardInputRef.current?.click()}
                    >
                      <input
                        type="file"
                        ref={standardInputRef}
                        onChange={handleStandardFileChange}
                        accept="image/jpeg,image/png,image/webp"
                        style={{ display: "none" }}
                      />
                      <div className="dropzone-icon">📷</div>
                      <p className="dropzone-text">
                        Arrastra una fotografía aquí o haz clic para explorar
                      </p>
                      <span className="dropzone-hint">
                        Formatos: JPG, PNG, WEBP (Máx. 10MB)
                      </span>
                    </div>
                  ) : (
                    <div className="preview-container">
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={standardPreview}
                        alt="Vista previa de plato"
                        className="preview-img"
                      />
                      <div className="preview-overlay">
                        <button
                          type="button"
                          className="remove-btn"
                          onClick={clearStandardFile}
                        >
                          Cambiar foto
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Dish Title & Description (Only if not adding version to existing dish) */}
              {!isAddingNewVersion && (
                <>
                  <div className="form-group">
                    <label className="form-label" htmlFor="dish-title">
                      Nombre del Plato
                    </label>
                    <input
                      id="dish-title"
                      type="text"
                      className="form-input"
                      placeholder="Ej. Hamburguesa Artesanal con Papas Rústicas"
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
                      placeholder="Ej. Pan brioche dorado, carne sellada a la parrilla, queso cheddar fundido"
                      value={dishPrompt}
                      onChange={(e) => setDishPrompt(e.target.value)}
                    />
                  </div>
                </>
              )}

              {/* Dynamic Real-Time Cost Estimator */}
              <CostEstimatorCard
                mode={generationMode}
                modelVersion={selectedModel}
                textureQuality={textureQuality}
                onTextureQualityChange={setTextureQuality}
                userBalance={balance}
                apiUrl={apiUrl}
              />

              {uploadError && (
                <div className="error-banner" style={{ marginBottom: "1rem" }}>
                  <span>{uploadError}</span>
                  <button
                    type="button"
                    onClick={() => setUploadError(null)}
                    style={{
                      background: "none",
                      border: "none",
                      color: "white",
                      cursor: "pointer",
                    }}
                  >
                    ✕
                  </button>
                </div>
              )}

              <button
                type="submit"
                className="btn-primary"
                disabled={
                  uploading ||
                  (generationMode === "standard"
                    ? !standardFile
                    : !multiviewFiles.front ||
                      Object.values(multiviewFiles).filter(Boolean).length < 2)
                }
              >
                {uploading ? (
                  <>
                    <span
                      className="viewer-spinner"
                      style={{ width: "16px", height: "16px", borderWidth: "2px" }}
                    />
                    <span>
                      {isAddingNewVersion
                        ? "Registrando nueva versión (0 créditos)..."
                        : "Guardando fotografías en Gastro3D (0 créditos)..."}
                    </span>
                  </>
                ) : (
                  <>
                    <span>
                      📤{" "}
                      {isAddingNewVersion
                        ? "Registrar Nueva Versión (0 créditos)"
                        : "Guardar Plato en Gastro3D (0 créditos)"}
                    </span>
                  </>
                )}
              </button>
            </form>

            {/* Active Dish Progress and Version Manager */}
            {activeDish && (
              <div style={{ marginTop: "1.75rem" }}>
                {/* Version Manager for current dish */}
                {dishVersions.length > 0 && (
                  <VersionManager
                    versions={dishVersions}
                    selectedVersionId={selectedVersion?.id || null}
                    onSelectVersion={(v) => setSelectedVersion(v)}
                    onActivateVersion={handleActivateVersion}
                    onAddNewVersion={() => {
                      setIsAddingNewVersion(true);
                      window.scrollTo({ top: 120, behavior: "smooth" });
                    }}
                    activating={activatingVersion}
                  />
                )}

                {/* Status & Generation progress widget */}
                <div className="progress-widget">
                  <div className="progress-header">
                    <span className="progress-label">
                      <span>Plato:</span>
                      <strong>{activeDish.title || "Sin título"}</strong>
                      {selectedVersion && (
                        <span style={{ color: "var(--accent-orange)", marginLeft: "0.4rem" }}>
                          (v{selectedVersion.version_number})
                        </span>
                      )}
                    </span>
                    <span className={`status-badge ${currentStatus}`}>
                      {currentStatus === "success" && "● Modelo 3D Listo"}
                      {currentStatus === "running" && "● Procesando con IA V3"}
                      {currentStatus === "queued" && "● En Cola V3"}
                      {currentStatus === "uploading" && "● Subiendo a Tripo V3"}
                      {currentStatus === "failed" && "● Fallido"}
                      {currentStatus === "pending" && "● Pendiente de Generar"}
                    </span>
                  </div>

                  <div className="progress-bar-bg">
                    <div
                      className="progress-bar-fill"
                      style={{ width: `${Math.max(currentProgress, 5)}%` }}
                    />
                  </div>

                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                      marginTop: "0.5rem",
                    }}
                  >
                    <span className="progress-desc">
                      {currentStatus === "pending" &&
                        "Fotos registradas con éxito. Revisa el coste antes de iniciar la generación 3D."}
                      {currentStatus === "uploading" &&
                        "Sintetizando tokens y subiendo vistas al CDN de Tripo V3..."}
                      {currentStatus === "queued" &&
                        "En cola de renderizado volumétrico en Tripo V3..."}
                      {currentStatus === "running" &&
                        `Generando malla geométrica y mapas PBR (${currentProgress}%)...`}
                      {currentStatus === "success" &&
                        "¡Generación 3D completada! Modelo GLB almacenado localmente de forma persistente."}
                      {currentStatus === "failed" &&
                        `Error: ${currentDisplayTarget?.error_message || "Fallo en generación"}`}
                    </span>
                    <span className="progress-pct">{currentProgress}%</span>
                  </div>

                  {/* Generation Trigger Button */}
                  {currentStatus === "pending" && (
                    <button
                      type="button"
                      className="btn-primary btn-generate"
                      style={{ marginTop: "1rem" }}
                      onClick={() => setShowConfirmModal(true)}
                      disabled={generating}
                    >
                      <span>
                        🚀 Iniciar Generación 3D ({requiredCredits} créditos Tripo V3)
                      </span>
                    </button>
                  )}

                  {currentStatus === "failed" && (
                    <button
                      type="button"
                      className="btn-primary"
                      style={{
                        marginTop: "1rem",
                        background: "rgba(239, 68, 68, 0.2)",
                        border: "1px solid rgba(239, 68, 68, 0.4)",
                      }}
                      onClick={() => setShowConfirmModal(true)}
                      disabled={generating}
                    >
                      <span>↺ Reintentar Generación ({requiredCredits} créditos)</span>
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Col 2: Visualizador 3D y Comparativa de Fotos */}
          <div className="glass-card" style={{ display: "flex", flexDirection: "column" }}>
            <h2 className="card-title-main">2. Visualizador 3D y Comparativa</h2>
            <p className="card-subtitle">
              Inspección interactiva 360°, zoom orbital y contraste con las fotografías originales.
            </p>

            {currentGlbUrl ? (
              <ModelViewer
                src={currentGlbUrl}
                dishTitle={activeDish?.title || "Plato 3D"}
                poster={selectedVersion?.thumbnail_url || activeDish?.thumbnail_url || activeDish?.image_url}
                versionNumber={selectedVersion?.version_number}
                mode={selectedVersion?.mode}
                textureQuality={selectedVersion?.texture_quality}
              />
            ) : (
              <div className="viewer-placeholder">
                <div style={{ fontSize: "3rem", marginBottom: "0.5rem" }}>🧊</div>
                <h3 style={{ fontSize: "1.1rem", fontWeight: 700, marginBottom: "0.4rem" }}>
                  {currentStatus === "running" ||
                  currentStatus === "queued" ||
                  currentStatus === "uploading"
                    ? "Generando modelo 3D con Tripo V3..."
                    : "Esperando inicio de generación..."}
                </h3>
                <p style={{ color: "var(--text-muted)", fontSize: "0.85rem", maxWidth: "380px" }}>
                  {currentStatus === "running"
                    ? "Tripo AI V3 está calculando la geometría y mapas PBR. El visor cargará automáticamente el GLB al llegar al 100%."
                    : "Presiona 'Iniciar Generación 3D' para procesar las fotografías con el motor de IA."}
                </p>
                {(currentStatus === "running" || currentStatus === "queued") && (
                  <div className="viewer-spinner" style={{ marginTop: "1.5rem" }} />
                )}
              </div>
            )}

            {/* Original Photo Comparison Component */}
            {activeDish && (
              <PhotoComparison
                key={`${activeDish.id}_${selectedVersion?.id || "base"}`}
                views={
                  selectedVersion?.views && selectedVersion.views.length > 0
                    ? selectedVersion.views
                    : activeDish.active_generation?.views && activeDish.active_generation.views.length > 0
                    ? activeDish.active_generation.views
                    : [{ angle: "front", image_url: activeDish.image_url }]
                }
                fallbackUrl={activeDish.image_url}
                dishTitle={activeDish.title || "Plato"}
              />
            )}
          </div>
        </section>
      )}

      {/* TAB 2: GALERÍA DE PLATOS */}
      {activeTab === "gallery" && (
        <section className="glass-card">
          <div
            style={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              marginBottom: "1.5rem",
              flexWrap: "wrap",
              gap: "0.75rem",
            }}
          >
            <div>
              <h2 className="card-title-main">Galería de Platos Registrados</h2>
              <p className="card-subtitle" style={{ marginBottom: 0 }}>
                Modelos 3D y fotografías persistidos en la base de datos PostgreSQL de Gastro3D.
              </p>
            </div>
            <button
              className="viewer-btn"
              onClick={fetchDishes}
              disabled={loadingDishes}
            >
              <span>{loadingDishes ? "Actualizando..." : "↺ Actualizar Galería"}</span>
            </button>
          </div>

          {dishes.length === 0 ? (
            <div className="viewer-placeholder" style={{ height: "260px" }}>
              <p style={{ color: "var(--text-muted)" }}>
                Aún no has registrado fotografías de platos.
              </p>
            </div>
          ) : (
            <div className="gallery-grid">
              {dishes.map((dish) => (
                <div
                  key={dish.id}
                  className="gallery-card"
                  onClick={() => handleSelectDishFromGallery(dish)}
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
                      <span className={`status-badge ${dish.status}`}>
                        {dish.status === "success" && "● 3D Listo"}
                        {dish.status === "running" && "● Procesando"}
                        {dish.status === "queued" && "● En Cola"}
                        {dish.status === "pending" && "● Pendiente"}
                        {dish.status === "failed" && "● Fallido"}
                      </span>
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
            <div className="glass-card">
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
                <strong>Next.js Frontend</strong>
                <span className="status-badge online">● En Línea</span>
              </div>
              <p style={{ fontSize: "0.85rem", color: "var(--text-secondary)" }}>
                React 19 &middot; TypeScript &middot; Multiview Controls &middot; Google Model Viewer
              </p>
            </div>

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
                PHP 8.4 CLI &middot; TripoService V3 &middot; Multiview & Versioning Endpoints
              </p>
            </div>

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
                Tablas: dish_models &middot; dish_generations &middot; cache_locks
              </p>
            </div>

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
            <button
              className="viewer-btn"
              onClick={() => {
                checkHealth();
                fetchBalance();
              }}
              disabled={healthLoading || balanceLoading}
            >
              <span>
                {healthLoading || balanceLoading
                  ? "Verificando..."
                  : "↺ Recomprobar Estado y Saldo"}
              </span>
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
                  {hasSufficientBalance
                    ? "Confirmar Generación 3D (Tripo V3)"
                    : "Saldo Insuficiente en Tripo AI"}
                </h3>
                <span style={{ fontSize: "0.78rem", color: "var(--text-muted)" }}>
                  Modo: {generationMode === "multiview" ? "Multivista" : "Estándar"} &middot;{" "}
                  {textureQuality === "detailed" ? "Textura PBR Detallada" : "Textura Estándar"}
                </span>
              </div>
            </div>

            <p className="modal-body">
              Estás a punto de procesar el plato{" "}
              <strong>&quot;{activeDish.title}&quot;</strong> con la API V3 de Tripo AI.
            </p>

            {/* Credit Breakdown Table */}
            <div
              style={{
                background: "rgba(0,0,0,0.3)",
                padding: "0.9rem",
                borderRadius: "10px",
                display: "flex",
                flexDirection: "column",
                gap: "0.45rem",
                fontSize: "0.84rem",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between" }}>
                <span style={{ color: "var(--text-muted)" }}>Saldo disponible (API Wallet):</span>
                <strong
                  style={{
                    fontFamily: "var(--font-mono)",
                    color: hasSufficientBalance ? "#34d399" : "#f87171",
                  }}
                >
                  {balance !== null ? `${balance.toFixed(2)} créditos` : "Consultando..."}
                </strong>
              </div>

              {frozenCredits > 0 && (
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span style={{ color: "var(--text-muted)" }}>Créditos retenidos / en proceso:</span>
                  <span style={{ fontFamily: "var(--font-mono)", color: "#fbbf24" }}>
                    {frozenCredits.toFixed(2)} créditos
                  </span>
                </div>
              )}

              <div style={{ display: "flex", justifyContent: "space-between" }}>
                <span style={{ color: "var(--text-muted)" }}>
                  Coste del renderizado ({generationMode} &middot; {textureQuality}):
                </span>
                <strong
                  style={{
                    fontFamily: "var(--font-mono)",
                    color: "var(--accent-orange)",
                  }}
                >
                  {requiredCredits} créditos
                </strong>
              </div>

              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  borderTop: "1px solid rgba(255,255,255,0.08)",
                  paddingTop: "0.4rem",
                }}
              >
                <span style={{ color: "var(--text-muted)" }}>Saldo disponible posterior estimado:</span>
                <strong
                  style={{
                    fontFamily: "var(--font-mono)",
                    color: hasSufficientBalance ? "#34d399" : "#f87171",
                  }}
                >
                  {balance !== null ? `${(balance - requiredCredits).toFixed(2)} créditos` : "N/A"}
                </strong>
              </div>
            </div>

            {/* Warning when balance is insufficient */}
            {!hasSufficientBalance && (
              <div className="insufficient-box">
                <strong>🚫 Generación Bloqueada por Seguridad:</strong>
                <span>
                  No dispones de saldo suficiente en tu API Wallet de Tripo AI (Disponible:{" "}
                  <strong>{balance?.toFixed(2) ?? "0.00"} créditos</strong>; necesarios:{" "}
                  <strong>{requiredCredits} créditos</strong>).
                  {frozenCredits > 0 &&
                    ` Hay ${frozenCredits.toFixed(0)} créditos retenidos o en proceso de acreditación.`}
                </span>
                <span>
                  Para evitar errores de cobro o llamadas fallidas, el botón de envío ha sido deshabilitado.
                </span>
                <a
                  href="https://platform.tripo3d.ai/"
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
