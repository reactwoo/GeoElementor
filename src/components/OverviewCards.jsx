import React from 'react'
import { 
  Target, 
  MousePointer, 
  Globe, 
  TrendingUp,
  Users,
  Activity
} from 'lucide-react'

const OverviewCards = ({ data }) => {
  const cards = [
    {
      title: 'Total Rules',
      value: data.totalRules,
      icon: Target,
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
      change: null
    },
    {
      title: 'Active Rules',
      value: data.activeRules,
      icon: Activity,
      color: 'text-green-600',
      bgColor: 'bg-green-100',
      change: data.totalRules > 0 ? Math.round((data.activeRules / data.totalRules) * 100) : 0,
      changeLabel: '% active'
    },
    {
      title: 'Total Clicks',
      value: data.totalClicks.toLocaleString(),
      icon: MousePointer,
      color: 'text-purple-600',
      bgColor: 'bg-purple-100',
      change: data.todayClicks,
      changeLabel: 'today'
    },
    {
      title: 'Countries Targeted',
      value: data.countriesTargeted,
      icon: Globe,
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
      change: null
    },
    {
      title: 'Conversion Rate',
      value: `${data.conversionRate}%`,
      icon: TrendingUp,
      color: 'text-indigo-600',
      bgColor: 'bg-indigo-100',
      change: null
    },
    {
      title: 'Variant Groups',
      value: data.variantGroups,
      icon: Users,
      color: 'text-pink-600',
      bgColor: 'bg-pink-100',
      change: null
    }
  ]

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      {cards.map((card, index) => {
        const Icon = card.icon
        return (
          <div key={index} className="metric-card">
            <div className="flex items-center">
              <div className={`flex-shrink-0 p-3 rounded-lg ${card.bgColor}`}>
                <Icon className={`w-6 h-6 ${card.color}`} />
              </div>
              <div className="ml-4 flex-1">
                <p className="metric-label">{card.title}</p>
                <p className="metric-value">{card.value}</p>
                {card.change !== null && (
                  <p className="metric-change positive">
                    +{card.change} {card.changeLabel}
                  </p>
                )}
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default OverviewCards