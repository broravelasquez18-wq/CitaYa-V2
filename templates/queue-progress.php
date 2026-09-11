<dialog class="queue-progress" id="queue-progress" data-queue-state="<?= h($request['status']) ?>" aria-labelledby="queue-title" aria-describedby="queue-message">
    <header class="queue-progress-header">
        <span class="queue-progress-icon" aria-hidden="true"><span></span></span>
        <div><h2 id="queue-title">Preparando tu envío…</h2><p data-queue-subtitle>Tu solicitud está en cola</p></div>
        <button type="button" class="queue-progress-close" aria-label="Cerrar ventana de progreso">×</button>
    </header>
    <div class="queue-progress-body">
        <div class="queue-progress-ring" role="progressbar" aria-label="Procesamiento del envío" aria-valuetext="En cola">
            <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="queue-ring-track" cx="60" cy="60" r="49"/><circle class="queue-ring-fill" cx="60" cy="60" r="49"/></svg>
            <span aria-hidden="true">···</span>
        </div>
        <p id="queue-message" role="status" aria-live="polite">Esperando turno para preparar tu historia…</p>
        <small>Puedes cerrar esta ventana. El envío continuará.</small>
    </div>
</dialog>
