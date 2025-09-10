import React, { useState } from 'react'
import { 
  Target, 
  MousePointer, 
  Eye, 
  TrendingUp, 
  MapPin,
  MoreHorizontal,
  ExternalLink
} from 'lucide-react'

const RulesTable = ({ data }) => {
  const [sortField, setSortField] = useState('clicks')
  const [sortDirection, setSortDirection] = useState('desc')

  const handleSort = (field) => {
    if (sortField === field) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc')
    } else {
      setSortField(field)
      setSortDirection('desc')
    }
  }

  const sortedData = [...data].sort((a, b) => {
    const aVal = a[sortField]
    const bVal = b[sortField]
    
    if (sortDirection === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })

  const getStatusBadge = (active) => {
    return active ? (
      <span className="badge badge-success">Active</span>
    ) : (
      <span className="badge badge-danger">Inactive</span>
    )
  }

  const getTypeBadge = (type) => {
    const colors = {
      'Page': 'badge-info',
      'Popup': 'badge-warning',
      'Section': 'badge-success',
      'Form': 'badge-danger',
      'Widget': 'badge-info'
    }
    
    return (
      <span className={`badge ${colors[type] || 'badge-info'}`}>
        {type}
      </span>
    )
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  return (
    <div className="chart-container">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-2">
          <Target className="w-5 h-5 text-gray-600" />
          <h3 className="text-lg font-semibold text-gray-900">Rules Performance</h3>
        </div>
        <div className="text-sm text-gray-500">
          {data.length} total rules
        </div>
      </div>

      <div className="table-container">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th 
                className="table-header cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort('title')}
              >
                <div className="flex items-center space-x-1">
                  <span>Rule Name</span>
                  {sortField === 'title' && (
                    <span className="text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                  )}
                </div>
              </th>
              <th 
                className="table-header cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort('type')}
              >
                <div className="flex items-center space-x-1">
                  <span>Type</span>
                  {sortField === 'type' && (
                    <span className="text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                  )}
                </div>
              </th>
              <th className="table-header">Countries</th>
              <th 
                className="table-header cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort('clicks')}
              >
                <div className="flex items-center space-x-1">
                  <MousePointer className="w-4 h-4" />
                  <span>Clicks</span>
                  {sortField === 'clicks' && (
                    <span className="text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                  )}
                </div>
              </th>
              <th 
                className="table-header cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort('views')}
              >
                <div className="flex items-center space-x-1">
                  <Eye className="w-4 h-4" />
                  <span>Views</span>
                  {sortField === 'views' && (
                    <span className="text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                  )}
                </div>
              </th>
              <th 
                className="table-header cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort('conversionRate')}
              >
                <div className="flex items-center space-x-1">
                  <TrendingUp className="w-4 h-4" />
                  <span>Conversion</span>
                  {sortField === 'conversionRate' && (
                    <span className="text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                  )}
                </div>
              </th>
              <th className="table-header">Status</th>
              <th className="table-header">Actions</th>
            </tr>
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {sortedData.map((rule) => (
              <tr key={rule.id} className="hover:bg-gray-50">
                <td className="table-cell">
                  <div className="flex items-center space-x-3">
                    <div>
                      <div className="text-sm font-medium text-gray-900">
                        {rule.title}
                      </div>
                      <div className="text-xs text-gray-500">
                        Created {formatDate(rule.created)}
                      </div>
                    </div>
                  </div>
                </td>
                <td className="table-cell">
                  {getTypeBadge(rule.type)}
                </td>
                <td className="table-cell">
                  <div className="flex items-center space-x-1">
                    <MapPin className="w-4 h-4 text-gray-400" />
                    <span className="text-sm text-gray-900">
                      {rule.countriesCount} countries
                    </span>
                  </div>
                  <div className="text-xs text-gray-500 mt-1">
                    {rule.countries.slice(0, 3).join(', ')}
                    {rule.countries.length > 3 && ` +${rule.countries.length - 3} more`}
                  </div>
                </td>
                <td className="table-cell">
                  <div className="flex items-center space-x-1">
                    <span className="text-sm font-medium text-gray-900">
                      {rule.clicks.toLocaleString()}
                    </span>
                  </div>
                </td>
                <td className="table-cell">
                  <div className="flex items-center space-x-1">
                    <span className="text-sm font-medium text-gray-900">
                      {rule.views.toLocaleString()}
                    </span>
                  </div>
                </td>
                <td className="table-cell">
                  <div className="flex items-center space-x-1">
                    <span className={`text-sm font-medium ${
                      rule.conversionRate >= 10 ? 'text-green-600' : 
                      rule.conversionRate >= 5 ? 'text-yellow-600' : 'text-red-600'
                    }`}>
                      {rule.conversionRate}%
                    </span>
                  </div>
                </td>
                <td className="table-cell">
                  {getStatusBadge(rule.active)}
                </td>
                <td className="table-cell">
                  <div className="flex items-center space-x-2">
                    <button className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                      Edit
                    </button>
                    <button className="text-gray-400 hover:text-gray-600">
                      <MoreHorizontal className="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {data.length === 0 && (
        <div className="text-center py-12">
          <Target className="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">No rules found</h3>
          <p className="text-gray-500">Create your first geo rule to start tracking performance.</p>
        </div>
      )}
    </div>
  )
}

export default RulesTable