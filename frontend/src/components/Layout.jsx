import { NavLink, useNavigate } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { Avatar } from './ui'
import api from '../lib/api'

export default function Layout({ children, title, headerExtra, onSearch, searchValue }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [counts, setCounts] = useState({ open: null, mine: null, unassigned: null })

  useEffect(() => {
    let cancelled = false
    async function loadCounts() {
      try {
        const [{ data: all }, { data: mine }, { data: unassigned }] = await Promise.all([
          api.get('/tickets', { params: { per_page: 1 } }),
          api.get('/tickets', { params: { mine: 1, per_page: 1 } }),
          api.get('/tickets', { params: { assignee_id: 'unassigned', per_page: 1 } }),
        ])
        if (!cancelled) {
          setCounts({ open: all.total, mine: mine.total, unassigned: unassigned.total })
        }
      } catch {
        /* sidebar counts are non-critical */
      }
    }
    loadCounts()
    return () => { cancelled = true }
  }, [])

  async function handleLogout() {
    await logout()
    navigate('/login')
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="sidebar-logo">
          <div className="logo-mark"><i className="ti ti-headset" aria-hidden="true" /></div>
          <div>
            <div className="logo-text">PulseDesk</div>
            <div className="logo-sub">{user?.organization?.name || '\u2014'}</div>
          </div>
        </div>

        <div className="nav-section">
          <div className="nav-label">Support</div>
          <NavLink to="/tickets" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
            <i className="ti ti-ticket" aria-hidden="true" />
            All tickets
            {counts.open != null && <span className="nav-badge muted">{counts.open}</span>}
          </NavLink>
          <NavLink to="/tickets/mine" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
            <i className="ti ti-user-check" aria-hidden="true" />
            My tickets
            {counts.mine != null && <span className="nav-badge">{counts.mine}</span>}
          </NavLink>
          <NavLink to="/tickets/unassigned" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
            <i className="ti ti-inbox" aria-hidden="true" />
            Unassigned
            {counts.unassigned != null && counts.unassigned > 0 && <span className="nav-badge red">{counts.unassigned}</span>}
          </NavLink>
        </div>

        <div className="nav-section">
          <div className="nav-label">Analytics</div>
          <NavLink to="/dashboard" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
            <i className="ti ti-chart-bar" aria-hidden="true" />
            Dashboard
          </NavLink>
        </div>

        {(user?.role === 'admin' || user?.role === 'agent') && (
          <div className="nav-section">
            <div className="nav-label">Settings</div>
            <NavLink to="/sla" className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
              <i className="ti ti-clock" aria-hidden="true" />
              SLA policies
            </NavLink>
          </div>
        )}

        <div className="sidebar-bottom">
          <div className="avatar-row" onClick={handleLogout} title="Sign out">
            <Avatar name={user?.name} tone={user?.role === 'customer' ? 'default' : 'agent'} />
            <div style={{ overflow: 'hidden' }}>
              <div className="avatar-row-name" style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{user?.name}</div>
              <div className="avatar-row-sub">{user?.role} \u00b7 Sign out</div>
            </div>
          </div>
        </div>
      </aside>

      <div className="main-col">
        <div className="topbar">
          <div className="topbar-title">{title}</div>
          {onSearch && (
            <div className="search-box">
              <i className="ti ti-search" aria-hidden="true" />
              <input
                type="text"
                placeholder="Search tickets\u2026"
                value={searchValue || ''}
                onChange={(e) => onSearch(e.target.value)}
              />
            </div>
          )}
          {headerExtra}
        </div>
        {children}
      </div>
    </div>
  )
}
