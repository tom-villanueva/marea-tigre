/**
 * Components Module - Individual dashboard component logic
 */
const Components = {
  // ==================== ALERTAS ====================
  alertas: {
    async refresh() {
      try {
        const data = await API.getAlertas();
        const container = document.getElementById("alertas-content");
        const card = document.getElementById("card-alertas");

        if (!container || !card) return;

        container.innerHTML = "";

        if (!data || data.length === 0) {
          container.innerHTML =
            '<p><i data-lucide="check-circle" class="icon-xs"></i> No hay avisos vigentes</p>';
          this.setAlertLevel(card, "info");
          lucide.createIcons();
          return;
        }

        let alertLevel = "info";

        data.forEach((desc) => {
          const p = document.createElement("p");
          p.innerHTML = desc;
          container.appendChild(p);

          const text = desc.toLowerCase();
          if (text.includes("alerta")) alertLevel = "danger";
          else if (text.includes("naranja") && alertLevel !== "danger")
            alertLevel = "orange";
          else if (text.includes("amarillo") && alertLevel === "info")
            alertLevel = "warning";
        });

        this.setAlertLevel(card, alertLevel);
      } catch (error) {
        console.error("Error loading alerts:", error);
        UI.setHtml(
          "alertas-content",
          "<p>Servicio temporalmente no disponible</p>"
        );
      }
    },

    setAlertLevel(card, level) {
      card.classList.remove(
        "alert-info",
        "alert-warning",
        "alert-orange",
        "alert-danger"
      );
      if (level !== "info") {
        card.classList.add(`alert-${level}`);
      }
    },
  },

  // ==================== SAN FERNANDO ====================
  sanFernando: {
    async refresh() {
      try {
        // Show loading state
        UI.setLoading("sf-altura");
        UI.setLoading("sf-tendencia-label");

        const data = await API.getAlturaSF();

        if (data.error) {
          console.warn("San Fernando:", data.error);
          UI.setText("sf-altura", "N/D");
          UI.setText("sf-tendencia-label", data.error);
          return;
        }

        // Display pre-formatted values from backend
        UI.setText("sf-altura", data.altura);
        UI.setText("sf-hora", data.hora || "-");
        UI.setText("sf-updated", UI.formatTime());

        // Update tendency display with pre-formatted data
        this.updateTendency(data);
      } catch (error) {
        console.error("Error loading San Fernando:", error);
        UI.setText("sf-altura", "Error");
        UI.setText("sf-tendencia-label", "No disponible");
      }
    },

    updateTendency(data) {
      const iconEl = document.getElementById("sf-tendencia-icono");
      const labelEl = document.getElementById("sf-tendencia-label");
      const changeEl = document.getElementById("sf-cambio");

      if (!iconEl || !labelEl || !changeEl) return;

      // Map tendencia to Lucide icon names
      const iconMap = {
        subiendo: "trending-up",
        bajando: "trending-down",
        estable: "minus",
      };

      // Set Lucide icon based on tendencia
      const lucideIcon = iconMap[data.tendencia] || "minus";
      iconEl.innerHTML = `<i data-lucide="${lucideIcon}" class="tendency-icon"></i>`;

      // Reset classes
      iconEl.className = "metric-icon";
      labelEl.className = "metric-label";
      changeEl.className = "metric-change";

      // Display pre-formatted label and change from backend
      labelEl.textContent = data.tendencia_label;
      changeEl.textContent = data.cambio_formatted;

      // Apply style classes based on tendency
      if (data.tendencia === "subiendo") {
        changeEl.classList.add("positive");
        iconEl.classList.add("rising");
      } else if (data.tendencia === "bajando") {
        changeEl.classList.add("negative");
        iconEl.classList.add("falling");
      } else {
        changeEl.classList.add("neutral");
        iconEl.classList.add("stable");
      }

      // Re-render Lucide icons
      lucide.createIcons();
    },
  },

  // ==================== PILOTE NORDEN ====================
  pilote: {
    async refresh() {
      try {
        UI.setLoading("pilote-tide");
        UI.setLoading("pilote-wind-speed");

        const data = await API.getTelemetria();

        if (data.error) {
          console.warn("Pilote:", data.error);
          UI.setText("pilote-datetime", "-");
          UI.setText("pilote-tide", "No disponible");
          UI.setText("pilote-wind-speed", "No disponible");
          UI.setText("pilote-wind-dir", "-");
          return;
        }

        // Display pre-parsed tide data
        if (data.tide) {
          UI.setText("pilote-datetime", data.tide.datetime || "-");
          UI.setText("pilote-tide", data.tide.height_formatted);
        } else {
          UI.setText("pilote-datetime", "-");
          UI.setText("pilote-tide", "No disponible");
        }

        // Display pre-parsed wind data
        if (data.wind) {
          UI.setText("pilote-wind-speed", data.wind.speed_formatted);
          UI.setText("pilote-wind-dir", data.wind.direction_formatted);

          // Update wind arrow rotation
          if (data.wind.direction_deg !== null) {
            const arrow = document.getElementById("wind-arrow");
            if (arrow) {
              arrow.style.transform = `rotate(${data.wind.direction_deg}deg)`;
            }
          }
        } else {
          UI.setText("pilote-wind-speed", "No disponible");
          UI.setText("pilote-wind-dir", "-");
        }
      } catch (error) {
        console.error("Error loading Pilote:", error);
        UI.setHtml(
          "pilote-tide",
          '<span style="color: var(--accent-red)">No disponible</span>'
        );
        UI.setHtml(
          "pilote-wind-speed",
          '<span style="color: var(--accent-red)">Intente recargar</span>'
        );
      }
    },
  },

  // ==================== SUDESTADA ====================
  sudestada: {
    async refresh() {
      try {
        const data = await API.getSudestada();
        const panel = document.getElementById("card-sudestada");

        if (!panel) return;

        if (data.activa) {
          panel.style.display = "";

          // Use pre-formatted values from backend
          UI.setText("sudestada-pico", data.pico_maximo_formatted);
          UI.setText("sudestada-altura-pilote", data.pico_maximo_formatted);
          UI.setText("sudestada-hora-pilote", data.hora_pico);
          UI.setText("sudestada-altura-tigre", data.altura_tigre_formatted);
          UI.setText("sudestada-hora-tigre", data.hora_tigre_estimada);
        } else {
          panel.style.display = "none";
        }
      } catch (error) {
        console.error("Error loading sudestada:", error);
        document.getElementById("card-sudestada").style.display = "none";
      }
    },
  },

  // ==================== FORECAST ====================
  forecast: {
    refresh() {
      const img = document.getElementById("forecast-img");
      if (!img) return;

      const now = Date.now();
      const baseUrl =
        "https://alerta.ina.gob.ar/ina/42-RIODELAPLATA/productos/Prono_SanFernando.png";

      img.classList.add("loading");
      img.src = `${baseUrl}?t=${now}`;

      img.onload = () => img.classList.remove("loading");
      img.onerror = () => {
        img.alt = "Imagen no disponible - Consulte INA.gob.ar";
        img.classList.remove("loading");
      };
    },
  },
};

// Export for module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = Components;
}
