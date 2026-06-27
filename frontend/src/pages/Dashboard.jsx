import { useEffect, useState } from 'react'
import Layout from '../components/Layout'
import { Spinner } from '../components/ui'
import api from '../lib/api'

const STATUS_COLORS = {
  open: 'var(--fill-accent)',
  pending: 'var(--fill-warning)',
  resolved: 'var(--fill-success)',
  closed: 'var(--border-stronger)',
}
const PRIORITY_COLORS = {
  urgent: 'var(--fill-danger)',
  high: 'var(--fill-warning)',
  medium: 'var(--fill-accent)',
  low: 'var(--border-stronger)',
}

export default function Dashboard() {
  const [metrics, setMetrics] = useState(null)
  const [agentLoad, setAgentLoad] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    async function load() {
      try {
        const [{ data: m }, { data: agents }] = await Promise.all([
          api.get('/dashboard/metrics'),
          api.get('/agents').catch(() => ({ data: [] })),
        ])
        if (cancelled) return
        setMetrics(m)

        // Agent workload: count tickets currently assigned to each agent.
        const counts = await Promise.all(
          agents.map((a) => api.get('/tickets', { params: { assignee_id: a.id, per_page: 1 } }).then((r) => ({ name: a.name, count: r.data.total })))
        )
        if (!cancelled) setAgentLoad(counts)
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    load()
    return () => { cancelled = true }
  }, [])

  if (loading || !metrics) {
    return (
      <Layout title="Dashboard">
        <div className="content" style={{ display: 'flex', justifyContent: 'center', paddingTop: 80 }}><Spinner /></div>
      </Layout>
    )
  }

  const statusEntries = [
    { label: 'Open', key: 'open', count: metrics.open },
    { label: 'Pending', key: 'pending', count: metrics.pending },
    { label: 'Resolved', key: 'resolved', count: metrics.resolved },
    { label: 'Closed', key: 'closed', count: metrics.closed },
  ]
  const priorityEntries = Object.entries(metrics.by_priority || {}).map(([key, count]) => ({
    label: key[0].toUpperCase() + key.slice(1), key, count,
  }))
  const maxPriority = Math.max(1, ...priorityEntries.map((p) => p.count))
  const maxAgent = Math.max(1, ...agentLoad.map((a) => a.count))

  return (
    <Layout title="Dashboard">
      <div className="content">
        <div className="stat-grid">
          <Stat label="Open tickets" value={metrics.open} color="var(--text-accent)" />
          <Stat label="Unassigned" value={metrics.unassigned} color="var(--text-danger)" />
          <Stat label="Avg first response" value={metrics.avg_first_response_minutes != null ? `${(metrics.avg_first_response_minutes / 60).toFixed(1)}h` : '\u2014'} />
          <Stat label="SLA breach rate" value={`${metrics.sla_breach_rate}%`} color="var(--text-warning)" />
        </div>

        <div className="dash-charts">
          <div className="chart-card">
            <div className="chart-title">Tickets created \u2014 last 14 days</div>
            <BarChart data={metrics.created_per_day} />
          </div>

          <div className="chart-card">
            <div className="chart-title">By status</div>
            <Donut entries={statusEntries} colors={STATUS_COLORS} total={metrics.total} />
          </div>

          <div className="chart-card">
            <div className="chart-title">By priority</div>
            <div className="metric-row">
              {priorityEntries.map((p) => (
                <MetricBar key={p.key} label={p.label} value={p.count} max={maxPriority} color={PRIORITY_COLORS[p.key]} />
              ))}
            </div>
          </div>

          <div className="chart-card">
            <div className="chart-title">Agent workload</div>
            <div className="metric-row">
              {agentLoad.length === 0 ? (
                <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>No agents yet.</div>
              ) : (
                agentLoad.map((a) => (
                  <MetricBar key={a.name} label={a.name} value={a.count} max={maxAgent} color="var(--fill-accent)" />
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  )
}

function Stat({ label, value, color }) {
  return (
    <div className="stat-card">
      <div className="stat-label">{label}</div>
      <div className="stat-val" style={color ? { color } : undefined}>{value}</div>
    </div>
  )
}

function MetricBar({ label, value, max, color }) {
  return (
    <div className="metric-row-item">
      <div className="metric-row-top">
        <span>{label}</span>
        <span style={{ color: 'var(--text-muted)' }}>{value}</span>
      </div>
      <div className="metric-row-track">
        <div className="metric-row-fill" style={{ width: `${Math.round((value / max) * 100)}%`, background: color }} />
      </div>
    </div>
  )
}

function BarChart({ data }) {
  const max = Math.max(1, ...data.map((d) => d.count))
  return (
    <div className="bar-chart">
      {data.map((d) => {
        const date = new Date(d.day)
        const label = date.toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 2)
        return (
          <div className="bar-wrap" key={d.day} title={`${d.day}: ${d.count}`}>
            <div className="bar" style={{ height: `${Math.max(3, (d.count / max) * 100)}px`, background: 'var(--fill-accent)' }} />
            <div className="bar-lbl">{label}</div>
          </div>
        )
      })}
    </div>
  )
}

function Donut({ entries, colors, total }) {
  const r = 38, cx = 45, cy = 45
  let angle = -Math.PI / 2
  const paths = entries
    .filter((e) => e.count > 0)
    .map((e) => {
      const frac = total ? e.count / total : 0
      const sweep = frac * 2 * Math.PI
      const x1 = cx + r * Math.cos(angle)
      const y1 = cy + r * Math.sin(angle)
      angle += sweep
      const x2 = cx + r * Math.cos(angle)
      const y2 = cy + r * Math.sin(angle)
      const large = sweep > Math.PI ? 1 : 0
      return { d: `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${large} 1 ${x2},${y2} Z`, color: colors[e.key] }
    })

  return (
    <div className="donut-wrap">
      <svg width="90" height="90" viewBox="0 0 90 90" aria-hidden="true">
        {paths.map((p, i) => <path key={i} d={p.d} fill={p.color} opacity="0.9" />)}
        <circle cx={cx} cy={cy} r="22" fill="var(--surface-1)" />
      </svg>
      <div className="legend">
        {entries.map((e) => (
          <div className="legend-item" key={e.key}>
            <div className="legend-dot" style={{ background: colors[e.key] }} />
            {e.label} ({e.count})
          </div>
        ))}
      </div>
    </div>
  )
}
