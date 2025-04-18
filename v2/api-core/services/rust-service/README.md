# Microservicios de Rust para 4chan v2

Este directorio contiene los microservicios implementados en Rust para la plataforma 4chan v2, especializados en el procesamiento de alta performance y seguridad crítica de archivos.

## Componentes Principales

- **Procesador de Archivos**: Validación avanzada y procesamiento seguro de archivos
- **Generador de Miniaturas**: Creación eficiente de miniaturas con múltiples tamaños
- **Detector de Contenido**: Verificación y validación de contenido mediante algoritmos optimizados

## Estructura del Proyecto

```
rust/
├── src/                   # Código fuente
│   ├── config.rs          # Configuración y variables de entorno
│   ├── error.rs           # Manejo centralizado de errores
│   ├── handlers/          # Manejadores de solicitudes
│   │   └── files.rs       # Lógica de manejo de archivos
│   ├── middleware/        # Middleware (autenticación, logs, etc.)
│   ├── models/            # Modelos de datos
│   │   └── file.rs        # Modelos relacionados con archivos
│   ├── repositories/      # Acceso a datos y almacenamiento
│   │   └── s3_repository.rs # Cliente para S3/MinIO
│   ├── routes/            # Definición de rutas de API
│   │   └── files.rs       # Rutas para operaciones de archivos
│   └── main.rs            # Punto de entrada de la aplicación
└── Cargo.toml             # Definición de dependencias
```

## Características Clave

- **Seguridad Zero-Copy**: Procesamiento de archivos sin copias innecesarias en memoria
- **Validación Criptográfica**: Verificación de integridad mediante hashes múltiples
- **Procesamiento Asíncrono**: Arquitectura no bloqueante para máxima eficiencia
- **Detección de Malware**: Integración potencial con escáneres de malware
- **Alto Rendimiento**: Optimizado para operaciones intensivas de CPU

## Tecnologías

- **Actix Web**: Framework web de alto rendimiento
- **Tokio**: Runtime asíncrono
- **AWS SDK for Rust**: Cliente para S3/MinIO
- **Image-rs**: Biblioteca de procesamiento de imágenes
- **SIMD**: Instrucciones vectoriales para procesamiento optimizado

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

## Servicios REST Expuestos

El servicio expone los siguientes endpoints principales:

- `POST /api/v1/files/upload`: Subir y procesar un archivo
- `GET /api/v1/files/{fileId}`: Obtener información de un archivo
- `GET /api/v1/files/{fileId}/content`: Obtener el contenido de un archivo
- `GET /api/v1/files/{fileId}/thumbnail`: Obtener la miniatura optimizada

## Rendimiento y Seguridad

Este servicio está diseñado para manejar cargas intensivas de procesamiento de archivos con garantías de seguridad. Utiliza las características de Rust para prevenir errores de memoria y condiciones de carrera, mientras mantiene un rendimiento comparable a C/C++ para el procesamiento de imágenes y validación.