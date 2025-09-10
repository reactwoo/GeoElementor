import React from 'react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts'
import { Globe } from 'lucide-react'

const CountryChart = ({ data }) => {
  // Prepare data for charts
  const barData = data.slice(0, 10).map(country => ({
    name: country.country,
    fullName: country.countryName,
    clicks: country.clicks,
    views: country.views,
    rules: country.rules
  }))

  const pieData = data.slice(0, 5).map(country => ({
    name: country.country,
    fullName: country.countryName,
    value: country.clicks,
    color: getRandomColor()
  }))

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']

  function getRandomColor() {
    return COLORS[Math.floor(Math.random() * COLORS.length)]
  }

  const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-lg">
          <p className="font-semibold">{payload[0].payload.fullName}</p>
          <p className="text-blue-600">Clicks: {payload[0].value}</p>
          <p className="text-green-600">Views: {payload[0].payload.views}</p>
          <p className="text-gray-600">Rules: {payload[0].payload.rules}</p>
        </div>
      )
    }
    return null
  }

  return (
    <div className="chart-container">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-2">
          <Globe className="w-5 h-5 text-gray-600" />
          <h3 className="text-lg font-semibold text-gray-900">Top Countries by Clicks</h3>
        </div>
        <div className="text-sm text-gray-500">
          {data.length} countries tracked
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Bar Chart */}
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={barData} margin={{ top: 20, right: 30, left: 20, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis 
                dataKey="name" 
                tick={{ fontSize: 12 }}
                angle={-45}
                textAnchor="end"
                height={60}
              />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip content={<CustomTooltip />} />
              <Bar dataKey="clicks" fill="#3b82f6" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Pie Chart */}
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={pieData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {pieData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip formatter={(value, name, props) => [
                value, 
                `${props.payload.fullName} (${props.payload.name})`
              ]} />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Top Countries List */}
      <div className="mt-6">
        <h4 className="text-sm font-medium text-gray-700 mb-3">Top Performing Countries</h4>
        <div className="space-y-2">
          {data.slice(0, 5).map((country, index) => (
            <div key={country.country} className="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
              <div className="flex items-center space-x-3">
                <span className="text-sm font-medium text-gray-500">#{index + 1}</span>
                <span className="text-sm font-medium text-gray-900">{country.countryName}</span>
                <span className="text-xs text-gray-500">({country.country})</span>
              </div>
              <div className="flex items-center space-x-4 text-sm">
                <span className="text-blue-600 font-medium">{country.clicks} clicks</span>
                <span className="text-gray-500">{country.rules} rules</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

export default CountryChart