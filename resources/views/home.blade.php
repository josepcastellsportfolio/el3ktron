<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>El3ktron · Backend de un almacén de implantaciones</title>
<style>
  :root { color-scheme: light dark; --fg: #1c2430; --muted: #5d6978; --line: #d5dde6; --accent: #2f6f9f; --bg: #fff; }
  @media (prefers-color-scheme: dark) { :root { --fg: #e6ebf1; --muted: #9aa8b8; --line: #3a4757; --accent: #9fc3e0; --bg: #141b24; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.55 system-ui, sans-serif; }
  main { max-width: 46rem; margin: 0 auto; padding: 4rem 1.5rem; }
  h1 { margin: 0; font-size: 2.25rem; letter-spacing: -0.02em; }
  h2 { margin: 2.5rem 0 0.75rem; font-size: 0.8rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--accent); }
  p, li { color: var(--muted); }
  strong { color: var(--fg); font-weight: 600; }
  a { color: var(--accent); }
  ul { padding-left: 1.2rem; }
  li { margin-bottom: 0.4rem; }
  .links { display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; padding: 0; list-style: none; }
  .links a { font-weight: 600; }
  table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
  td { padding: 0.5rem 0; border-bottom: 1px solid var(--line); color: var(--muted); vertical-align: top; }
  td:first-child { padding-right: 1rem; color: var(--fg); }
  code { font-size: 0.9em; }
</style>
</head>
<body>
<main>
  <h1>El3ktron</h1>
  <p>Backend en Laravel para el almacén que prepara <strong>implantaciones</strong>: los expositores y lineales,
     con su surtido, que un distribuidor de ferretería y material eléctrico monta en las tiendas de sus clientes.
     Proyecto de portfolio con datos sintéticos.</p>

  <ul class="links">
    @if ($docsUrl)
      <li><a href="{{ $docsUrl }}">Documentación de la API</a></li>
    @endif
    <li><a href="{{ url('/api/v1/health') }}">Estado del sistema</a></li>
    <li><a href="https://github.com/josepcastellsportfolio/el3ktron">Código en GitHub</a></li>
  </ul>

  <h2>Qué resuelve</h2>
  <table>
    <tr><td>Picking en ruta en S</td><td><strong>−29,7 %</strong> de metros frente a la lista por ubicación</td></tr>
    <tr><td>Disponibilidad y sustitutas antes de reservar</td><td>Implantaciones incompletas del <strong>73 % al 5 %</strong></td></tr>
    <tr><td>Sincronización con el ERP por RabbitMQ</td><td>Marca las implantaciones afectadas y avisa si falla o se para</td></tr>
    <tr><td>Pedidos escritos por personas</td><td>Un extractor propone el borrador y una persona lo aprueba</td></tr>
  </table>
  <p>Cifras de un benchmark sintético reproducible (<code>php artisan el3ktron:bench</code>), explicado en el README.</p>

  <h2>Stack</h2>
  <p>PHP 8.4, Laravel 13, MariaDB 11, RabbitMQ 4, nginx y PHP-FPM en Docker. API REST v1 con tokens Sanctum.
     Pest, Larastan nivel 6 y Pint en GitHub Actions.</p>
</main>
</body>
</html>
