import { useEffect, useState } from 'react'
import Layout from '../components/Layout'
import { PriorityBadge, Spinner } from '../components/ui'
import { useAuth } from '../context/AuthContext'
import api from '../lib/api'

function minutesToLabel(mins) {
  if (mins % 1440 === 0) return `${mins / 1440}d`
  if (mins % 60 === 0) return `${mins / 60}h`
  return `${mins}m`
}
function labelToMinutes(label) {
  const m = label.trim().match(/^(\d+(?:\.\d+)?)\s*(m|h|d)?$/i)
  if (!m) return null
  const n = parseFloat(m[1])
  const unit = (m[2] || 'm').toLowerCase()
  if (unit === 'd') return Math.round(n * 1440)
  if (unit === 'h') return Math.round(n * 60)
  return Math.round(n)
}

export default function SlaSettings() {
  const { user } = useAuth()
  const [policies, setPolicies] = useState([])
  const [loading, setLoading] = useState(true)
  const [editingId, setEditingId] = useState(null)
  const [draft, setDraft] = useState({ response: '', resolution: '' })
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    api.get('/sla-policies').then(({ data }) => setPolicies(data)).finally(() => setLoading(false))
  }, [])

  function startEdit(p) {
    setEditingId(p.id)
    setDraft({ response: minutesToLabel(p.response_minutes), resolution: minutesToLabel(p.resolution_minutes) })
  }

  async function save(p) {
    const response_minutes = labelToMinutes(draft.response)
    const resolution_minutes = labelToMinutes(draft.resolution)
    if (!response_minutes || !resolution_minutes) return
    setSaving(true)
    try {
      const { data } = await api.patch(`/sla-policies/${p.id}`, { response_minutes, resolution_minutes })
      setPolicies((list) => list.map((x) => (x.id === p.id ? data : x)))
      setEditingId(null)
    } finally {
      setSaving(false)
    }
  }

  const canEdit = user?.role === 'admin'

  return (
    <Layout title="SLA policies">
      <div className="content">
        <div style={{ marginBottom: 16, fontSize: 13, color: 'var(--text-secondary)' }}>
          SLA policies define response and resolution time targets per priority. Use values like <code>30m</code>, <code>4h</code>, or <code>2d</code>.
        </div>

        {loading ? (
          <div style={{ display: 'flex', justifyContent: 'center', padding: 40 }}><Spinner /></div>
        ) : (
          <div style={{ border: '0.5px solid var(--border)', borderRadius: 'var(--radius)', overflow: 'hidden' }}>
            <div className="sla-table-head">
              <div>Priority</div><div>First response</div><div>Resolution</div><div>Updated</div><div></div>
            </div>
            {policies.map((p, i) => (
              <div
                key={p.id}
                className="sla-table-row"
                style={{ borderTop: i > 0 ? '0.5px solid var(--border)' : 'none', background: 'var(--surface-1)' }}
              >
                <div><PriorityBadge priority={p.priority} /></div>
                {editingId === p.id ? (
                  <>
                    <div><input className="input" style={{ maxWidth: 100 }} value={draft.response} onChange={(e) => setDraft((d) => ({ ...d, response: e.target.value }))} /></div>
                    <div><input className="input" style={{ maxWidth: 100 }} value={draft.resolution} onChange={(e) => setDraft((d) => ({ ...d, resolution: e.target.value }))} /></div>
                    <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>{new Date(p.updated_at).toLocaleDateString()}</div>
                    <div style={{ display: 'flex', gap: 6 }}>
                      <button className="btn sm primary" onClick={() => save(p)} disabled={saving}>Save</button>
                      <button className="btn sm ghost" onClick={() => setEditingId(null)}>Cancel</button>
                    </div>
                  </>
                ) : (
                  <>
                    <div style={{ fontSize: 13 }}>{minutesToLabel(p.response_minutes)}</div>
                    <div style={{ fontSize: 13 }}>{minutesToLabel(p.resolution_minutes)}</div>
                    <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>{new Date(p.updated_at).toLocaleDateString()}</div>
                    <div>
                      {canEdit && (
                        <button className="btn sm" onClick={() => startEdit(p)}>
                          <i className="ti ti-edit" aria-hidden="true" /> Edit
                        </button>
                      )}
                    </div>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </Layout>
  )
}
