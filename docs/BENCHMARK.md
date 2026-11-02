# Benchmark

Generado con `php artisan el3ktron:bench --seed=42 --write`. Datos sintéticos:
ninguna cifra sale de una empresa real. Misma semilla, mismos números. Los supuestos
están al final y cada uno se cambia con una opción del comando.

## 1. Ruta de picking

Metros medios por implantación, desde el muelle y de vuelta.

| Orden de la lista | Metros | Ruta en S ahorra |
|---|---:|---:|
| Por SKU, como sale del catálogo (peor caso) | 2.421 m | 1.999 m (82,6 %) |
| Por ubicación, cada pasillo desde el frente (referencia justa) | 600 m | 179 m (29,7 %) |
| **Ruta en S** | **422 m** | — |

La cifra que cuenta es la segunda fila: comparar contra el orden del catálogo
infla el ahorro, porque nadie recorre un almacén en orden de SKU si la lista
ya viene por ubicación.

## 2. Comprobación de disponibilidad

| | Implantaciones que saldrían incompletas |
|---|---:|
| Sin comprobar | 146 de 200 (73,0 %) |
| Comprobando y con sustitutas | 10 de 200 (5,0 %) |

Líneas sustituidas: 286. Líneas sin sustituta: 10.
Las sustitutas son propuestas: la implantación queda marcada hasta que una persona las confirma.

## Euros (una fórmula, no una promesa)

| | Al año |
|---|---:|
| Ruta en S frente a orden por ubicación: 3,7 min × 500 implantaciones × 22 €/h | 682 € |
| Ruta en S frente a orden de catálogo (peor caso): 41,6 min × 500 × 22 €/h | 7.635 € |
| Envíos incompletos evitados: (73,0 % − 5,0 %) × 500 × 35 € | 11.900 € |

La última fila supone que cada implantación incompleta cuesta un envío extra. Si la tienda
acepta recibirla incompleta, el coste es otro (un lineal con huecos), pero no es cero.

## Supuestos

- Almacén: 12 pasillos × 20 huecos, 3,0 m entre pasillos, 1,4 m por hueco, muelle al frente del pasillo 0.
- 2000 productos en 400 familias, una ubicación cada uno, colocados al azar.
- 200 implantaciones de 40 a 120 productos, de 2 a 12 unidades por línea.
- 1,5 % de productos sin stock suficiente, 0,5 % descatalogados.
- Cada implantación se comprueba con el stock entero (no compiten entre ellas por las unidades).
- El tiempo de coger cada línea es el mismo en todos los órdenes: solo se compara el caminar.
- 0,8 m/s con carro, 22 €/h, 35 € por envío extra, 500 implantaciones al año.
