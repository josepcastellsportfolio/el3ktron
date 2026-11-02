# 0001. La lógica que se mide es pura y la comparten la API y el benchmark

Fecha: 2026-11-02. Estado: aceptada.

## Contexto

El proyecto promete mejoras medibles: menos metros de picking y menos implantaciones incompletas. Si el
benchmark tuviera su propia copia de la lógica, mediría otra cosa que la que sirve la API, y la cifra no
valdría nada.

## Decisión

La ruta (`PickingRoute`), la distancia (`WarehouseLayout`) y la regla de sustitutas (`SubstitutePicker`)
son funciones puras sobre objetos de valor (`Point`, `PickLine`, `Candidate`), sin Eloquent. Los
servicios de la API traducen modelos a esos objetos; el benchmark los genera sintéticos. Los dos llaman al
mismo código.

## Consecuencias

- Se testean sin base de datos y en milisegundos.
- Hay una capa de traducción modelo → valor en `PickingList` y `AvailabilityChecker`.
- Cambiar la regla de sustitutas cambia a la vez la API y el benchmark, que es lo que se busca.
