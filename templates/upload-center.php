<?php
$uploadLimits=App\BulkImport::limits();
$uploadMode=(($_POST['action'] ?? '')==='bulk-upload' || (empty($_POST['action']) && ($_SESSION['upload_mode'] ?? '')==='bulk')) ? 'bulk' : 'single';
?>
<section class="card upload-center" aria-labelledby="upload-heading" data-upload-center data-upload-mode="<?= $uploadMode ?>">
<div class="upload-heading"><div><h2 id="upload-heading">Cargar historias clínicas</h2><p class="muted">Elige según las atenciones que contienen tus archivos.</p></div><span class="upload-format">Documentos PDF</span></div>
<div class="upload-options" role="tablist" aria-label="Tipo de carga" data-upload-options hidden>
<button type="button" id="upload-tab-single" class="upload-option" role="tab" aria-controls="upload-panel-single" aria-selected="true" data-upload-choice="single">
<span class="upload-option-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8Z"/><path d="M14 3v5h5M8 12h8M8 16h6"/></svg></span>
<span class="upload-option-copy"><strong>Una atención</strong><span>Historia completa de un paciente, con sus anexos.</span></span><span class="upload-choice-mark" aria-hidden="true"></span>
</button>
<button type="button" id="upload-tab-bulk" class="upload-option" role="tab" aria-controls="upload-panel-bulk" aria-selected="false" tabindex="-1" data-upload-choice="bulk">
<span class="upload-option-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 6h9l4 4v11H8ZM17 6v4h4M4 17H3V2h10l2 2M11 14h7M11 17h5"/></svg></span>
<span class="upload-option-copy"><strong>Varias atenciones</strong><span>Carga masiva de uno o varios pacientes.</span></span><span class="upload-choice-mark" aria-hidden="true"></span>
</button>
</div>
<div id="upload-panel-single" class="upload-panel" data-upload-panel="single">
<h3>Historia de una atención</h3><p class="muted upload-panel-description">Incluye la consulta, las órdenes, las fórmulas y los demás anexos de esa misma atención. El sistema leerá los datos del paciente.</p>
<?php require __DIR__ . '/single-upload.php'; ?>
</div>
<div id="upload-panel-bulk" class="upload-panel" data-upload-panel="bulk">
<h3>Carga masiva de PDF</h3><p class="muted upload-panel-description">Selecciona los documentos de varias atenciones. El sistema los organizará por paciente e ingreso y mostrará el resultado de la carga.</p>
<?php require __DIR__ . '/bulk-upload.php'; ?>
</div>
</section>
<?php require __DIR__ . '/bulk-report.php'; ?>
<?php require __DIR__ . '/upload-progress.php'; ?>
<script src="assets/upload-progress.js?v=<?= filemtime(dirname(__DIR__) . '/public/assets/upload-progress.js') ?>" defer></script>
