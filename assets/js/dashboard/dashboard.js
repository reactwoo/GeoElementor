var GeoElementorDashboard=(()=>{var d=(v,a,s)=>new Promise((e,i)=>{var t=r=>{try{c(s.next(r))}catch(l){i(l)}},n=r=>{try{c(s.throw(r))}catch(l){i(l)}},c=r=>r.done?e(r.value):Promise.resolve(r.value).then(t,n);c((s=s.apply(v,a)).next())});var o=class{constructor(){this.data={overview:null,countries:[],rules:[],trends:[]},this.init()}init(){return d(this,null,function*(){this.renderLoading(),yield this.fetchData(),this.render()})}fetchData(){return d(this,null,function*(){try{let a=window.egpDashboard&&egpDashboard.restBase?egpDashboard.restBase.replace(/\/$/,""):"/wp-json/geo-elementor/v1",[s,e,i,t,n]=yield Promise.all([this.apiCall(`${a}/analytics/overview`),this.apiCall(`${a}/analytics/countries`),this.apiCall(`${a}/analytics/rules`),this.apiCall(`${a}/analytics/trends`),this.apiCall(`${a}/analytics/untracked`)]);this.data={overview:s,countries:e,rules:i,trends:t,untracked:n}}catch(a){this.renderError()}})}apiCall(a){return d(this,null,function*(){let s={};window.egpDashboard&&egpDashboard.nonce&&(s["X-WP-Nonce"]=egpDashboard.nonce);let e=yield fetch(a,{headers:s});if(!e.ok)throw new Error(`HTTP ${e.status}`);return e.json()})}renderLoading(){let a=document.getElementById("geo-el-admin-app");a&&(a.innerHTML=`
        <div class="geo-dashboard-loading">
          <div class="loading-spinner"></div>
          <h3>Loading Dashboard...</h3>
          <p>Fetching your analytics data</p>
        </div>
      `)}renderError(){let a=document.getElementById("geo-el-admin-app");a&&(a.innerHTML=`
        <div class="geo-dashboard-error">
          <h3>Error Loading Dashboard</h3>
          <p>Unable to fetch analytics data. Please check your connection and try again.</p>
          <button onclick="location.reload()" class="retry-btn">Retry</button>
        </div>
      `)}render(){let a=document.getElementById("geo-el-admin-app");a&&(a.innerHTML=`
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
    `,setTimeout(()=>{this.initCharts()},100))}renderHeader(){return`
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-title">
            <div class="header-icon">${this.icon("globe")}</div>
            <div>
              <h1>Geo Analytics Dashboard</h1>
              <p>Monitor your geo-targeted content performance</p>
            </div>
          </div>
          <button onclick="location.reload()" class="refresh-btn">
            ${this.icon("refresh",18)} Refresh Data
          </button>
        </div>
      </div>
    `}renderOverviewCards(){if(!this.data.overview)return"";let{overview:a}=this.data;return`
      <div class="overview-cards">
        ${[{title:"Total Rules",value:a.totalRules,icon:this.icon("target")},{title:"Active Rules",value:a.activeRules,icon:this.icon("check")},{title:"Total Clicks",value:this.formatNumber(a.totalClicks),icon:this.icon("mouse")},{title:"Countries",value:a.countriesTargeted,icon:this.icon("globe")},{title:"Conversion",value:`${a.conversionRate}%`,icon:this.icon("chart")},{title:"Groups",value:a.variantGroups,icon:this.icon("users")}].map(e=>`
          <div class="metric-card">
            <div class="metric-icon">${e.icon}</div>
            <div class="metric-content">
              <div class="metric-value">${e.value}</div>
              <div class="metric-label">${e.title}</div>
            </div>
          </div>
        `).join("")}
      </div>
    `}renderCountryChart(){if(!this.data.countries.length)return"";let a=this.data.countries.slice(0,8),s=Math.max(...a.map(e=>e.clicks));return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("globe",16)} Top Countries by Clicks</h3>
          <span class="chart-subtitle">${this.data.countries.length} countries tracked</span>
        </div>
        <div class="country-bars">
          ${a.map(e=>`
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${e.countryName}</span>
                <span class="country-code">(${e.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width: ${e.clicks/s*100}%"></div>
                <span class="bar-value">${e.clicks}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}renderTrendsChart(){if(!this.data.trends.length)return"";let a=this.data.trends.reduce((i,t)=>i+t.clicks,0),s=this.data.trends.reduce((i,t)=>i+t.views,0),e=this.data.trends.length>0?(this.data.trends.reduce((i,t)=>i+t.conversionRate,0)/this.data.trends.length).toFixed(1):0;return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("chart",16)} Performance Trends</h3>
          <span class="chart-subtitle">Last 30 days</span>
        </div>
        <div class="trends-summary">
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(a)}</div>
            <div class="trend-label">Total Clicks</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(s)}</div>
            <div class="trend-label">Total Views</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${e}%</div>
            <div class="trend-label">Avg Conversion</div>
          </div>
        </div>
        <div class="trends-chart" id="trends-chart">
          ${this.renderTrendsBars()}
        </div>
      </div>
    `}renderTrendsBars(){let a=Math.max(...this.data.trends.map(e=>Math.max(e.clicks,e.views)));return`
      <div class="trends-bars">
        ${this.data.trends.slice(-14).map((e,i)=>{let t=new Date(e.date).toLocaleDateString("en-US",{month:"short",day:"numeric"}),n=e.clicks/a*100,c=e.views/a*100;return`
            <div class="trend-bar-group">
              <div class="trend-bar-container">
                <div class="trend-bar clicks-bar" style="height: ${n}%"></div>
                <div class="trend-bar views-bar" style="height: ${c}%"></div>
              </div>
              <div class="trend-date">${t}</div>
            </div>
          `}).join("")}
      </div>
    `}renderRulesTable(){if(!this.data.rules.length)return`
        <div class="chart-container">
          <div class="empty-state">
            <div class="empty-icon">${this.icon("target",40)}</div>
            <h3>No rules found</h3>
            <p>Create your first geo rule to start tracking performance.</p>
          </div>
        </div>
      `;let a=[...this.data.rules].sort((s,e)=>e.clicks-s.clicks);return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("target",16)} Rules Performance</h3>
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
          ${a.slice(0,10).map(s=>`
            <div class="table-row">
              <div class="table-cell">
                <div class="rule-name">${s.title}</div>
                <div class="rule-date">Created ${this.formatDate(s.created)}</div>
              </div>
              <div class="table-cell">
                <span class="type-badge type-${s.type.toLowerCase()}">${s.type}</span>
              </div>
              <div class="table-cell">
                <span class="country-count">${s.countriesCount} countries</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(s.clicks)}</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(s.views)}</span>
              </div>
              <div class="table-cell">
                <span class="conversion-rate ${s.conversionRate>=10?"high":s.conversionRate>=5?"medium":"low"}">
                  ${s.conversionRate}%
                </span>
              </div>
              <div class="table-cell">
                <span class="status-badge ${s.active?"active":"inactive"}">
                  ${s.active?"Active":"Inactive"}
                </span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}renderUntrackedCard(){let a=Array.isArray(this.data.untracked)?this.data.untracked:[];return a.length?`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("flag",16)} Untracked Countries</h3>
          <span class="chart-subtitle">Top ${a.length} without rules</span>
        </div>
        <div class="country-bars">
          ${a.map(s=>`
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${s.countryName}</span>
                <span class="country-code">(${s.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width:${Math.min(100,s.hits/a[0].hits*100)}%"></div>
                <span class="bar-value">${this.formatNumber(s.hits)}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `:""}icon(a,s=20){let t=`width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"`;switch(a){case"globe":return`<svg ${t}><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20a15.3 15.3 0 0 1 0-20z"/></svg>`;case"chart":return`<svg ${t}><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/></svg>`;case"target":return`<svg ${t}><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>`;case"check":return`<svg ${t}><path d="M20 6L9 17l-5-5"/></svg>`;case"mouse":return`<svg ${t}><rect x="8" y="3" width="8" height="18" rx="4"/><path d="M12 7v4"/></svg>`;case"users":return`<svg ${t}><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;case"refresh":return`<svg ${t}><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>`;case"flag":return`<svg ${t}><path d="M4 22V6a2 2 0 0 1 2-2h0l2 1l2-1h6a2 2 0 0 1 2 2v9h-8l-2 1l-2-1H4z"/></svg>`;default:return`<svg ${t}></svg>`}}initCharts(){}formatNumber(a){return new Intl.NumberFormat().format(a)}formatDate(a){return new Date(a).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"})}};document.addEventListener("DOMContentLoaded",()=>{new o});})();
