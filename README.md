# CompraLatino  -  Fase III
## Inteligencia de Negocios y Funcionalidad - Guía del equipo + Fundamento teórico (Clases 09 a 12)

> **Para el equipo:** este README es la guía paso a paso para levantar el
> proyecto, auditarlo, tomar las capturas del documento y extraer la teoría.
> Si no escribiste el código, no te preocupes: cada sección explica **qué hace**,
> **dónde hacer clic** y **por qué funciona así** según la arquitectura por capas.

**Proyecto ejecutable:** `compralatino-app/` (Laravel 11 + SQLite + colas en BD).
**Código académico para anexar:** `app/`, `database/`, `resources/views/`, `routes/`
del nivel superior (copia sincronizada del código que corre en `compralatino-app/`).

---

## 1. Resumen de la Fase III (qué hace este código)

CompraLatino es un **e-commerce intermediario (monolito en Laravel)**: el cliente
compra en nuestra plataforma y nosotros ejecutamos la compra real en la tienda
externa japonesa **YAuctions**. La clave arquitectónica heredada de la Fase II es
que esa comunicación externa **no bloquea al usuario**: al confirmar la compra, el
controlador crea la orden en estado `pendiente`, despacha un **Job a la cola
`compras-yauctions`** y responde al instante. Un worker en segundo plano
(`queue:work`) contacta a YAuctions, recibe el acuse (`YAU-xxxxxxxx`) y marca la
orden como `completada`. La página de seguimiento se auto-recarga cada 4 segundos,
así el usuario *ve* el avance sin esperar.

La Fase III agrega tres requisitos sobre esa base:

1. **Plataforma e-commerce multiplataforma.** Registro de cuentas
(`/registro`), catálogo con **buscador** (texto + categoría + plataforma),
compras **locales** (confirmación inmediata, acuse `LOCAL-{id}`) y compras
**YAuctions** (asíncronas, acuse `YAU-...`). Cada producto declara su
`plataforma` y su `external_id` (puente con Japón).
2. **Motor de recomendaciones basado en historial.** `RecommendationService`
construye un perfil de intereses por categoría ponderando eventos
(compra = 5, carrito = 3, vista = 1), excluye lo ya comprado y ordena por
puntaje de afinidad. Sin historial usa *cold-start* por tendencia. Todo lo que
muestra queda registrado en la bitácora para medir conversión.
3. **Dashboard de Inteligencia de Negocios (BI).** Panel administrativo
(`/admin/dashboard`) con KPIs reales: ingresos, órdenes, ticket promedio,
conversión del recomendador, ventas por plataforma/día, top productos y fallos
de sincronización; más una vista de **auditoría** (`/admin/auditoria`) con el
JSON de cada fallo. El seeder genera ~90 órdenes de 30 días para que las
métricas nunca salgan vacías.

## 2. Guía de instalación y ejecución (desde cero)

> Funciona en Windows, Linux y macOS. Solo necesitan **PHP 8.2+**,
> **Composer** y este repositorio. La base de datos ya viene configurada como
> **SQLite** (archivo `database/database.sqlite`): no requiere instalar MySQL.

```bash
# 1. Entrar al proyecto ejecutable
cd compralatino-app

# 2. Instalar dependencias PHP (solo la primera vez)
composer install

# 3. Crear el archivo de entorno y la clave (solo la primera vez)
cp .env.example .env        # en Windows: copy .env.example .env
php artisan key:generate

# 4. Crear las tablas Y cargar datos de prueba (¡un solo comando!)
php artisan migrate --seed
# Crea: 1 admin + 8 clientes, 18 productos (local + YAuctions),
# ~90 órdenes de 30 días en 4 estados y bitácora de comportamiento.

# 5. Levantar el servidor (TERMINAL 1  -  dejarla abierta)
php artisan serve
# Abrir en el navegador: http://127.0.0.1:8000

# 6. Procesar las colas (TERMINAL 2  -  dejarla abierta) L MÁS IMPORTANTE
php artisan queue:work --queue=compras-yauctions --tries=3 --backoff=30,120,300 -v
# Sin este worker las compras YAuctions se quedan en "pendiente" para siempre.
# Con él, se completan solas en segundos con su acuse YAU-xxxxxxxx.
```

