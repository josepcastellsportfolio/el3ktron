# 0004. Benchmark sintético y honesto

Fecha: 2026-11-02. Estado: aceptada.

## Contexto

No hay datos reales de un almacén. Un número inventado sin decirlo sería peor que no tener número.

## Decisión

- Los datos se generan con una semilla fija (`Mt19937`): misma semilla, mismos números. El CI lo comprueba.
- Cada mejora se compara con una alternativa explícita. Para la ruta hay dos: el orden de catálogo
  (peor caso) y el orden por ubicación (la referencia justa). La cifra que se cita es la segunda.
- Los supuestos (tamaño del almacén, tasas de rotura, coste por hora, coste de un envío) son opciones del
  comando y salen impresos junto al resultado.
- Los euros son una fórmula, nunca una promesa.

## Consecuencias

- Con datos reales, el mismo comando sirve cambiando el generador por una lectura de la base de datos.
- Una primera versión con tasas de rotura del 6 % daba un 100 % de implantaciones incompletas: era cierto
  con esos supuestos, pero inútil. Se bajaron a valores defendibles y se dejaron como opciones.
