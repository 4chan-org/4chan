# Documentación del Backend de 4chan v2

Este directorio contiene documentación técnica y de diseño para el backend de 4chan v2.

## Contenido

- [Arquitectura](architecture.md): Visión general de la arquitectura del sistema
- [Integración de Servicios](service-integration.md): Cómo se comunican los diferentes servicios
- [Guías de Implementación](implementation-guides/): Guías específicas por servicio
- [Seguridad](security.md): Consideraciones y prácticas de seguridad
- [API](api.md): Documentación general de la API
- [Base de Datos](database.md): Esquema y relaciones de la base de datos
- [Operaciones](operations.md): Guías de operación y mantenimiento

## Arquitectura General

El backend de 4chan v2 está diseñado como una arquitectura de microservicios, donde cada servicio tiene responsabilidades bien definidas:

1. **API Principal (Node.js/NestJS)**:
   - Gestión central de datos
   - Lógica de negocio principal
   - Autenticación y autorización

2. **Servicio de Archivos (Go)**:
   - Gestión eficiente de cargas de archivos
   - Almacenamiento en S3/MinIO
   - Validación básica de archivos

3. **Procesamiento de Archivos (Rust)**:
   - Procesamiento seguro de alto rendimiento
   - Generación avanzada de miniaturas
   - Validación profunda de seguridad

## Comunicación entre Servicios

La comunicación entre servicios se realiza mediante:

- **API REST**: Para operaciones síncronas
- **Mensajería asíncrona**: Para operaciones en segundo plano
- **Recursos compartidos**: Definiciones compartidas para mantener la consistencia

## Bases de Datos y Almacenamiento

El sistema utiliza múltiples tecnologías de almacenamiento:

- **PostgreSQL**: Base de datos relacional principal
- **Redis**: Caché y gestión de sesiones
- **MinIO/S3**: Almacenamiento de objetos para archivos
- **ElasticSearch** (opcional): Para búsqueda avanzada

## Convenciones de Código

Cada servicio sigue las convenciones idiomáticas del lenguaje en que está implementado, pero todos comparten algunos principios básicos:

- Código limpio y bien documentado
- Pruebas automatizadas
- Gestión de errores consistente
- Logging estructurado
- Validación estricta de entrada

## Desarrollo, Pruebas y Despliegue

Para instrucciones detalladas sobre:
- Configuración del entorno de desarrollo
- Ejecución de pruebas
- Proceso de CI/CD
- Despliegue en producción

Consulte la documentación específica de cada servicio en su directorio correspondiente.

## Actualizaciones de Documentación

Esta documentación debe actualizarse cuando:
- Se implementan nuevas características
- Se cambian interfaces de API
- Se modifican esquemas de base de datos
- Se introducen nuevos servicios o componentes

Fecha de última actualización: Abril 2025