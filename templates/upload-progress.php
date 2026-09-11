<dialog class="queue-progress upload-progress" id="upload-progress" aria-labelledby="upload-progress-title" aria-describedby="upload-progress-message">
    <header class="queue-progress-header">
        <span class="queue-progress-icon" aria-hidden="true"><span></span></span>
        <div><h2 id="upload-progress-title">Cargando PDF…</h2><p data-upload-subtitle>Subiendo archivos al servidor…</p></div>
        <button type="button" class="queue-progress-close" aria-label="Cerrar mensaje de carga" hidden>×</button>
    </header>
    <div class="queue-progress-body">
        <div class="queue-progress-ring" role="progressbar" aria-label="Subida de archivos PDF">
            <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="queue-ring-track" cx="60" cy="60" r="49"/><circle class="queue-ring-fill" cx="60" cy="60" r="49" pathLength="100"/></svg>
            <span data-upload-percent aria-hidden="true">0%</span>
        </div>
        <p id="upload-progress-message" role="status" aria-live="polite">Subiendo archivos al servidor…</p>
        <small data-upload-note>Mantén esta página abierta hasta que termine.</small>
    </div>
</dialog>
