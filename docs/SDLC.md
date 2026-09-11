# Flujo vigente: solicitud como buscador

Adaptación móvil: estilos comunes en `public/assets/responsive.css`, formularios de una columna, campos de 16 px y controles táctiles, navegación administrativa en cuadrícula y tablas convertidas en tarjetas con etiquetas a partir de 700 px. Los encabezados conservan sus roles y asociaciones; en escritorio se mantiene la tabla. Las cargas y modales se ajustan al ancho y alto disponibles. Se verificaron 10 vistas con datos ficticios a 320, 390, 768 y 1280 px (40 combinaciones), ausencia de desbordamiento y funcionamiento de fecha desconocida, selector de carga y confirmación/cancelación de reapertura. Se revisaron visualmente el formulario real y un modal ficticio a 390 px. No se enviaron correos en esta validación.

Reapertura administrativa: confirmación modal con radicado, Cancelar y Confirmar. Guarda fecha, administrador y el último trabajo existente como límite del ciclo; conserva los estados de entrega. Mientras no exista un envío real posterior a ese límite, el caso figura pendiente de gestión fuera del historial de cerrados y primero en Solicitudes y envíos. Reabrir no encola mensajes. La operación es transaccional, exige administrador activo y CSRF, bloquea envíos activos y detecta formularios obsoletos. Pruebas con datos ficticios cubren persistencia, auditoría, no duplicación, ausencia de envíos y cierre por un envío posterior incluso en el mismo segundo.

Historial de enviadas: vista administrativa paginada con radicado/fecha, paciente, atención, tipo, cierre, aprobación automática y ojo para detalle en modal. Se deriva de solicitudes con coincidencia validada (o verificación del flujo anterior) y al menos un trabajo de historia enviado con fecha de aceptación SMTP. No requiere migración ni intervención manual. Conserva una fila por solicitud y la fecha del primer envío exitoso aunque un reenvío posterior falle. Excluye simulaciones y trabajos pendientes; no implica verificación de identidad ni recepción en la bandeja del paciente. Se probaron inclusión/exclusión, búsquedas, persistencia del cierre, deduplicación, acceso autenticado y navegación al modal.

Carga masiva: selector múltiple con límites de cantidad y tamaño, comprobación de recepción completa, lectura previa de todos los PDF y agrupación por paciente e ingreso. Un archivo no identificable impide publicar el lote; los conflictos de datos identificables se informan por atención. Conserva integridad, transacciones, anexos y versiones. Se verifica agrupación, deduplicación con diferente orden, errores y carga multipart autenticada con documentos ficticios, sin envío de correos.

El detalle administrativo de envío se abre en un modal desde el botón de ojo, conservando búsqueda y paginación. Usa diálogo nativo con cierre por botón, Escape y fondo, carga autenticada sin caché y cancelación de peticiones al cerrar. La vista completa queda disponible como alternativa sin JavaScript.

Gestión administrativa: se añade búsqueda paginada por nombre, radicado y documento, y detalle mediante botón de ojo. El detalle muestra todos los envíos de historia (original y reenvíos), destinatario, estado, tiempos, intentos, errores y PDF asociados. Solo permite lectura con sesión administrativa. Las nuevas solicitudes conservan el documento declarado para buscar también solicitudes sin coincidencia. Pruebas de consultas, paginación, historial, acceso HTTP, escape de entradas y respuesta 404.

Se añade **No recuerdo la fecha** al formulario. El calendario deja de ser obligatorio al marcar la opción; la fecha se almacena como NULL y la solicitud presenta la última consulta disponible para elección explícita. La validación de paciente, especialidad, integridad y destinatario se mantiene. La migración permite fechas nulas sin alterar datos existentes.

La fecha de las solicitudes nuevas se compara primero por día exacto. Si no hay una historia disponible, se recomienda la consulta más reciente con paquete íntegro del mismo paciente y especialidad; sin especialidad seleccionada, se consideran todas. La fecha alternativa puede quedar fuera del rango anterior de 30 días. El paciente debe elegirla para generar el envío. La selección vuelve a validar la recomendación y conserva el correo declarado.

Se incorpora el botón **Reenviar historia clínica** en el estado de entrega: mismo paquete y destinatario, espera de 60 segundos, máximo de tres reenvíos manuales y bloqueo mientras haya un envío activo. Conserva cada intento y vuelve a comprobar la integridad del paquete. Las pruebas cubren espera, solicitudes repetidas, paquetes retirados o alterados, límite de reenvíos, estados de la interfaz, sesión y CSRF.

El formulario inicia directamente la comparación con los datos extraídos de los PDF. Compara tipo/número de documento, nombre normalizado, fecha aproximada y especialidad. Una coincidencia encola el paquete automáticamente para el correo declarado; varias requieren elegir la atención; ninguna muestra el resultado sin crear un envío. No se generan códigos para solicitudes nuevas y no se registra una identidad verificada por el mero hecho de coincidir.

