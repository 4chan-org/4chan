# Media Processor - Procesador de Contenido Multimedia 4chan v2

Este servicio, implementado en Rust, es responsable del procesamiento avanzado de contenido multimedia para la plataforma 4chan v2. Se especializa en operaciones de alta seguridad y rendimiento relacionadas con la validación, transformación y optimización de imágenes y otros formatos de medios.

## Responsabilidades Principales

- **Procesamiento de Imágenes**: Generación de miniaturas, optimización y transformaciones
- **Validación de Seguridad**: Verificación profunda de la integridad y seguridad de los archivos
- **Detección de Contenido**: Análisis avanzado de imágenes y medios
- **Optimización de Medios**: Recodificación y optimización para diferentes contextos de uso
- **Metadata Avanzada**: Extracción y manejo de metadatos multimedia

## Estructura del Proyecto

```
media-processor/
├── src/                   # Código fuente
│   ├── config.rs          # Configuración y variables de entorno
│   ├── error.rs           # Manejo centralizado de errores
│   ├── handlers/          # Manejadores de solicitudes
│   │   └── files.rs       # Lógica de procesamiento de archivos
│   ├── middleware/        # Middleware (autenticación, logs, etc.)
│   ├── models/            # Modelos de datos
│   │   └── file.rs        # Modelos relacionados con archivos
│   ├── repositories/      # Acceso a datos y almacenamiento
│   │   └── s3_repository.rs # Cliente para S3/MinIO
│   ├── routes/            # Definición de rutas de API
│   │   └── files.rs       # Rutas para operaciones de archivos
│   ├── processors/        # Lógica de procesamiento de medios
│   │   ├── image.rs       # Procesamiento de imágenes
│   │   ├── video.rs       # Procesamiento de video
│   │   └── security.rs    # Validaciones de seguridad
│   └── main.rs            # Punto de entrada de la aplicación
└── Cargo.toml             # Definición de dependencias
```

## Características Clave

- **Rendimiento Superior**: Implementado en Rust para máxima eficiencia y seguridad de memoria
- **Procesamiento Paralelo**: Utilización eficiente de núcleos múltiples
- **Seguridad Sin Compromisos**: Validación exhaustiva y manejo seguro de memoria
- **Optimización Avanzada**: Algoritmos especializados para diferentes tipos de contenido
- **Bajo Consumo de Recursos**: Diseñado para alta eficiencia con mínimo uso de RAM

## Tecnologías

- **Rust**: Lenguaje de programación seguro y de alto rendimiento
- **Actix Web**: Framework web asíncrono de alto rendimiento
- **Image-rs**: Biblioteca nativa de procesamiento de imágenes
- **FFmpeg**: Para procesamiento de video (vía bindings)
- **AWS SDK for Rust**: Cliente para S3/MinIO
- **Tokio**: Runtime asíncrono de alto rendimiento

## Capacidades de Procesamiento

- **Miniaturas Avanzadas**: Generación inteligente con preservación de aspectos clave
- **Validación Profunda**: Análisis completo de la estructura de archivos
- **Detección de Manipulación**: Identificación de alteraciones potencialmente maliciosas
- **Eliminación de Metadatos**: Limpieza de información sensible en EXIF y otros formatos
- **Optimización por Tipo**: Tratamiento especializado según formato de archivo

## Endpoints de API

- `POST /api/v1/media/process`: Procesar un archivo multimedia
- `POST /api/v1/media/validate`: Realizar validación profunda de seguridad
- `POST /api/v1/media/thumbnails`: Generar múltiples variantes de miniaturas
- `POST /api/v1/media/optimize`: Optimizar un archivo para reducir tamaño
- `POST /api/v1/media/analyze`: Analizar y extraer metadatos de un archivo

## Ejecutar en Desarrollo

```bash
# Configurar variables de entorno
cp .env.example .env
# Editar .env con las configuraciones necesarias

# Compilar en modo desarrollo
cargo build

# Ejecutar la aplicación
cargo run

# Pruebas
cargo test
```

## Integración con otros Servicios

El Media Processor se comunica con:

- **File Service**: Recibe solicitudes para procesamiento avanzado de archivos
- **API Core**: Envía notificaciones sobre estado de procesamiento
- **MinIO/S3**: Para acceso y almacenamiento de archivos

## Rendimiento y Seguridad

Este servicio está diseñado con los más altos estándares de rendimiento y seguridad:

- **Memory Safety**: Garantías de seguridad de memoria garantizadas en tiempo de compilación
- **Error Handling**: Manejo exhaustivo de errores sin posibilidad de estados indefinidos
- **Concurrency**: Modelo de concurrencia seguro sin condiciones de carrera
- **Resource Management**: Uso eficiente de CPU y memoria incluso bajo carga alta

## Ventajas del Uso de Rust

- **Seguridad**: Prevención de errores comunes como buffer overflows y use-after-free
- **Rendimiento**: Velocidad comparable a C/C++ sin sacrificar seguridad
- **Concurrencia**: Modelo avanzado para operaciones paralelas seguras
- **Fiabilidad**: Garantías del sistema de tipos para prevenir estados indefinidos
- **Mantenibilidad**: Compilador estricto que previene clases enteras de errores