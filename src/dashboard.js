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
      const [overview, countries, rules, trends, untracked] = await Promise.all([
        this.apiCall(`${base}/analytics/overview`),
        this.apiCall(`${base}/analytics/countries`),
        this.apiCall(`${base}/analytics/rules`),
        this.apiCall(`${base}/analytics/trends`),
        this.apiCall(`${base}/analytics/untracked`)
      ]);

      this.data = { overview, countries, rules, trends, untracked };
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
          ${this.renderUntrackedCard()}
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
            <div class="header-icon">${this.icon('globe')}</div>
            <div>
              <h1>Geo Analytics Dashboard</h1>
              <p>Monitor your geo-targeted content performance</p>
            </div>
          </div>
          <button onclick="location.reload()" class="refresh-btn">
            ${this.icon('refresh', 18)} Refresh Data
          </button>
        </div>
      </div>
    `;
  }

  renderOverviewCards() {
    if (!this.data.overview) return '';

    const { overview } = this.data;
    const cards = [
      { title: 'Total Rules', value: overview.totalRules, icon: this.icon('target') },
      { title: 'Active Rules', value: overview.activeRules, icon: this.icon('check') },
      { title: 'Total Clicks', value: this.formatNumber(overview.totalClicks), icon: this.icon('mouse') },
      { title: 'Countries', value: overview.countriesTargeted, icon: this.icon('globe') },
      { title: 'Conversion', value: `${overview.conversionRate}%`, icon: this.icon('chart') },
      { title: 'Groups', value: overview.variantGroups, icon: this.icon('users') }
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
          <h3>${this.icon('globe', 16)} Top Countries by Clicks</h3>
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
          <h3>${this.icon('chart', 16)} Performance Trends</h3>
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
            <div class="empty-icon">${this.icon('target', 40)}</div>
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
          <h3>${this.icon('target', 16)} Rules Performance</h3>
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

  renderUntrackedCard() {
    const items = Array.isArray(this.data.untracked) ? this.data.untracked : [];
    if (!items.length) return '';
    return `
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon('flag', 16)} Untracked Countries</h3>
          <span class="chart-subtitle">Top ${items.length} without rules</span>
        </div>
        <div class="country-bars">
          ${items.map(it => `
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${it.countryName}</span>
                <span class="country-code">(${it.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width:${Math.min(100, (it.hits / items[0].hits) * 100)}%"></div>
                <span class="bar-value">${this.formatNumber(it.hits)}</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  icon(name, size = 20) {
    const stroke = '#6b7280';
    const sw = 2;
    const attrs = `width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round"`;
    switch (name) {
      case 'globe': return `<svg ${attrs}><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20a15.3 15.3 0 0 1 0-20z"/></svg>`;
      case 'chart': return `<svg ${attrs}><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/></svg>`;
      case 'target': return `<svg ${attrs}><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>`;
      case 'check': return `<svg ${attrs}><path d="M20 6L9 17l-5-5"/></svg>`;
      case 'mouse': return `<svg ${attrs}><rect x="8" y="3" width="8" height="18" rx="4"/><path d="M12 7v4"/></svg>`;
      case 'users': return `<svg ${attrs}><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;
      case 'refresh': return `<svg ${attrs}><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>`;
      case 'flag': return `<svg ${attrs}><path d="M4 22V6a2 2 0 0 1 2-2h0l2 1l2-1h6a2 2 0 0 1 2 2v9h-8l-2 1l-2-1H4z"/></svg>`;
      default: return `<svg ${attrs}></svg>`;
    }
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