Por decisión del usuario, el envío SMTP de las solicitudes nuevas usa el correo declarado sin registro ni autorización previa. El modo demo tampoco limita estas entregas a test_recipient. Se mantienen los controles de coincidencia, integridad y destinatario de la solicitud, sin marcar identidad ni correo como verificados. SMTP está configurado y Gmail aceptó una prueba sin datos clínicos. Las simulaciones anteriores no se reenvían automáticamente.

Validación de este cambio: suite de dominio con destinatarios SMTP no registrados, dirección inválida, paciente desactivado y manipulación del destino en la cola. El transporte de prueba utiliza exclusivamente 127.0.0.1 con credenciales ficticias.

---

## Historial de decisiones previas

# Cambio de alcance: indexación automática desde PDF

La implementación vigente reemplaza el registro manual de pacientes y atenciones por extracción local del PDF. El panel solicita solo los documentos del paquete y la confirmación de completitud. Se agregó el lector CEDIM, el conteo de páginas, detección de inconsistencias y deduplicación/versionado. El primer ejemplo real fue reindexado y está disponible para pruebas locales.

En modo local, los códigos usan el correo declarado sin exigir registro previo del paciente. Esa excepción no aplica a SMTP externo: la extracción del PDF no verifica identidad ni autorización del destinatario. El envío externo sigue pendiente de configuración y validación de destinatario.

Se verificaron ambos ejemplos reales sin enviarlos, 12 comprobaciones del lector, 47 de dominio, 18 HTTP y 10 de carga. La guía vigente está en README.md.

---

## Registro histórico de la fase inicial (sustituido donde contradiga el cambio anterior)

# Seguimiento SDLC · versión de desarrollo 0.1

## Fases 1–3: alcance, requisitos y diseño

Objetivo: solicitud y entrega automática de la historia completa de una atención, después de verificar acceso al correo previamente registrado. No hay aprobación humana por solicitud. Un repositorio local simula el sistema clínico de origen.

Estructura: paciente → atenciones (fecha, especialidad, ingreso) → versión del paquete → uno o varios PDF originales. El paciente solicita solo historias clínicas; no hay selección independiente de resultados de estudios.

El formulario recoge tipo y número de identificación, nombre, fecha aproximada, especialidad (o «No recuerdo») y correo electrónico obligatorio. Este correo se guarda en la solicitud; el destino de verificación y entrega procede del registro confiable del paciente. La carga y asociación inicial del repositorio es administrativa.

## Fase 4: implementación

| Requisito | Implementación | Evidencia |
|---|---|---|
| RF-01 Repositorio simulado | Repository, panel interno, seed-demo | Prueba de carga multipart |
| RF-02 Paquetes disponibles | Publicación inicial completa y retiro | Integridad y retiro antes de envío |
| RF-03 Radicado | Application::createRequest | Solicitud en navegador y BD |
| RF-04 Verificación | Código al correo registrado | Código válido y acceso sin verificar |
| RF-05 Control de códigos | Hash, cifrado de cola, caducidad, intentos y reenvío | Código vencido, usado y agotado |
| RF-06 Búsqueda | Paciente + rango ±30 días + especialidad | Filtros y exclusión de otras personas |
| RF-07 Selección de atención | Pantalla de coincidencias | Recorrido con dos atenciones |
| RF-08 Sin coincidencias | Ajustar filtros sin cambiar identidad | Restricciones de filtros y estado |
| RF-09 Preparación | Verificación de archivos, hash y tamaño | Adjuntos completos, faltante y alterado |
| RF-10 Correo automático | MailWorker + PHPMailer + cola | MIME local con todos los PDF; SMTP externo pendiente |
| RF-11 Fallos y reintentos | Hasta tres intentos, estado incierto | Conexión SMTP local fallida y worker abandonado |
| RF-12 Estado | Pantalla vinculada a sesión verificada | Entrega simulada observada en navegador |
| RF-13 Trazabilidad | Eventos y trabajos de correo | Radicado y estado visibles en panel |

Se crearon la base MySQL, formularios, catálogo de especialidades, acceso administrativo, almacenamiento privado, verificador, procesador de correo y datos ficticios. La búsqueda no extrae ni analiza el contenido médico del PDF.

## Comprobaciones durante el desarrollo

- 40 comprobaciones de dominio e integración MySQL/PHPMailer.
- 18 comprobaciones HTTP de rutas, CSRF, sesiones y archivos privados.
- 7 comprobaciones de acceso administrativo y carga de PDF.
- Recorrido visual de escritorio: solicitud → código local → dos atenciones → entrega simulada → bandeja con código y correo de historia.
- Sintaxis PHP verificada.

