import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

// Get the container element
const container = document.getElementById('geo-el-admin-app')

if (container) {
  const root = ReactDOM.createRoot(container)
  root.render(<App />)
} else {
  console.error('Dashboard container not found')
}