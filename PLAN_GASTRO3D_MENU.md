# GASTRO3D MENU — PLAN TÉCNICO Y GUÍA DE DESARROLLO

**Producto:** Gastro3D Menu (Módulo comercial SaaS de menús digitales interactivos en 3D y Realidad Aumentada)  
**Primer Caso Piloto:** Coffelia (Cafetería de Especialidad en Colombia)  
**Fecha de Inicio:** 18 de octubre de 2026  
**Arquitectura:** Laravel 13 (API Backend), PostgreSQL 16 (Multi-Tenant Relacional), Next.js 16 (Frontend Web App), Model-Viewer / WebXR (3D & AR).

---

## 1. VISIÓN COMERCIAL Y ESTRATEGIA

Gastro3D Menu es el primer producto comercializable del ecosistema Gastro3D. Permite a restaurantes, cafeterías y negocios gastronómicos transformar sus cartas tradicionales en experiencias visuales interactivas mediante códigos QR.

### Principios Fundamentales:
1. **Multi-Tenant Real:** Cada establecimiento gestiona de forma aislada sus categorías, productos, precios, colores de marca y modelos 3D.
2. **Independencia de Gastro3D Studio:** Studio continúa funcionando como el motor y laboratorio de generación 3D (Tripo AI). Menu consume de forma segura los modelos 3D terminados sin duplicar archivos ni alterar Studio.
3. **Piloto Coffelia:** Coffelia es el cliente piloto de lanzamiento para validar tracción y experiencia de usuario. La plataforma está diseñada para cualquier negocio gastronómico; Coffelia es un tenant configurable, no una estructura fija.
4. **Respeto a la Información Comercial:** No se inventan ni publican datos oficiales de precios o fotografías sin autorización del cliente.
5. **Enfoque Iterativo:** Validar primero la experiencia completa (QR → Menú → 3D → AR → Carrito/Pedido) antes de construir módulos administrativos o facturación compleja.

---

## 2. ETAPAS DEL PROYECTO

### ETAPA 1: BASE DE DATOS Y MODELOS ELOQUENT MULTI-TENANT (COMPLETADA)
- **Estado:** ✅ Completado y Verificado (18 de Octubre de 2026).
- **Entregables:**
  - Migraciones para `establishments`, `menu_categories`, `menu_products`.
  - Modelos `Establishment`, `MenuCategory`, `MenuProduct` con relaciones bidireccionales.
  - Relación inversa en `DishModel` (`menuProducts()`) con desacoplamiento seguro (`nullOnDelete`).
  - Validación de aislamiento estricto en el ciclo de vida del modelo (`MenuProduct::saving`) y servicio `TenantIsolationService`.
  - Respaldo verificado en `backups/backup_pre_etapa1_multi_tenant.sql` y prueba de restauración en BD temporal.
  - 52 pruebas automatizadas en verde (246 aserciones). Compilación de Next.js sin errores.

---

### ETAPA 2: ENDPOINTS REST Y CATÁLOGO PILOTO COFFELIA (COMPLETADA)
- **Estado:** ✅ Completado y Verificado (18 de Octubre de 2026).
- **Entregables:**
  1. **Endpoints Públicos del Menú:**
     - `GET /api/v1/establishments/{slug}/menu`: Consulta rápida y estructurada con eager loading de categorías activas y productos disponibles.
     - `GET /api/v1/establishments/{slug}/products/{productSlug}`: Detalle de producto por slug o UUID, con metadatos 3D/AR seguros.
  2. **Controlador y Recursos:**
     - `EstablishmentMenuController` con `EstablishmentMenuResource`, `MenuCategoryResource`, `MenuProductResource`, `MenuProductDetailResource`.
     - Manejo de respuestas 404 para slugs inexistentes, inactivos o acceso cross-tenant no autorizado.
  3. **Catálogo Demostrativo Piloto (Seeder) y Placeholders:**
     - Seeder `CoffeliaPilotSeeder` idempotente con el tenant `coffelia`, paleta de café artesanal (`theme_config`) y moneda `COP`.
     - 4 categorías: Cafés de Especialidad, Bebidas Frías, Repostería Artesanal, Panadería & Salados.
     - 9 productos con placeholders SVG locales (`/images/placeholders/food-placeholder.svg`, etc.) y precios expresamente identificados como demostrativos en `meta` (`is_demo_price: true`), sin asociar modelos 3D ajenos.
  4. **Estrategia Estable de Slugs:**
     - Migración `2026_10_18_030003_add_slug_to_menu_products_table.php` con índice compuesto `UNIQUE(establishment_id, slug)`.
     - Slugs permanentes autogenerados en `MenuProduct::booted()` con prevención de colisiones.
  5. **Pruebas Automatizadas:**
     - 11 pruebas en `EstablishmentMenuApiTest.php` validando estructura, ordenación por `sort_order`, filtrado de inactivos, aislamiento cross-tenant, sanitización de respuestas privadas, estabilidad de slugs e idempotencia del seeder.
     - Total: 63 pruebas pasando en Laravel (348 aserciones). Compilación de Next.js sin errores. Studio y GLBs intactos.