Se corrigieron dos problemas específicos de Windows: clasificación de errores de socket como errores SMTP permanentes y permisos heredados al mover archivos temporales de Apache. La carga ahora copia los bytes a la carpeta privada, conservando sus permisos de destino.

La revisión visual móvil no pudo completarse por un tiempo de espera de la herramienta de navegador. El CSS contiene adaptación para pantallas pequeñas, pendiente de confirmar visualmente en la fase de pruebas.

## Ejemplos proporcionados

Se revisaron un paquete de cinco páginas y otro de once páginas. El segundo incluye reporte clínico, medicamentos y control, con numeraciones internas que reinician. Se mantiene el PDF íntegro. La fecha de impresión puede diferir de la fecha de atención; la búsqueda usa esta última. Se añadió Alergología al catálogo.

Inicialmente solo se probaron documentos ficticios. Por solicitud posterior se preparó el primer PDF real en el repositorio privado, con paciente deshabilitado y paquete pendiente de destinatario autorizado y SMTP; no se ha enviado. El segundo ejemplo evidencia la necesidad futura de diseñar el flujo de representantes de menores; no se supone que un código de correo pruebe esa representación.

## Fase 5: validación pendiente

- Prueba de aceptación con el usuario y revisión móvil.
- Configurar SMTP en el archivo privado y comprobar recepción efectiva en el destinatario autorizado.
- Casos ampliados de errores SMTP, concurrencia y recuperación operativa.
- Decidir proceso de registro y cambio confiable del correo y autorización de representantes antes de introducir datos reales.

## Fases 6–7

No se ha desplegado a Internet ni se ha instalado una tarea permanente. La cola local puede ejecutarse con el script de arranque. Para producción faltan configuración HTTPS, permisos de cuenta DB, almacenamiento fuera de raíces públicas, respaldos, política de retención, supervisión y la integración con el origen clínico real.

## Ventana de progreso del envío

Mientras una solicitud del paciente está en cola, enviándose o esperando reintento, se abre una ventana con fondo sombreado, encabezado celeste e indicador circular animado. Consulta el estado cada cinco segundos sin recargar la pantalla durante la espera; al salir de esos estados, carga el resultado de la solicitud. La ventana puede cerrarse sin cancelar el envío y respeta la preferencia de movimiento reducido. El círculo es indeterminado porque la cola no mide un porcentaje real. Sin soporte de diálogo se conserva la actualización anterior. Comprobados los tres mensajes y el cierre en navegador con datos sintéticos; sintaxis PHP y JavaScript validada, sin enviar correos.

## Progreso de carga de PDF

La carga individual y la masiva muestran una ventana con fondo sombreado y un círculo con el porcentaje real de transferencia. Al terminar la subida, el indicador pasa a indeterminado mientras PHP lee, valida y guarda los PDF. Las peticiones de carga con Accept: application/json reciben el destino de navegación o el error; el formulario tradicional mantiene sus redirecciones. Se conservan CSRF, autenticación, archivos seleccionados tras errores y el informe de carga masiva. No se reintenta automáticamente una petición cuyo resultado se desconoce. Validación: node tests/upload-progress.cjs; sintaxis PHP/JS; comprobación HTTP de CSRF y acceso protegido en JSON y HTML; revisión visual al 56% con transferencia simulada, sin documentos ni correos reales.

## Límites de carga en Docker

El contenedor carga docker/uploads.ini: 12 MiB por PDF, post_max_size de 34 MiB (margen para los formularios de lotes de hasta 32 MiB), 20 archivos y 180 segundos de procesamiento. Los avisos PHP se registran sin contaminar las respuestas JSON. Antes de iniciar la aplicación se rechazan con HTTP 413 las peticiones que exceden post_max_size; se reconoce una carga JSON por su tipo multipart incluso cuando PHP descarta POST y FILES. El modal diferencia errores 413, sesión y timeout del proxy. Se reprodujo una petición multipart de 2290 bytes contra PHP con límite de 1 KiB y se verificó la respuesta JSON 413. Pruebas: php tests/upload-http.php y node tests/upload-progress.cjs. El cambio de límites requiere reconstruir la imagen en Dokploy. La causa concreta de una incidencia en el VPS debe confirmarse con tamaño de archivos y registros del servidor.

Corrección del destino de subida: los formularios incluyen un input oculto llamado action, que oculta la propiedad form.action en el DOM. La ventana de progreso utilizaba esa propiedad como URL y podía enviar a [object HTMLInputElement], recibiendo una respuesta HTML inesperada incluso con PDF pequeños. Ahora obtiene el atributo mediante getAttribute('action') y usa la URL actual cuando no existe. La regresión simula el campo oculto y comprueba el destino POST; también se verificó en navegador con un formulario sintético y transporte simulado. La captura del caso muestra dos PDF de 1 MB en total, dentro de los límites existentes, por lo que el ajuste de límites es independiente de este fallo.
