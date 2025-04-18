# File Service - Servicio de Gestión de Archivos 4chan v2

Este servicio, implementado en Go, es responsable de la gestión eficiente y segura de archivos en la plataforma 4chan v2. Maneja la subida, almacenamiento, distribución y gestión del ciclo de vida de todos los archivos del sistema.

## Responsabilidades Principales

- **Gestión de Subidas**: Validación y procesamiento de archivos subidos
- **Almacenamiento Seguro**: Integración con soluciones de almacenamiento distribuido
- **Gestión de Metadatos**: Registro y mantenimiento de información sobre archivos
- **Distribución de Contenido**: Entrega optimizada de archivos a usuarios
- **Políticas de Retención**: Gestión del ciclo de vida de los archivos

## Estructura del Proyecto

```
file-service/
├── config/                # Configuración y variables de entorno
├── internal/              # Código interno de la aplicación
│   ├── api/               # Controladores y middlewares de API
│   │   ├── handlers/      # Manejadores de solicitudes HTTP
│   │   ├── middleware/    # Middleware (autenticación, logs, etc.)
│   │   ├── models/        # Modelos de datos para la API
│   │   └── router.go      # Configuración de rutas
│   ├── database/          # Conexiones a bases de datos (PostgreSQL, Redis)
│   ├── storage/           # Servicios de almacenamiento (MinIO/S3)
│   └── utils/             # Utilidades y helpers
└── main.go                # Punto de entrada de la aplicación
```

## Características Clave

- **Alto Rendimiento**: Optimizado para manejar grandes volúmenes de archivos simultáneamente
- **Validación Multicapa**: Seguridad reforzada para prevenir ataques vía archivos
- **Almacenamiento Distribuido**: Utiliza MinIO compatible con S3 para escalabilidad
- **Cache Eficiente**: Implementación de estrategias de caché para contenido frecuente
- **Metadata Extensible**: Sistema flexible para almacenar y recuperar metadatos de archivos

## Tecnologías

- **Go**: Lenguaje de alto rendimiento y bajo consumo de recursos
- **Gin**: Framework web de alto rendimiento para Go
- **MinIO Client**: SDK para almacenamiento compatible con S3
- **PostgreSQL**: Almacenamiento de metadatos (a través de pgx)
- **Redis**: Caché y limitación de tasa

## Capacidades de Procesamiento

- **Validación de Formato**: Verificación de tipos MIME y estructura de archivo
- **Detección de Malware**: Integración con servicios de escaneo (opcional)
- **Cálculo de Hashes**: MD5, SHA256 para verificación e identificación
- **Metadatos de Imágenes**: Extracción de dimensiones y otra información
- **Optimización**: Compresión y optimización automática según configuración

## Endpoints de API

- `POST /api/v1/files/upload`: Subir nuevo archivo
- `GET /api/v1/files/{fileId}`: Obtener metadatos de archivo
- `GET /api/v1/files/{fileId}/content`: Descargar contenido de archivo
- `GET /api/v1/files/{fileId}/thumbnail`: Obtener miniatura de archivo
- `POST /api/v1/files/check`: Verificar existencia por hash
- `DELETE /api/v1/files/{fileId}`: Eliminar archivo (acceso restringido)

## Ejecutar en Desarrollo

```bash
# Configurar variables de entorno
cp .env.example .env
# Editar .env con las configuraciones necesarias

# Instalar dependencias
go mod download

# Ejecutar la aplicación
go run main.go

# Pruebas
go test ./...
```

## Integración con otros Servicios

El File Service se comunica con:

- **API Core**: Recibe solicitudes para operaciones de archivos
- **Media Processor**: Delega procesamiento avanzado de imágenes y media
- **MinIO/S3**: Para almacenamiento persistente de archivos
- **PostgreSQL**: Para almacenamiento de metadatos
- **Redis**: Para caché y limitación de tasa

## Seguridad y Rendimiento

Este servicio implementa múltiples capas de seguridad:

- **Validación de Contenido**: Verificación de tipo MIME y estructura
- **Limitación de Tamaño**: Restricciones configurables por tipo de archivo
- **Escaneo de Seguridad**: Posibilidad de integración con servicios de antivirus
- **Tokens Firmados**: URLs firmadas para acceso a archivos
- **Políticas de CORS**: Control preciso de dominios permitidos

El diseño de alto rendimiento permite:

- **Subidas Paralelas**: Manejo eficiente de múltiples subidas simultáneas
- **Distribución Geográfica**: Soporte para CDN y almacenamiento distribuido
- **Escalabilidad Horizontal**: Capacidad para escalar según la demanda