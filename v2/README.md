# 4chan v2 - Proyecto de Modernización

Este proyecto representa la reimplementación moderna de 4chan, siguiendo una arquitectura de microservicios con Go y Rust para el backend, y React con TypeScript para el frontend.

## Estructura del Proyecto

```
v2/
├── backend_go/            # Servicios principales en Go (API, moderación)
├── backend_rust/          # Servicios críticos en Rust (archivos, seguridad)
├── frontend_new/          # Aplicación frontend moderna en React/TypeScript
└── docs/                  # Documentación técnica
```

## Arquitectura

El sistema sigue una arquitectura de microservicios distribuida:

- **API Gateway**: Punto de entrada único para todas las solicitudes
- **Servicio de Autenticación (Go)**: Manejo de identidades y permisos
- **Servicio de Contenido (Go)**: Gestión de tablones, hilos y posts
- **Servicio de Archivos (Rust)**: Procesamiento seguro de archivos e imágenes
- **Servicio de Moderación (Go)**: Herramientas avanzadas de moderación

## Tecnologías Principales

### Backend (Go)
- Go 1.22+
- Gin Gonic para API REST
- JWT para autenticación
- PostgreSQL con pgx para la persistencia
- Redis para caché y gestión de sesiones
- Prometheus para métricas

### Backend (Rust)
- Rust (Edition 2021)
- Actix Web para servicios HTTP
- Tokio para procesamiento asíncrono
- SQLx para acceso a base de datos
- AWS SDK para almacenamiento S3

### Frontend
- React 18 con TypeScript
- React Query para gestión de estado del servidor
- Redux Toolkit para estado global
- Styled Components y Tailwind CSS para estilos
- Framer Motion para animaciones

### Infraestructura
- Docker y Docker Compose para desarrollo
- Kubernetes para orquestación en producción
- Github Actions para CI/CD
- Neon PostgreSQL en la nube
- Prometheus, Grafana para monitoreo

## Requisitos

- Go 1.22+
- Rust 1.70+
- Node.js 20+
- Docker y Docker Compose
- PostgreSQL 15+ (o conexión a Neon PostgreSQL)
- Redis 7+

## Comenzando

### Configuración de la Base de Datos

Este proyecto utiliza Neon PostgreSQL como base de datos en la nube:

```
jdbc:postgresql://ep-ancient-bonus-a4qfas6k-pooler.us-east-1.aws.neon.tech/neondb?user=neondb_owner&password=npg_mGXsRdat0E7q&sslmode=require
```

### Configuración Backend Go

```bash
cd v2/backend_go
cp .env.example .env
# Editar .env con las credenciales correctas
go mod download
go run .
```

### Configuración Backend Rust

```bash
cd v2/backend_rust
cp .env.example .env
# Editar .env con las credenciales correctas
cargo build
cargo run
```

### Configuración Frontend

```bash
cd v2/frontend_new
cp .env.example .env
# Editar .env con las URLs correctas
npm install
npm run dev
```

### Ejecución con Docker Compose

```bash
docker-compose up -d
```

## Seguridad

Este proyecto implementa múltiples capas de seguridad:

- Validación estricta de entrada en todos los niveles
- Sanitización de datos y contenido
- Autenticación multifactor para administradores
- Procesamiento seguro de archivos en entornos aislados
- Headers de seguridad y CSP
- Encriptación en tránsito y en reposo
- Protección contra ataques comunes (XSS, CSRF, inyección)
- Pruebas automatizadas de seguridad

## Capacidades Técnicas

- **Escalabilidad**: Arquitectura diseñada para escalar horizontalmente
- **Rendimiento**: Optimizaciones a nivel de código y base de datos
- **Resiliencia**: Manejo inteligente de fallos y degradación elegante
- **Observabilidad**: Métricas, trazas y logs centralizados
- **Accesibilidad**: Implementación de WCAG 2.1 AA

## Licencia

Este proyecto está licenciado bajo los términos especificados en el archivo LICENSE.