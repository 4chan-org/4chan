# Documentación Técnica 4chan

## 1. Introducción

Este documento proporciona una documentación técnica detallada del proyecto 4chan, un imageboard anónimo lanzado en 2003. La documentación tiene como objetivo facilitar la migración por fases del sistema, siguiendo las recomendaciones del análisis forense y propuesta de modernización presentada en el documento "soyspeedygonzales.md".

### 1.1 Propósito

El propósito de este documento es:
- Proporcionar una comprensión técnica profunda de la arquitectura actual
- Identificar componentes clave y sus interrelaciones
- Establecer una hoja de ruta para la migración gradual
- Documentar patrones de diseño y decisiones arquitectónicas

### 1.2 Alcance

Esta documentación abarca todos los componentes principales del sistema 4chan, incluyendo:
- Arquitectura general y estructura de directorios
- Sistemas de base de datos y almacenamiento
- Componentes frontend y backend
- Mecanismos de seguridad y autenticación
- Sistemas de caché y optimización

## 2. Arquitectura General

### 2.1 Visión General

4chan utiliza una arquitectura monolítica tradicional de aplicación PHP, con separación parcial en componentes y un patrón MVC básico. El sistema está diseñado para manejar alto tráfico con bajo consumo de recursos, utilizando un enfoque de base de datos centralizada con optimizaciones como memcached.

### 2.2 Tecnologías Principales

- **Backend**: PHP (versiones antiguas)
- **Base de datos**: MySQL
- **Frontend**: HTML/CSS/JavaScript
- **Caché**: Memcached
- **Servidor web**: No especificado explícitamente, presumiblemente Apache o Nginx

### 2.3 Estructura de Directorios

