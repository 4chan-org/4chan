# Microfrontends Architecture

Este directorio contiene la implementación de la arquitectura de microfrontends para la versión modernizada de la aplicación.

## Estructura

La aplicación está dividida en los siguientes microfrontends, cada uno con una responsabilidad específica:

- **shell**: Aplicación principal que orquesta y carga los diferentes microfrontends
- **board-viewer**: Visualización de tablones e hilos
- **catalog-viewer**: Vista específica de catálogo de publicaciones
- **post-creator**: Componente para la creación de posts y nuevos hilos
- **auth**: Gestión de autenticación y registro de usuarios
- **media-viewer**: Visualización de imágenes y archivos multimedia
- **moderation**: Herramientas para moderadores
- **shared**: Componentes, utilidades y estilos compartidos entre microfrontends

## Tecnologías

- Web Components con Lit para la encapsulación de cada microfrontend
- React 18 para el Shell y componentes internos
- TypeScript para un desarrollo tipado y seguro
- Vite para el desarrollo y construcción
- Redux/Toolkit para la gestión de estado global (en Shell)

## Desarrollo

Cada microfrontend es un proyecto independiente con su propio:
- Package.json y dependencias
- Ciclo de vida de desarrollo y build
- Repositorio de código (dentro del monorepo)

### Pasos para ejecutar

1. Instalar dependencias de cada microfrontend:
```
cd shell && npm install
cd ../board-viewer && npm install
cd ../catalog-viewer && npm install
...
```

2. Iniciar el desarrollo:
```
cd shell && npm run dev
```

### Consideraciones

- Los microfrontends se comunican a través de eventos personalizados
- La aplicación shell es responsable de la orquestación y enrutamiento
- Cada microfrontend debe encapsular su lógica y estilos
- Los componentes shared proporcionan una experiencia visual coherente