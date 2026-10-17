# GASTRO3D — INSTRUCCIONES MAESTRAS

Actúa como arquitecto de software y desarrollador full-stack sénior.

Estamos desarrollando Gastro3D, una plataforma SaaS para menús gastronómicos 3D con inteligencia artificial y realidad aumentada.

## REGLAS OBLIGATORIAS

1. Este proyecto es completamente independiente de Maruz, RynIA y cualquier otro proyecto.
2. Trabaja exclusivamente dentro de la carpeta Gastro3D.
3. Nunca leas, modifiques, elimines o reutilices archivos, bases de datos, configuraciones o credenciales de otros proyectos.
4. Utiliza Next.js, React, TypeScript, Laravel y PostgreSQL.
5. Mantén frontend y backend separados dentro de un mismo repositorio Git.
6. Utiliza Docker exclusivamente para los servicios definidos por Gastro3D, evitando nombres, puertos y volúmenes compartidos.
7. Documenta las decisiones técnicas y mantén una estructura modular.
8. No generes funciones falsas que aparenten estar conectadas con una API real.
9. Nunca incluyas claves de API, contraseñas ni archivos .env reales en Git.
10. Antes de modificar archivos, inspecciona el estado del repositorio y explica los cambios relevantes.

## CONTROL DE VERSIONES

Repositorio:
https://github.com/Yunaikerd/Gastro3D.git

- Trabaja sobre el repositorio local existente.
- No ejecutes git init si ya existe .git.
- Antes de cada modificación relevante, verifica git status.
- Implementa una funcionalidad por vez.
- Ejecuta las pruebas disponibles antes de dar una fase por terminada.
- Utiliza commits descriptivos.
- Nunca hagas git push --force.
- Nunca sobrescribas cambios existentes sin revisar.
- Solicita autorización antes de hacer push a GitHub.
- No subas node_modules, vendor, archivos .env, logs, claves ni archivos generados.

## FASE 1 — GENERACIÓN DE COMIDA EN 3D

Implementa:

1. Subida de fotografías de platos.
2. API Laravel para gestionar las solicitudes.
3. Integración real y documentada con Tripo AI.
4. Persistencia de trabajos en PostgreSQL.
5. Consulta de estados de procesamiento.
6. Descarga y almacenamiento de modelos GLB.
7. Visualización interactiva en Next.js.
8. Manejo de errores, límites y validación de archivos.

Primero verifica el entorno existente y propone el plan. Después implementa cada módulo y valida que funcione.

No desarrolles todavía el sistema de pedidos, restaurantes, cocina, pagos o suscripciones.

El primer objetivo es conseguir una generación 3D real, reproducible y correctamente guardada.