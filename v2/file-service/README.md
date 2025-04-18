# File Service

Servicio de gestión de archivos implementado en Go, especializado en validación segura, transformación y almacenamiento eficiente de archivos.

## Características

- Validación exhaustiva de archivos para detectar contenido malicioso
- Transformación y optimización de imágenes (redimensionamiento, compresión)
- Generación de thumbnails y previsualizaciones
- Almacenamiento eficiente en S3/MinIO 
- Integración con el servicio media-processor para análisis avanzado
- Alta performance y baja latencia

## Estructura del Proyecto

```
file-service/
├── config/
│   └── config.go        # Configuración del servicio
├── internal/
│   ├── api/             # API HTTP
│   │   ├── handlers/    # Manejadores de peticiones
│   │   ├── models/      # Modelos de datos para la API
│   │   └── router.go    # Definición de rutas
│   ├── database/        # Conexiones a bases de datos
│   │   ├── postgres.go  # Cliente PostgreSQL
│   │   └── redis.go     # Cliente Redis
│   ├── models/          # Modelos de dominio
│   ├── storage/         # Almacenamiento de archivos
│   │   └── minio.go     # Cliente S3/MinIO
│   └── utils/           # Utilidades
├── main.go              # Punto de entrada
└── go.mod               # Dependencias
```

## Tecnologías

- Go 1.20+
- PostgreSQL (metadatos de archivos)
- Redis (caché)
- S3/MinIO (almacenamiento de archivos)
- Fiber (framework web)
- GORM (ORM)

## Desarrollo

```bash
# Configuración de entorno
cp .env.example .env

# Iniciar servicio en modo desarrollo
go run main.go

# Ejecutar tests
go test ./...

# Compilar para producción
go build -o file-service
```

## API

El servicio expone una API REST con los siguientes endpoints principales:

- `POST /files` - Subir un archivo nuevo
- `GET /files/:id` - Obtener un archivo por ID
- `GET /files/:id/thumbnail` - Obtener thumbnail de un archivo
- `DELETE /files/:id` - Eliminar un archivo

## Integración con Servicios

- Se comunica con `media-processor` para análisis avanzado de contenido
- Expone métricas para monitoreo en Prometheus
- Utiliza tracing distribuido con OpenTelemetry