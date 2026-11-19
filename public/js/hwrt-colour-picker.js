function colourPicker(initial, eventName) {
  const standard = ['#ff5f1f', '#d9534f', '#e2e8f0', '#38bdf8', '#22c55e', '#eab308', '#f97316', '#ef4444', '#a855f7', '#14b8a6', '#64748b', '#0f172a', '#ffffff'];
  return {
    standard,
    eventName,
    hex: '#ff5f1f',
    r: 255, g: 95, b: 31,
    init() {
      this.setHex(initial || '#ff5f1f', false);
    },
    setHex(value, emit = true) {
      if (!/^#[0-9a-fA-F]{6}$/.test(String(value || ''))) return;
      this.hex = value.toLowerCase();
      this.r = parseInt(this.hex.slice(1, 3), 16);
      this.g = parseInt(this.hex.slice(3, 5), 16);
      this.b = parseInt(this.hex.slice(5, 7), 16);
      if (emit) this.$dispatch(this.eventName, this.hex);
    },
    updateFromRgb() {
      this.hex = '#' + [this.r, this.g, this.b]
        .map(v => Math.max(0, Math.min(255, Number(v) || 0)).toString(16).padStart(2, '0'))
        .join('');
      this.$dispatch(this.eventName, this.hex);
    },
  };
}
