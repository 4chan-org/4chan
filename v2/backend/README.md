# 4chan v2 Backend

Este repositorio contiene la implementación del backend para el proyecto de modernización de 4chan.

## Estructura del Proyecto

```
backend/
├── services/               # Implementaciones de microservicios especializados
│   ├── api-core/           # API principal y lógica de negocio (NestJS)
│   │   ├── prisma/         # Esquema y migraciones de base de datos
│   │   ├── src/            # Código fuente de la API
│   │   └── ...
│   ├── file-service/       # Servicio de gestión de archivos (Go)
│   │   ├── config/         # Configuración
│   │   ├── internal/       # Código fuente
│   │   │   ├── api/        # Controladores de API
│   │   │   ├── database/   # Conexiones a bases de datos
│   │   │   ├── storage/    # Servicios de almacenamiento
│   │   │   └── ...
│   │   └── ...
│   └── media-processor/    # Procesamiento avanzado de contenido multimedia (Rust)
│       ├── src/            # Código fuente
│       │   ├── handlers/   # Manejadores de solicitudes
│       │   ├── models/     # Modelos de datos
│       │   ├── processors/ # Procesadores de medios
│       │   └── ...
│       └── ...
├── shared/                 # Recursos compartidos entre servicios
│   ├── api-specs/          # Especificaciones OpenAPI
│   ├── schemas/            # Esquemas compartidos
│   └── utils/              # Utilidades comunes
└── docs/                   # Documentación específica del backend
```

## Arquitectura de Microservicios

El backend está estructurado siguiendo una arquitectura de microservicios, donde cada servicio tiene responsabilidades específicas:

1. **API Core (NestJS/TypeScript)**: 
   - Núcleo central de la plataforma
   - Gestión de usuarios, autenticación y autorización
   - Lógica de negocio principal (tablones, hilos, posts)
   - Interfaz central para clientes frontend

2. **File Service (Go)**:
   - Gestión eficiente de archivos subidos
   - Validación básica de contenido
   - Almacenamiento seguro en sistemas distribuidos (S3/MinIO)
   - Entrega optimizada de archivos a usuarios

3. **Media Processor (Rust)**:
   - Procesamiento avanzado de imágenes y contenido multimedia
   - Generación de miniaturas de alta calidad
   - Validación profunda de seguridad de archivos
   - Optimización de medios para diferentes contextos

## Flujo de Comunicación

Los servicios se comunican entre sí mediante:
- API REST para operaciones síncronas
- Cola de mensajes para operaciones asíncronas

## Tecnologías Principales

### Bases de Datos y Almacenamiento
- **PostgreSQL**: Base de datos principal para todos los datos estructurados
- **Redis**: Caché, sesiones y colas de tareas
- **MinIO/S3**: Almacenamiento de objetos para archivos subidos

### Frameworks y Lenguajes
- **NestJS/TypeScript**: Para el API Core
- **Go/Gin**: Para el File Service
- **Rust/Actix**: Para el Media Processor

## Desarrollo

Cada servicio tiene su propio README con instrucciones específicas de ejecución, pero en general:

```bash
# Para API Core
cd services/api-core
npm install
npm run start:dev

# Para File Service
cd services/file-service
go mod download
go run main.go

# Para Media Processor
cd services/media-processor
cargo build
cargo run
```

Para desarrollo local, se recomienda usar Docker Compose para levantar todos los servicios.

## Aspectos de Seguridad

- Todos los servicios implementan validación de entrada estricta
- Autenticación JWT con rotación de tokens
- Validación de archivos en múltiples capas
- Auditoría completa de acciones administrativas

## Documentación Adicional

Consulta el directorio `/docs` para documentación detallada sobre:
- Arquitectura general
- Comunicación entre servicios
- Esquemas de base de datos
- Guías de implementación específicas