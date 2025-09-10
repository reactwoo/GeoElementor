/**
 * Geo Elementor Dashboard - Lightweight Vanilla JS
 * Modern analytics dashboard with minimal dependencies
 */

class GeoElementorDashboard {
  constructor() {
    this.data = {
      overview: null,
      countries: [],
      rules: [],
      trends: []
    };
    this.init();
  }

  async init() {
    this.renderLoading();
    await this.fetchData();
    this.render();
  }

  async fetchData() {
    try {
      const base = (window.egpDashboard && egpDashboard.restBase) ? egpDashboard.restBase.replace(/\/$/, '') : '/wp-json/geo-elementor/v1';
      const [overview, countries, rules, trends] = await Promise.all([
        this.apiCall(`${base}/analytics/overview`),
        this.apiCall(`${base}/analytics/countries`),
        this.apiCall(`${base}/analytics/rules`),
        this.apiCall(`${base}/analytics/trends`)
      ]);

      this.data = { overview, countries, rules, trends };
    } catch (error) {
      console.error('Error fetching data:', error);
      this.renderError();
    }
  }

  async apiCall(url) {
    const headers = {};
    if (window.egpDashboard && egpDashboard.nonce) {
      headers['X-WP-Nonce'] = egpDashboard.nonce;
    }
    const response = await fetch(url, { headers });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  renderLoading() {
    const container = document.getElementById('geo-el-admin-app');
    if (container) {
      container.innerHTML = `
        <div class="geo-dashboard-loading">
          <div class="loading-spinner"></div>
          <h3>Loading Dashboard...</h3>
          <p>Fetching your analytics data</p>
        </div>
      `;
    }
  }

  renderError() {
    const container = document.getElementById('geo-el-admin-app');
    if (container) {
      container.innerHTML = `
        <div class="geo-dashboard-error">
          <h3>Error Loading Dashboard</h3>
          <p>Unable to fetch analytics data. Please check your connection and try again.</p>
          <button onclick="location.reload()" class="retry-btn">Retry</button>
        </div>
      `;
    }
  }

  render() {
    const container = document.getElementById('geo-el-admin-app');
    if (!container) return;

    container.innerHTML = `
      <div class="geo-dashboard">
        ${this.renderHeader()}
        <div class="dashboard-content">
          ${this.renderOverviewCards()}
          <div class="charts-grid">
            ${this.renderCountryChart()}
            ${this.renderTrendsChart()}
          </div>
          ${this.renderRulesTable()}
        </div>
      </div>
    `;

    // Initialize charts after DOM is ready
    setTimeout(() => {
      this.initCharts();
    }, 100);
  }

  renderHeader() {
    return `
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-title">
            <div class="header-icon">🌍</div>
            <div>
              <h1>Geo Analytics Dashboard</h1>
              <p>Monitor your geo-targeted content performance</p>
            </div>
          </div>
          <button onclick="location.reload()" class="refresh-btn">
            🔄 Refresh Data
          </button>
        </div>
      </div>
    `;
  }

  renderOverviewCards() {
    if (!this.data.overview) return '';

    const { overview } = this.data;
    const cards = [
      { title: 'Total Rules', value: overview.totalRules, icon: '🎯' },
      { title: 'Active Rules', value: overview.activeRules, icon: '✅' },
      { title: 'Total Clicks', value: this.formatNumber(overview.totalClicks), icon: '🖱️' },
      { title: 'Countries', value: overview.countriesTargeted, icon: '🌍' },
      { title: 'Conversion', value: `${overview.conversionRate}%`, icon: '📈' },
      { title: 'Groups', value: overview.variantGroups, icon: '👥' }
    ];

    return `
      <div class="overview-cards">
        ${cards.map(card => `
          <div class="metric-card">
            <div class="metric-icon">${card.icon}</div>
            <div class="metric-content">
              <div class="metric-value">${card.value}</div>
              <div class="metric-label">${card.title}</div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  renderCountryChart() {
    if (!this.data.countries.length) return '';

    const topCountries = this.data.countries.slice(0, 8);
    const maxClicks = Math.max(...topCountries.map(c => c.clicks));

    return `
      <div class="chart-container">
        <div class="chart-header">
          <h3>🌍 Top Countries by Clicks</h3>
          <span class="chart-subtitle">${this.data.countries.length} countries tracked</span>
        </div>
        <div class="country-bars">
          ${topCountries.map(country => `
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${country.countryName}</span>
                <span class="country-code">(${country.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width: ${(country.clicks / maxClicks) * 100}%"></div>
                <span class="bar-value">${country.clicks}</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  renderTrendsChart() {
    if (!this.data.trends.length) return '';

    const totalClicks = this.data.trends.reduce((sum, item) => sum + item.clicks, 0);
    const totalViews = this.data.trends.reduce((sum, item) => sum + item.views, 0);
    const avgConversion = this.data.trends.length > 0 ? 
      (this.data.trends.reduce((sum, item) => sum + item.conversionRate, 0) / this.data.trends.length).toFixed(1) : 0;

    return `
      <div class="chart-container">
        <div class="chart-header">
          <h3>📈 Performance Trends</h3>
          <span class="chart-subtitle">Last 30 days</span>
        </div>
        <div class="trends-summary">
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(totalClicks)}</div>
            <div class="trend-label">Total Clicks</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(totalViews)}</div>
            <div class="trend-label">Total Views</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${avgConversion}%</div>
            <div class="trend-label">Avg Conversion</div>
          </div>
        </div>
        <div class="trends-chart" id="trends-chart">
          ${this.renderTrendsBars()}
        </div>
      </div>
    `;
  }

  renderTrendsBars() {
    const maxValue = Math.max(...this.data.trends.map(t => Math.max(t.clicks, t.views)));
    const sampleData = this.data.trends.slice(-14); // Last 14 days

    return `
      <div class="trends-bars">
        ${sampleData.map((item, index) => {
          const date = new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
          const clicksHeight = (item.clicks / maxValue) * 100;
          const viewsHeight = (item.views / maxValue) * 100;
          
          return `
            <div class="trend-bar-group">
              <div class="trend-bar-container">
                <div class="trend-bar clicks-bar" style="height: ${clicksHeight}%"></div>
                <div class="trend-bar views-bar" style="height: ${viewsHeight}%"></div>
              </div>
              <div class="trend-date">${date}</div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  renderRulesTable() {
    if (!this.data.rules.length) {
      return `
        <div class="chart-container">
          <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <h3>No rules found</h3>
            <p>Create your first geo rule to start tracking performance.</p>
          </div>
        </div>
      `;
    }

    const sortedRules = [...this.data.rules].sort((a, b) => b.clicks - a.clicks);

    return `
      <div class="chart-container">
        <div class="chart-header">
          <h3>🎯 Rules Performance</h3>
          <span class="chart-subtitle">${this.data.rules.length} total rules</span>
        </div>
        <div class="rules-table">
          <div class="table-header">
            <div class="table-cell">Rule Name</div>
            <div class="table-cell">Type</div>
            <div class="table-cell">Countries</div>
            <div class="table-cell">Clicks</div>
            <div class="table-cell">Views</div>
            <div class="table-cell">Conversion</div>
            <div class="table-cell">Status</div>
          </div>
          ${sortedRules.slice(0, 10).map(rule => `
            <div class="table-row">
              <div class="table-cell">
                <div class="rule-name">${rule.title}</div>
                <div class="rule-date">Created ${this.formatDate(rule.created)}</div>
              </div>
              <div class="table-cell">
                <span class="type-badge type-${rule.type.toLowerCase()}">${rule.type}</span>
              </div>
              <div class="table-cell">
                <span class="country-count">${rule.countriesCount} countries</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(rule.clicks)}</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(rule.views)}</span>
              </div>
              <div class="table-cell">
                <span class="conversion-rate ${rule.conversionRate >= 10 ? 'high' : rule.conversionRate >= 5 ? 'medium' : 'low'}">
                  ${rule.conversionRate}%
                </span>
              </div>
              <div class="table-cell">
                <span class="status-badge ${rule.active ? 'active' : 'inactive'}">
                  ${rule.active ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  initCharts() {
    // Add any interactive chart functionality here if needed
    // For now, the charts are static and CSS-based
  }

  formatNumber(num) {
    return new Intl.NumberFormat().format(num);
  }

  formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new GeoElementorDashboard();
});