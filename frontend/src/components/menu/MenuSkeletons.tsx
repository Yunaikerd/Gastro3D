"use client";

import React from "react";
import styles from "@/app/menu/[slug]/menu.module.css";

export default function MenuSkeletons() {
  return (
    <div className={styles.menuContainer} data-theme="dark">
      {/* Top Utility Skeleton */}
      <div className={styles.topUtilityBar}>
        <div className={`${styles.skeleton}`} style={{ width: 140, height: 24, borderRadius: 999 }} />
        <div className={`${styles.skeleton}`} style={{ width: 100, height: 28, borderRadius: 999 }} />
      </div>

      {/* Hero Skeleton */}
      <div className={styles.heroSection}>
        <div className={`${styles.skeleton} ${styles.coverCard}`} style={{ height: 240 }} />
        <div className={styles.establishmentProfile} style={{ marginTop: -50 }}>
          <div className={`${styles.skeleton} ${styles.logoWrapper}`} style={{ width: 100, height: 100 }} />
          <div className={`${styles.skeleton}`} style={{ width: 260, height: 32, marginTop: 16 }} />
          <div className={`${styles.skeleton}`} style={{ width: 340, height: 18, marginTop: 10 }} />
          <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
            <div className={`${styles.skeleton}`} style={{ width: 80, height: 26, borderRadius: 999 }} />
            <div className={`${styles.skeleton}`} style={{ width: 110, height: 26, borderRadius: 999 }} />
          </div>
        </div>
      </div>

      {/* Nav Skeleton */}
      <div className={styles.stickyNavWrapper}>
        <div className={styles.categoryScroll}>
          {[1, 2, 3, 4, 5].map((i) => (
            <div
              key={i}
              className={`${styles.skeleton}`}
              style={{ width: 120, height: 36, borderRadius: 999, flexShrink: 0 }}
            />
          ))}
        </div>
      </div>

      {/* Product Grid Skeleton */}
      <main className={styles.mainContent}>
        <div className={`${styles.skeleton}`} style={{ width: 180, height: 28, marginBottom: 20 }} />
        <div className={styles.productGrid}>
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div
              key={i}
              className={styles.card}
              style={{ pointerEvents: "none" }}
            >
              <div className={`${styles.skeleton}`} style={{ width: "100%", height: 200 }} />
              <div className={styles.cardBody}>
                <div className={`${styles.skeleton}`} style={{ width: "70%", height: 20, marginBottom: 10 }} />
                <div className={`${styles.skeleton}`} style={{ width: "95%", height: 14, marginBottom: 6 }} />
                <div className={`${styles.skeleton}`} style={{ width: "80%", height: 14, marginBottom: 14 }} />
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                  <div className={`${styles.skeleton}`} style={{ width: 90, height: 24 }} />
                  <div className={`${styles.skeleton}`} style={{ width: 80, height: 30, borderRadius: 999 }} />
                </div>
              </div>
            </div>
          ))}
        </div>
      </main>
    </div>
  );
}
