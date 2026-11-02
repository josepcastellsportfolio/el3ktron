# El3ktron

Backend en Laravel para el almacén que prepara **implantaciones**: los expositores y lineales, con su
surtido, que un distribuidor de ferretería y material eléctrico monta en las tiendas de sus clientes.

Es un proyecto de portfolio con **datos sintéticos**. El distribuidor, el catálogo y los números son
inventados. Los problemas no: un catálogo que llega de un ERP y cambia, stock que no alcanza, un mozo
que recorre el almacén y pedidos que llegan escritos por personas.

| | |
|---|---|
| Stack | PHP 8.4, Laravel 13, MariaDB 11, RabbitMQ 4, nginx + PHP-FPM, Docker |
| API | REST v1 con tokens Sanctum, OpenAPI generado con Scramble ([`docs/openapi.json`](docs/openapi.json)) |
| Calidad | 41 tests Pest (SQLite en local, MariaDB en CI), Larastan nivel 6, Pint, CI en GitHub Actions |

## Qué resuelve, y cuánto

Cada mejora se mide contra una alternativa sencilla, sobre los mismos datos, con el mismo código que
sirve la API. `php artisan el3ktron:bench` regenera [docs/BENCHMARK.md](docs/BENCHMARK.md), y el CI
falla si el resultado deja de ser reproducible.

| Problema | Qué hace El3ktron | Resultado (sintético, semilla 42) |
|---|---|---|
| El mozo recorre el almacén de más | Lista de picking en ruta en S | **−29,7 % de metros** frente a la lista por ubicación; −82,6 % frente al orden de catálogo |
| Una implantación sale incompleta y hace falta un segundo envío | Comprueba la disponibilidad antes de fijar fecha y propone sustitutas | Implantaciones incompletas: **del 73 % al 5 %** |
| Un precio cambia o un producto se descataloga en el ERP y nadie se entera | La sincronización marca las implantaciones afectadas y crea una alerta | Probado en los tests y en el stack de Docker |
| Los pedidos llegan por email, escritos a mano | Un extractor los convierte en borradores que una persona aprueba | Reglas: precisión 100 %, recall 77,3 % en 8 pedidos etiquetados |

Los euros del benchmark son una fórmula con supuestos que se cambian con opciones del comando, no una
promesa. Están todos al final de [docs/BENCHMARK.md](docs/BENCHMARK.md).

## Las cuatro piezas

**1. Sincronización del catálogo** ([`app/Catalog`](app/Catalog), [`app/Jobs/SyncCatalog.php`](app/Jobs/SyncCatalog.php))
- Lee la exportación del ERP (CSV) detrás de una interfaz `ErpFeed`, para cambiarla por una API sin tocar el resto.
- Es idempotente: cada fila se compara por su hash, así que la misma exportación dos veces no escribe nada.
- Una fila mala se salta y se reporta; no tumba las otras.
- Corre en la cola (RabbitMQ) cada 15 minutos, con 3 reintentos y backoff. Si se rinde, alerta crítica.
- Aparte, `el3ktron:check-sync-freshness` alerta si no ha habido una sincronización correcta en 60 minutos,
  sea cual sea la causa (worker caído, cola atascada, cron borrado), y cierra la alerta sola cuando vuelve.

**2. Disponibilidad y sustitutas** ([`app/Installations`](app/Installations))
- Antes de reservar, cada producto del planograma está *ok*, *sustituido* (misma familia, activo, con stock,
  el más cercano en precio) o *falta*.
- Si hay sustitutas o faltas, la implantación queda marcada y **no se puede reservar hasta que una persona lo confirma**.
- La reserva es todo o nada, con `SELECT … FOR UPDATE`: dos implantaciones no se llevan las mismas unidades.

**3. Lista de picking** ([`app/Warehouse`](app/Warehouse))
- Ruta en S (sube un pasillo, baja el siguiente), medida con la misma función de distancia que las alternativas.
- La API devuelve la lista ordenada y los metros frente al orden de catálogo.

