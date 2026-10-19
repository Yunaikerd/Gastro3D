"use client";

import React, { useEffect, useState, useMemo, use } from "react";
import MenuHeader from "@/components/menu/MenuHeader";
import CategoryNav from "@/components/menu/CategoryNav";
import ProductCard from "@/components/menu/ProductCard";
import ProductDetailModal from "@/components/menu/ProductDetailModal";
import MenuSkeletons from "@/components/menu/MenuSkeletons";
import { EstablishmentMenuData, MenuProductData } from "@/types/menu";
import styles from "./menu.module.css";

interface PageProps {
  params: Promise<{ slug: string }>;
}

function MenuContent({ slug }: { slug: string }) {

  const [establishment, setEstablishment] = useState<EstablishmentMenuData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // User Interactive State
  const [themeMode, setThemeMode] = useState<"dark" | "light">("dark");
  const [searchQuery, setSearchQuery] = useState("");
  const [activeCategoryId, setActiveCategoryId] = useState<string | "all">("all");
  const [selectedProduct, setSelectedProduct] = useState<MenuProductData | null>(null);

  const apiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

  // Load saved theme preference without hydration mismatch
  useEffect(() => {
    const savedTheme = localStorage.getItem("gastro3d_menu_theme") as "dark" | "light" | null;
    if (savedTheme === "light" || savedTheme === "dark") {
      setThemeMode(savedTheme);
    }
  }, []);

  const handleToggleTheme = () => {
    const nextTheme = themeMode === "dark" ? "light" : "dark";
    setThemeMode(nextTheme);
    localStorage.setItem("gastro3d_menu_theme", nextTheme);
  };

  // Fetch Menu Data
  const fetchMenu = async () => {
    setLoading(true);
    setError(null);

    try {
      const res = await fetch(`${apiUrl}/v1/establishments/${slug}/menu`, {
        cache: "no-store",
      });

      if (!res.ok) {
        if (res.status === 404) {
          throw new Error("El establecimiento solicitado no existe o no tiene una carta activa.");
        }
        throw new Error(`Error del servidor (${res.status}). No fue posible cargar la carta.`);
      }

      const json = await res.json();
      if (!json.success || !json.data) {
        throw new Error(json.message || "Respuesta inválida del servidor.");
      }

      setEstablishment(json.data);
    } catch (err: any) {
      setError(err.message || "Error al conectar con Gastro3D API.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (slug) {
      fetchMenu();
    }
  }, [slug]);

  // Filter Categories & Products based on search query
  const filteredCategories = useMemo(() => {
    if (!establishment || !establishment.categories) return [];

    const query = searchQuery.trim().toLowerCase();

    return establishment.categories
      .map((cat) => {
        // If a specific category is active and it's not "all", skip other categories
        if (activeCategoryId !== "all" && cat.id !== activeCategoryId) {
          return null;
        }

        const filteredProducts = cat.products.filter((p) => {
          if (!query) return true;
          const matchName = p.name.toLowerCase().includes(query);
          const matchDesc = p.description ? p.description.toLowerCase().includes(query) : false;
          const matchTags = p.dietary_tags ? p.dietary_tags.some((t) => t.toLowerCase().includes(query)) : false;
          return matchName || matchDesc || matchTags;
        });

        if (filteredProducts.length === 0) return null;

        return {
          ...cat,
          products: filteredProducts,
        };
      })
      .filter((cat): cat is NonNullable<typeof cat> => cat !== null);
  }, [establishment, searchQuery, activeCategoryId]);

  const totalFilteredProducts = useMemo(() => {
    return filteredCategories.reduce((acc, cat) => acc + cat.products.length, 0);
  }, [filteredCategories]);

  // Loading Skeleton State
  if (loading) {
    return <MenuSkeletons />;
  }

  // Error State
  if (error || !establishment) {
    return (
      <div className={styles.menuContainer} data-theme={themeMode}>
        <div className={styles.stateCard}>
          <div className={styles.stateIcon}>🍽️</div>
          <h2 className={styles.stateTitle}>Carta no disponible</h2>
          <p className={styles.stateDesc}>
            {error || "No fue posible encontrar el establecimiento indicado."}
          </p>
          <button
            type="button"
            className={styles.stateActionBtn}
            onClick={fetchMenu}
          >
            Reintentar
          </button>
        </div>
      </div>
    );
  }

  return (
    <div
      className={styles.menuContainer}
      data-theme={themeMode}
      style={{
        // Dynamic theme config overrides
        ...(establishment.theme_config.primary_color
          ? ({ "--menu-accent": establishment.theme_config.primary_color } as React.CSSProperties)
          : {}),
      }}
    >
      {/* Restaurant Header & Profile */}
      <MenuHeader
        establishment={establishment}
        themeMode={themeMode}
        onToggleTheme={handleToggleTheme}
      />

      {/* Real-time Search Filter */}
      <div className={styles.searchContainer}>
        <div className={styles.searchBox}>
          <span className={styles.searchIcon}>🔍</span>
          <input
            type="search"
            className={styles.searchInput}
            placeholder="Buscar por nombre, ingrediente o etiqueta (ej. Espresso, Vegano)..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            aria-label="Buscar en la carta"
          />
          {searchQuery && (
            <button
              type="button"
              className={styles.clearSearchBtn}
              onClick={() => setSearchQuery("")}
              aria-label="Limpiar búsqueda"
            >
              ✕
            </button>
          )}
        </div>
      </div>

      {/* Sticky Category Pills Navigation */}
      <CategoryNav
        categories={establishment.categories}
        activeCategoryId={activeCategoryId}
        onSelectCategory={setActiveCategoryId}
      />

      {/* Main Gastronomic Menu Content */}
      <main className={styles.mainContent}>
        {filteredCategories.length > 0 ? (
          filteredCategories.map((category) => (
            <section
              key={category.id}
              id={`cat-${category.id}`}
              className={styles.categorySection}
            >
              <div className={styles.categoryHeading}>
                <h2 className={styles.categoryTitle}>
                  <span>{category.name}</span>
                </h2>
                {category.description && (
                  <p className={styles.categoryDesc}>{category.description}</p>
                )}
              </div>

              <div className={styles.productGrid}>
                {category.products.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    currency={establishment.currency || "COP"}
                    onSelect={setSelectedProduct}
                  />
                ))}
              </div>
            </section>
          ))
        ) : (
          <div className={styles.stateCard}>
            <div className={styles.stateIcon}>🔍</div>
            <h3 className={styles.stateTitle}>Sin resultados</h3>
            <p className={styles.stateDesc}>
              No encontramos productos que coincidan con &quot;{searchQuery}&quot; en esta categoría.
            </p>
            <button
              type="button"
              className={styles.stateActionBtn}
              onClick={() => {
                setSearchQuery("");
                setActiveCategoryId("all");
              }}
            >
              Ver toda la carta
            </button>
          </div>
        )}
      </main>

      {/* Product Detail Modal */}
      <ProductDetailModal
        product={selectedProduct}
        currency={establishment.currency || "COP"}
        onClose={() => setSelectedProduct(null)}
      />
    </div>
  );
}

function MenuSlugResolver({ params }: PageProps) {
  const resolvedParams = use(params);
  return <MenuContent slug={resolvedParams.slug} />;
}

export default function DigitalMenuPage(props: PageProps) {
  return (
    <React.Suspense fallback={<MenuSkeletons />}>
      <MenuSlugResolver {...props} />
    </React.Suspense>
  );
}

