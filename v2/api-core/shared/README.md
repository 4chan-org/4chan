# Recursos Compartidos para Backend 4chan v2

Este directorio contiene recursos y definiciones compartidas entre los diferentes servicios del backend de 4chan v2.

## Estructura

```
shared/
├── api-specs/          # Especificaciones OpenAPI (Swagger)
│   ├── boards.yaml     # Definición de API de tablones
│   ├── captcha.yaml    # Definición de API de captcha
│   ├── files.yaml      # Definición de API de archivos
│   ├── moderator.yaml  # Definición de API de moderación
│   ├── openapi.yaml    # Definición principal de API
│   └── threads.yaml    # Definición de API de hilos
├── schemas/            # Esquemas compartidos
│   ├── dto/            # Objetos de transferencia de datos
│   └── types/          # Definiciones de tipos comunes
└── utils/              # Utilidades compartidas
    ├── security/       # Utilidades de seguridad
    ├── validation/     # Funciones de validación
    └── formatting/     # Utilidades de formato
```

## Especificaciones API (OpenAPI)

Las definiciones OpenAPI proporcionan una especificación formal de las APIs que implementan los diferentes servicios. Estas definiciones sirven como contrato entre servicios y son utilizadas para:

1. Generar documentación interactiva (Swagger UI)
2. Generar clientes en diferentes lenguajes
3. Validar implementaciones de API
4. Pruebas automatizadas

## Esquemas Compartidos

Este directorio contiene esquemas y tipos comunes utilizados por múltiples servicios. Mantener estas definiciones en un lugar centralizado asegura la consistencia entre implementaciones en diferentes lenguajes.

## Utilidades Compartidas

Funciones comunes que pueden ser reutilizadas por diferentes servicios, como:

- Validación de archivos
- Funciones de seguridad
- Formateo de datos

## Uso desde Servicios

Cada servicio debe incluir estos recursos como submódulos o mediante otro mecanismo de compartición de código, asegurando que todos los servicios utilicen las mismas definiciones y versiones.

## Proceso de Actualización

Al modificar recursos compartidos:

1. Crear una nueva rama para los cambios
2. Actualizar la documentación si es necesario
3. Revisar el impacto en los diferentes servicios
4. Solicitar revisión por parte de otros desarrolladores
5. Actualizar la versión según sea necesario siguiendo SemVer (MAJOR.MINOR.PATCH)

Los cambios que rompen compatibilidad deben incrementar el número MAJOR y ser comunicados a todos los equipos.