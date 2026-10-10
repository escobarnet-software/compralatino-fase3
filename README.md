# CompraLatino â€” Fase III
## Inteligencia de Negocios y Funcionalidad Â· GuÃ­a del equipo + Fundamento teÃ³rico (Clases 09â€“12)

> **Para el equipo:** este README es la guÃ­a paso a paso para levantar el
> proyecto, auditarlo, tomar las capturas del documento y extraer la teorÃ­a.
> Si no escribiste el cÃ³digo, no te preocupes: cada secciÃ³n explica **quÃ© hace**,
> **dÃ³nde hacer clic** y **por quÃ© funciona asÃ­** segÃºn la arquitectura por capas.

**Proyecto ejecutable:** `compralatino-app/` (Laravel 11 + SQLite + colas en BD).
**CÃ³digo acadÃ©mico para anexar:** `app/`, `database/`, `resources/views/`, `routes/`
del nivel superior (copia sincronizada del cÃ³digo que corre en `compralatino-app/`).

---

## 1. Resumen de la Fase III (quÃ© hace este cÃ³digo)

CompraLatino es un **e-commerce intermediario (monolito en Laravel)**: el cliente
compra en nuestra plataforma y nosotros ejecutamos la compra real en la tienda
externa japonesa **YAuctions**. La clave arquitectÃ³nica heredada de la Fase II es
que esa comunicaciÃ³n externa **no bloquea al usuario**: al confirmar la compra, el
controlador crea la orden en estado `pendiente`, despacha un **Job a la cola
`compras-yauctions`** y responde al instante. Un worker en segundo plano
(`queue:work`) contacta a YAuctions, recibe el acuse (`YAU-xxxxxxxx`) y marca la
orden como `completada`. La pÃ¡gina de seguimiento se auto-recarga cada 4 segundos,
asÃ­ el usuario *ve* el avance sin esperar.

La Fase III agrega tres requisitos sobre esa base:

1. **Plataforma e-commerce multiplataforma.** Registro de cuentas
(`/registro`), catÃ¡logo con **buscador** (texto + categorÃ­a + plataforma),
compras **locales** (confirmaciÃ³n inmediata, acuse `LOCAL-{id}`) y compras
**YAuctions** (asÃ­ncronas, acuse `YAU-â€¦`). Cada producto declara su
`plataforma` y su `external_id` (puente con JapÃ³n).
2. **Motor de recomendaciones basado en historial.** `RecommendationService`
construye un perfil de intereses por categorÃ­a ponderando eventos
(compra = 5, carrito = 3, vista = 1), excluye lo ya comprado y ordena por
puntaje de afinidad. Sin historial usa *cold-start* por tendencia. Todo lo que
muestra queda registrado en la bitÃ¡cora para medir conversiÃ³n.
3. **Dashboard de Inteligencia de Negocios (BI).** Panel administrativo
(`/admin/dashboard`) con KPIs reales: ingresos, Ã³rdenes, ticket promedio,
conversiÃ³n del recomendador, ventas por plataforma/dÃ­a, top productos y fallos
de sincronizaciÃ³n; mÃ¡s una vista de **auditorÃ­a** (`/admin/auditoria`) con el
JSON de cada fallo. El seeder genera ~90 Ã³rdenes de 30 dÃ­as para que las
mÃ©tricas nunca salgan vacÃ­as.

## 2. GuÃ­a de instalaciÃ³n y ejecuciÃ³n (desde cero)

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

# 4. Crear las tablas Y cargar datos de prueba (Â¡un solo comando!)
php artisan migrate --seed
# Crea: 1 admin + 8 clientes, 18 productos (local + YAuctions),
# ~90 Ã³rdenes de 30 dÃ­as en 4 estados y bitÃ¡cora de comportamiento.

# 5. Levantar el servidor (TERMINAL 1 â€” dejarla abierta)
php artisan serve
# Abrir en el navegador: http://127.0.0.1:8000

# 6. Procesar las colas (TERMINAL 2 â€” dejarla abierta) â­ EL MÃS IMPORTANTE
php artisan queue:work --queue=compras-yauctions --tries=3 --backoff=30,120,300 -v
# Sin este worker las compras YAuctions se quedan en "pendiente" para siempre.
# Con Ã©l, se completan solas en segundos con su acuse YAU-xxxxxxxx.
```

**Cuentas de prueba (creadas por el seeder):**

| Rol | Email | ContraseÃ±a | Puede ver |
|-----|-------|------------|-----------|
| Administradora BI | `admin@compralatino.test` | `secret1234` | Tienda + `/admin/dashboard` + `/admin/auditoria` |
| Clientes (8) | `cliente1@compralatino.test` â€¦ `cliente8@compralatino.test` | `password` | Tienda, mis Ã³rdenes, Para ti |

**ConfiguraciÃ³n clave (ya viene lista en `.env`):**

| Variable | Valor | Para quÃ© sirve |
|----------|-------|----------------|
| `DB_CONNECTION` | `sqlite` | BD en un archivo, sin servidor externo |
| `QUEUE_CONNECTION` | `database` | Las colas se guardan en la tabla `jobs` |
| `YACTIONS_URL` | `http://127.0.0.1:8000` | Apunta al **simulador local** de YAuctions (ver Â§3) |
| `YACTIONS_TOKEN` | `demo-token` | Token del simulador |

