# CitaYa · Historias clínicas desde PDF

La aplicación lee los PDF de las historias clínicas y crea automáticamente su índice de pacientes y atenciones. No se requiere registrar al paciente ni digitar su identificación, nombre, fecha o especialidad al cargar una historia.

## Uso local

- Portal: http://localhost/CitaYaV2/public/
- Repositorio privado: http://localhost/CitaYaV2/public/?page=admin
- Credenciales iniciales: `.runtime/first-login.txt`.

En el panel, selecciona **Cargar historia clínica desde PDF**, adjunta el paquete completo de una sola atención y confirma que incluye todos sus documentos. El sistema extrae los datos y guarda los archivos originales. Puedes seleccionar varios PDF que compongan una misma atención. En la carga individual, las historias de pacientes o ingresos diferentes deben cargarse por separado; la carga masiva las organiza automáticamente.

El formulario público funciona como un buscador: pide identificación, nombre, fecha aproximada, especialidad y correo electrónico. Compara estos datos con el índice extraído de los PDF. El nombre admite diferencias de mayúsculas, tildes y espacios; la identificación debe coincidir exactamente. Las solicitudes nuevas buscan primero el día indicado. Si no hay una historia disponible, sugieren la consulta más reciente del mismo paciente y especialidad con un paquete íntegro, incluso fuera del rango anterior de 30 días. Si se elige «No recuerdo la especialidad», se consideran todas. El rango configurado (±30 días) se conserva para el flujo histórico de verificación.

Al enviar el formulario:

La opción **No recuerdo la fecha** desactiva el calendario y permite buscar sin fecha. La solicitud conserva la fecha vacía hasta que el paciente elija la consulta más reciente disponible de su especialidad (o de cualquiera si no la recuerda). Esa elección completa la fecha real y prepara el envío. No se envían documentos automáticamente al marcar la opción. `setup.php` adapta la columna de fecha en instalaciones existentes sin modificar las solicitudes guardadas.

1. Si encuentra una única atención, prepara y encola automáticamente todos los PDF de su paquete para el correo indicado.
2. Si encuentra varias atenciones, muestra sus fechas y especialidades para elegir una.
3. Si no encuentra una historia para ese día, muestra la fecha de la consulta más reciente disponible y un botón para solicitar esa historia. La sugerencia no genera un envío hasta que el paciente la elija. Si no hay alternativas, permite iniciar otra búsqueda con los datos corregidos.

La sugerencia excluye otros pacientes, fechas futuras y paquetes retirados, incompletos o alterados. Al elegirla se vuelve a comprobar su disponibilidad, se actualiza la fecha de la solicitud y se encola únicamente la atención seleccionada al mismo correo.

Las nuevas solicitudes no muestran ni generan un código de verificación. No se marca la coincidencia como identidad o correo verificado: tiene su propio estado de búsqueda. La simulación guarda el correo con los PDF en **Acceso interno → Bandeja local**, sin envío externo. No hace falta registrar al paciente: el índice se crea automáticamente desde sus PDF.

## Carga masiva

En **Acceso interno → Repositorio → Carga masiva de PDF**, selecciona hasta 20 PDF (o el límite menor de PHP), máximo 12 MB por archivo y 32 MB por lote, ajustados a los límites del servidor. Confirma que incluiste todos los documentos de cada atención. El formulario indica cantidad y tamaño, detecta límites antes de enviar y el servidor comprueba que recibió el lote completo.

Primero se leen todos los documentos. Si algún PDF no puede identificarse, no se guarda el lote para evitar publicar una atención con anexos faltantes. Los documentos legibles se agrupan por tipo y número de documento e ingreso; cada grupo valida nombre, fecha y especialidad y admite hasta 15 PDF. Un conflicto dentro de un grupo no afecta a los grupos válidos. La carga repetida de los mismos archivos, aunque cambie su orden, reutiliza la historia existente. Un conjunto diferente mantiene el versionado de paquetes existente.

Al finalizar, se muestra un informe con archivos, paciente, fecha, historias guardadas, ya registradas y resultados que requieren revisión. La carga no crea solicitudes ni envía correos. La operación se ejecuta en una petición con hasta 180 segundos de procesamiento; para lotes mayores, utiliza varias cargas respetando los paquetes completos.

## Gestión administrativa de envíos

Para procesar una entrega pendiente concreta desde la consola: `php bin/worker.php --job=ID`. Ejecuta como máximo ese trabajo, conserva sus validaciones y no modifica otros trabajos, incluidos los abandonados. No admite `--loop` ni repite entregas ya completadas. El procesador general debe permanecer activo para atender automáticamente solicitudes nuevas.

