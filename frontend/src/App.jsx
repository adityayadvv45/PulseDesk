import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import ProtectedRoute from './components/ProtectedRoute'
import Login from './pages/Login'
import Register from './pages/Register'
import Tickets from './pages/Tickets'
import Dashboard from './pages/Dashboard'
import SlaSettings from './pages/SlaSettings'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />

          <Route path="/" element={<Navigate to="/tickets" replace />} />

          <Route path="/tickets" element={<ProtectedRoute><Tickets scope="all" /></ProtectedRoute>} />
          <Route path="/tickets/:id" element={<ProtectedRoute><Tickets scope="all" /></ProtectedRoute>} />

          <Route path="/tickets/mine" element={<ProtectedRoute><Tickets scope="mine" /></ProtectedRoute>} />
          <Route path="/tickets/mine/:id" element={<ProtectedRoute><Tickets scope="mine" /></ProtectedRoute>} />

          <Route path="/tickets/unassigned" element={<ProtectedRoute><Tickets scope="unassigned" /></ProtectedRoute>} />
          <Route path="/tickets/unassigned/:id" element={<ProtectedRoute><Tickets scope="unassigned" /></ProtectedRoute>} />

          <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
          <Route path="/sla" element={<ProtectedRoute><SlaSettings /></ProtectedRoute>} />

          <Route path="*" element={<Navigate to="/tickets" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
