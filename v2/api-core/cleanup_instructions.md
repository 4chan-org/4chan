# Instrucciones para limpiar estructura de directorios

Una vez que hayas verificado que los nuevos servicios tienen el contenido correcto, puedes eliminar los directorios redundantes con estos comandos:

```bash
# Verificar primero que las nuevas carpetas de servicios tienen el contenido correcto
ls -la /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/api-core/
ls -la /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/file-service/
ls -la /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/media-processor/

# Si todo está correcto, puedes eliminar las carpetas originales redundantes
rm -rf /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/nodejs
rm -rf /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/go
rm -rf /Users/oscarvalois/Documents/GitHub/4chan/v2/api-core/services/rust

# Y por último, eliminar los directorios de api-core redundantes
rm -rf /Users/oscarvalois/Documents/GitHub/4chan/v2/backend_go
rm -rf /Users/oscarvalois/Documents/GitHub/4chan/v2/backend_rust
```

La nueva estructura optimizada será:

```
v2/
├── api/                   # (Mantener si tiene algo útil adicional)
├── backend/
│   ├── docs/              # Documentación técnica
│   ├── services/          # Implementaciones por funcionalidad
│   │   ├── api-core/      # API principal y lógica de negocio (NestJS)
│   │   ├── file-service/  # Servicio de gestión de archivos (Go)
│   │   └── media-processor/ # Procesador de contenido multimedia (Rust)
│   └── shared/            # Recursos compartidos entre servicios
│       ├── api-specs/     # Especificaciones OpenAPI
│       ├── schemas/       # Esquemas compartidos
│       └── utils/         # Utilidades comunes
├── frontend/              # Frontend actual
├── frontend_new/          # Nuevo frontend
└── infrastructure/        # Infraestructura como código
```

Esta estructura:

1. Organiza los servicios según su función en el sistema, no por lenguaje
2. Hace que sea más fácil entender el propósito de cada componente
3. Facilita la colaboración entre equipos con responsabilidades claras
4. Mejora la modularidad y mantenibilidad del sistema
5. Proporciona una base sólida para el desarrollo futuro

Los nombres de los servicios ahora describen claramente su propósito:

- **api-core**: El núcleo central de la plataforma
- **file-service**: Encargado de la gestión de archivos
- **media-processor**: Especializado en procesamiento avanzado de medios