En Repositorio, **Cargar historias clínicas** reúne dos opciones: **Una atención** (historia completa con anexos) y **Varias atenciones** (carga masiva, incluso de un mismo paciente). Solo se muestra el formulario elegido y cambiar de opción conserva los archivos seleccionados. El selector admite teclado y se adapta a pantallas pequeñas; sin JavaScript, ambos formularios siguen disponibles.

El botón verde **Reabrir caso** del historial abre una confirmación con el radicado, **Cancelar** y **Confirmar**. Al confirmar, pasa a **Pendiente de gestión** y aparece primero en Solicitudes y envíos. Se conservan los PDF y los correos anteriores, y se auditan administrador y fecha. Reabrir no genera correos; un envío real nuevo completa el caso y lo devuelve al historial. Se exige sesión administrativa, CSRF y un caso cerrado sin envíos en curso; se rechazan confirmaciones repetidas u obsoletas. Para actualizar una instalación existente: `php bin/migrate-case-reopening.php` (no carga datos de ejemplo).

En **Historial de enviadas**, las solicitudes validadas automáticamente aparecen como **Aprobado / Cerrado** cuando el servidor de correo acepta una entrega real. La tabla muestra radicado, paciente, atención, tipo de solicitud y fecha del primer envío exitoso; permite buscar por nombre, cédula o radicado y abrir el detalle en el modal con el ojo. Incluye los envíos anteriores que cumplen estas condiciones, sin duplicar solicitudes por reenvíos. Excluye simulaciones y solicitudes sin una entrega exitosa; un reenvío fallido no elimina el cierre anterior. «Aprobado» expresa la validación automática de coincidencias, no una aprobación humana ni una verificación de identidad.

En **Acceso interno → Solicitudes y envíos**, busca por nombre, parte del radicado o número de documento. Los resultados incluyen todas las solicitudes y se presentan en páginas de 20 filas. El botón de ojo abre una ventana modal sin cambiar la búsqueda ni la página de resultados. Puede cerrarse con el botón Cerrar, Escape o un clic fuera; al cerrar se devuelve el foco al ojo. Carga el detalle mediante una petición autenticada y sin caché, con mensajes para sesión vencida o errores de conexión. El detalle incluye: paciente, documento, correo, fecha de consulta y seguimiento del envío original y sus reenvíos, con estado, intentos, errores y archivos asociados. La vista no envía ni reenvía mensajes al abrirla. «Correo enviado» significa aceptación por el servidor, no recepción final ni lectura.

Las nuevas solicitudes conservan el documento declarado incluso cuando no encuentran un paciente. Para las solicitudes antiguas vinculadas se utiliza el documento del paciente; si nunca se vinculó y no se guardó el documento, se indica que ese dato no está disponible. El buscador y los detalles requieren sesión administrativa y usan consultas parametrizadas y encabezados sin caché.

## Lectura de documentos

El lector admite fechas con o sin ceros iniciales (por ejemplo, 7/09/2026 y 07/09/2026) y compara los anexos por fecha normalizada. Mantiene la validación de calendario y la comprobación de que todas las páginas pertenecen a la misma atención.

El lector está adaptado a los PDF con texto de CEDIM / Indigo compartidos: tipo y número de documento, nombres y apellidos, fecha y hora de ingreso (o fecha de historia si no hay ingreso), especialidad, ingreso, folio y número de páginas. Distingue los datos del paciente de los del profesional; no usa fecha de impresión ni de nacimiento como fecha de atención.

Se comprobó la lectura de los ejemplos de 5 y 11 páginas. Se conserva el paquete entero incluso si las numeraciones reinician en los anexos. Si una página no identifica al paciente, debe contener una referencia comprobable al mismo ingreso y fecha. Un PDF escaneado sin texto, ilegible o con datos incompatibles se rechaza con una explicación; OCR y otros formatos todavía no están implementados.

La base de datos almacena un índice de búsqueda automático, no diagnósticos extraídos. El PDF identifica a quién pertenece el documento, pero no prueba quién está usando el formulario ni certifica una dirección de correo.

## Instalación y cola

Requiere PHP 8.2, MySQL/MariaDB y Composer. En XAMPP deben estar activos Apache y MySQL.

```powershell
composer install
php bin/setup.php
php bin/seed-demo.php
php bin/worker.php --loop
```

`setup.php` crea la base configurada y agrega columnas faltantes sin borrar información. `seed-demo.php` es opcional y mantiene un paciente ficticio para probar el portal. La cola requiere un proceso activo: `worker.php --loop` comprueba trabajos cada tres segundos; sin el argumento procesa lo pendiente y termina. Reinícialo al cambiar código o configuración.

