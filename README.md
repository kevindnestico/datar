# DatAR — economía argentina con la API del BCRA

Sitio en PHP (sin frameworks) que consume las [APIs públicas del Banco Central](https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp) y muestra indicadores y gráficos interactivos. No requiere token.

## Secciones

| Página | Contenido |
|---|---|
| `index.php` | Panel: 12 indicadores con variación y minigráfico, dólar + bandas cambiarias, reservas, inflación |
| `cambio.php` | Cotizaciones de ~50 monedas, conversor, historial por moneda, mayorista vs. minorista |
| `monetario.php` | Base monetaria / reservas (la página original), cobertura, agregados M1–M3, depósitos, préstamos |
| `tasas.php` | Tasas de referencia, depósitos y préstamos; rendimiento real del plazo fijo vs. inflación |
| `precios.php` | Inflación mensual y acumulada, UVA/ICL/dólar en base 100, calculadora de actualización |
| `explorador.php` | Buscador de las ~1600 series del BCRA: comparar hasta 4, base 100, CSV, enlace compartible |

Todos los gráficos tienen selector de rango, tooltip, tabla y descarga CSV, y funcionan en tema claro/oscuro.

## Estructura

```
lib/bcra.php          Cliente de la API con caché en disco (cache/, 6 h)
api.php               Endpoint JSON que usan los gráficos (serie, cotizacion, variables)
assets/app.js         Utilidades de gráficos (Chart.js 4)
assets/app.css        Estilos
partials/             Header, footer y tarjetas de indicadores
actualizar_cache.php  Precarga la caché (para cron)
```

## Correr en local

Requiere PHP 8.1+ con la extensión curl.

```bash
PHP_CLI_SERVER_WORKERS=8 php -S localhost:8080
```

La API del BCRA puede tardar 10–20 s en devolver una serie completa la primera vez. En producción conviene un cron:

```
0 */4 * * * php /ruta/al/sitio/actualizar_cache.php
```

La carpeta `cache/` tiene que poder escribirla el usuario del servidor web.
