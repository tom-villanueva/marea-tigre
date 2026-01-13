/**
 * API Module - Handles all data fetching from backend
 *
 * Provides clean async functions for each endpoint
 */
const API = {
  baseUrl: "",

  /**
   * Generic fetch wrapper with error handling
   */
  async fetch(endpoint) {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(`API Error [${endpoint}]:`, error);
      throw error;
    }
  },

  /**
   * Get river alerts from Hidro RSS
   * @returns {Promise<string[]>} Array of alert descriptions
   */
  async getAlertas() {
    return this.fetch("/api/alertas");
  },

  /**
   * Get San Fernando height with tendency
   * @returns {Promise<{altura: string, hora: string, tendencia: string, cambio: number}>}
   */
  async getAlturaSF() {
    return this.fetch("/api/altura_sf");
  },

  /**
   * Get Pilote Norden telemetry data
   * @returns {Promise<{tide: object, wind: object}>}
   */
  async getTelemetria() {
    return this.fetch("/api/telemetria");
  },

  /**
   * Get sudestada status and Tigre prediction
   * @returns {Promise<{activa: boolean, pico_maximo?: number, hora_pico?: string, ...}>}
   */
  async getSudestada() {
    return this.fetch("/api/sudestada");
  },
};

// Export for module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = API;
}