---

### ETAPA 3: MENÚ DIGITAL RESPONSIVE EN NEXT.JS
- **Objetivo:** Desarrollar la interfaz web pública `/menu/[slug]` con diseño ultra-premium optimizado para teléfonos móviles.
- **Alcance Técnico:**
  1. Ruta dinámica `app/menu/[slug]/page.tsx` en Next.js.
  2. Cabecera con identidad visual del establecimiento (logo, banner, paleta de colores personalizada).
  3. Navegación rápida horizontal tipo sticky para categorías.
  4. Cards de productos con fotografía 2D, badges de "Disponible en 3D" y "Ver en AR".
  5. Sheet o modal de detalle de producto.

---

### ETAPA 4: VISUALIZACIÓN 3D Y REALIDAD AUMENTADA (AR)
- **Objetivo:** Permitir explorar el producto en 360° y proyectarlo sobre la mesa del comensal.
- **Alcance Técnico:**
  1. Integración de `<model-viewer>` con carga diferida (lazy loading).
  2. Soporte AR nativo:
     - Android: Scene Viewer / WebXR.
     - iOS: Quick Look con conversión/fallback controlado o GLB/USDZ según soporte.
  3. Botón de acción principal: *"Ver en mi mesa"*.
  4. Optimización de renderizado para no congelar navegadores móviles de gama media.

---

### ETAPA 5: CARRITO INTERACTIVO Y PEDIDOS POR WHATSAPP
- **Objetivo:** Permitir al cliente armar su pedido y enviarlo directamente a la barra o cocina.
- **Alcance Técnico:**
  1. Carrito en estado local (Zustand o Context de React) con persistencia temporal.
  2. Selector de cantidades y notas especiales para la preparación.
  3. Generador de enlace de WhatsApp estructurado con el resumen del pedido, total en moneda local y número de mesa (si viene en URL o parámetro).

---

### ETAPA 6: ADMINISTRADOR SAAS BÁSICO Y GENERADOR DE QR
- **Objetivo:** Panel para que el equipo o cliente gestione sus productos y descargue el material para mesas.
- **Alcance Técnico:**
  1. Gestión básica de categorías y productos.
  2. Vinculador visual entre productos del menú y modelos generados en Gastro3D Studio.
  3. Generador de códigos QR vectoriales con el logo del establecimiento y slug personalizado.

---

## 3. GUÍA PARA RETOMAR EL PROYECTO MAÑANA

Al reanudar la sesión de desarrollo, sigue estos pasos en orden:

1. **Verificar el Estado del Repositorio:**
   ```bash
   git status
   git branch
   git log -1
   ```
   *Confirmar que estás en la rama `main` y sincronizado con `origin/main`.*

2. **Verificar Contenedores Docker Activos:**
   ```bash
   docker ps
   ```
   *Asegurarse de que `gastro3d_backend`, `gastro3d_frontend` y `gastro3d_postgres` estén ejecutándose.*

3. **Verificar la Suite de Pruebas:**
   ```bash
   docker exec gastro3d_backend php artisan test
   ```
   *Deben pasar las 52 pruebas existentes.*

4. **Proceder con la Etapa 2:**
   - Solicitar confirmación para iniciar el desarrollo de la **Etapa 2 (Endpoints REST y Catálogo Piloto Coffelia)**.
   - Crear los controladores `EstablishmentMenuController`, JsonResources y las rutas en `backend/routes/api.php`.
   - Implementar el seeder piloto controlado sin datos sensibles.
   - Escribir las pruebas de endpoints de menú.
   - Ejecutar la suite de pruebas y validar respuestas JSON.
