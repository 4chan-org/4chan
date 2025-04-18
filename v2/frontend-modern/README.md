# Frontend Modern

Implementación avanzada del frontend utilizando una arquitectura de micro-frontends y tecnologías modernas.

## Características

- Arquitectura de micro-frontends basada en Web Components
- Enfoque de "design system" para consistencia visual
- Optimizado para dispositivos móviles y desktop
- Comunicación en tiempo real (WebSockets)
- Soporte para temas claros y oscuros
- Rendimiento optimizado con Code Splitting y lazy loading
- PWA (Progressive Web App) con soporte offline

## Tecnologías

- React 18
- TypeScript
- Redux Toolkit para gestión de estado
- React Query para manejo de datos
- Vite como build tool
- Web Components para encapsulación
- Testing con Vitest y Testing Library

## Estructura

```
frontend-modern/
├── src/
│   ├── api/               # Cliente API con interceptores
│   ├── components/        # Componentes de UI compartidos
│   ├── features/          # Módulos organizados por funcionalidad
│   │   ├── auth/          # Autenticación y gestión de usuarios
│   │   ├── boards/        # Visualización de tablones
│   │   ├── posts/         # Creación y visualización de posts
│   │   └── ...            
│   ├── utils/             # Utilidades compartidas
│   ├── App.tsx            # Componente raíz
│   └── main.tsx           # Punto de entrada
└── package.json           # Dependencias y scripts
```

## Seguridad

- Implementación completa de CSP (Content Security Policy)
- Sanitización de contenido con DOMPurify
- Validación de entrada con Zod/Yup
- Defensa contra XSS, CSRF, y ataques de inyección
- Autenticación segura con tokens JWT y refresh tokens

## Instalación y Desarrollo

```bash
cd frontend-modern
npm install
npm run dev
```

## Integración con Micro-frontends

Este frontend moderno trabaja en conjunto con la arquitectura de micro-frontends implementada en el directorio `/v2/microfrontends`. Actúa como la base para la nueva experiencia mientras se migra gradualmente hacia una completa arquitectura de micro-frontends.