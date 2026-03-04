<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Chronology API — Documentación</title>

    <link rel="preconnect" href="https://fonts.bunny.net">

    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&family=fira-code:400" rel="stylesheet" />

    <style>

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }



        :root {

            --bg: #0f1117;

            --surface: #181c27;

            --surface2: #1e2333;

            --border: #2a2f42;

            --text: #e2e8f0;

            --muted: #8892a4;

            --accent: #6366f1;

            --get: #22c55e;

            --get-bg: #052e16;

            --post: #3b82f6;

            --post-bg: #0c1a3a;

            --put: #f59e0b;

            --put-bg: #2d1d00;

            --patch: #f97316;

            --patch-bg: #2d1200;

            --delete: #ef4444;

            --delete-bg: #2d0000;

            --code-bg: #0d1117;

            --radius: 8px;

        }



        html { scroll-behavior: smooth; }



        body {

            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;

            background: var(--bg);

            color: var(--text);

            line-height: 1.6;

            min-height: 100vh;

        }



        .wrapper { display: flex; min-height: 100vh; }



        /* ── Sidebar ─────────────────────────────────────────── */

        .sidebar {

            width: 260px;

            min-width: 260px;

            background: var(--surface);

            border-right: 1px solid var(--border);

            padding: 24px 0;

            position: sticky;

            top: 0;

            height: 100vh;

            overflow-y: auto;

        }

        .sidebar-logo {

            padding: 0 20px 20px;

            border-bottom: 1px solid var(--border);

            margin-bottom: 16px;

        }

        .sidebar-logo h1 { font-size: 1.1rem; font-weight: 600; color: var(--text); }

        .sidebar-logo span { font-size: 0.75rem; color: var(--muted); }

        .sidebar-section { padding: 8px 20px 4px; }

        .sidebar-section-label {

            font-size: 0.65rem;

            font-weight: 600;

            letter-spacing: 0.1em;

            text-transform: uppercase;

            color: var(--muted);

            margin-bottom: 4px;

        }

        .sidebar a {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 6px 8px;

            border-radius: 6px;

            font-size: 0.85rem;

            color: var(--muted);

            text-decoration: none;

            transition: background 0.15s, color 0.15s;

            margin-bottom: 1px;

        }

        .sidebar a:hover { background: var(--surface2); color: var(--text); }

        .sidebar a .method-dot {

            width: 7px; height: 7px;

            border-radius: 50%; flex-shrink: 0;

        }



        /* ── Main ──────────────────────────────────────────────── */

        .main { flex: 1; padding: 48px; max-width: 900px; }



        .hero { margin-bottom: 56px; }

        .hero h1 { font-size: 2.25rem; font-weight: 600; margin-bottom: 8px; }

        .hero p { color: var(--muted); font-size: 1rem; max-width: 560px; }

        .base-url-box {

            display: inline-flex;

            align-items: center;

            gap: 12px;

            background: var(--surface);

            border: 1px solid var(--border);

            border-radius: var(--radius);

            padding: 10px 16px;

            margin-top: 20px;

        }

        .base-url-box .label { font-size: 0.75rem; color: var(--muted); font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }



        /* ── Sections ─────────────────────────────────────────── */

        .section { margin-bottom: 60px; scroll-margin-top: 32px; }

        .section-title {

            font-size: 1.35rem; font-weight: 600;

            margin-bottom: 24px; padding-bottom: 12px;

            border-bottom: 1px solid var(--border);

        }



        /* ── Endpoint card ──────────────────────────────────── */

        .endpoint {

            background: var(--surface);

            border: 1px solid var(--border);

            border-radius: var(--radius);

            margin-bottom: 16px;

            overflow: hidden;

            scroll-margin-top: 32px;

        }

        .endpoint-header {

            display: flex; align-items: center; gap: 12px;

            padding: 14px 20px; cursor: pointer; user-select: none;

        }

        .endpoint-header:hover { background: var(--surface2); }

        .endpoint-header::after {

            content: '▸'; font-size: 0.75rem; color: var(--muted);

            margin-left: auto; transition: transform 0.2s;

        }

        .endpoint.open .endpoint-header::after { transform: rotate(90deg); }

        .endpoint-body {

            border-top: 1px solid var(--border);

            padding: 20px; display: none;

        }

        .endpoint.open .endpoint-body { display: block; }

        .endpoint-desc { color: var(--muted); font-size: 0.875rem; margin-bottom: 16px; }



        /* ── Method badges ──────────────────────────────────── */

        .method-badge {

            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;

            padding: 3px 8px; border-radius: 4px;

            font-family: 'Fira Code', ui-monospace, monospace;

            min-width: 60px; text-align: center;

        }

        .method-get    { background: var(--get-bg);    color: var(--get);    border: 1px solid #16502d; }

        .method-post   { background: var(--post-bg);   color: var(--post);   border: 1px solid #1e3a6e; }

        .method-put    { background: var(--put-bg);    color: var(--put);    border: 1px solid #5a3d00; }

        .method-patch  { background: var(--patch-bg);  color: var(--patch);  border: 1px solid #5a2500; }

        .method-delete { background: var(--delete-bg); color: var(--delete); border: 1px solid #5a0000; }



        .endpoint-path {

            font-family: 'Fira Code', ui-monospace, monospace;

            font-size: 0.9rem; color: var(--text); flex: 1;

        }



        /* ── Role badges ────────────────────────────────────── */

        .role-badge {

            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em;

            padding: 2px 8px; border-radius: 999px;

            border: 1px solid var(--border); color: var(--muted);

        }

        .role-public     { color: #a3e635; border-color: #2d4a00; background: #0d1a00; }

        .role-any        { color: var(--accent); border-color: #312e81; background: #0d0c2a; }

        .role-manager    { color: #22d3ee; border-color: #0c393e; background: #021a1d; }

        .role-superadmin { color: #f472b6; border-color: #4a1535; background: #1a0510; }



        /* ── Code blocks ────────────────────────────────────── */

        .code-block {

            background: var(--code-bg); border: 1px solid var(--border);

            border-radius: 6px; padding: 16px; overflow-x: auto; margin-bottom: 16px;

        }

        .code-block pre {

            font-family: 'Fira Code', ui-monospace, monospace;

            font-size: 0.8rem; color: #c9d1d9; white-space: pre; line-height: 1.7;

        }

        code {

            font-family: 'Fira Code', ui-monospace, monospace; font-size: 0.85em;

            background: var(--code-bg); border: 1px solid var(--border);

            border-radius: 4px; padding: 1px 6px; color: #c9d1d9;

        }



        .block-label {

            font-size: 0.72rem; font-weight: 600; letter-spacing: 0.08em;

            text-transform: uppercase; color: var(--muted);

            margin-bottom: 6px; margin-top: 16px;

        }

        .block-label:first-child { margin-top: 0; }



        .header-example {

            background: var(--code-bg); border: 1px solid var(--border);

            border-radius: 6px; padding: 12px 16px;

            font-family: 'Fira Code', ui-monospace, monospace;

            font-size: 0.8rem; color: #c9d1d9; margin-bottom: 16px;

            white-space: pre;

        }



        /* ── Params table ───────────────────────────────────── */

        .params-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin-bottom: 12px; }

        .params-table th {

            text-align: left; padding: 6px 10px;

            font-size: 0.7rem; font-weight: 600; letter-spacing: 0.06em;

            text-transform: uppercase; color: var(--muted);

            border-bottom: 1px solid var(--border);

        }

        .params-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }

        .params-table tr:last-child td { border-bottom: none; }

        .param-name { color: #a78bfa; font-family: 'Fira Code', monospace; }

        .param-type { color: var(--muted); font-size: 0.78rem; }

        .param-req  { color: #f87171; font-size: 0.72rem; font-weight: 600; }

        .param-opt  { color: var(--muted); font-size: 0.72rem; }

        .param-desc { color: var(--text); }



        /* ── Syntax colors ──────────────────────────────────── */

        .kw  { color: #ff7b72; }

        .str { color: #a5d6ff; }

        .num { color: #79c0ff; }

        .key { color: #c9d1d9; }

        .cmt { color: #6e7781; }

        .boo { color: #f97316; }



        /* ── Alert boxes ────────────────────────────────────── */

        .alert {

            display: flex; gap: 10px; padding: 12px 16px;

            border-radius: 6px; font-size: 0.83rem; margin-bottom: 16px; border: 1px solid;

        }

        .alert-info { background: #0c1a3a; border-color: #1e3a6e; color: #7dd3fc; }

        .alert-warn { background: #2d1d00; border-color: #5a3d00; color: #fcd34d; }



        ::-webkit-scrollbar { width: 6px; height: 6px; }

        ::-webkit-scrollbar-track { background: var(--bg); }

        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    </style>

</head>

<body>



<div class="wrapper">



    <!-- ── Sidebar ─────────────────────────────────────────────── -->

    <nav class="sidebar">

        <div class="sidebar-logo">

            <h1>⏱ Chronology API</h1>

            <span>Documentación de endpoints</span>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Inicio</div>

            <a href="#intro"><span class="method-dot" style="background:#6366f1"></span> Introducción</a>

            <a href="#autenticacion-header"><span class="method-dot" style="background:#6366f1"></span> Autenticación</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Auth</div>

            <a href="#ep-register"><span class="method-dot" style="background:var(--post)"></span> Registrar usuario</a>

            <a href="#ep-login"><span class="method-dot" style="background:var(--post)"></span> Login</a>

            <a href="#ep-logout"><span class="method-dot" style="background:var(--post)"></span> Logout</a>

            <a href="#ep-me"><span class="method-dot" style="background:var(--get)"></span> Perfil</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Empleados</div>

            <a href="#ep-employees-index"><span class="method-dot" style="background:var(--get)"></span> Listar</a>

            <a href="#ep-employees-show"><span class="method-dot" style="background:var(--get)"></span> Ver</a>

            <a href="#ep-employees-update"><span class="method-dot" style="background:var(--put)"></span> Actualizar</a>

            <a href="#ep-employees-toggle"><span class="method-dot" style="background:var(--patch)"></span> Toggle activo</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Turnos</div>

            <a href="#ep-shifts-index"><span class="method-dot" style="background:var(--get)"></span> Listar</a>

            <a href="#ep-shifts-show"><span class="method-dot" style="background:var(--get)"></span> Ver</a>

            <a href="#ep-shifts-store"><span class="method-dot" style="background:var(--post)"></span> Crear</a>

            <a href="#ep-shifts-update"><span class="method-dot" style="background:var(--put)"></span> Actualizar</a>

            <a href="#ep-shifts-destroy"><span class="method-dot" style="background:var(--delete)"></span> Eliminar</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Asignaciones</div>

            <a href="#ep-eshift-index"><span class="method-dot" style="background:var(--get)"></span> Listar por empleado</a>

            <a href="#ep-eshift-show"><span class="method-dot" style="background:var(--get)"></span> Ver</a>

            <a href="#ep-eshift-store"><span class="method-dot" style="background:var(--post)"></span> Crear</a>

            <a href="#ep-eshift-update"><span class="method-dot" style="background:var(--put)"></span> Actualizar</a>

            <a href="#ep-eshift-destroy"><span class="method-dot" style="background:var(--delete)"></span> Eliminar</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Asistencia</div>

            <a href="#ep-attendance-index"><span class="method-dot" style="background:var(--get)"></span> Listar</a>

            <a href="#ep-attendance-show"><span class="method-dot" style="background:var(--get)"></span> Ver detalle</a>

            <a href="#ep-attendance-employee"><span class="method-dot" style="background:var(--get)"></span> Por empleado</a>

            <a href="#ep-attendance-date"><span class="method-dot" style="background:var(--get)"></span> Por fecha</a>

            <a href="#ep-attendance-update"><span class="method-dot" style="background:var(--put)"></span> Editar</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Importación CSV</div>

            <a href="#ep-import-store"><span class="method-dot" style="background:var(--post)"></span> Subir CSV</a>

            <a href="#ep-import-index"><span class="method-dot" style="background:var(--get)"></span> Listar batches</a>

            <a href="#ep-import-show"><span class="method-dot" style="background:var(--get)"></span> Ver batch</a>

            <a href="#ep-import-reprocess"><span class="method-dot" style="background:var(--post)"></span> Reprocesar</a>

        </div>



        <div class="sidebar-section">

            <div class="sidebar-section-label">Configuración</div>

            <a href="#ep-settings-index"><span class="method-dot" style="background:var(--get)"></span> Listar</a>

            <a href="#ep-settings-update"><span class="method-dot" style="background:var(--put)"></span> Actualizar</a>

        </div>

    </nav>



    <!-- ── Main ──────────────────────────────────────────────────── -->

    <main class="main">



        <!-- Hero -->

        <div class="hero" id="intro">

            <h1>Chronology API</h1>

            <p>API REST para gestión de asistencia de empleados. Procesa exportaciones del reloj biométrico, calcula tardanzas y horas extra, y expone los datos para clientes HTTP.</p>

            <div class="base-url-box">

                <span class="label">Base URL</span>

                <code>{{ rtrim(config('app.url'), '/') }}/api</code>

            </div>

            <div style="margin-top:10px">

                <div class="base-url-box">

                    <span class="label">Formato</span>

                    <code>application/json</code>

                </div>

            </div>

        </div>



        <!-- Autenticación — introducción -->

        <div class="section" id="autenticacion-header">

            <div class="section-title">Autenticación</div>

            <p style="color:var(--muted);font-size:0.9rem;margin-bottom:16px">

                La API usa <strong style="color:var(--text)">Laravel Sanctum</strong> con tokens Bearer.

                Obtén el token con <code>/api/login</code> y envíalo en cada petición protegida.

            </p>

            <div class="block-label">Header requerido en endpoints protegidos</div>

            <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">tu_token_aqui</span></div>

            <div class="alert alert-info">

                <span>ℹ️</span>

                <span>

                    Los roles disponibles son <code>superadmin</code> y <code>manager</code>.

                    Los endpoints marcados como <strong>superadmin</strong> rechazan al rol manager con <code>403 Forbidden</code>.

                </span>

            </div>

        </div>



        <!-- ═══════════════ AUTH ════════════════════════════════ -->

        <div class="section" id="auth">

            <div class="section-title">Auth</div>



            <!-- POST /register -->

            <div class="endpoint" id="ep-register">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/register</span>

                    <span class="role-badge role-public">público</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Crea un nuevo usuario. No requiere autenticación.</p>

                    <div class="block-label">Body (JSON)</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"name"</span>:                  <span class="str">"Ana Torres"</span>,

  <span class="key">"email"</span>:                 <span class="str">"ana@empresa.com"</span>,

  <span class="key">"password"</span>:              <span class="str">"secreto123"</span>,

  <span class="key">"password_confirmation"</span>: <span class="str">"secreto123"</span>,

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 201</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"user"</span>:  <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">1</span>, <span class="key">"name"</span>: <span class="str">"Ana Torres"</span>, <span class="key">"email"</span>: <span class="str">"ana@empresa.com"</span>, <span class="key">"role"</span>: <span class="str">"manager"</span> <span class="kw">}</span>,

  <span class="key">"token"</span>: <span class="str">"1|abcdef1234567890..."</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- POST /login -->

            <div class="endpoint" id="ep-login">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/login</span>

                    <span class="role-badge role-public">público</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Autentica al usuario y devuelve un token Sanctum.</p>

                    <div class="block-label">Body (JSON)</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"email"</span>:    <span class="str">"ana@empresa.com"</span>,

  <span class="key">"password"</span>: <span class="str">"secreto123"</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"user"</span>:  <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">1</span>, <span class="key">"name"</span>: <span class="str">"Ana Torres"</span>, <span class="key">"email"</span>: <span class="str">"ana@empresa.com"</span>, <span class="key">"role"</span>: <span class="str">"manager"</span> <span class="kw">}</span>,

  <span class="key">"token"</span>: <span class="str">"1|abcdef1234567890..."</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 401 — credenciales inválidas</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"message"</span>: <span class="str">"Credenciales inválidas."</span> <span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- POST /logout -->

            <div class="endpoint" id="ep-logout">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/logout</span>

                    <span class="role-badge role-any">autenticado</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Revoca todos los tokens del usuario autenticado. No requiere body.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"message"</span>: <span class="str">"Sesión cerrada correctamente."</span> <span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /me -->

            <div class="endpoint" id="ep-me">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/me</span>

                    <span class="role-badge role-any">autenticado</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Devuelve los datos del usuario autenticado.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"id"</span>:    <span class="num">1</span>,

  <span class="key">"name"</span>:  <span class="str">"Ana Torres"</span>,

  <span class="key">"email"</span>: <span class="str">"ana@empresa.com"</span>,

  <span class="key">"role"</span>:  <span class="str">"manager"</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ EMPLEADOS ═══════════════════════════ -->

        <div class="section" id="empleados">

            <div class="section-title">Empleados</div>



            <!-- GET /employees -->

            <div class="endpoint" id="ep-employees-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/employees</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista todos los empleados paginados (20 por página), ordenados por apellido. Incluye asignaciones de turno.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Query params opcionales</div>

                    <table class="params-table">

                        <thead><tr><th>Param</th><th>Tipo</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">page</td><td class="param-type">integer</td><td class="param-desc">Número de página (default: 1)</td></tr>

                        </tbody>

                    </table>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">[{</span>

    <span class="key">"id"</span>:               <span class="num">1</span>,

    <span class="key">"internal_id"</span>:      <span class="str">"1001"</span>,

    <span class="key">"first_name"</span>:       <span class="str">"JUAN"</span>,

    <span class="key">"last_name"</span>:        <span class="str">"CARLOS PEREZ"</span>,

    <span class="key">"department"</span>:       <span class="str">"Tecnología"</span>,

    <span class="key">"position"</span>:         <span class="kw">null</span>,

    <span class="key">"is_active"</span>:        <span class="boo">true</span>,

    <span class="key">"shift_assignments"</span>: <span class="kw">[...]</span>

  <span class="kw">}]</span>,

  <span class="key">"links"</span>: <span class="kw">{...}</span>,

  <span class="key">"meta"</span>:  <span class="kw">{</span> <span class="key">"current_page"</span>: <span class="num">1</span>, <span class="key">"total"</span>: <span class="num">42</span>, <span class="key">"per_page"</span>: <span class="num">20</span> <span class="kw">}</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /employees/{id} -->

            <div class="endpoint" id="ep-employees-show">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/employees/{id}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Devuelve un empleado con sus asignaciones de turno.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                </div>

            </div>



            <!-- PUT /employees/{id} -->

            <div class="endpoint" id="ep-employees-update">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-put">PUT</span>

                    <span class="endpoint-path">/api/employees/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Actualiza datos editables de un empleado. Los empleados son creados automáticamente por el pipeline de importación; aquí se corrigen nombres, departamento y cargo.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON) — todos opcionales</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"first_name"</span>: <span class="str">"Juan"</span>,

  <span class="key">"last_name"</span>:  <span class="str">"Pérez"</span>,

  <span class="key">"department"</span>: <span class="str">"Tecnología"</span>,

  <span class="key">"position"</span>:   <span class="str">"Desarrollador"</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- PATCH /employees/{id}/toggle-active -->

            <div class="endpoint" id="ep-employees-toggle">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-patch">PATCH</span>

                    <span class="endpoint-path">/api/employees/{id}/toggle-active</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Alterna el estado activo/inactivo del empleado. No requiere body.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"message"</span>:   <span class="str">"Empleado desactivado correctamente."</span>,

  <span class="key">"is_active"</span>: <span class="boo">false</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ TURNOS ══════════════════════════════ -->

        <div class="section" id="turnos">

            <div class="section-title">Turnos</div>



            <!-- GET /shifts -->

            <div class="endpoint" id="ep-shifts-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/shifts</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista todos los turnos paginados (20 por página), ordenados por nombre.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200 — estructura de un turno</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"id"</span>:                         <span class="num">1</span>,

  <span class="key">"name"</span>:                       <span class="str">"Turno Diurno"</span>,

  <span class="key">"start_time"</span>:                 <span class="str">"07:00"</span>,

  <span class="key">"end_time"</span>:                   <span class="str">"16:00"</span>,

  <span class="key">"crosses_midnight"</span>:           <span class="boo">false</span>,

  <span class="key">"lunch_required"</span>:             <span class="boo">true</span>,

  <span class="key">"lunch_duration_minutes"</span>:     <span class="num">60</span>,

  <span class="key">"tolerance_minutes"</span>:          <span class="num">5</span>,

  <span class="key">"overtime_enabled"</span>:           <span class="boo">true</span>,

  <span class="key">"overtime_min_block_minutes"</span>:  <span class="num">30</span>,

  <span class="key">"max_daily_overtime_minutes"</span>:  <span class="num">120</span>,

  <span class="key">"is_active"</span>:                  <span class="boo">true</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /shifts/{id} -->

            <div class="endpoint" id="ep-shifts-show">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/shifts/{id}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Devuelve los detalles de un turno específico.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                </div>

            </div>



            <!-- POST /shifts -->

            <div class="endpoint" id="ep-shifts-store">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/shifts</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Crea un nuevo turno.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON)</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"name"</span>:                       <span class="str">"Turno Nocturno"</span>,  <span class="cmt">// requerido</span>

  <span class="key">"start_time"</span>:                 <span class="str">"20:00"</span>,           <span class="cmt">// requerido, HH:mm</span>

  <span class="key">"end_time"</span>:                   <span class="str">"06:00"</span>,           <span class="cmt">// requerido, HH:mm</span>

  <span class="key">"crosses_midnight"</span>:           <span class="boo">true</span>,              <span class="cmt">// opcional</span>

  <span class="key">"lunch_required"</span>:             <span class="boo">false</span>,             <span class="cmt">// opcional</span>

  <span class="key">"lunch_duration_minutes"</span>:     <span class="num">0</span>,                 <span class="cmt">// opcional, 0–120</span>

  <span class="key">"tolerance_minutes"</span>:          <span class="num">10</span>,                <span class="cmt">// opcional, 0–60</span>

  <span class="key">"overtime_enabled"</span>:           <span class="boo">true</span>,              <span class="cmt">// opcional</span>

  <span class="key">"overtime_min_block_minutes"</span>:  <span class="num">30</span>,                <span class="cmt">// opcional</span>

  <span class="key">"max_daily_overtime_minutes"</span>:  <span class="num">120</span>,               <span class="cmt">// opcional</span>

  <span class="key">"is_active"</span>:                  <span class="boo">true</span>               <span class="cmt">// opcional</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 201</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"data"</span>: <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">2</span>, <span class="key">"name"</span>: <span class="str">"Turno Nocturno"</span>, <span class="key">"..."</span> <span class="kw">} }</span></pre></div>

                </div>

            </div>



            <!-- PUT /shifts/{id} -->

            <div class="endpoint" id="ep-shifts-update">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-put">PUT</span>

                    <span class="endpoint-path">/api/shifts/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Actualiza un turno. Todos los campos son opcionales.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON) — enviar solo los campos a modificar</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"tolerance_minutes"</span>: <span class="num">15</span>,

  <span class="key">"is_active"</span>:         <span class="boo">false</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- DELETE /shifts/{id} -->

            <div class="endpoint" id="ep-shifts-destroy">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-delete">DELETE</span>

                    <span class="endpoint-path">/api/shifts/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Elimina un turno. No requiere body.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"message"</span>: <span class="str">"Turno eliminado correctamente."</span> <span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ ASIGNACIONES DE TURNO ═══════════════ -->

        <div class="section" id="asignaciones">

            <div class="section-title">Asignaciones de Turno</div>

            <div class="alert alert-info" style="margin-bottom:20px">

                <span>ℹ️</span>

                <span>Una asignación vincula un empleado con un turno durante un rango de fechas. El motor de asistencia la usa para calcular tardanzas y horas extra.</span>

            </div>



            <!-- GET /employees/{employee}/shifts -->

            <div class="endpoint" id="ep-eshift-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/employees/{employee}/shifts</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista el historial de asignaciones de turno de un empleado, de más reciente a más antigua.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200 — estructura de una asignación</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"id"</span>:             <span class="num">1</span>,

  <span class="key">"employee_id"</span>:    <span class="num">5</span>,

  <span class="key">"shift_id"</span>:       <span class="num">2</span>,

  <span class="key">"effective_date"</span>: <span class="str">"2026-01-01"</span>,

  <span class="key">"end_date"</span>:       <span class="kw">null</span>,

  <span class="key">"employee"</span>:       <span class="kw">{...}</span>,

  <span class="key">"shift"</span>:          <span class="kw">{...}</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /employee-shifts/{id} -->

            <div class="endpoint" id="ep-eshift-show">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/employee-shifts/{id}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Devuelve una asignación de turno con su empleado y turno anidados.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                </div>

            </div>



            <!-- POST /employee-shifts -->

            <div class="endpoint" id="ep-eshift-store">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/employee-shifts</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Crea una nueva asignación de turno para un empleado.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON)</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"employee_id"</span>:    <span class="num">5</span>,              <span class="cmt">// requerido, debe existir en employees</span>

  <span class="key">"shift_id"</span>:       <span class="num">2</span>,              <span class="cmt">// requerido, debe existir en shifts</span>

  <span class="key">"effective_date"</span>: <span class="str">"2026-01-01"</span>,   <span class="cmt">// requerido, fecha de inicio</span>

  <span class="key">"end_date"</span>:       <span class="str">"2026-12-31"</span>    <span class="cmt">// opcional, null = indefinido</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 201</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"data"</span>: <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">1</span>, <span class="key">"employee"</span>: <span class="kw">{...}</span>, <span class="key">"shift"</span>: <span class="kw">{...}</span>, <span class="key">"..."</span> <span class="kw">} }</span></pre></div>

                </div>

            </div>



            <!-- PUT /employee-shifts/{id} -->

            <div class="endpoint" id="ep-eshift-update">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-put">PUT</span>

                    <span class="endpoint-path">/api/employee-shifts/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Modifica una asignación existente. Útil para cambiar fechas o reasignar otro turno.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON) — campos opcionales</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"shift_id"</span>: <span class="num">3</span>,

  <span class="key">"end_date"</span>: <span class="str">"2026-06-30"</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- DELETE /employee-shifts/{id} -->

            <div class="endpoint" id="ep-eshift-destroy">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-delete">DELETE</span>

                    <span class="endpoint-path">/api/employee-shifts/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Elimina una asignación de turno. No requiere body.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"message"</span>: <span class="str">"Asignación eliminada correctamente."</span> <span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ ASISTENCIA ═══════════════════════════ -->

        <div class="section" id="asistencia">

            <div class="section-title">Asistencia</div>

            <div class="alert alert-info" style="margin-bottom:20px">

                <span>ℹ️</span>

                <span>Los registros de asistencia se generan automáticamente al procesar un CSV importado. Cada registro corresponde a un empleado en un día específico.</span>

            </div>



            <!-- GET /attendance -->

            <div class="endpoint" id="ep-attendance-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/attendance</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista registros de asistencia con filtros y paginación. Incluye empleado y turno.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Query params — filtros</div>

                    <table class="params-table">

                        <thead><tr><th>Param</th><th>Tipo</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">employee_id</td><td class="param-type">integer</td><td class="param-desc">Filtrar por ID de empleado</td></tr>

                            <tr><td class="param-name">date</td><td class="param-type">date</td><td class="param-desc">Fecha exacta (<code>2026-01-15</code>)</td></tr>

                            <tr><td class="param-name">date_from</td><td class="param-type">date</td><td class="param-desc">Desde fecha (inclusive)</td></tr>

                            <tr><td class="param-name">date_to</td><td class="param-type">date</td><td class="param-desc">Hasta fecha (inclusive)</td></tr>

                            <tr><td class="param-name">status</td><td class="param-type">string</td><td class="param-desc"><code>present</code>, <code>absent</code>, <code>incomplete</code>, <code>rest</code>, <code>holiday</code></td></tr>

                            <tr><td class="param-name">has_overtime</td><td class="param-type">boolean</td><td class="param-desc"><code>1</code> = solo con horas extra</td></tr>

                            <tr><td class="param-name">has_late</td><td class="param-type">boolean</td><td class="param-desc"><code>1</code> = solo con tardanza</td></tr>

                            <tr><td class="param-name">per_page</td><td class="param-type">integer</td><td class="param-desc">Resultados por página (default: 15, max: 100)</td></tr>

                            <tr><td class="param-name">page</td><td class="param-type">integer</td><td class="param-desc">Número de página</td></tr>

                        </tbody>

                    </table>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">[{</span>

    <span class="key">"id"</span>:                        <span class="num">1</span>,

    <span class="key">"employee_id"</span>:               <span class="num">5</span>,

    <span class="key">"employee"</span>:                  <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">5</span>, <span class="key">"first_name"</span>: <span class="str">"JUAN"</span>, <span class="key">"..."</span> <span class="kw">}</span>,

    <span class="key">"date_reference"</span>:            <span class="str">"2026-01-15"</span>,

    <span class="key">"shift_id"</span>:                  <span class="num">2</span>,

    <span class="key">"shift"</span>:                     <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">2</span>, <span class="key">"name"</span>: <span class="str">"Diurno"</span>, <span class="key">"..."</span> <span class="kw">}</span>,

    <span class="key">"first_check_in"</span>:            <span class="str">"2026-01-15 08:05:00"</span>,

    <span class="key">"last_check_out"</span>:            <span class="str">"2026-01-15 17:02:00"</span>,

    <span class="key">"worked_minutes"</span>:            <span class="num">537</span>,

    <span class="key">"overtime_minutes"</span>:           <span class="num">0</span>,

    <span class="key">"overtime_diurnal_minutes"</span>:   <span class="num">0</span>,

    <span class="key">"overtime_nocturnal_minutes"</span>: <span class="num">0</span>,

    <span class="key">"late_minutes"</span>:              <span class="num">5</span>,

    <span class="key">"early_departure_minutes"</span>:   <span class="num">0</span>,

    <span class="key">"status"</span>:                    <span class="str">"present"</span>,

    <span class="key">"is_manually_edited"</span>:        <span class="boo">false</span>

  <span class="kw">}]</span>,

  <span class="key">"links"</span>: <span class="kw">{...}</span>,

  <span class="key">"meta"</span>:  <span class="kw">{</span> <span class="key">"current_page"</span>: <span class="num">1</span>, <span class="key">"total"</span>: <span class="num">120</span>, <span class="key">"per_page"</span>: <span class="num">15</span> <span class="kw">}</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /attendance/{id} -->

            <div class="endpoint" id="ep-attendance-show">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/attendance/{id}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Detalle de un registro de asistencia incluyendo empleado, turno e historial de ediciones manuales.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">{</span>

    <span class="key">"id"</span>:                 <span class="num">1</span>,

    <span class="key">"employee"</span>:           <span class="kw">{...}</span>,

    <span class="key">"shift"</span>:              <span class="kw">{...}</span>,

    <span class="key">"date_reference"</span>:     <span class="str">"2026-01-15"</span>,

    <span class="key">"worked_minutes"</span>:     <span class="num">537</span>,

    <span class="key">"status"</span>:             <span class="str">"present"</span>,

    <span class="key">"is_manually_edited"</span>: <span class="boo">true</span>,

    <span class="key">"edits"</span>: <span class="kw">[{</span>

      <span class="key">"id"</span>:            <span class="num">1</span>,

      <span class="key">"field_changed"</span>: <span class="str">"status"</span>,

      <span class="key">"old_value"</span>:    <span class="str">"absent"</span>,

      <span class="key">"new_value"</span>:    <span class="str">"present"</span>,

      <span class="key">"reason"</span>:       <span class="str">"Corrección de marcaje"</span>,

      <span class="key">"editor"</span>:       <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">1</span>, <span class="key">"name"</span>: <span class="str">"Admin"</span> <span class="kw">}</span>,

      <span class="key">"created_at"</span>:   <span class="str">"2026-01-16T10:00:00"</span>

    <span class="kw">}]</span>

  <span class="kw">}</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /attendance/employee/{employee} -->

            <div class="endpoint" id="ep-attendance-employee">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/attendance/employee/{employee}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista los registros de asistencia de un empleado específico. Soporta filtros por rango de fecha y estado.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Query params opcionales</div>

                    <table class="params-table">

                        <thead><tr><th>Param</th><th>Tipo</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">date_from</td><td class="param-type">date</td><td class="param-desc">Desde fecha (inclusive)</td></tr>

                            <tr><td class="param-name">date_to</td><td class="param-type">date</td><td class="param-desc">Hasta fecha (inclusive)</td></tr>

                            <tr><td class="param-name">status</td><td class="param-type">string</td><td class="param-desc">Filtrar por estado</td></tr>

                            <tr><td class="param-name">per_page</td><td class="param-type">integer</td><td class="param-desc">Resultados por página (default: 15)</td></tr>

                        </tbody>

                    </table>

                </div>

            </div>



            <!-- GET /attendance/day/{date} -->

            <div class="endpoint" id="ep-attendance-date">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/attendance/day/{date}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista la asistencia de todos los empleados para una fecha específica. Útil para reportes diarios.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Parámetro de ruta</div>

                    <table class="params-table">

                        <thead><tr><th>Param</th><th>Tipo</th><th>Ejemplo</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">date</td><td class="param-type">date</td><td class="param-desc"><code>2026-01-15</code></td></tr>

                        </tbody>

                    </table>

                    <div class="block-label">Query params opcionales</div>

                    <table class="params-table">

                        <thead><tr><th>Param</th><th>Tipo</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">status</td><td class="param-type">string</td><td class="param-desc">Filtrar por estado</td></tr>

                            <tr><td class="param-name">per_page</td><td class="param-type">integer</td><td class="param-desc">Resultados por página (default: 15)</td></tr>

                        </tbody>

                    </table>

                </div>

            </div>



            <!-- PUT /attendance/{id} -->

            <div class="endpoint" id="ep-attendance-update">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-put">PUT</span>

                    <span class="endpoint-path">/api/attendance/{id}</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">

                        Edición manual de un registro de asistencia. Cada campo modificado genera un registro en <code>attendance_edits</code>

                        con valor anterior, nuevo y razón. Marca el día como <code>is_manually_edited = true</code>.

                    </p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON)</div>

                    <table class="params-table">

                        <thead><tr><th>Campo</th><th>Tipo</th><th>Regla</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">first_check_in</td><td class="param-type">datetime</td><td class="param-opt">opcional</td><td class="param-desc">Hora de entrada</td></tr>

                            <tr><td class="param-name">last_check_out</td><td class="param-type">datetime</td><td class="param-opt">opcional</td><td class="param-desc">Hora de salida</td></tr>

                            <tr><td class="param-name">worked_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">Minutos trabajados</td></tr>

                            <tr><td class="param-name">overtime_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">Total horas extra</td></tr>

                            <tr><td class="param-name">overtime_diurnal_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">HE diurnas</td></tr>

                            <tr><td class="param-name">overtime_nocturnal_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">HE nocturnas</td></tr>

                            <tr><td class="param-name">late_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">Minutos de tardanza</td></tr>

                            <tr><td class="param-name">early_departure_minutes</td><td class="param-type">integer</td><td class="param-opt">opcional</td><td class="param-desc">Minutos de salida temprana</td></tr>

                            <tr><td class="param-name">status</td><td class="param-type">string</td><td class="param-opt">opcional</td><td class="param-desc"><code>present</code> <code>absent</code> <code>incomplete</code> <code>rest</code> <code>holiday</code></td></tr>

                            <tr><td class="param-name">reason</td><td class="param-type">string</td><td class="param-req">requerido</td><td class="param-desc">Motivo de la edición (max 500 caracteres)</td></tr>

                        </tbody>

                    </table>

                    <div class="block-label">Ejemplo</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"status"</span>:         <span class="str">"present"</span>,

  <span class="key">"worked_minutes"</span>: <span class="num">540</span>,

  <span class="key">"late_minutes"</span>:   <span class="num">0</span>,

  <span class="key">"reason"</span>:         <span class="str">"Reloj biométrico no registró entrada, pero empleado estaba presente"</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">{</span>

    <span class="key">"id"</span>: <span class="num">1</span>,

    <span class="key">"status"</span>: <span class="str">"present"</span>,

    <span class="key">"is_manually_edited"</span>: <span class="boo">true</span>,

    <span class="key">"edits"</span>: <span class="kw">[{</span>

      <span class="key">"field_changed"</span>: <span class="str">"status"</span>,

      <span class="key">"old_value"</span>: <span class="str">"absent"</span>,

      <span class="key">"new_value"</span>: <span class="str">"present"</span>,

      <span class="key">"reason"</span>: <span class="str">"Reloj biométrico no registró..."</span>,

      <span class="key">"editor"</span>: <span class="kw">{</span> <span class="key">"id"</span>: <span class="num">1</span>, <span class="key">"name"</span>: <span class="str">"Admin"</span> <span class="kw">}</span>

    <span class="kw">}]</span>,

    <span class="key">"..."</span>

  <span class="kw">}</span>,

  <span class="key">"edits_created"</span>: <span class="num">2</span>

<span class="kw">}</span></pre></div>

                    <div class="alert alert-info">

                        <span>ℹ️</span>

                        <span>Si un campo no cambió realmente (valor anterior = valor nuevo), no se crea registro de edición para ese campo.</span>

                    </div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ IMPORTACIÓN CSV ═════════════════════ -->

        <div class="section" id="importacion">

            <div class="section-title">Importación CSV</div>



            <!-- Configuración previa requerida -->

            <div style="background:#111827; border:1px solid #374151; border-radius:10px; padding:20px 24px; margin-bottom:20px">

                <div style="font-size:1rem; font-weight:700; color:#f3f4f6; margin-bottom:12px">📋 Configuración previa requerida</div>

                <p style="color:#9ca3af; font-size:0.85rem; margin-bottom:14px">Antes de subir un CSV, asegúrese de que el sistema tenga la siguiente configuración:</p>



                <!-- 1. Turnos -->

                <div style="display:flex; gap:10px; margin-bottom:12px; align-items:flex-start">

                    <span style="background:#10b981; color:#fff; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; white-space:nowrap; margin-top:2px">REQUERIDO</span>

                    <div>

                        <div style="color:#e5e7eb; font-weight:600; font-size:0.85rem">Turnos (<code style="color:#a78bfa">POST /api/shifts</code>)</div>

                        <p style="color:#9ca3af; font-size:0.8rem; margin:4px 0 0 0">

                            Debe existir al menos un turno activo con <code>start_time</code>, <code>end_time</code> y <code>tolerance_minutes</code> configurados.

                            Sin turnos, la asistencia se procesa sin cálculos de tardanza, horas extra ni deducción de almuerzo — el campo <code>shift_id</code> queda <code>null</code>.

                        </p>

                    </div>

                </div>



                <!-- 2. Asignación de turno o auto-assign -->

                <div style="display:flex; gap:10px; margin-bottom:12px; align-items:flex-start">

                    <span style="background:#f59e0b; color:#111; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; white-space:nowrap; margin-top:2px">RECOMENDADO</span>

                    <div>

                        <div style="color:#e5e7eb; font-weight:600; font-size:0.85rem">Asignación de turno a empleados (<code style="color:#a78bfa">POST /api/employee-shifts</code>)</div>

                        <p style="color:#9ca3af; font-size:0.8rem; margin:4px 0 0 0">

                            Si los empleados ya tienen turno asignado, se usa ese turno directamente.

                            Si no tienen asignación y <code>auto_assign_shift</code> está activo (por defecto: <code>true</code>), el sistema intenta asignarles un turno automáticamente basándose en su hora de entrada.

                        </p>

                    </div>

                </div>



                <!-- 3. System settings -->

                <div style="display:flex; gap:10px; margin-bottom:12px; align-items:flex-start">

                    <span style="background:#6366f1; color:#fff; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; white-space:nowrap; margin-top:2px">OPCIONAL</span>

                    <div>

                        <div style="color:#e5e7eb; font-weight:600; font-size:0.85rem">Configuración del sistema (<code style="color:#a78bfa">PUT /api/settings</code>)</div>

                        <p style="color:#9ca3af; font-size:0.8rem; margin:4px 0 0 0">

                            Los settings tienen valores por defecto seguros si no están configurados:

                        </p>

                        <table style="width:100%; margin-top:8px; font-size:0.78rem; border-collapse:collapse">

                            <thead><tr style="border-bottom:1px solid #374151; color:#9ca3af">

                                <th style="text-align:left; padding:4px 8px">Setting</th>

                                <th style="text-align:left; padding:4px 8px">Default</th>

                                <th style="text-align:left; padding:4px 8px">Descripción</th>

                            </tr></thead>

                            <tbody style="color:#d1d5db">

                                <tr><td style="padding:4px 8px"><code>noise_window_minutes</code></td><td style="padding:4px 8px"><code>60</code></td><td style="padding:4px 8px">Ventana (min) para filtrar marcajes duplicados del biométrico</td></tr>

                                <tr><td style="padding:4px 8px"><code>auto_assign_shift</code></td><td style="padding:4px 8px"><code>true</code></td><td style="padding:4px 8px">Habilitar auto-asignación de turno si el empleado no tiene uno</td></tr>

                                <tr><td style="padding:4px 8px"><code>auto_assign_tolerance_minutes</code></td><td style="padding:4px 8px"><code>30</code></td><td style="padding:4px 8px">Ventana (±min) alrededor del inicio de turno para considerar match</td></tr>

                            </tbody>

                        </table>

                    </div>

                </div>



                <!-- 4. Empleados -->

                <div style="display:flex; gap:10px; align-items:flex-start">

                    <span style="background:#374151; color:#d1d5db; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; white-space:nowrap; margin-top:2px">AUTOMÁTICO</span>

                    <div>

                        <div style="color:#e5e7eb; font-weight:600; font-size:0.85rem">Empleados</div>

                        <p style="color:#9ca3af; font-size:0.8rem; margin:4px 0 0 0">

                            No es necesario crearlos previamente. Se crean automáticamente al importar el CSV usando la columna <code>ID de persona</code> como <code>internal_id</code>.

                            Si el empleado ya existe (mismo <code>internal_id</code>), se reutiliza.

                        </p>

                    </div>

                </div>

            </div>



            <div class="alert alert-warn" style="margin-bottom:20px">

                <span>⚠️</span>

                <span>El CSV debe ser la exportación del reloj biométrico. Columnas requeridas: <code>ID de persona</code>, <code>Hora</code>. Tamaño máximo: 10 MB. El archivo se procesa en background vía cola.</span>

            </div>

            <div class="alert alert-info" style="margin-bottom:20px">

                <span>ℹ️</span>

                <span><strong>Auto-asignación de turno:</strong> Si un empleado no tiene turno asignado y su hora de entrada coincide con el inicio de algún turno activo (± tolerancia configurable), se le asigna automáticamente.
                    Controlado por los settings <code>auto_assign_shift</code> y <code>auto_assign_tolerance_minutes</code>.</span>

            </div>



            <!-- POST /import -->

            <div class="endpoint" id="ep-import-store">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/import</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">

                        Sube un CSV del biométrico. Valida el archivo, almacena los registros crudos en <code>raw_logs</code>,

                        crea o reutiliza empleados automáticamente y despacha un job en background para procesar la asistencia.

                    </p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span>

Content-Type: multipart/form-data</div>

                    <div class="block-label">Body (form-data)</div>

                    <table class="params-table">

                        <thead><tr><th>Campo</th><th>Tipo</th><th>Regla</th><th>Descripción</th></tr></thead>

                        <tbody>

                            <tr>

                                <td class="param-name">file</td>

                                <td class="param-type">File</td>

                                <td class="param-req">requerido</td>

                                <td class="param-desc">Archivo CSV del biométrico (<code>.csv</code> o <code>.txt</code>, max 10 MB)</td>

                            </tr>

                        </tbody>

                    </table>

                    <div class="alert alert-info">

                        <span>ℹ️</span>

                        <span>

                            En Postman/Insomnia: <strong>Body → form-data</strong>, key <code>file</code> de tipo <strong>File</strong> y seleccionar el CSV.

                            <strong>No</strong> usar JSON ni raw.

                        </span>

                    </div>

                    <div class="block-label">Estructura esperada del CSV</div>

                    <div class="code-block"><pre><span class="cmt">ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,...</span>

<span class="str">'1001,JUAN CARLOS PEREZ,Tecnología,2026-01-15 08:05:00,Nada,Entrada_Puerta1,-,...</span>

<span class="str">'1001,JUAN CARLOS PEREZ,Tecnología,2026-01-15 17:02:00,Nada,Entrada_Puerta1,-,...</span></pre></div>

                    <div class="block-label">Formatos de fecha aceptados en columna Hora</div>

                    <table class="params-table">

                        <thead><tr><th>Formato</th><th>Ejemplo</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">Y-m-d H:i:s</td><td class="param-desc">2026-01-15 08:05:00</td></tr>

                            <tr><td class="param-name">Y-m-d H:i</td><td class="param-desc">2026-01-15 08:05</td></tr>

                            <tr><td class="param-name">d/m/Y H:i:s</td><td class="param-desc">15/01/2026 08:05:00</td></tr>

                            <tr><td class="param-name">d/m/Y H:i</td><td class="param-desc">15/01/2026 08:05</td></tr>

                        </tbody>

                    </table>

                    <div class="block-label">Respuesta 201 — importación exitosa</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">{</span>

    <span class="key">"id"</span>:                <span class="num">1</span>,

    <span class="key">"uploaded_by"</span>:       <span class="num">1</span>,

    <span class="key">"original_filename"</span>: <span class="str">"marcaciones.csv"</span>,

    <span class="key">"file_hash"</span>:         <span class="str">"sha256..."</span>,

    <span class="key">"status"</span>:            <span class="str">"processing"</span>,

    <span class="key">"total_rows"</span>:        <span class="num">4719</span>,

    <span class="key">"processed_rows"</span>:    <span class="num">4719</span>,

    <span class="key">"failed_rows"</span>:       <span class="num">0</span>,

    <span class="key">"errors"</span>:            <span class="kw">null</span>,

    <span class="key">"processed_at"</span>:      <span class="kw">null</span>

  <span class="kw">}</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 422 — archivo ya importado</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"errors"</span>: <span class="kw">[</span><span class="str">"Este archivo ya fue importado anteriormente."</span><span class="kw">]</span> <span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 422 — columnas faltantes</div>

                    <div class="code-block"><pre><span class="kw">{</span> <span class="key">"errors"</span>: <span class="kw">[</span><span class="str">"Columnas requeridas faltantes: id de persona, hora"</span><span class="kw">]</span> <span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- GET /import -->

            <div class="endpoint" id="ep-import-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/import</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista los batches de importación paginados (20 por página), del más reciente al más antiguo.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Valores posibles de <code>status</code></div>

                    <table class="params-table">

                        <thead><tr><th>Valor</th><th>Significado</th></tr></thead>

                        <tbody>

                            <tr><td class="param-name">pending</td><td class="param-desc">Recién subido, pendiente de procesar.</td></tr>

                            <tr><td class="param-name">processing</td><td class="param-desc">raw_logs almacenados, job en cola.</td></tr>

                            <tr><td class="param-name">completed</td><td class="param-desc">Asistencia calculada correctamente.</td></tr>

                            <tr><td class="param-name">failed</td><td class="param-desc">Error de validación o procesamiento.</td></tr>

                        </tbody>

                    </table>

                </div>

            </div>



            <!-- GET /import/{id} -->

            <div class="endpoint" id="ep-import-show">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/import/{id}</span>

                    <span class="role-badge role-manager">manager+</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Detalle de un batch de importación específico incluyendo errores si los hubo.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                </div>

            </div>



            <!-- POST /import/{id}/reprocess -->

            <div class="endpoint" id="ep-import-reprocess">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-post">POST</span>

                    <span class="endpoint-path">/api/import/{id}/reprocess</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">

                        Reprocesa un batch de importación: elimina los registros de asistencia generados (excepto los editados manualmente),

                        reinicia el estado del batch y redespacha los jobs de procesamiento.

                    </p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="alert alert-warn">

                        <span>⚠️</span>

                        <span>Los registros con <code>is_manually_edited = true</code> <strong>no se eliminan</strong> durante el reprocesamiento para preservar correcciones manuales.</span>

                    </div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"message"</span>:                <span class="str">"Batch reprocessing started."</span>,

  <span class="key">"deleted_attendance_days"</span>: <span class="num">45</span>,

  <span class="key">"groups_to_process"</span>:      <span class="num">12</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ CONFIGURACIÓN ════════════════════════ -->

        <div class="section" id="configuracion">

            <div class="section-title">Configuración del Sistema</div>

            <div class="alert alert-warn" style="margin-bottom:20px">

                <span>🔒</span>

                <span>Solo accesible para <strong>superadmin</strong>. Controla parámetros del motor de asistencia: ventana de ruido, inicio de jornada diurna/nocturna, etc.</span>

            </div>



            <!-- GET /settings -->

            <div class="endpoint" id="ep-settings-index">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-get">GET</span>

                    <span class="endpoint-path">/api/settings</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Lista todas las configuraciones del sistema, ordenadas por grupo y clave.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"data"</span>: <span class="kw">[</span>

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"diurnal_start_time"</span>,   <span class="key">"value"</span>: <span class="str">"06:00"</span>, <span class="key">"group"</span>: <span class="str">"attendance"</span> <span class="kw">}</span>,

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"nocturnal_start_time"</span>, <span class="key">"value"</span>: <span class="str">"20:00"</span>, <span class="key">"group"</span>: <span class="str">"attendance"</span> <span class="kw">}</span>,

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"noise_window_minutes"</span>,  <span class="key">"value"</span>: <span class="str">"60"</span>,    <span class="key">"group"</span>: <span class="str">"attendance"</span> <span class="kw">}</span>,

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"auto_assign_shift"</span>,    <span class="key">"value"</span>: <span class="str">"true"</span>,  <span class="key">"group"</span>: <span class="str">"attendance"</span> <span class="kw">}</span>,

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"auto_assign_tolerance_minutes"</span>, <span class="key">"value"</span>: <span class="str">"30"</span>, <span class="key">"group"</span>: <span class="str">"attendance"</span> <span class="kw">}</span>

  <span class="kw">]</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>



            <!-- PUT /settings -->

            <div class="endpoint" id="ep-settings-update">

                <div class="endpoint-header" onclick="toggle(this)">

                    <span class="method-badge method-put">PUT</span>

                    <span class="endpoint-path">/api/settings</span>

                    <span class="role-badge role-superadmin">superadmin</span>

                </div>

                <div class="endpoint-body">

                    <p class="endpoint-desc">Actualiza una o varias configuraciones en una sola petición usando <code>updateOrCreate</code> por clave.</p>

                    <div class="block-label">Headers</div>

                    <div class="header-example">Authorization: Bearer <span style="color:#a78bfa">{token}</span></div>

                    <div class="block-label">Body (JSON)</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"settings"</span>: <span class="kw">[</span>

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"noise_window_minutes"</span>,  <span class="key">"value"</span>: <span class="str">"45"</span>    <span class="kw">}</span>,

    <span class="kw">{</span> <span class="key">"key"</span>: <span class="str">"diurnal_start_time"</span>,   <span class="key">"value"</span>: <span class="str">"07:00"</span> <span class="kw">}</span>

  <span class="kw">]</span>

