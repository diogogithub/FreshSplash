(() => {
  const input = document.querySelector('[data-image-input]');
  const zone = document.querySelector('[data-dropzone]');
  const preview = document.querySelector('[data-preview]');
  const label = document.querySelector('[data-file-label]');

  const showFile = () => {
    const file = input?.files?.[0];
    if (!file) return;
    if (label) label.textContent = `${file.name} · ${Math.round(file.size / 1024)} KB`;
    if (preview) {
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
    }
  };

  input?.addEventListener('change', showFile);
  ['dragenter', 'dragover'].forEach((event) => zone?.addEventListener(event, () => zone.classList.add('dragging')));
  ['dragleave', 'drop'].forEach((event) => zone?.addEventListener(event, () => zone.classList.remove('dragging')));

  document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm') || 'Continue?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const selector = button.getAttribute('data-copy');
      const target = selector ? document.querySelector(selector) : null;
      if (!target) return;
      await navigator.clipboard.writeText(target.textContent.trim());
      const previous = button.textContent;
      button.textContent = 'Copied';
      setTimeout(() => { button.textContent = previous; }, 1200);
    });
  });
})();
