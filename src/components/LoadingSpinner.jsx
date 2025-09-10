import React from 'react'
import { Activity } from 'lucide-react'

const LoadingSpinner = () => {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="text-center">
        <div className="flex items-center justify-center mb-4">
          <Activity className="w-8 h-8 text-primary-600 animate-spin" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">Loading Dashboard</h3>
        <p className="text-gray-500">Fetching your analytics data...</p>
      </div>
    </div>
  )
}

export default LoadingSpinner