# Gastro3D — Menús Gastronómicos 3D con IA

Plataforma SaaS para la transformación de fotografías de platos culinarios en modelos 3D interactivos y realidad aumentada mediante Inteligencia Artificial (Tripo AI).

---

## 🏛️ Arquitectura del Proyecto

- **Frontend**: Next.js 16 (App Router, React 19, TypeScript) en `http://localhost:3000`
- **Backend API**: Laravel 13 (PHP 8.4 CLI, PostgreSQL driver, GD) en `http://localhost:8000`
- **Base de Datos**: PostgreSQL 16 (contenedor aislado `gastro3d_postgres`) en `localhost:5433` (interno 5432)
- **Infraestructura**: Docker Compose con red y volúmenes aislados (`gastro3d_network`, `gastro3d_pgdata`)

---

## 🚀 Comandos de Inicio y Detención

Todos los comandos deben ejecutarse desde la raíz del proyecto `Gastro3D`:

### 1. Iniciar el entorno completo
```bash
docker compose up -d
```
> Esto iniciará PostgreSQL, el backend Laravel y el frontend Next.js en segundo plano de forma aislada.

### 2. Verificar el estado de los contenedores
```bash
docker compose ps
```

### 3. Ejecutar migraciones de base de datos
```bash
docker compose exec backend php artisan migrate
```

### 4. Consultar logs en tiempo real
```bash
# Todos los servicios
docker compose logs -f

# Solo backend
docker compose logs -f backend

# Solo frontend
docker compose logs -f frontend
```

### 5. Detener el entorno
```bash
# Detiene los contenedores preservando datos de PostgreSQL
docker compose stop

# O para bajar los contenedores y liberar la red
docker compose down
```

---

## 🔍 Verificación de Servicios

| Servicio | URL / Host | Estado |
|---|---|---|
| **Frontend Web** | [http://localhost:3000](http://localhost:3000) | Activo (Next.js) |
| **Backend API Health** | [http://localhost:8000/api/health](http://localhost:8000/api/health) | Activo (Conectado a PostgreSQL) |
| **PostgreSQL DB** | `localhost:5433` (DB: `gastro3d`) | Saludable (Healthcheck activo) |

### Ejemplo de respuesta `/api/health`:
```json
{
  "status": "healthy",
  "service": "Gastro3D Backend API",
  "timestamp": "2026-10-17T23:04:37+00:00",
  "database": {
    "status": "connected",
    "driver": "pgsql",
    "error": null
  }
}
```
