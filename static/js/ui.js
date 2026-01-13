/**
 * UI Utilities - Collapsible panels, helpers
 */
const UI = {
  /**
   * Initialize all collapsible card panels
   */
  initCollapsiblePanels() {
    const panels = document.querySelectorAll(".card-collapsible");

    panels.forEach((panel) => {
      const header = panel.querySelector(".card-header");
      const body = panel.querySelector(".card-body");
      const toggle = header.querySelector(".card-toggle");

      if (!header || !body) return;

      // Get stored state
      const panelId = panel.id || panel.dataset.panelId;
      const storedState = localStorage.getItem(`panel_${panelId}`);

      // Apply initial state
      if (storedState === "collapsed") {
        this.collapsePanel(panel, body, header, toggle, false);
      }

      // Add click handler
      header.addEventListener("click", (e) => {
        e.stopPropagation();

        if (panel.dataset.collapsed === "true") {
          this.expandPanel(panel, body, header, toggle);
        } else {
          this.collapsePanel(panel, body, header, toggle);
        }
      });
    });
  },

  /**
   * Collapse a panel
   */
  collapsePanel(panel, body, header, toggle, saveState = true) {
    panel.dataset.collapsed = "true";
    body.classList.add("collapsed");
    header.classList.add("collapsed");

    if (toggle) {
      toggle.textContent = "▶";
    }

    // Special styling for emergency panel
    if (panel.classList.contains("card-emergency")) {
      panel.classList.add("collapsed");
    }

    if (saveState) {
      const panelId = panel.id || panel.dataset.panelId;
      localStorage.setItem(`panel_${panelId}`, "collapsed");
    }
  },

  /**
   * Expand a panel
   */
  expandPanel(panel, body, header, toggle) {
    panel.dataset.collapsed = "false";
    body.classList.remove("collapsed");
    header.classList.remove("collapsed");

    if (toggle) {
      toggle.textContent = "▼";
    }

    // Special styling for emergency panel
    if (panel.classList.contains("card-emergency")) {
      panel.classList.remove("collapsed");
    }

    const panelId = panel.id || panel.dataset.panelId;
    localStorage.setItem(`panel_${panelId}`, "expanded");
  },

  /**
   * Format time to local string (used for live clock)
   */
  formatTime(date = new Date()) {
    return date.toLocaleTimeString("es-AR", {
      hour: "2-digit",
      minute: "2-digit",
    });
  },

  /**
   * Set element text with loading state
   */
  setLoading(elementId, loading = true) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (loading) {
      el.innerHTML = '<span class="loading">Cargando...</span>';
    }
  },

  /**
   * Set element text content safely
   */
  setText(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) {
      el.textContent = text;
    }
  },

  /**
   * Set element HTML content safely
   */
  setHtml(elementId, html) {
    const el = document.getElementById(elementId);
    if (el) {
      el.innerHTML = html;
    }
  },

  /**
   * Update the live clock
   */
  updateClock() {
    const clockEl = document.getElementById("live-clock");
    if (clockEl) {
      clockEl.textContent = this.formatTime();
    }
  },
};

// Export for module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = UI;
}
