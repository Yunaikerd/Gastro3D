"use client";

import React, { useRef } from "react";
import { MenuCategoryData } from "@/types/menu";
import styles from "@/app/menu/[slug]/menu.module.css";

interface CategoryNavProps {
  categories: MenuCategoryData[];
  activeCategoryId: string | "all";
  onSelectCategory: (id: string | "all") => void;
}

export default function CategoryNav({
  categories,
  activeCategoryId,
  onSelectCategory,
}: CategoryNavProps) {
  const scrollContainerRef = useRef<HTMLDivElement | null>(null);

  const getCategoryIcon = (iconName: string | null) => {
    switch (iconName) {
      case "coffee":
        return "☕";
      case "glass-water":
        return "🥤";
      case "cake":
        return "🍰";
      case "bread-slice":
        return "🥐";
      default:
        return "🍽️";
    }
  };

  const handleTabClick = (catId: string | "all") => {
    onSelectCategory(catId);

    if (catId !== "all") {
      const targetElement = document.getElementById(`cat-${catId}`);
      if (targetElement) {
        targetElement.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    } else {
      window.scrollTo({ top: 400, behavior: "smooth" });
    }
  };

  return (
    <nav className={styles.stickyNavWrapper} aria-label="Categorías del menú">
      <div className={styles.categoryScroll} ref={scrollContainerRef}>
        <button
          className={`${styles.categoryTab} ${
            activeCategoryId === "all" ? styles.categoryTabActive : ""
          }`}
          onClick={() => handleTabClick("all")}
          aria-pressed={activeCategoryId === "all"}
        >
          <span>✨</span>
          <span>Todos</span>
        </button>

        {categories.map((category) => (
          <button
            key={category.id}
            className={`${styles.categoryTab} ${
              activeCategoryId === category.id ? styles.categoryTabActive : ""
            }`}
            onClick={() => handleTabClick(category.id)}
            aria-pressed={activeCategoryId === category.id}
          >
            <span>{getCategoryIcon(category.icon)}</span>
            <span>{category.name}</span>
          </button>
        ))}
      </div>
    </nav>
  );
}
