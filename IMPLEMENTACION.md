# Implementación de la API de Archivos para 4chan v2

Este documento detalla la implementación de la API de archivos para la modernización de 4chan, utilizando tanto Go como Rust para proporcionar un servicio eficiente y seguro de gestión de archivos.

## Especificación de API

La API de archivos implementa los siguientes endpoints según la especificación OpenAPI:

- `POST /files/upload` - Subir un archivo nuevo
- `GET /files/{fileId}` - Obtener información de un archivo
- `DELETE /files/{fileId}` - Eliminar un archivo (requiere autenticación)
- `GET /files/{fileId}/content` - Obtener el contenido binario de un archivo
- `GET /files/{fileId}/thumbnail` - Obtener la miniatura de un archivo
- `POST /files/check` - Verificar si un archivo con un hash MD5 ya existe
- `GET /files/banned` - Obtener una lista de hashes de archivos prohibidos
- `GET /files/stats` - Obtener estadísticas de archivos (requiere autenticación)
- `POST /files/admin/purge` - Purgar archivos antiguos (requiere autenticación de administrador)

## Implementación en Go

### Estructura del Código

La implementación en Go sigue una arquitectura en capas:

1. **Handlers (API)**: Implementados en `internal/api/handlers/file_handler.go`
   - Gestiona las solicitudes HTTP
   - Valida las entradas
   - Coordina las operaciones de servicio

2. **Modelos**: Definidos en `internal/api/models/file.go`
   - Define estructuras de datos para requests/responses
   - Implementa validaciones

3. **Servicios de Almacenamiento**: Implementados en `internal/storage/minio.go`
   - Abstrae las operaciones de almacenamiento
   - Gestiona la comunicación con MinIO (compatible con S3)

### Características de Seguridad

- Validación de tipos MIME
- Limitación de tamaño de archivos
- Generación y verificación de hashes MD5
- Generación de nombres de archivo únicos
- Autenticación mediante JWT para operaciones protegidas

### Procesamiento de Imágenes

- Detección de dimensiones de imágenes
- Generación de miniaturas (se simula en la implementación actual)
- Soporte para marcar imágenes como spoiler

## Implementación en Rust

### Estructura del Código

La implementación en Rust sigue una arquitectura similar pero con diferencias idiomáticas:

1. **Handlers**: Implementados en `src/handlers/files.rs`
   - Gestionan solicitudes HTTP usando Actix Web
   - Implementan validación y lógica de negocio

2. **Modelos**: Definidos en `src/models/file.rs`
   - Usan anotaciones de Serde para serialización/deserialización
   - Incluyen documentación incorporada

3. **Repositorios**: Implementados en `src/repositories/s3_repository.rs`
   - Abstraen operaciones de almacenamiento
   - Utilizan AWS SDK de Rust para S3

4. **Rutas**: Definidas en `src/routes/files.rs`
   - Configuran los endpoints de la API
   - Definen middleware de autenticación por endpoint

### Características de Seguridad

- Manejo de autenticación mediante middleware de JWT
- Autorización basada en roles
- Validación de entrada asíncrona
- Control de tamaño de carga útil
- Generación segura de URLs prefirmadas

### Rendimiento y Concurrencia

- Uso de operaciones asíncronas con Tokio
- Manejo eficiente de streams de bytes
- Procesamiento paralelo cuando es posible

## Ventajas de Cada Implementación

### Go

1. **Simplicidad**: Código más directo y fácil de entender
2. **Herramientas de Desarrollo**: Entorno de desarrollo maduro
3. **Biblioteca Estándar**: Amplio soporte para HTTP, JSON, etc.
4. **Rendimiento**: Buen equilibrio entre velocidad de ejecución y tiempo de desarrollo

### Rust

1. **Seguridad de Memoria**: Garantías en tiempo de compilación
2. **Manejo de Errores**: Sistema de tipos que obliga a manejar todos los casos de error
3. **Rendimiento**: Excelente rendimiento sin garbage collector
4. **Concurrencia Segura**: Prevención de condiciones de carrera en tiempo de compilación

## Consideraciones de Implementación

### Almacenamiento

Ambas implementaciones utilizan almacenamiento compatible con S3 (MinIO) para:

1. **Escalabilidad**: Facilita la distribución geográfica
2. **Durabilidad**: Replicación automática de datos
3. **Gestión de Acceso**: Políticas granulares de acceso
4. **Integración**: Fácil integración con CDN para mejor rendimiento

### Validación de Archivos

Se implementa un pipeline de validación para:

1. **Verificación de Tipo**: Asegura que solo se acepten tipos permitidos
2. **Detección de Malware**: Infraestructura para integración con escaneo antivirus
3. **Validación de Tamaño**: Previene abusos por archivos demasiado grandes
4. **Deduplicación**: Detección de archivos duplicados mediante hashes

### Generación de Miniaturas

En una implementación completa, se añadiría:

1. **Redimensionamiento Adaptativo**: Múltiples tamaños para diferentes contextos
2. **Procesamiento Asíncrono**: Cola de trabajo para procesamiento en segundo plano
3. **Optimización de Imágenes**: Compresión inteligente según tipo y uso
4. **Almacenamiento en Caché**: CDN y caché distribuida para miniaturas

## Próximos Pasos

1. **Implementar Base de Datos**: Integrar con PostgreSQL para metadata de archivos
2. **Sistema de Eventos**: Implementar publicación de eventos para procesamiento asíncrono
3. **Monitoreo y Alertas**: Añadir métricas detalladas y alertas
4. **Pruebas de Carga**: Evaluar rendimiento bajo carga pesada
5. **Integración con Moderación**: Conectar con sistema de moderación para detección automática de contenido problemático

## Consideraciones de Seguridad Adicionales

1. **Escaneo de Malware**: Integración con ClamAV u otras soluciones
2. **Análisis de Imágenes**: Detección de contenido prohibido mediante ML
3. **Límites por IP/Usuario**: Prevención de abuso
4. **Autenticación Multifactor**: Para acciones administrativas
5. **Auditoría de Acceso**: Registro detallado de todas las operaciones sensibles

Esta implementación establece las bases para un sistema seguro, escalable y eficiente de gestión de archivos que cumple con los requisitos de la modernización de 4chan.