# 0002. Ninguna integración falla en silencio

Fecha: 2026-11-02. Estado: aceptada.

## Contexto

El fallo caro de una integración no es el que lanza una excepción: es el que no hace nada. El cron que se
borró, el worker que murió, el ERP que cambió un precio y nadie avisó a quien estaba montando el expositor.

## Decisión

- Toda sincronización deja un `SyncRun` (empezó, terminó, cuántas filas, error).
- Tres alertas distintas, con causas distintas:
  - `sync_failed`: el job agotó sus reintentos (`failed()` del job).
  - `sync_stale`: no hay una sincronización correcta reciente, por la causa que sea. La comprueba un
    comando independiente del job, porque si el job no corre, tampoco puede avisar.
  - `catalog_change`: un precio o un estado cambió y hay implantaciones activas que lo usan.
- Las alertas se deduplican por clave mientras están abiertas, y `sync_stale` y `sync_failed` se cierran
  solas cuando el problema desaparece.
- `/api/v1/health` expone la edad de la última sincronización correcta para la monitorización externa.

## Consecuencias

- Una fila mala se reporta (`sync_invalid_rows`) pero no para las demás.
- El canal (log, email) es secundario: la alerta existe en la base de datos aunque el email falle.