**Cuentas de prueba (creadas por el seeder):**

| Rol | Email | Contraseña | Puede ver |
|-----|-------|------------|-----------|
| Administradora BI | `admin@compralatino.test` | `secret1234` | Tienda + `/admin/dashboard` + `/admin/auditoria` |
| Clientes (8) | `cliente1@compralatino.test` a `cliente8@compralatino.test` | `password` | Tienda, mis órdenes, Para ti |

**Configuración clave (ya viene lista en `.env`):**

| Variable | Valor | Para qué sirve |
|----------|-------|----------------|
| `DB_CONNECTION` | `sqlite` | BD en un archivo, sin servidor externo |
| `QUEUE_CONNECTION` | `database` | Las colas se guardan en la tabla `jobs` |
| `YACTIONS_URL` | `http://127.0.0.1:8000` | Apunta al **simulador local** de YAuctions (ver 3) |
| `YACTIONS_TOKEN` | `demo-token` | Token del simulador |

> Para demostrar el manejo de fallos (auditoría de errores), cambia
> temporalmente `YACTIONS_URL=http://127.0.0.1:9` (puerto muerto), reinicia el
> worker y compra algo YAuctions: verás la orden en `en_proceso` con su fallo
> auditado en `/admin/auditoria`. Luego restaura el valor y reprocesa.


---

## 3. Guía de auditoría y capturas (paso a paso para el documento)

> Pre-requisitos: servidor corriendo (`php artisan serve`) y worker corriendo
> (`queue:work --queue=compras-yauctions`). Navegador en `http://127.0.0.1:8000`.
> Cada paso indica **qué captura tomar** y **qué demuestra** para el informe.

### Paso 1  - " Registro / Login de usuario
1. Ve a `http://127.0.0.1:8000/registro` -> completa nombre, correo, contraseña
(2 veces) y plataforma preferida -> **Crear cuenta**. Entrarás directo al catálogo.
2. Alternativa: `http://127.0.0.1:8000/login` con `cliente1@compralatino.test` /
`password`.
3. [foto] **Captura 1:** catálogo con tu nombre arriba y la sección
-* Recomendados para ti.
*Demuestra: alta de cuentas multiplataforma + autenticación por sesión.*

### Paso 2  - " Compra (orden en estado Pendiente)
1. Clic en cualquier producto con badge **YAuctions** -> elige cantidad 1 y
plataforma `YAuctions` -> **Confirmar compra**.
2. Serás redirigido a `/ordenes/{id}`: verás el badge **Pendiente** (la página se
recarga sola cada 4 s mientras esté en tránsito).
3. [foto] **Captura 2:** seguimiento con estado **Pendiente**, **inmediatamente**
después de comprar (antes de que el worker la tome).
*Demuestra: respuesta instantánea desacoplada de la API externa (Fase II).*

### Paso 3  - " Worker en acción (orden Completada con acuse YAU-XXX) >>
1. Si el worker NO corría, ábrelo ahora en la Terminal 2:
`php artisan queue:work --queue=compras-yauctions -v`. Observa la línea
`ProcessYAuctionPurchase ... DONE` (~400 ms: es la llamada a YAuctions).
2. Recarga la página de tu orden: ahora muestra **Completada** + acuse
**`YAU-xxxxxxxx`** + fecha de sincronización.
3. [foto] **Captura 3a:** terminal del worker con el Job en `DONE`.
[foto] **Captura 3b:** la misma orden ahora **Completada** con su código YAU.
*Demuestra: comunicación asíncrona real  - " la prueba reina de la arquitectura.*
4. (Opcional, para el informe de excepciones) Para una captura de fallo: detén el
servidor 10 segundos, compra otro producto YAuctions, reinicia el servidor y el
worker; la orden pasará a **En proceso** y en `/admin/auditoria` verás el JSON
del fallo con su reintento programado.

### Paso 4  - " Recomendaciones (motor basado en historial)
1. Como cliente, visita `http://127.0.0.1:8000/para-ti`: verás hasta 12 tarjetas
con su **puntaje de afinidad** ((Afinidad 12.40 - modelo)).
2. Compra algo de una categoría nueva y vuelve a `/para-ti`: las sugerencias
cambian (el perfil se recalculó con tu compra).
3. [foto] **Captura 4:** página "Para ti" con puntajes visibles.
*Demuestra: ofertas personalizadas a partir del historial (requisito Fase III).*