**4. Pedidos escritos por personas** ([`app/Orders`](app/Orders))
- `RuleBasedExtractor` (sin IA) lee pedidos con SKU o EAN. `LlmExtractor` (API de Anthropic) lee texto libre.
- Los dos solo proponen: el resultado es un borrador pendiente. La API no deja aprobarlo mientras haya
  líneas sin producto. El revisor las corrige y aprueba.
- `php artisan el3ktron:eval-extractor --extractor=rules|llm` mide precisión y recall sobre
  [pedidos etiquetados](tests/Fixtures/orders). El extractor con LLM no está medido en este repositorio:
  necesita una clave de API.

**Alertas** ([`app/Alerts/AlertService.php`](app/Alerts/AlertService.php)): se guardan (la API las lista),
se registran y se envían por email si hay dirección. La misma alerta abierta no se repite: un fallo cada
5 minutos manda un correo, no 288.

## Arrancarlo

```bash
cp .env.example .env
docker compose up -d --build        # nginx :8080, MariaDB, RabbitMQ (panel :15672), worker y scheduler
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed --force   # imprime un token de API
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" localhost:8080/api/v1/products
```

Si los puertos están ocupados: `EL3KTRON_HTTP_PORT=8095 EL3KTRON_RABBITMQ_UI_PORT=15673 docker compose up -d`.
La documentación de la API está en `/docs/api` con `APP_ENV=local`.

Sin Docker, con PHP 8.4 y Composer: `composer install && vendor/bin/pest`.

## API

| Método | Ruta | Para qué |
|---|---|---|
| GET | `/api/v1/health` | Base de datos, última sincronización, alertas críticas abiertas (sin token) |
| GET | `/api/v1/products` | Catálogo con stock libre; filtros `family`, `status`, `q`, `per_page` |
| GET | `/api/v1/products/{sku}` | Un producto |
| POST | `/api/v1/installations` | Crear implantación (`store`, `planogram`, `scheduled_for`) |
| POST | `/api/v1/installations/{id}/check` | Comprobar disponibilidad y proponer sustitutas |
| POST | `/api/v1/installations/{id}/confirm-review` | Una persona acepta sustitutas y faltas |
| POST | `/api/v1/installations/{id}/reserve` | Reservar stock (todo o nada; 409 si no se puede) |
| GET | `/api/v1/installations/{id}/picking-list` | Lista en ruta, con metros frente al orden de catálogo |
| GET, POST | `/api/v1/alerts`, `/alerts/{id}/resolve` | Alertas abiertas y cerrarlas |
| POST, GET, PUT | `/api/v1/order-drafts[/{id}]` | Leer un pedido, verlo, corregir líneas |
| POST | `/api/v1/order-drafts/{id}/approve`, `/reject` | Decisión de la persona que revisa |
| POST, GET | `/api/v1/sync/catalog`, `/sync/runs` | Lanzar una sincronización, ver las últimas |

Errores: 401 sin token, 404, 409 cuando el estado no lo permite, 422 de validación. 120 peticiones por
minuto por token.

## Decisiones

En [docs/adr](docs/adr):
1. [La lógica que se mide es pura y la comparten la API y el benchmark](docs/adr/0001-logica-pura-compartida.md)
2. [Ninguna integración falla en silencio](docs/adr/0002-integraciones-que-avisan.md)
3. [La IA propone, una persona decide](docs/adr/0003-ia-propone-persona-decide.md)
4. [Benchmark sintético y honesto](docs/adr/0004-benchmark-sintetico.md)

## Lo que no es

- No son datos de ninguna empresa. Las tasas de rotura, el almacén y los costes son supuestos editables.
- La ruta en S es la heurística estándar del picking manual, no una ruta óptima.
- La sustituta es una regla (misma familia, precio más cercano). En un caso real la decidiría el equipo de producto.
- No hay interfaz: es el backend que consumirían la web, las apps de los comerciales y los integradores.

## Cómo se ha hecho

Con Claude Code, siguiendo las reglas de [CLAUDE.md](CLAUDE.md): todo cambio pasa por tests, Larastan y
Pint, y lo revisa una persona antes de fusionarlo.
