# API Core - Backend Principal de 4chan v2

Este directorio contiene el núcleo de la API para la plataforma 4chan v2, desarrollado con NestJS. Este servicio actúa como el componente central del backend, gestionando la lógica de negocio principal, la autenticación y las interacciones con la base de datos.

## Responsabilidades Principales

- **Gestión de Usuarios**: Autenticación, autorización y administración de perfiles
- **Operaciones de Tablones**: Creación, configuración y gestión de tablones
- **Publicación de Contenido**: Hilos, posts y gestión de contenido
- **Moderación**: Herramientas para moderadores y administradores
- **Persistencia de Datos**: Interacción con la base de datos principal

## Estructura del Proyecto

```
api-core/
├── prisma/                # ORM y esquema de base de datos
│   └── schema.prisma      # Definición del esquema de datos
├── src/                   # Código fuente
│   ├── app.ts             # Configuración de la aplicación
│   ├── config/            # Configuración y variables de entorno
│   ├── controllers/       # Controladores de API
│   ├── middleware/        # Middleware (autenticación, validación, etc.)
│   ├── models/            # DTOs y modelos de datos
│   ├── services/          # Servicios y lógica de negocio
│   └── utils/             # Utilidades y helpers
├── tests/                 # Pruebas unitarias e integración
└── package.json           # Dependencias y scripts
```

## Características Clave

- **Arquitectura Modular**: Organizada por dominios de negocio
- **API Versionada**: Soporte para múltiples versiones de API
- **Documentación Swagger**: Documentación interactiva automática
- **Validación de Datos**: Validación de entrada robusta con class-validator
- **ORM Avanzado**: Acceso tipo-seguro a base de datos con Prisma

## Tecnologías

- **NestJS**: Framework de Node.js para aplicaciones escalables
- **TypeScript**: Lenguaje tipado para desarrollo seguro
- **Prisma**: ORM moderno para TypeScript
- **PostgreSQL**: Base de datos principal
- **Redis**: Caché, sesiones y limitación de tasa
- **JWT**: Autenticación basada en tokens
- **Swagger**: Documentación automática de API

## Instalación y Desarrollo

```bash
# Configurar variables de entorno
cp .env.example .env
# Editar .env con las configuraciones necesarias

# Instalar dependencias
npm install

# Generar cliente Prisma
npm run prisma:generate

# Ejecutar migraciones
npm run prisma:migrate

# Iniciar en modo desarrollo
npm run dev

# Pruebas
npm run test
```

## Docker

```bash
# Construir imagen
docker build -t 4chan-api-core .

# Ejecutar contenedor
docker run -p 3000:3000 --env-file .env 4chan-api-core
```

## Principales Endpoints

La API expone varios endpoints organizados por dominio:

- `/api/v1/auth/*`: Autenticación y gestión de usuarios
- `/api/v1/boards/*`: Gestión de tablones
- `/api/v1/boards/:boardId/threads/*`: Gestión de hilos
- `/api/v1/boards/:boardId/threads/:threadId/posts/*`: Gestión de posts
- `/api/v1/moderation/*`: Herramientas de moderación

Para una documentación completa, acceda a la interfaz Swagger en `/api/docs` durante el desarrollo.

## Gestión de Base de Datos

El proyecto utiliza Prisma como ORM, con un esquema completo definido en `prisma/schema.prisma`. Incluye modelos para:

- Usuarios, roles y autenticación
- Tablones, categorías y configuraciones
- Hilos y posts
- Archivos y metadatos
- Reportes y moderación
- Filtros de palabras
- Logs de auditoría

## Seguridad

Este backend implementa múltiples capas de seguridad:

- Encriptación de contraseñas con Argon2
- Protección contra ataques de inyección SQL (a través de Prisma)
- Rate limiting para prevenir abusos
- Validación estricta de entrada
- Headers de seguridad con Helmet
- Autenticación mediante JWT con rotación de tokens
- Logs detallados de actividad

## Comunicación con Microservicios

El API Core se comunica con otros servicios especializados:

- **File Service**: Para operaciones de almacenamiento y gestión de archivos
- **Media Processor**: Para procesamiento avanzado de imágenes y contenido multimedia

La comunicación se realiza a través de APIs RESTful y mensajería asíncrona para operaciones que requieren procesamiento en segundo plano.