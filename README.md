![4chan-1-2021-800-3992876958](https://github.com/user-attachments/assets/b05f4584-04e0-47fd-baf4-6269d0024bf4)


This is the original source code powering 4chan, an anonymous imageboard launched in 2003. The codebase was designed to be lightweight, fast, and easy to deploy on minimal hosting resources. It draws heavy inspiration from Japanese imageboards such as Futaba Channel (2chan), and was initially written in PHP with a MySQL backend.

Debemos aprender a **desaprender**: no es una contradicción, sino un acto de reparación interna. En un mundo que avanza tan rápido como el nuestro, muchas enseñanzas se vuelven obsoletas y nos limitan. Solo al soltar lo aprendido podemos abrirnos al crecimiento auténtico.

Hace falta **ideas nuevas** que nazcan de nuestro sentir profundo. Cuando las costumbres se han vuelto códigos rígidos y las rutinas parecen guiones escritos de antemano, solo la creatividad genuina puede liberarnos del encierro de la repetición.

No fui un usuario activo de 4chan, pero reconozco que la libertad de expresión—y los espacios que la fomentan—son esenciales. Cada mente alberga una chispa de sabiduría: no inauguré este foro para cargar con la responsabilidad de lo que allí ocurra, sino para ofrecer un lugar donde cada cabeza encuentre su propia respuesta.

—Verso Terso

## Proyecto de Modernización

El directorio `v2/` contiene la modernización completa con arquitectura de microservicios y micro-frontends.

### Carpetas Principales en v2/

- **api-specs**: Especificaciones OpenAPI
- **api-core**: Núcleo de la API (TypeScript/NestJS)
- **file-service**: Servicio de gestión de archivos (Go)
- **media-processor**: Procesador de medios (Rust)
- **frontend-legacy**: Frontend React tradicional
- **frontend-modern**: Frontend React avanzado
- **microfrontends**: Implementación de micro-frontends
- **docs**: Documentación técnica
- **infrastructure**: Configuración de infraestructura

## Seguridad

**IMPORTANTE**: Antes de iniciar el desarrollo:

1. **Nunca** uses los valores predeterminados en archivos `.env.example` en producción
2. **Siempre** cambia todas las contraseñas, secretos y claves de API
3. Todos los valores sensibles están marcados con `[REDACTED]` y deben ser reemplazados