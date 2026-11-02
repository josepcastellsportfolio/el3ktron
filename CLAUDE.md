# CLAUDE.md

Backend Laravel 13 de un almacén de implantaciones. Lee el README y docs/adr antes de cambiar nada.

## Reglas para la IA

- Nada se fusiona sin que una persona lo lea. Propón el cambio; no lo des por hecho.
- Todo cambio de lógica lleva su test. Antes de proponer un cambio, deben pasar:
  `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`.
- No toques `docs/BENCHMARK.md` a mano: se regenera con `php artisan el3ktron:bench --write`, y si cambia,
  el PR explica por qué.
- No inventes cifras ni datos de empresas reales. Todo lo medido es sintético y lo dice.
- No leas ni escribas `.env`. Las claves (ANTHROPIC_API_KEY) solo por entorno.
- El LLM extrae; nunca ejecuta, nunca escribe en la base de datos (docs/adr/0003).
- Una integración nueva deja rastro y avisa cuando falla o deja de correr (docs/adr/0002).

## Convenciones

- Código, comentarios y commits en inglés; README y ADRs en español.
- Lógica medible: pura, sin Eloquent, compartida con el benchmark (docs/adr/0001).
- Errores de estado: `InstallationException` → 409. Validación → 422.
- Sin carga perezosa de relaciones fuera de producción (`Model::preventLazyLoading`).