<span class="kw">}</span></pre></div>

                    <div class="block-label">Respuesta 200</div>

                    <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"message"</span>:      <span class="str">"Configuración actualizada correctamente."</span>,

  <span class="key">"updated_keys"</span>: <span class="kw">[</span><span class="str">"noise_window_minutes"</span>, <span class="str">"diurnal_start_time"</span><span class="kw">]</span>

<span class="kw">}</span></pre></div>

                </div>

            </div>

        </div>



        <!-- ═══════════════ ERRORES COMUNES ═════════════════════ -->

        <div class="section" id="errores">

            <div class="section-title">Respuestas de error comunes</div>

            <table class="params-table">

                <thead><tr><th>Código</th><th>Situación</th></tr></thead>

                <tbody>

                    <tr><td class="param-name">401</td><td class="param-desc">Token ausente, inválido o expirado. Incluir <code>Authorization: Bearer {token}</code>.</td></tr>

                    <tr><td class="param-name">403</td><td class="param-desc">Rol insuficiente. El usuario no tiene permiso para ese recurso.</td></tr>

                    <tr><td class="param-name">404</td><td class="param-desc">Recurso no encontrado. Verificar el ID en la URL.</td></tr>

                    <tr><td class="param-name">422</td><td class="param-desc">Validación fallida. El body <code>{ "errors": {...} }</code> detalla los campos inválidos.</td></tr>

                    <tr><td class="param-name">500</td><td class="param-desc">Error interno. Consultar <code>storage/logs/laravel.log</code>.</td></tr>

                </tbody>

            </table>

            <div class="block-label" style="margin-top:20px">Ejemplo respuesta 422</div>

            <div class="code-block"><pre><span class="kw">{</span>

  <span class="key">"message"</span>: <span class="str">"The email field is required."</span>,

  <span class="key">"errors"</span>: <span class="kw">{</span>

    <span class="key">"email"</span>: <span class="kw">[</span><span class="str">"The email field is required."</span><span class="kw">]</span>

  <span class="kw">}</span>

<span class="kw">}</span></pre></div>

        </div>



        <footer style="border-top:1px solid var(--border);padding-top:24px;color:var(--muted);font-size:0.8rem;margin-top:40px">

            Chronology API &mdash; Laravel {{ app()->version() }} &mdash; {{ now()->format('Y') }}

        </footer>



    </main>

</div>



<script>

    function toggle(header) {

        header.closest('.endpoint').classList.toggle('open');

    }

    document.querySelectorAll('.section .endpoint:first-of-type').forEach(el => el.classList.add('open'));

</script>



</body>

</html>

