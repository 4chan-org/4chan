# API Specifications

Este directorio contiene las definiciones OpenAPI (anteriormente conocidas como Swagger) que especifican las interfaces de programación de la aplicación.

## Estructura

- **openapi.yaml**: Documento principal que integra todas las especificaciones
- **boards.yaml**: Endpoints relacionados con tablones
- **threads.yaml**: Endpoints relacionados con hilos y posts
- **files.yaml**: Endpoints relacionados con gestión de archivos
- **captcha.yaml**: Endpoints para verificación CAPTCHA
- **moderator.yaml**: Endpoints para herramientas de moderación

## Uso

Estas especificaciones sirven para:

1. **Documentación**: Proporcionar documentación clara y completa de las APIs
2. **Generación de código**: Generar clientes y stubs de servidor automáticamente
3. **Testing**: Crear pruebas basadas en la especificación
4. **Validación**: Validar solicitudes y respuestas contra el esquema

## Herramientas

Puedes utilizar las siguientes herramientas para trabajar con estas especificaciones:

- [Swagger UI](https://swagger.io/tools/swagger-ui/): Visualización interactiva
- [Redoc](https://github.com/Redocly/redoc): Documentación generada
- [OpenAPI Generator](https://openapi-generator.tech/): Generación de código
- [Postman](https://www.postman.com/): Testing de API

## Desarrollo

Al modificar las especificaciones:

1. Mantén la compatibilidad hacia atrás cuando sea posible
2. Utiliza referencias ($ref) para reutilizar componentes
3. Documenta cada propiedad y parámetro
4. Incluye ejemplos de uso
5. Especifica los códigos de error que pueden ocurrir

## Estructura de Endpoints

Las APIs siguen la siguiente estructura general:

- `/api/v1/boards`: Acceso a tablones
- `/api/v1/boards/{boardId}/threads`: Acceso a hilos
- `/api/v1/threads/{threadId}/posts`: Acceso a posts
- `/api/v1/files`: Gestión de archivos
- `/api/v1/captcha`: Verificación CAPTCHA
- `/api/v1/mod`: Herramientas de moderación

## Versionado

Las APIs están versionadas en el path (v1, v2, etc.) para permitir evolucionar la API sin romper la compatibilidad con clientes existentes.