`bin/start-worker.ps1` inicia el proceso en segundo plano, guarda su PID y evita copias simultáneas. `bin/install-worker-task.ps1` instala la tarea **CitaYaV2 - Procesador de correos** en Windows: arranca al iniciar sesión y comprueba cada minuto si debe recuperar el proceso. Está configurada con la cuenta del usuario, sin privilegios elevados, y usa la ruta absoluta de PHP. Los registros locales quedan en `.runtime/worker-output.log` y `.runtime/worker-error.log`. Requiere el equipo encendido, sesión iniciada, MySQL e Internet disponibles; Apache debe estar activo para recibir formularios. No puede enviar mientras Windows esté apagado o suspendido.

También puedes importar una historia desde la consola:

```powershell
php bin/import-pdf.php "C:\ruta\historia.pdf" "C:\ruta\anexo.pdf"
```

Los archivos repetidos dentro de una carga se deduplican por SHA-256. Cargar otra vez el mismo paquete no duplica al paciente ni a su atención. Cargar un conjunto diferente para el mismo ingreso crea otra versión y retira la versión antes disponible. Las versiones ya enviadas no se modifican.

## Correo real y datos de prueba

El transporte predeterminado es `local`: PHPMailer genera mensajes MIME privados, sin transmitirlos a Internet. Esta instalación tiene SMTP configurado; Gmail aceptó un mensaje de prueba sin datos clínicos.

Para conectar SMTP, incorpora host, puerto, cifrado, usuario, contraseña y remitente en el archivo privado `config/local.php`, usando `config/local.example.php` como referencia. Conserva la clave `app_key`. No compartas contraseñas en el chat. Reinicia la cola después del cambio.

Las solicitudes nuevas de búsqueda directa entregan al correo válido escrito en el formulario, también por SMTP y en modo demo. No requieren que el correo esté registrado o autorizado en el paciente ni que coincida con `test_recipient`. Se mantienen la coincidencia de datos, la pertenencia e integridad del paquete y la igualdad entre el destinatario de la cola y el de la solicitud. Este flujo no comprueba identidad ni titularidad del correo. Los controles del flujo histórico de códigos se conservan para solicitudes antiguas. Las entregas simuladas anteriores no se reenvían automáticamente al activar SMTP: se puede usar el botón de reenvío en la sesión de la solicitud o iniciar una nueva solicitud.

La lectura automática del PDF no inventa el correo del paciente ni marca como verificado el correo del formulario.

## Protección y límites

En el estado de una entrega enviada, simulada, fallida o incierta aparece **Reenviar historia clínica**. Reutiliza el paquete original y el correo de la solicitud, con una espera de 60 segundos desde el último intento y hasta tres reenvíos manuales. No permite reenviar mientras un trabajo esté en cola, enviándose o esperando reintento automático. Cada reenvío conserva el historial anterior y tiene un identificador de mensaje nuevo; valida otra vez el paquete. Para resultados inciertos, la pantalla explica que podría llegar una copia adicional. El botón requiere la sesión de la solicitud y CSRF válido. Un formulario antiguo no puede generar otro envío. Los límites se configuran con `history_resend_seconds` y `history_resend_max`.

- PDF originales privados, tamaño y hash comprobados antes de preparar y enviar.
- Hasta 15 PDF por paquete, 12 MiB por archivo y mensaje de 20 MiB, además de los límites de PHP mostrados en el panel.
- Identificación única por tipo y número; consultas preparadas y comprobación de pertenencia de la atención.
- Límites de solicitudes por origen e identificación. El flujo de códigos anterior se conserva solo para solicitudes históricas; las nuevas usan búsqueda directa.
- CSRF, sesiones HttpOnly/SameSite, 15 minutos de inactividad y acceso administrativo para la bandeja local.
- Cola con reserva transaccional y clave de deduplicación. Tres intentos como máximo para fallos temporales. Respuestas inciertas no se reenvían a ciegas.
- “Enviada” indica aceptación SMTP; no confirma lectura ni recepción final.

En XAMPP, `.htaccess` permite solo `public/` y bloquea configuración, almacenamiento y credenciales. Para producción, configura `public/` como raíz web y mueve `storage_path` fuera de cualquier raíz pública. También están pendientes HTTPS, cuenta DB dedicada, respaldos, retención y supervisión de la cola.

## Pruebas

```powershell
php tests/pdf-reader.php
php tests/run.php
php tests/http.php
php tests/upload.php
php tests/resend-ui.php
php tests/admin-http.php
php tests/bulk-http.php
```

El lector se prueba con documentos sintéticos, páginas incompatibles y fechas alternativas. La suite de dominio crea y elimina su propia base MySQL temporal. Las pruebas HTTP requieren Apache; la prueba de carga usa el administrador inicial, importa PDF sintéticos sin campos de paciente y limpia únicamente los datos que ella creó. Ninguna suite envía correos externos.

Dependencias: [PHPMailer](https://github.com/PHPMailer/PHPMailer) y [Smalot PDF Parser](https://github.com/smalot/pdfparser), fijadas en `composer.lock`.
