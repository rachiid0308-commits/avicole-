// Ajuste la largeur des listes déroulantes à la valeur sélectionnée.
// Utilisé par les barres de filtres marquées .toolbar-fit (ex. units.php).
(() => {
  const MIN_WIDTH = 96;
  const MAX_WIDTH = 340;
  const ARROW_SPACE = 24;
  let canvas = null;

  const measure = (text, font) => {
    if (!canvas) canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    if (!context) return text.length * 8;
    context.font = font;
    return context.measureText(text).width;
  };

  const fitSelect = (select) => {
    const option = select.options[select.selectedIndex];
    if (!option) return;
    const style = getComputedStyle(select);
    if (style.display === 'none') return;
    const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
    const border = (parseFloat(style.borderLeftWidth) || 0) + (parseFloat(style.borderRightWidth) || 0);
    const text = (option.textContent || '').trim();
    const width = Math.ceil(measure(text, style.font) + padding + border + ARROW_SPACE);
    select.style.width = `${Math.min(Math.max(width, MIN_WIDTH), MAX_WIDTH)}px`;
  };

  const fitAll = (root = document) => {
    root.querySelectorAll('.toolbar-fit select').forEach(fitSelect);
  };

  const start = () => {
    fitAll();
    // Les polices web peuvent changer la mesure après leur chargement.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => fitAll()).catch(() => {});
  };

  document.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLSelectElement && target.closest('.toolbar-fit')) fitSelect(target);
  });

  let resizeTimer = null;
  window.addEventListener('resize', () => {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(() => fitAll(), 120);
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