> Para demostrar el manejo de fallos (auditorÃ­a de errores), cambia
> temporalmente `YACTIONS_URL=http://127.0.0.1:9` (puerto muerto), reinicia el
> worker y compra algo YAuctions: verÃ¡s la orden en `en_proceso` con su fallo
> auditado en `/admin/auditoria`. Luego restaura el valor y reprocesa.


---

## 3. GuÃ­a de auditorÃ­a y capturas (paso a paso para el documento)

> Pre-requisitos: servidor corriendo (`php artisan serve`) y worker corriendo
> (`queue:work --queue=compras-yauctions`). Navegador en `http://127.0.0.1:8000`.
> Cada paso indica **quÃ© captura tomar** y **quÃ© demuestra** para el informe.

### Paso 1 â€” Registro / Login de usuario
1. Ve a `http://127.0.0.1:8000/registro` â†’ completa nombre, correo, contraseÃ±a
(2 veces) y plataforma preferida â†’ **Crear cuenta**. EntrarÃ¡s directo al catÃ¡logo.
2. Alternativa: `http://127.0.0.1:8000/login` con `cliente1@compralatino.test` /
`password`.
3. ðŸ“¸ **Captura 1:** catÃ¡logo con tu nombre arriba y la secciÃ³n
â€œâœ¨ Recomendados para tiâ€.
*Demuestra: alta de cuentas multiplataforma + autenticaciÃ³n por sesiÃ³n.*

### Paso 2 â€” Compra (orden en estado Pendiente)
1. Clic en cualquier producto con badge **YAuctions** â†’ elige cantidad 1 y
plataforma `YAuctions` â†’ **Confirmar compra**.
2. SerÃ¡s redirigido a `/ordenes/{id}`: verÃ¡s el badge **Pendiente** (la pÃ¡gina se
recarga sola cada 4 s mientras estÃ© en trÃ¡nsito).
3. ðŸ“¸ **Captura 2:** seguimiento con estado **Pendiente**, **inmediatamente**
despuÃ©s de comprar (antes de que el worker la tome).
*Demuestra: respuesta instantÃ¡nea desacoplada de la API externa (Fase II).*

### Paso 3 â€” Worker en acciÃ³n (orden Completada con acuse YAU-XXX) â­
1. Si el worker NO corrÃ­a, Ã¡brelo ahora en la Terminal 2:
`php artisan queue:work --queue=compras-yauctions -v`. Observa la lÃ­nea
`ProcessYAuctionPurchase ... DONE` (â‰ˆ400 ms: es la llamada a YAuctions).
2. Recarga la pÃ¡gina de tu orden: ahora muestra **Completada** + acuse
**`YAU-xxxxxxxx`** + fecha de sincronizaciÃ³n.
3. ðŸ“¸ **Captura 3a:** terminal del worker con el Job en `DONE`.
ðŸ“¸ **Captura 3b:** la misma orden ahora **Completada** con su cÃ³digo YAU.
*Demuestra: comunicaciÃ³n asÃ­ncrona real â€” la prueba reina de la arquitectura.*
4. (Opcional, para el informe de excepciones) Para una captura de fallo: detÃ©n el
servidor 10 segundos, compra otro producto YAuctions, reinicia el servidor y el
worker; la orden pasarÃ¡ a **En proceso** y en `/admin/auditoria` verÃ¡s el JSON
del fallo con su reintento programado.

### Paso 4 â€” Recomendaciones (motor basado en historial)
1. Como cliente, visita `http://127.0.0.1:8000/para-ti`: verÃ¡s hasta 12 tarjetas
con su **puntaje de afinidad** (â€œAfinidad 12.40 Â· â€¦â€).
2. Compra algo de una categorÃ­a nueva y vuelve a `/para-ti`: las sugerencias
cambian (el perfil se recalculÃ³ con tu compra).
3. ðŸ“¸ **Captura 4:** pÃ¡gina â€œPara tiâ€ con puntajes visibles.
*Demuestra: ofertas personalizadas a partir del historial (requisito Fase III).*

