# Media Processor

Servicio avanzado para procesamiento de archivos multimedia implementado en Rust, especializado en análisis forense de imágenes, detección de contenido prohibido y procesamiento de alta performance.

## Características

- Análisis forense de imágenes y archivos multimedia
- Detección avanzada de contenido prohibido mediante ML
- Extracción de metadatos y verificación de integridad
- Procesamiento concurrente de alta performance
- Interfaz gRPC para comunicación eficiente entre servicios
- Endpoints REST para integración con otros sistemas

## Estructura del Proyecto

```
media-processor/
├── src/
│   ├── main.rs             # Punto de entrada
│   ├── config.rs           # Configuración del servicio
│   ├── error.rs            # Manejo centralizado de errores
│   ├── handlers/           # Manejadores de peticiones
│   │   ├── mod.rs          # Módulo handlers
│   │   └── files.rs        # Manejador de archivos
│   ├── middleware/         # Middleware
│   │   └── mod.rs          # Módulo middleware
│   ├── models/             # Modelos de datos
│   │   ├── mod.rs          # Módulo models
│   │   ├── file.rs         # Modelo de archivo
│   │   └── user.rs         # Modelo de usuario
│   ├── repositories/       # Acceso a datos
│   │   ├── mod.rs          # Módulo repositories
│   │   ├── postgres_repository.rs  # Repositorio PostgreSQL
│   │   └── s3_repository.rs        # Repositorio S3
│   └── routes/             # Definición de rutas
│       ├── mod.rs          # Módulo routes
│       └── files.rs        # Rutas para archivos
└── Cargo.toml              # Dependencias y configuración
```

## Tecnologías

- Rust 1.70+
- Actix Web (framework web)
- Tokio (runtime asíncrono)
- PostgreSQL (metadatos)
- S3/MinIO (almacenamiento)
- Redis (caché)
- tonic (gRPC)
- Tensorflow/ONNX Runtime (modelos ML)

## Desarrollo

```bash
# Configuración de entorno
cp .env.example .env

# Desarrollo
cargo run

# Tests
cargo test

# Compilación optimizada
cargo build --release
```

## API

El servicio expone dos interfaces:

### REST API
- `POST /analyze` - Analizar un archivo
- `GET /results/:id` - Obtener resultados de análisis
- `GET /health` - Estado del servicio

### gRPC
- `AnalyzeFile` - Análisis de archivo
- `GetAnalysisResult` - Obtener resultados
- `BatchAnalyze` - Análisis por lotes

## Seguridad

- Validación robusta de entrada
- Análisis exhaustivo contra exploits conocidos
- Aislamiento de procesos para análisis de contenido potencialmente malicioso
- Limitación de recursos y timeouts
- Auditoría completa de operaciones