### Paso 5  - " Dashboard BI (métricas reales del seeder)
1. Cierra sesión y entra como `admin@compralatino.test` / `secret1234` -> irás
directo a `http://127.0.0.1:8000/admin/dashboard`.
2. Recorre: KPIs (ingresos 30 días, órdenes, ticket, conversión del recomendador,
fallos), gráfico de barras por día, ventas por plataforma, top 5 productos.
3. Visita `http://127.0.0.1:8000/admin/auditoria`: tabla de fallos con payload.
4. [foto] **Captura 5a:** dashboard con KPIs y gráficas.
[foto] **Captura 5b:** auditoría con un fallo desplegado (`<details>` del JSON).
*Demuestra: herramienta BI para monitoreo y toma de decisiones con datos reales.*

**Checklist de capturas para el documento:** 1 catálogo - 2 orden pendiente -
3a worker DONE - 3b orden completada YAU - 4 recomendaciones - 5a dashboard -
5b auditoría. ¡Siete imágenes que cubren todos los requisitos!


---

## 4. Insumos teóricos para el documento (Clases 09 a 12, borrador redactable)

> Cada viñeta es un párrafo casi listo: cita el archivo, explica el concepto de
> clase y cómo nuestro código lo aplica. Úsenlas como base de la redacción final.

### Capa de Datos (Clase 09 - modelo relacional e integridad)
- **Tablas y claves:** `users` (identidad + `rol` + `plataforma_preferida`),
`products` (catálogo unificado local/YAuctions con `sku` UNIQUE y `precio`
DECIMAL exacto), `orders` (puente transaccional con FK a `users` y `products` en
`RESTRICT`  - "no se puede borrar un cliente o producto con compras - " más máquina de
estados `pendiente -> en_proceso -> completada/fallida`) y `user_history`
(bitácora de eventos `vista/carrito/compra/recomendacion` con FK en `CASCADE`).
- **Dominios cerrados:** `plataforma` solo admite `local|yauctions` y `estado`
solo los 4 valores del ciclo de vida, tanto en la migración (`enum`) como en la
validación (`Rule::in`). Así la BD rechaza datos fuera de dominio.
- **Eloquent como traductor objeto-relacional:** los modelos (`User`, `Product`,
`Order`, `UserHistory`) exponen relaciones (`hasMany`, `belongsTo`) y scopes
(`visibles()`, `completadas()`) para que el resto del sistema hable en objetos
y nunca escriba SQL disperso; los bindings parametrizados previenen inyección.

### Capa de Persistencia (Clase 10  - " transacciones e integridad de datos)
- **Atomicidad con `DB::transaction`:** el Job confirma cada orden dentro de una
transacción (estado + `external_reference` + `sincronizado_en` + auditoría se
guardan juntos o no se guarda nada). Si la API falla, la BD nunca queda a medias.
- **Bloqueo pesimista:** `lockForUpdate()` al tomar la orden evita que dos
workers confirmen la misma compra dos veces (condición de carrera).
- **Protección en el modelo:** `$fillable` (lista blanca anti mass-assignment),
`$hidden` (la contraseña y el JSON interno jamás salen a vistas/JSON), casts
(`decimal`, `datetime`) y `forceFill` solo dentro de la capa autorizada.
- **Trazabilidad total:** `Order::registrarAuditoria()` anexa cada fase
(`inicio_sincronizacion`, `venta_local_confirmada`, `sincronizacion_exitosa`,
`fallo_comunicacion`) con payload y no. de intento al JSON `datos_sincronizacion`.


