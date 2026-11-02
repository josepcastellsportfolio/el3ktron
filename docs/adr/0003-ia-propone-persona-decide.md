# 0003. La IA propone, una persona decide

Fecha: 2026-11-02. Estado: aceptada.

## Contexto

Leer pedidos escritos a mano es una tarea manual que un LLM hace bien casi siempre. «Casi siempre» no
basta para mover stock o facturar.

## Decisión

- `OrderExtractor` es una interfaz con dos implementaciones: reglas (sin IA) y LLM. Se elige por
  configuración.
- El LLM solo extrae líneas en JSON. No ve la base de datos ni ejecuta nada. Su salida se valida: una
  línea sin cantidad positiva o sin identificador se descarta.
- Toda extracción acaba en un `OrderDraft` pendiente. Aprobar exige una persona autenticada y que todas
  las líneas estén casadas con un producto; si no, 422 y el revisor corrige.
- Lo mismo vale para las sustitutas de una implantación: se proponen, pero no se reserva stock hasta que
  alguien confirma la revisión.
- La calidad se mide (`el3ktron:eval-extractor`) sobre pedidos etiquetados, no a ojo.

## Consecuencias

- El extractor por reglas es el punto de partida y la línea base: 100 % de precisión, 77 % de recall.
  Lo que no lee es texto libre, que es justo donde el LLM debe ganarse su coste.
