/**
 * App.js - Main application entry point
 *
 * Initializes the dashboard and manages refresh intervals
 */
const App = {
  // Refresh interval in milliseconds (5 minutes)
  REFRESH_INTERVAL: 300000,

  // Interval IDs for cleanup
  intervals: [],

  /**
   * Initialize the application
   */
  init() {
    console.log("Marea Tigre Dashboard initializing...");

    // Initialize Lucide icons
    lucide.createIcons();

    // Initialize UI components
    UI.initCollapsiblePanels();

    // Start live clock
    this.startClock();

    // Load all data
    this.loadAllData();

    // Set up refresh intervals
    this.setupIntervals();

    console.log("Dashboard ready");
  },

  /**
   * Load all data components
   */
  async loadAllData() {
    // Load in parallel for faster initial load
    await Promise.all([
      Components.alertas.refresh(),
      Components.sanFernando.refresh(),
      Components.pilote.refresh(),
      Components.forecast.refresh(),
    ]);

    // Load sudestada after pilote (depends on that data)
    setTimeout(() => Components.sudestada.refresh(), 1000);
  },

  /**
   * Set up automatic refresh intervals
   */
  setupIntervals() {
    // Main data refresh
    this.intervals.push(
      setInterval(() => this.loadAllData(), this.REFRESH_INTERVAL)
    );

    // Clock update every minute
    this.intervals.push(setInterval(() => UI.updateClock(), 60000));

    // Update "last updated" time
    this.intervals.push(
      setInterval(() => {
        UI.setText("sf-updated", UI.formatTime());
      }, 60000)
    );
  },

  /**
   * Start the live clock
   */
  startClock() {
    UI.updateClock();
    setInterval(() => UI.updateClock(), 1000);
  },

  /**
   * Manual refresh all data
   */
  refresh() {
    console.log("🔄 Manual refresh triggered");
    this.loadAllData();
  },

  /**
   * Cleanup intervals (for SPA navigation)
   */
  destroy() {
    this.intervals.forEach((id) => clearInterval(id));
    this.intervals = [];
  },
};

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  App.init();
});

// Export for module usage
if (typeof module !== "undefined" && module.exports) {
  module.exports = App;
}