### Capa de Negocio (Clase 11  - " reglas, servicios y colas)
- **Servicio desacoplado:** `RecommendationService` aísla el algoritmo
(recomendarPara / registrarRecomendaciones / reporteDeEfectividad) del
controlador mediante inyección de dependencias: el controlador pide
-recomendacionesó sin saber cómo se calculan (reemplazable por un modelo ML).
- **Heurística explicable:** perfil por categoría (pesos 5/3/1), +2 por
coincidencia exacta, exclusión de lo ya comprado, *cold-start* por tendencia
global y puntaje redondeado que la vista muestra ((Afinidad 12.40ó).
- **Job asíncrono:** `ProcessYAuctionPurchase` (cola `compras-yauctions`,
3 intentos, backoff 30/120/300 s) separa la llamada HTTP lenta de la respuesta
al usuario; distingue compras `local` (confirmación directa) de `yauctions`
(API REST con token + timeout); convierte todo fallo en
`YAuctionCommunicationException` tipada (timeout / HTTP / regla de negocio) que
siempre deja auditoría; `failed()` marca la orden como `fallida` al agotar
reintentos.
- **Interfaz prevista por YAuctions:** `YAuctionsFakeController`
(`GET /simulador-yauctions/catalogo`, `POST /simulador-yauctions/comprar`)
implementa el contrato de la tienda japonesa en local: el Job la consume cuando
`YACTIONS_URL` apunta a esta app y consumiría la API real con el mismo código.

### Capa de Presentación (Clase 12 - MVC, validación y diseño visual)
- **MVC estricto:** `routes/web.php` mapea URL->controlador; los controladores
(`StoreController`, `BiDashboardController`) orquestan sin SQL; las vistas Blade
(`store/*`, `orders/*`, `dashboard-bi`, `bi/auditoria`, `auth/*`) solo renderizan
datos entregados. Flujo unidireccional Modelo -> Controlador -> Vista.
- **Validación en frontera:** `StoreOrderRequest` (producto existente, cantidad
1-10, plataforma válida) y `StoreRegisterRequest` (email único, contraseña min 8
+ confirmación) verifican ANTES de la Capa de Negocio; el controlador solo usa
`validated()`, nunca `all()` (defensa en profundidad).
- **Seguridad por capas:** `auth` (solo registrados compran),
`can:administrador` (solo admin ve el BI), `guest` (solo invitados se registran),
`@csrf` en cada formulario, `{{ }}` escapado anti-XSS y scopes anti-IDOR.
- **Diseño responsive y accesible:** HTML semántico, mobile-first (1 columna ->
tablet -> 4 en escritorio con Grid + `minmax()`), skip-link, ARIA
(`role=search/status/alert`), tablas con `scope`, color + texto en los estados
y táctiles de 44 px. El seguimiento usa PRG + auto-refresh como
retroalimentación del proceso asíncrono.

---

## 5. Mapa de archivos -> capas (para anexar al documento)

| Capa | Archivo | Clase | Qué demuestra |
|------|---------|-------|---------------|
| Datos/Persistencia | `database/migrations/2026_10_10_00000{1,2,3,4}_*.php` | 09 - "10 | 4 tablas, FK RESTRICT/CASCADE, enums, índices |
| Datos/Persistencia | `app/Models/{User,Product,Order,UserHistory}.php` | 10 | ORM/DAO, `$fillable`/`$hidden`, scopes, auditoría |
| Negocio | `app/Services/RecommendationService.php` | 11 | Motor ponderado + reporte de efectividad para BI |
| Negocio | `app/Jobs/ProcessYAuctionPurchase.php` | 11 | Cola, transacciones, try/catch, backoff, auditoría |
| Negocio | `app/Exceptions/YAuctionCommunicationException.php` | 11 | Excepciones tipadas con contexto |
| Negocio | `app/Http/Controllers/YAuctionsFakeController.php` | 11 | Interfaz/contrato de la tienda japonesa |
| Presentación | `app/Http/Requests/{StoreOrderRequest,StoreRegisterRequest}.php` | 12 | Validación en frontera |
| Presentación | `app/Http/Controllers/{StoreController,BiDashboardController,RegisterController,AuthController}.php` | 12 | Orquestación MVC + autorización |
| Presentación | `resources/views/{store,orders,bi,auth,dashboard-bi}.blade.php` | 12 | Vistas responsive y accesibles |
| Presentación | `routes/web.php` | 12 | Enrutamiento RESTful + middlewares |
| Demo | `database/seeders/{DatabaseSeeder,DemoDataSeeder}.php` | 10 | Datos reales para BI y recomendaciones |

*Supuesto académico declarado: `orders` guarda una línea de producto (en
producción sería `order_items` 1:N); el motor es heurístico y reemplazable por ML
(DIP); la API japonesa real se sustituye por el simulador local con el mismo
contrato.*

