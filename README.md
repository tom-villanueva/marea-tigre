# Marea Tigre

Sistema de monitoreo en tiempo real del Río de la Plata para San Fernando, Tigre y el Delta.

## Descripción

Marea Tigre es una aplicación web que proporciona información actualizada sobre las condiciones del Río de la Plata, incluyendo:

- **Altura del río en San Fernando**: Datos en tiempo real del Instituto Nacional del Agua (INA) con tendencia (subiendo/bajando/estable)
- **Telemetría Pilote Norden**: Mediciones de altura de marea y viento desde la estación del Servicio de Hidrografía Naval
- **Alertas de Sudestada**: Detección y pronóstico de eventos de sudestada con estimación de picos máximos
- **Pronóstico oficial**: Visualización del pronóstico del INA para San Fernando
- **Contactos de emergencia**: Información de servicios de rescate y asistencia en la zona

## Tecnologías

### Backend

- PHP 8.3+
- Servicios modulares para integración con APIs externas
- Sistema de caché con fallback para alta disponibilidad
- Gestión de datos históricos en JSON

### Frontend

- HTML5 / CSS3
- JavaScript vanilla (sin frameworks)
- Lucide Icons
- Diseño responsive y mobile-first
- PWA (Progressive Web App) con manifest

## Estructura del Proyecto

```
marea-tigre/
├── index.php              # Punto de entrada y router
├── src/                   # Servicios PHP
│   ├── AlertService.php   # Alertas del INA
│   ├── SanFernandoService.php  # Altura en San Fernando
│   ├── TelemetryService.php    # Pilote Norden
│   ├── SudestadaService.php    # Detección de sudestadas
│   ├── HttpClient.php     # Cliente HTTP con caché
│   └── DataManager.php    # Gestión de datos históricos
├── static/                # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── manifest.json
├── templates/             # Templates HTML
│   └── index.html
└── data/                  # Datos históricos (generados)
    ├── alturas_historico.json
    ├── pilote_historico.json
    └── sudestada_actual.json
```

## Instalación

### Requisitos

- PHP 8.3 o superior
- Extensiones PHP: `curl`, `xml`, `json`
- Servidor web (Apache, Nginx, o PHP built-in server)

### Configuración

1. Clonar el repositorio:

```bash
git clone [url-del-repo]
cd marea-tigre
```

2. Asegurar permisos de escritura en el directorio `data/`:

```bash
chmod 755 data/
```

3. Ejecutar servidor de desarrollo:

```bash
php -S localhost:8000
```

4. Acceder a `http://localhost:8000`

## Fuentes de Datos

- **Instituto Nacional del Agua (INA)**: RSS de alturas hidrométricas
- **Servicio de Hidrografía Naval**: Telemetría del Pilote Norden
- **INA**: Pronóstico para San Fernando

## API Endpoints

- `GET /api/alertas` - Alertas vigentes
- `GET /api/altura_sf` - Altura actual en San Fernando con tendencia
- `GET /api/tendencia_sf` - Solo información de tendencia
- `GET /api/telemetria` - Datos de marea y viento del Pilote Norden
- `GET /api/sudestada` - Estado de sudestada y pronóstico

## Características

### Sistema de Caché

- Caché en memoria para reducir llamadas a APIs externas
- Estrategia de fallback con datos históricos
- TTL configurable por servicio

### Detección de Sudestada

- Análisis automático de datos de altura
- Cálculo de picos máximos y horarios
- Estimación de altura en diferentes puntos del río
- Alertas visuales cuando se detecta evento activo

### Historial de Datos

- Almacenamiento de hasta 72 registros históricos
- Usado para cálculo de tendencias
- Persistencia en archivos JSON

## Desarrollo

### Agregar un nuevo servicio

1. Crear clase en `src/NuevoServicio.php`
2. Implementar método público para el endpoint
3. Agregar ruta en `index.php`
4. Crear componente en `static/js/components.js`
5. Actualizar UI en `templates/index.html`

### Estructura de Respuesta API

Todas las APIs devuelven JSON. En caso de error:

```json
{
  "error": "Mensaje descriptivo del error"
}
```

## Licencia

Proyecto de código abierto para la comunidad del Tigre y Delta del Paraná.

## Contacto

Para reportar problemas o sugerencias, crear un issue en el repositorio.
