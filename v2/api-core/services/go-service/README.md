# Microservicios de Go para 4chan v2

Este directorio contiene los microservicios implementados en Go para la plataforma 4chan v2, especializados en el manejo eficiente de archivos y operaciones que requieren alto rendimiento.

## Componentes Principales

- **Servicio de Archivos**: Validación, procesamiento y almacenamiento de archivos subidos
- **Servicio de Caché**: Gestión de caché para contenido frecuentemente accedido
- **API Gateway**: Enrutamiento y balanceo de carga para los diferentes servicios

## Estructura del Proyecto

```
go/
├── config/                 # Configuración y variables de entorno
├── internal/               # Código interno de la aplicación
│   ├── api/                # Controladores y middlewares de API
│   │   ├── handlers/       # Manejadores de solicitudes HTTP
│   │   ├── middleware/     # Middleware (autenticación, logs, etc.)
│   │   ├── models/         # Modelos de datos para la API
│   │   └── router.go       # Configuración de rutas
│   ├── database/           # Conexiones a bases de datos (PostgreSQL, Redis)
│   ├── storage/            # Servicios de almacenamiento (MinIO/S3)
│   └── utils/              # Utilidades y helpers
└── main.go                 # Punto de entrada de la aplicación
```

## Características Clave

- **Procesamiento de Archivos Seguro**: Validación multi-capa para prevenir ataques
- **Alta Eficiencia**: Diseñado para manejar grandes volúmenes de subida de archivos
- **Escalabilidad Horizontal**: Arquitectura que permite escalar con facilidad
- **Monitorización Integrada**: Exportación de métricas Prometheus

## Tecnologías

- **Gin**: Framework HTTP de alto rendimiento
- **pgx**: Driver nativo de PostgreSQL optimizado
- **MinIO Client**: Cliente para almacenamiento de objetos compatible con S3
- **JWT**: Para autenticación y autorización
- **Redis**: Para caché y limitación de tasa

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

## Servicios REST Expuestos

El servicio expone los siguientes endpoints principales:

- `POST /api/v1/files/upload`: Subir un archivo
- `GET /api/v1/files/{fileId}`: Obtener información de un archivo
- `GET /api/v1/files/{fileId}/content`: Obtener el contenido de un archivo
- `GET /api/v1/files/{fileId}/thumbnail`: Obtener la miniatura de un archivo

Para más detalles, consulte la documentación Swagger disponible en modo desarrollo en `/api/v1/swagger/index.html`.