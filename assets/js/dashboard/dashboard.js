var GeoElementorDashboard=(()=>{var d=(v,e,a)=>new Promise((s,t)=>{var i=r=>{try{n(a.next(r))}catch(l){t(l)}},c=r=>{try{n(a.throw(r))}catch(l){t(l)}},n=r=>r.done?s(r.value):Promise.resolve(r.value).then(i,c);n((a=a.apply(v,e)).next())});var o=class{constructor(){this.data={overview:null,countries:[],rules:[],trends:[]},this.init()}init(){return d(this,null,function*(){this.renderLoading(),yield this.fetchData(),this.render()})}fetchData(){return d(this,null,function*(){try{let e=window.egpDashboard&&egpDashboard.restBase?egpDashboard.restBase.replace(/\/$/,""):"/wp-json/geo-elementor/v1",[a,s,t,i]=yield Promise.all([this.apiCall(`${e}/analytics/overview`),this.apiCall(`${e}/analytics/countries`),this.apiCall(`${e}/analytics/rules`),this.apiCall(`${e}/analytics/trends`)]);this.data={overview:a,countries:s,rules:t,trends:i}}catch(e){this.renderError()}})}apiCall(e){return d(this,null,function*(){let a={};window.egpDashboard&&egpDashboard.nonce&&(a["X-WP-Nonce"]=egpDashboard.nonce);let s=yield fetch(e,{headers:a});if(!s.ok)throw new Error(`HTTP ${s.status}`);return s.json()})}renderLoading(){let e=document.getElementById("geo-el-admin-app");e&&(e.innerHTML=`
        <div class="geo-dashboard-loading">
          <div class="loading-spinner"></div>
          <h3>Loading Dashboard...</h3>
          <p>Fetching your analytics data</p>
        </div>
      `)}renderError(){let e=document.getElementById("geo-el-admin-app");e&&(e.innerHTML=`
        <div class="geo-dashboard-error">
          <h3>Error Loading Dashboard</h3>
          <p>Unable to fetch analytics data. Please check your connection and try again.</p>
          <button onclick="location.reload()" class="retry-btn">Retry</button>
        </div>
      `)}render(){let e=document.getElementById("geo-el-admin-app");e&&(e.innerHTML=`
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
    `,setTimeout(()=>{this.initCharts()},100))}renderHeader(){return`
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-title">
            <div class="header-icon">\u{1F30D}</div>
            <div>
              <h1>Geo Analytics Dashboard</h1>
              <p>Monitor your geo-targeted content performance</p>
            </div>
          </div>
          <button onclick="location.reload()" class="refresh-btn">
            \u{1F504} Refresh Data
          </button>
        </div>
      </div>
    `}renderOverviewCards(){if(!this.data.overview)return"";let{overview:e}=this.data;return`
      <div class="overview-cards">
        ${[{title:"Total Rules",value:e.totalRules,icon:"\u{1F3AF}"},{title:"Active Rules",value:e.activeRules,icon:"\u2705"},{title:"Total Clicks",value:this.formatNumber(e.totalClicks),icon:"\u{1F5B1}\uFE0F"},{title:"Countries",value:e.countriesTargeted,icon:"\u{1F30D}"},{title:"Conversion",value:`${e.conversionRate}%`,icon:"\u{1F4C8}"},{title:"Groups",value:e.variantGroups,icon:"\u{1F465}"}].map(s=>`
          <div class="metric-card">
            <div class="metric-icon">${s.icon}</div>
            <div class="metric-content">
              <div class="metric-value">${s.value}</div>
              <div class="metric-label">${s.title}</div>
            </div>
          </div>
        `).join("")}
      </div>
    `}renderCountryChart(){if(!this.data.countries.length)return"";let e=this.data.countries.slice(0,8),a=Math.max(...e.map(s=>s.clicks));return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>\u{1F30D} Top Countries by Clicks</h3>
          <span class="chart-subtitle">${this.data.countries.length} countries tracked</span>
        </div>
        <div class="country-bars">
          ${e.map(s=>`
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${s.countryName}</span>
                <span class="country-code">(${s.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width: ${s.clicks/a*100}%"></div>
                <span class="bar-value">${s.clicks}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}renderTrendsChart(){if(!this.data.trends.length)return"";let e=this.data.trends.reduce((t,i)=>t+i.clicks,0),a=this.data.trends.reduce((t,i)=>t+i.views,0),s=this.data.trends.length>0?(this.data.trends.reduce((t,i)=>t+i.conversionRate,0)/this.data.trends.length).toFixed(1):0;return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>\u{1F4C8} Performance Trends</h3>
          <span class="chart-subtitle">Last 30 days</span>
        </div>
        <div class="trends-summary">
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(e)}</div>
            <div class="trend-label">Total Clicks</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(a)}</div>
            <div class="trend-label">Total Views</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${s}%</div>
            <div class="trend-label">Avg Conversion</div>
          </div>
        </div>
        <div class="trends-chart" id="trends-chart">
          ${this.renderTrendsBars()}
        </div>
      </div>
    `}renderTrendsBars(){let e=Math.max(...this.data.trends.map(s=>Math.max(s.clicks,s.views)));return`
      <div class="trends-bars">
        ${this.data.trends.slice(-14).map((s,t)=>{let i=new Date(s.date).toLocaleDateString("en-US",{month:"short",day:"numeric"}),c=s.clicks/e*100,n=s.views/e*100;return`
            <div class="trend-bar-group">
              <div class="trend-bar-container">
                <div class="trend-bar clicks-bar" style="height: ${c}%"></div>
                <div class="trend-bar views-bar" style="height: ${n}%"></div>
              </div>
              <div class="trend-date">${i}</div>
            </div>
          `}).join("")}
      </div>
    `}renderRulesTable(){if(!this.data.rules.length)return`
        <div class="chart-container">
          <div class="empty-state">
            <div class="empty-icon">\u{1F3AF}</div>
            <h3>No rules found</h3>
            <p>Create your first geo rule to start tracking performance.</p>
          </div>
        </div>
      `;let e=[...this.data.rules].sort((a,s)=>s.clicks-a.clicks);return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>\u{1F3AF} Rules Performance</h3>
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
          ${e.slice(0,10).map(a=>`
            <div class="table-row">
              <div class="table-cell">
                <div class="rule-name">${a.title}</div>
                <div class="rule-date">Created ${this.formatDate(a.created)}</div>
              </div>
              <div class="table-cell">
                <span class="type-badge type-${a.type.toLowerCase()}">${a.type}</span>
              </div>
              <div class="table-cell">
                <span class="country-count">${a.countriesCount} countries</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(a.clicks)}</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(a.views)}</span>
              </div>
              <div class="table-cell">
                <span class="conversion-rate ${a.conversionRate>=10?"high":a.conversionRate>=5?"medium":"low"}">
                  ${a.conversionRate}%
                </span>
              </div>
              <div class="table-cell">
                <span class="status-badge ${a.active?"active":"inactive"}">
                  ${a.active?"Active":"Inactive"}
                </span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}initCharts(){}formatNumber(e){return new Intl.NumberFormat().format(e)}formatDate(e){return new Date(e).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"})}};document.addEventListener("DOMContentLoaded",()=>{new o});})();