### Paso 5 â€” Dashboard BI (mÃ©tricas reales del seeder)
1. Cierra sesiÃ³n y entra como `admin@compralatino.test` / `secret1234` â†’ irÃ¡s
directo a `http://127.0.0.1:8000/admin/dashboard`.
2. Recorre: KPIs (ingresos 30 dÃ­as, Ã³rdenes, ticket, conversiÃ³n del recomendador,
fallos), grÃ¡fico de barras por dÃ­a, ventas por plataforma, top 5 productos.
3. Visita `http://127.0.0.1:8000/admin/auditoria`: tabla de fallos con payload.
4. ðŸ“¸ **Captura 5a:** dashboard con KPIs y grÃ¡ficas.
ðŸ“¸ **Captura 5b:** auditorÃ­a con un fallo desplegado (`<details>` del JSON).
*Demuestra: herramienta BI para monitoreo y toma de decisiones con datos reales.*

**Checklist de capturas para el documento:** 1 catÃ¡logo Â· 2 orden pendiente Â·
3a worker DONE Â· 3b orden completada YAU Â· 4 recomendaciones Â· 5a dashboard Â·
5b auditorÃ­a. Â¡Siete imÃ¡genes que cubren todos los requisitos!


---

## 4. Insumos teÃ³ricos para el documento (Clases 09 a 12, borrador redactable)

> Cada viÃ±eta es un pÃ¡rrafo casi listo: cita el archivo, explica el concepto de
> clase y cÃ³mo nuestro cÃ³digo lo aplica. Ãšsenlas como base de la redacciÃ³n final.

### Capa de Datos (Clase 09 â€” modelo relacional e integridad)
- **Tablas y claves:** `users` (identidad + `rol` + `plataforma_preferida`),
`products` (catÃ¡logo unificado local/YAuctions con `sku` UNIQUE y `precio`
DECIMAL exacto), `orders` (puente transaccional con FK a `users` y `products` en
`RESTRICT` â€”no se puede borrar un cliente o producto con comprasâ€” mÃ¡s mÃ¡quina de
estados `pendiente â†’ en_proceso â†’ completada/fallida`) y `user_history`
(bitÃ¡cora de eventos `vista/carrito/compra/recomendacion` con FK en `CASCADE`).
- **Dominios cerrados:** `plataforma` solo admite `local|yauctions` y `estado`
solo los 4 valores del ciclo de vida, tanto en la migraciÃ³n (`enum`) como en la
validaciÃ³n (`Rule::in`). AsÃ­ la BD rechaza datos fuera de dominio.
- **Eloquent como traductor objeto-relacional:** los modelos (`User`, `Product`,
`Order`, `UserHistory`) exponen relaciones (`hasMany`, `belongsTo`) y scopes
(`visibles()`, `completadas()`) para que el resto del sistema hable en objetos
y nunca escriba SQL disperso; los bindings parametrizados previenen inyecciÃ³n.

### Capa de Persistencia (Clase 10 â€” transacciones e integridad de datos)
- **Atomicidad con `DB::transaction`:** el Job confirma cada orden dentro de una
transacciÃ³n (estado + `external_reference` + `sincronizado_en` + auditorÃ­a se
guardan juntos o no se guarda nada). Si la API falla, la BD nunca queda a medias.
- **Bloqueo pesimista:** `lockForUpdate()` al tomar la orden evita que dos
workers confirmen la misma compra dos veces (condiciÃ³n de carrera).
- **ProtecciÃ³n en el modelo:** `$fillable` (lista blanca anti mass-assignment),
`$hidden` (la contraseÃ±a y el JSON interno jamÃ¡s salen a vistas/JSON), casts
(`decimal`, `datetime`) y `forceFill` solo dentro de la capa autorizada.
- **Trazabilidad total:** `Order::registrarAuditoria()` anexa cada fase
(`inicio_sincronizacion`, `venta_local_confirmada`, `sincronizacion_exitosa`,
`fallo_comunicacion`) con payload y nÂº de intento al JSON `datos_sincronizacion`.


