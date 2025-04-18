# Frontend Legacy

Este es el frontend tradicional del proyecto, que mantiene la compatibilidad con la experiencia clásica del sitio mientras incorpora mejoras tecnológicas modernas.

## Características

- Interfaz de usuario responsiva
- Soporte para los temas clásicos (Yotsuba, Tomorrow, Futaba, etc.)
- Implementado con React 18 y TypeScript
- Accesibilidad WCAG 2.1 AA
- Componentes UI reutilizables

## Tecnologías Utilizadas

- React 18
- TypeScript
- React Router
- React Query
- Zustand (gestión de estado)
- Styled Components
- Zod (validación)
- DOMPurify (sanitización)
- Vite (build tool)
- Vitest (testing)

## Estructura de Directorios

```
src/
   components/       # Componentes reusables
   containers/       # Componentes de página/contenedor
   hooks/            # Custom React hooks
   services/         # Servicios de API
   utils/            # Utilidades y helpers
   themes/           # Definiciones de temas
   types/            # Definiciones de TypeScript
   assets/           # Imágenes, iconos, etc.
```

## Instalación

```bash
# Instalar dependencias
npm install

# Copiar archivo de entorno y configurar
cp .env.example .env
```

## Desarrollo

```bash
# Iniciar servidor de desarrollo
npm run dev

# Ejecutar tests
npm run test

# Ejecutar linting
npm run lint
```

## Seguridad

Este frontend implementa múltiples capas de seguridad, incluyendo:

- Sanitización estricta de HTML con DOMPurify
- Validación de datos con Zod
- Protección contra XSS
- CSP implementada en los headers
- Autenticación segura con tokens JWT

## Notas de Migración

Este frontend forma parte de la estrategia de migración gradual. En el futuro, muchas de sus funcionalidades se migrarán a la arquitectura de micro-frontends implementada en `frontend-modern` y `microfrontends`.