import React, { useState, useEffect } from 'react'
import { 
  BarChart3, 
  Globe, 
  Target, 
  TrendingUp, 
  Users, 
  MousePointer,
  Activity,
  MapPin
} from 'lucide-react'
import OverviewCards from './components/OverviewCards'
import CountryChart from './components/CountryChart'
import RulesTable from './components/RulesTable'
import TrendsChart from './components/TrendsChart'
import LoadingSpinner from './components/LoadingSpinner'

function App() {
  const [loading, setLoading] = useState(true)
  const [data, setData] = useState({
    overview: null,
    countries: [],
    rules: [],
    trends: []
  })

  useEffect(() => {
    fetchDashboardData()
  }, [])

  const fetchDashboardData = async () => {
    try {
      setLoading(true)
      
      const [overviewRes, countriesRes, rulesRes, trendsRes] = await Promise.all([
        fetch('/wp-json/geo-elementor/v1/analytics/overview'),
        fetch('/wp-json/geo-elementor/v1/analytics/countries'),
        fetch('/wp-json/geo-elementor/v1/analytics/rules'),
        fetch('/wp-json/geo-elementor/v1/analytics/trends')
      ])

      const [overview, countries, rules, trends] = await Promise.all([
        overviewRes.json(),
        countriesRes.json(),
        rulesRes.json(),
        trendsRes.json()
      ])

      setData({
        overview,
        countries,
        rules,
        trends
      })
    } catch (error) {
      console.error('Error fetching dashboard data:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return <LoadingSpinner />
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b border-gray-200">
        <div className="px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="flex items-center justify-center w-10 h-10 bg-primary-600 rounded-lg">
                <Globe className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Geo Analytics Dashboard</h1>
                <p className="text-sm text-gray-500">Monitor your geo-targeted content performance</p>
              </div>
            </div>
            <button
              onClick={fetchDashboardData}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
            >
              <Activity className="w-4 h-4 mr-2" />
              Refresh Data
            </button>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="p-6 space-y-6">
        {/* Overview Cards */}
        {data.overview && <OverviewCards data={data.overview} />}

        {/* Charts Row */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Country Performance Chart */}
          <CountryChart data={data.countries} />
          
          {/* Trends Chart */}
          <TrendsChart data={data.trends} />
        </div>

        {/* Rules Performance Table */}
        <RulesTable data={data.rules} />
      </div>
    </div>
  )
}

export default App