### Capa de Negocio (Clase 11 â€” reglas, servicios y colas)
- **Servicio desacoplado:** `RecommendationService` aÃ­sla el algoritmo
(recomendarPara / registrarRecomendaciones / reporteDeEfectividad) del
controlador mediante inyecciÃ³n de dependencias: el controlador pide
â€œrecomendacionesâ€ sin saber cÃ³mo se calculan (reemplazable por un modelo ML).
- **HeurÃ­stica explicable:** perfil por categorÃ­a (pesos 5/3/1), +2 por
coincidencia exacta, exclusiÃ³n de lo ya comprado, *cold-start* por tendencia
global y puntaje redondeado que la vista muestra (â€œAfinidad 12.40â€).
- **Job asÃ­ncrono:** `ProcessYAuctionPurchase` (cola `compras-yauctions`,
3 intentos, backoff 30/120/300 s) separa la llamada HTTP lenta de la respuesta
al usuario; distingue compras `local` (confirmaciÃ³n directa) de `yauctions`
(API REST con token + timeout); convierte todo fallo en
`YAuctionCommunicationException` tipada (timeout / HTTP / regla de negocio) que
siempre deja auditorÃ­a; `failed()` marca la orden como `fallida` al agotar
reintentos.
- **Interfaz prevista por YAuctions:** `YAuctionsFakeController`
(`GET /simulador-yauctions/catalogo`, `POST /simulador-yauctions/comprar`)
implementa el contrato de la tienda japonesa en local: el Job la consume cuando
`YACTIONS_URL` apunta a esta app y consumirÃ­a la API real con el mismo cÃ³digo.

### Capa de PresentaciÃ³n (Clase 12 â€” MVC, validaciÃ³n y diseÃ±o visual)
- **MVC estricto:** `routes/web.php` mapea URLâ†’controlador; los controladores
(`StoreController`, `BiDashboardController`) orquestan sin SQL; las vistas Blade
(`store/*`, `orders/*`, `dashboard-bi`, `bi/auditoria`, `auth/*`) solo renderizan
datos entregados. Flujo unidireccional Modelo â†’ Controlador â†’ Vista.
- **ValidaciÃ³n en frontera:** `StoreOrderRequest` (producto existente, cantidad
1â€“10, plataforma vÃ¡lida) y `StoreRegisterRequest` (email Ãºnico, contraseÃ±a min 8
+ confirmaciÃ³n) verifican ANTES de la Capa de Negocio; el controlador solo usa
`validated()`, nunca `all()` (defensa en profundidad).
- **Seguridad por capas:** `auth` (solo registrados compran),
`can:administrador` (solo admin ve el BI), `guest` (solo invitados se registran),
`@csrf` en cada formulario, `{{ }}` escapado anti-XSS y scopes anti-IDOR.
- **DiseÃ±o responsive y accesible:** HTML semÃ¡ntico, mobile-first (1 columna â†’
tablet â†’ 4 en escritorio con Grid + `minmax()`), skip-link, ARIA
(`role=search/status/alert`), tablas con `scope`, color + texto en los estados
y tÃ¡ctiles de 44 px. El seguimiento usa PRG + auto-refresh como
retroalimentaciÃ³n del proceso asÃ­ncrono.

---

## 5. Mapa de archivos â†’ capas (para anexar al documento)

| Capa | Archivo | Clase | QuÃ© demuestra |
|------|---------|-------|---------------|
| Datos/Persistencia | `database/migrations/2026_10_10_00000{1,2,3,4}_*.php` | 09â€“10 | 4 tablas, FK RESTRICT/CASCADE, enums, Ã­ndices |
| Datos/Persistencia | `app/Models/{User,Product,Order,UserHistory}.php` | 10 | ORM/DAO, `$fillable`/`$hidden`, scopes, auditorÃ­a |
| Negocio | `app/Services/RecommendationService.php` | 11 | Motor ponderado + reporte de efectividad para BI |
| Negocio | `app/Jobs/ProcessYAuctionPurchase.php` | 11 | Cola, transacciones, try/catch, backoff, auditorÃ­a |
| Negocio | `app/Exceptions/YAuctionCommunicationException.php` | 11 | Excepciones tipadas con contexto |
| Negocio | `app/Http/Controllers/YAuctionsFakeController.php` | 11 | Interfaz/contrato de la tienda japonesa |
| PresentaciÃ³n | `app/Http/Requests/{StoreOrderRequest,StoreRegisterRequest}.php` | 12 | ValidaciÃ³n en frontera |
| PresentaciÃ³n | `app/Http/Controllers/{StoreController,BiDashboardController,RegisterController,AuthController}.php` | 12 | OrquestaciÃ³n MVC + autorizaciÃ³n |
| PresentaciÃ³n | `resources/views/{store,orders,bi,auth,dashboard-bi}.blade.php` | 12 | Vistas responsive y accesibles |
| PresentaciÃ³n | `routes/web.php` | 12 | Enrutamiento RESTful + middlewares |
| Demo | `database/seeders/{DatabaseSeeder,DemoDataSeeder}.php` | 10 | Datos reales para BI y recomendaciones |

*Supuesto acadÃ©mico declarado: `orders` guarda una lÃ­nea de producto (en
producciÃ³n serÃ­a `order_items` 1:N); el motor es heurÃ­stico y reemplazable por ML
(DIP); la API japonesa real se sustituye por el simulador local con el mismo
contrato.*

