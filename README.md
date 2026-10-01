# DatAR — economía argentina en datos

Sitio en PHP (sin frameworks) con indicadores y gráficos interactivos a partir de dos fuentes públicas, sin token:

- **BCRA** (azul): [APIs públicas del Banco Central](https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp).
- **INDEC** (violeta): series del INDEC publicadas en la [API de Series de Tiempo de datos.gob.ar](https://datos.gob.ar/series/api). El INDEC no tiene API propia.

Publicado en https://datar.revelos-software.com

## Secciones

| Página | Contenido |
|---|---|
| `index.php` | Panel: 12 indicadores con variación y minigráfico, dólar + bandas cambiarias, reservas, inflación |
| `cambio.php` | Cotizaciones de ~50 monedas, conversor, historial por moneda, mayorista vs. minorista |
| `monetario.php` | Base monetaria / reservas (la página original), cobertura, agregados M1–M3, depósitos, préstamos |
| `tasas.php` | Tasas de referencia, depósitos y préstamos; rendimiento real del plazo fijo vs. inflación |
| `precios.php` | Inflación mensual y acumulada, UVA/ICL/dólar en base 100, calculadora de actualización |
| `explorador.php` | Buscador de las ~1600 series del BCRA: comparar hasta 4, base 100, CSV, enlace compartible |
| `actividad.php` (INDEC) | EMAE, actividad por sector, industria (IPI), construcción (ISAC), capacidad instalada, PIB |
| `precios-salarios.php` (INDEC) | IPC por tipo de precio, rubro y región; salario real; líneas de pobreza e indigencia |
| `trabajo-comercio.php` (INDEC) | Desocupación, informalidad, Gini, distribución del ingreso, comercio exterior, términos del intercambio, turismo |

Las series del INDEC que usa el sitio están listadas en `INDEC_SERIES` ([lib/indec.php](lib/indec.php)); para agregar una, sumala ahí con su id de datos.gob.ar.

Todos los gráficos tienen selector de rango, tooltip, tabla y descarga CSV, y funcionan en tema claro/oscuro.

## Estructura

```
lib/bcra.php          Cliente de la API del BCRA con caché en disco (cache/, 6 h)
lib/indec.php         Series del INDEC (datos.gob.ar) y sus transformaciones
api.php               Endpoint JSON que usan los gráficos (serie, cotizacion, variables)
assets/app.js         Utilidades de gráficos (Chart.js 4)
assets/app.css        Estilos
partials/             Header, footer y tarjetas de indicadores
actualizar_cache.php  Precarga la caché (para cron, si se usa un hosting con PHP)
build.php             Genera la versión estática en dist/ (GitHub Pages)
.github/workflows/    Publica en GitHub Pages en cada push y cada 4 horas
```

## Publicación en GitHub Pages

GitHub Pages no ejecuta PHP, así que una GitHub Action corre `php build.php`, que:

1. descarga las series del BCRA y del INDEC que usan las páginas y las guarda como JSON en `dist/data/`,
2. renderiza cada página PHP a `.html`,
3. publica `dist/` en Pages.

Se ejecuta en cada push a `master`, cada 4 horas (para actualizar los datos) y a mano desde *Actions → Publicar en GitHub Pages → Run workflow*.
En la versión estática, las series que no están pregeneradas (explorador, otras monedas) se piden directo a la API del BCRA desde el navegador.

Configuración única: *Settings → Pages → Build and deployment → Source: **GitHub Actions***.

Para probar la versión estática en local:

```bash
php build.php && php -S localhost:8081 -t dist
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
