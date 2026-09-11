(() => {
  const root = document.querySelector('[data-upload-result]');
  if (!root) return;
  const dialog = root.querySelector('dialog');
  if (typeof dialog.showModal !== 'function') return;
  const opener = root.querySelector('[data-open-upload-result]');
  const report = root.querySelector('[data-upload-result-report]');
  // Move the existing, server-escaped report instead of duplicating patient data.
  root.querySelector('[data-upload-result-content]').append(report);
  opener.hidden = false;
  const open = () => {
    dialog.showModal();
    document.body.classList.add('upload-result-open');
  };
  opener.addEventListener('click', open);
  root.querySelector('[data-close-upload-result]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  dialog.addEventListener('close', () => {
    document.body.classList.remove('upload-result-open');
    opener.focus();
  });
  if (root.dataset.autoOpen === '1') open();
})();
