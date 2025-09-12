var GeoElementorDashboard=(()=>{var d=(v,e,t)=>new Promise((s,i)=>{var a=r=>{try{c(t.next(r))}catch(l){i(l)}},n=r=>{try{c(t.throw(r))}catch(l){i(l)}},c=r=>r.done?s(r.value):Promise.resolve(r.value).then(a,n);c((t=t.apply(v,e)).next())});var o=class{constructor(){this.data={overview:null,countries:[],rules:[],trends:[]},this.init()}init(){return d(this,null,function*(){this.renderLoading(),yield this.fetchData(),this.render()})}fetchData(){return d(this,null,function*(){try{let e=window.egpDashboard&&egpDashboard.restBase?egpDashboard.restBase.replace(/\/$/,""):"/wp-json/geo-elementor/v1",[t,s,i,a,n]=yield Promise.all([this.apiCall(`${e}/analytics/overview`),this.apiCall(`${e}/analytics/countries`),this.apiCall(`${e}/analytics/rules`),this.apiCall(`${e}/analytics/trends`),this.apiCall(`${e}/analytics/untracked`)]);this.data={overview:t,countries:s,rules:i,trends:a,untracked:n}}catch(e){this.renderError()}})}apiCall(e){return d(this,null,function*(){let t={};window.egpDashboard&&egpDashboard.nonce&&(t["X-WP-Nonce"]=egpDashboard.nonce);let s=yield fetch(e,{headers:t});if(!s.ok)throw new Error(`HTTP ${s.status}`);return s.json()})}renderLoading(){let e=document.getElementById("geo-el-admin-app");e&&(e.innerHTML=`
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
    `}renderOverviewCards(){if(!this.data.overview)return"";let{overview:e}=this.data;return`
      <div class="overview-cards">
        ${[{title:"Total Rules",value:e.totalRules,icon:this.icon("target")},{title:"Active Rules",value:e.activeRules,icon:this.icon("check")},{title:"Impressions",value:this.formatNumber(e.totalImpressions),icon:this.icon("eye")},{title:"Total Clicks",value:this.formatNumber(e.totalClicks),icon:this.icon("mouse")},{title:"Form Submissions",value:this.formatNumber(e.totalFormSubmissions),icon:this.icon("file-text")},{title:"Countries",value:e.countriesTargeted,icon:this.icon("globe")},{title:"CTR",value:`${e.clickThroughRate}%`,icon:this.icon("chart")},{title:"Form Conv.",value:`${e.formConversionRate}%`,icon:this.icon("trending-up")},{title:"Groups",value:e.variantGroups,icon:this.icon("users")}].map(s=>`
          <div class="metric-card">
            <div class="metric-icon">${s.icon}</div>
            <div class="metric-content">
              <div class="metric-value">${s.value}</div>
              <div class="metric-label">${s.title}</div>
            </div>
          </div>
        `).join("")}
      </div>
    `}renderCountryChart(){if(!this.data.countries.length)return"";let e=this.data.countries.slice(0,8),t=Math.max(...e.map(s=>s.clicks));return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("globe",16)} Top Countries by Clicks</h3>
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
                <div class="bar" style="width: ${s.clicks/t*100}%"></div>
                <span class="bar-value">${s.clicks}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}renderTrendsChart(){if(!this.data.trends.length)return"";let e=this.data.trends.reduce((i,a)=>i+a.clicks,0),t=this.data.trends.reduce((i,a)=>i+a.views,0),s=this.data.trends.length>0?(this.data.trends.reduce((i,a)=>i+a.conversionRate,0)/this.data.trends.length).toFixed(1):0;return`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("chart",16)} Performance Trends</h3>
          <span class="chart-subtitle">Last 30 days</span>
        </div>
        <div class="trends-summary">
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(e)}</div>
            <div class="trend-label">Total Clicks</div>
          </div>
          <div class="trend-stat">
            <div class="trend-value">${this.formatNumber(t)}</div>
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
        ${this.data.trends.slice(-14).map((s,i)=>{let a=new Date(s.date).toLocaleDateString("en-US",{month:"short",day:"numeric"}),n=s.clicks/e*100,c=s.views/e*100;return`
            <div class="trend-bar-group">
              <div class="trend-bar-container">
                <div class="trend-bar clicks-bar" style="height: ${n}%"></div>
                <div class="trend-bar views-bar" style="height: ${c}%"></div>
              </div>
              <div class="trend-date">${a}</div>
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
      `;let e=[...this.data.rules].sort((t,s)=>s.clicks-t.clicks);return`
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
          ${e.slice(0,10).map(t=>`
            <div class="table-row">
              <div class="table-cell">
                <div class="rule-name">${t.title}</div>
                <div class="rule-date">Created ${this.formatDate(t.created)}</div>
              </div>
              <div class="table-cell">
                <span class="type-badge type-${t.type.toLowerCase()}">${t.type}</span>
              </div>
              <div class="table-cell">
                <span class="country-count">${t.countriesCount} countries</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(t.clicks)}</span>
              </div>
              <div class="table-cell">
                <span class="metric-value">${this.formatNumber(t.views)}</span>
              </div>
              <div class="table-cell">
                <span class="conversion-rate ${t.conversionRate>=10?"high":t.conversionRate>=5?"medium":"low"}">
                  ${t.conversionRate}%
                </span>
              </div>
              <div class="table-cell">
                <span class="status-badge ${t.active?"active":"inactive"}">
                  ${t.active?"Active":"Inactive"}
                </span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `}renderUntrackedCard(){let e=Array.isArray(this.data.untracked)?this.data.untracked:[];return e.length?`
      <div class="chart-container">
        <div class="chart-header">
          <h3>${this.icon("flag",16)} Untracked Countries</h3>
          <span class="chart-subtitle">Top ${e.length} without rules</span>
        </div>
        <div class="country-bars">
          ${e.map(t=>`
            <div class="country-bar">
              <div class="country-info">
                <span class="country-name">${t.countryName}</span>
                <span class="country-code">(${t.country})</span>
              </div>
              <div class="bar-container">
                <div class="bar" style="width:${Math.min(100,t.hits/e[0].hits*100)}%"></div>
                <span class="bar-value">${this.formatNumber(t.hits)}</span>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    `:""}icon(e,t=20){let a=`width="${t}" height="${t}" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"`;switch(e){case"globe":return`<svg ${a}><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20a15.3 15.3 0 0 1 0-20z"/></svg>`;case"chart":return`<svg ${a}><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/></svg>`;case"target":return`<svg ${a}><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>`;case"check":return`<svg ${a}><path d="M20 6L9 17l-5-5"/></svg>`;case"mouse":return`<svg ${a}><rect x="8" y="3" width="8" height="18" rx="4"/><path d="M12 7v4"/></svg>`;case"users":return`<svg ${a}><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`;case"refresh":return`<svg ${a}><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>`;case"flag":return`<svg ${a}><path d="M4 22V6a2 2 0 0 1 2-2h0l2 1l2-1h6a2 2 0 0 1 2 2v9h-8l-2 1l-2-1H4z"/></svg>`;case"eye":return`<svg ${a}><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;case"file-text":return`<svg ${a}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>`;case"trending-up":return`<svg ${a}><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>`;default:return`<svg ${a}></svg>`}}initCharts(){}formatNumber(e){return new Intl.NumberFormat().format(e)}formatDate(e){return new Date(e).toLocaleDateString("en-US",{year:"numeric",month:"short",day:"numeric"})}};document.addEventListener("DOMContentLoaded",()=>{new o});})();