- **/config/**: Configuraciones en archivos .ini
  - **/boards/**: Configuración por tablón
  - **/categories/**: Configuración por categoría
- **/lib/**: Bibliotecas y funcionalidades principales
- **/views/**: Plantillas de visualización
- **/css/**: Estilos y temas
- **/js/**: JavaScript
- **/plugins/**: Sistema de plugins
- **/wordfilters/**: Filtros de palabras
- **/modes/**: Modos de operación
- **/forms/**: Formularios

### 2.4 Componentes Principales

- **Sistema de configuración**: Basado en INI (yotsuba_config.php)
- **Motor de renderizado**: Para hilos e imágenes (imgboard.php)
- **Sistema de captcha**: (captcha.php)
- **Sistema de publicación**: Gestión de posts (imgboard.php)
- **Administración**: (admin.php, admin-test.php)
- **API JSON**: (json.php)
- **Sistema de catálogo**: (catalog.php)
- **Autenticación**: (auth.php)
- **Sistema de pases**: (signin.php)

## 3. Sistema de Base de Datos

### 3.1 Estructura

4chan utiliza MySQL con dos implementaciones de conexión:
- **db.php**: Implementación antigua con funciones mysql_* (ahora obsoletas)
- **db_pdo.php**: Implementación más moderna con PDO

El sistema mantiene dos contextos de conexión:
- **Global**: Para configuración, usuarios, y datos compartidos
- **Por tablón**: Cada tablón tiene sus propias tablas con la misma estructura

### 3.2 Patrones de Acceso a Datos

- **Conexión dual**: Manejo de dos conexiones separadas:
  - `$gcon` para consultas globales (autenticación, configuración)
  - `$con` para consultas específicas de tableros
- **Reintentos de conexión**: Sistema para gestionar errores de conexión
- **Prepared statements**: En la versión PDO para mitigar SQLi
- **Escape automático**: Con funciones auxiliares
- **Transacciones**: Sistema de bloqueo/desbloqueo de tablas
- **Registro de consultas**: Capacidad para registrar y cronometrar consultas SQL

### 3.3 Tablas Principales

No se especifica completamente en el código analizado, pero se pueden inferir las siguientes tablas:
- **Tablas de tablones** (como `b`, `pol`, etc.): Almacenan posts
- **boardlist**: Mapeo de directorios de tablón a nombres y bases de datos
- **PASS_USERS**: Usuarios con pases

## 4. Frontend

### 4.1 Organización de la Estructura Frontend

El frontend de 4chan está estructurado siguiendo un enfoque modular con separación clara entre CSS, JavaScript y plantillas PHP:

#### Estructura de Directorios
- **/css/**: Contiene todos los archivos de estilos (temas visuales)
- **/js/**: Contiene los scripts JavaScript para la interactividad
- **/views/**: Contiene las plantillas PHP para la generación de HTML
- **/imgtop/**: Contiene recursos para las partes superiores del sitio

#### Sistema de Generación
- El sistema utiliza PHP para renderizar las páginas server-side
- Los archivos principales como `imgboard.php` contienen las funciones de renderizado
- Las vistas generadas se almacenan como archivos HTML estáticos

### 4.2 Sistema de Temas y Visualización

4chan ofrece múltiples temas visuales que pueden ser seleccionados por el usuario:

#### Temas Principales
- **Yotsuba**: Tema claro con tonos amarillos
- **Yotsuba B**: Variante azul de Yotsuba
- **Futaba**: Tema clásico rosa/rojizo
- **Burichan**: Tema en tonos azules
- **Tomorrow**: Tema oscuro moderno
- **Photon**: Tema minimalista claro

#### Variantes de Visualización
- Versiones móviles
- Versiones para catálogo
- Soporte para temas especiales temporales o eventos

### 4.3 JavaScript

El sistema frontend utiliza muy pocas dependencias externas, optando por código personalizado:

#### Scripts Principales
- **core.js**: Funcionalidad central y utilidades básicas
- **catalog.js**: Manejo específico de la vista de catálogo
- **extension.js**: Funcionalidades extendidas y opcionales
- **tcaptcha.js**: Implementación personalizada del sistema CAPTCHA

#### Funcionalidades Dinámicas
- **Sistema de Tooltip**: Implementado en `Tip` en core.js
- **Filtros de contenido**: Permite filtrar hilos en tiempo real
- **Quick Reply**: Sistema para respuestas rápidas
- **Thread Watcher**: Para seguir hilos específicos
- **Oekaki** (dibujo): Sistema para dibujar imágenes

## 5. Patrones de Diseño y Estructuras de Control

### 5.1 Patrones de Renderizado y Generación de HTML

- **Patrón de Vista Parcial**: Los archivos en `/views/` contienen lógica de renderizado de interfaz
- **Renderizado por Funciones**: Funciones específicas para cada tipo de contenido
- **Inyección de Plantillas**: El contenido generado se inyecta en variables (`$dat`)
- **Composición de Página**: El renderizado se divide en partes que se combinan
- **Generación Condicional**: Uso de condiciones para determinar qué renderizar
- **Caché HTML**: Generación de archivos HTML estáticos para mejorar rendimiento

### 5.2 Sistema de Gestión de Archivos/Imágenes

- **Validación Extensiva**: Verificación de tipo, tamaño, dimensiones y contenido
- **Detección de Formato**: Análisis de cabeceras para verificar tipos MIME
- **Sistema de Miniaturas**: Generación automática de versiones reducidas
- **Procesamiento Especializado**: Manejo diferenciado según tipo de archivo
- **Oekaki (Dibujo)**: Sistema integrado para dibujo
- **Almacenamiento Estructurado**: Organización basada en tableros
- **Eliminación Programada**: Sistema para eliminar archivos antiguos o reportados

### 5.3 Patrones de Seguridad y Autenticación

- **Sistema de Pases**: Mecanismo de autenticación premium
- **Limitación de Intentos**: Control de intentos fallidos
- **Timeouts de Sesión**: Caducidad automática de sesiones
- **CAPTCHA**: Sistema integrado para prevenir spam
- **Permisos por Niveles**: Diferentes niveles de acceso
- **Flags de Capacidad**: Sistema para habilitar funciones específicas
- **Verificación de IP**: Validación y seguimiento de IPs
- **Purificación HTML**: Uso de HTMLPurifier para sanitizar la entrada
- **Detección de País**: Integración con GeoIP

### 5.4 Mecanismos de Caché

- **Caché de Datos**: Almacenamiento en memoria de información frecuente
- **Caché HTML**: Generación de archivos HTML estáticos
- **Rebuildd**: Demonio dedicado para regenerar páginas
- **Caché Adaptativa**: Ajuste de intervalos según actividad
- **Invalidación Selectiva**: Actualización de contenido modificado
- **Reconstrucción Periódica**: Sistema para reconstruir el catálogo
- **Caché de Consultas**: Almacenamiento de resultados frecuentes

## 6. Deficiencias Técnicas Identificadas

Según el análisis forense documentado en "soyspeedygonzales.md", se identificaron las siguientes deficiencias:

### 6.1 Seguridad

- **Vulnerabilidades críticas**: En Ghostscript y PHP obsoleto (pre-7.x)
- **Validación inadecuada**: Sin verificación criptográfica ni validación MIME adecuada
- **Dependencias desactualizadas**: CVEs críticos sin parchear
- **Modelo de permisos básico**: Sistema binario sin segregación de responsabilidades

### 6.2 Arquitectura y Rendimiento

- **Ausencia de monitorización**: MTTD superior a 12 horas
- **Frontend obsoleto**: Pre-HTML5, uso de tablas para layout (Lighthouse Score: 34/100)
- **Búsqueda limitada**: Solo en títulos, sin indexación completa
- **API limitada**: JSON read-only sin versionado ni autenticación
- **Comunicación ineficiente**: Mediante polling en lugar de tiempo real
- **Despliegue monolítico**: En VMs con configuración manual
- **Backups inseguros**: Sin verificación de integridad

## 7. Plan de Modernización

### 7.1 Propuestas Arquitectónicas

- **Seguridad multicapa**: Con validación criptográfica
- **CI/CD**: Con auditoría automática de dependencias
- **Observabilidad integrada**: Con OpenTelemetry
- **Micro-frontends**: Con Web Components
- **API Gateway**: Con soporte REST/GraphQL
- **Comunicación tiempo real**: WebSockets/SSE
- **Framework RBAC**: Con zero-trust architecture
- **Infraestructura como código**: Contenedores y Kubernetes
- **Preservación digital**: Con replicación geográfica

### 7.2 Componentes a Reescribir

- **Pipeline de validación de archivos**
- **Frontend completo** (migración a HTML5)
- **Sistema de búsqueda**
- **Capa de API e integraciones**
- **Sistemas de moderación y control de acceso**
- **Infraestructura de comunicación**
- **Plataforma de gestión de identidad**
- **Sistema de respaldo y archivado**

### 7.3 Tecnologías Recomendadas

- **Contenedores OCI** con Kubernetes y service mesh
- **GitOps** para gestión declarativa
- **OpenTelemetry** para instrumentación
- **Elasticsearch** para búsqueda avanzada
- **Web Components** para micro-frontends
- **OAuth2/OIDC** para autenticación
- **WebSockets/SSE** para comunicación en tiempo real
- **Apache Kafka/RabbitMQ** como broker de mensajes
- **IA/ML** para moderación asistida

## 8. Estrategia de Migración por Fases

Basándose en el análisis técnico, se recomienda una migración progresiva que preserve la funcionalidad existente mientras se modernizan componentes individuales:

### Fase 1: Fundación y Seguridad

1. **Actualización de dependencias críticas** (PHP, bibliotecas)
2. **Implementación de observabilidad básica**
3. **Containerización de la aplicación existente**
4. **Establecer pipeline CI/CD básico**
5. **Reforzar la seguridad de autenticación**

### Fase 2: Modernización del Backend

1. **Refactorización de la capa de datos** (PDO completo)
2. **Implementación de API RESTful versionada**
3. **Desarrollo de sistema de autenticación moderno**
4. **Actualización del pipeline de procesamiento de archivos**
5. **Implementación de caché distribuida**

### Fase 3: Modernización del Frontend

1. **Desarrollo de nuevos componentes UI**
2. **Implementación de sistema de temas responsive**
3. **Integración de comunicación en tiempo real**
4. **Mejora de accesibilidad y rendimiento**
5. **Desarrollo de vistas adaptativas**

### Fase 4: Características Avanzadas

1. **Sistema de búsqueda avanzada**
2. **Moderación asistida por IA**
3. **Mecanismos de reputación anónima**
4. **Plataforma de notificaciones**
5. **Ampliación de API para integraciones**

### Fase 5: Infraestructura Empresarial

1. **Implementación completa de Kubernetes**
2. **Orquestación avanzada con service mesh**
3. **Sistema de recuperación de desastres**
4. **Monitorización y alertas avanzadas**
5. **Optimización final de rendimiento**

## 9. Conclusiones

El proyecto 4chan representa un sistema legacy con una arquitectura diseñada para eficiencia y rendimiento en una era anterior del desarrollo web. A pesar de sus limitaciones técnicas, el sistema demuestra patrones de diseño efectivos para gestionar alto tráfico con recursos limitados.

La migración propuesta busca mantener estos principios de eficiencia mientras moderniza la arquitectura para abordar las deficiencias de seguridad, mantenibilidad y escalabilidad. El enfoque por fases permite una transición gradual que minimiza el riesgo y mantiene la funcionalidad durante todo el proceso.

Para lograr una migración exitosa, será necesario un equipo multidisciplinario con experiencia en:
- Ingeniería de seguridad
- Arquitectura de sistemas distribuidos
- Desarrollo frontend moderno
- Ingeniería de datos
- DevOps y automatización
- IA/ML para sistemas de moderación

Este documento proporciona la base técnica para iniciar este proceso de migración y debe actualizarse continuamente a medida que se completen las fases.

---

Documentación preparada como parte del análisis técnico para la migración del sistema 4chan.