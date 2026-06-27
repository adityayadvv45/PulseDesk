import { useEffect, useState, useCallback, useRef } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import Layout from '../components/Layout'
import { StatusBadge, PriorityBadge, RoleBadge, TagPill, Avatar, EmptyState, Spinner } from '../components/ui'
import { relativeTime } from '../lib/format'
import { useAuth } from '../context/AuthContext'
import api from '../lib/api'

const STATUS_OPTIONS = ['open', 'pending', 'resolved', 'closed']
const PRIORITY_OPTIONS = ['urgent', 'high', 'medium', 'low']

function slaWidth(sla) {
  if (!sla) return 0
  if (sla.met) return 100
  if (sla.breached) return 100
  const mins = sla.minutes_remaining ?? 0
  return Math.min(100, Math.max(4, 100 - (mins / (24 * 60)) * 100))
}
function slaClass(sla) {
  if (!sla) return 'sla-ok'
  if (sla.breached) return 'sla-breach'
  if (!sla.met && sla.minutes_remaining < 60) return 'sla-warn'
  return 'sla-ok'
}

// scope: 'all' | 'mine' | 'unassigned' — controls the base ticket pool for this view.
export default function Tickets({ scope = 'all' }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const { id: ticketId } = useParams()
  const [searchParams] = useSearchParams()

  const [tickets, setTickets] = useState([])
  const [loading, setLoading] = useState(true)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [priorityFilter, setPriorityFilter] = useState('all')

  const [active, setActive] = useState(null)
  const [activeLoading, setActiveLoading] = useState(false)
  const [agents, setAgents] = useState([])
  const [replyType, setReplyType] = useState('public')
  const [replyText, setReplyText] = useState('')
  const [sending, setSending] = useState(false)

  const searchTimer = useRef(null)

  const loadTickets = useCallback(async () => {
    setLoading(true)
    try {
      const params = { per_page: 50 }
      if (scope === 'mine') params.mine = 1
      if (scope === 'unassigned') params.assignee_id = 'unassigned'
      if (statusFilter !== 'all') params.status = statusFilter
      if (priorityFilter !== 'all') params.priority = priorityFilter
      if (search) params.search = search

      const { data } = await api.get('/tickets', { params })
      setTickets(data.data)
      setTotal(data.total)
    } catch {
      setTickets([])
    } finally {
      setLoading(false)
    }
  }, [scope, statusFilter, priorityFilter, search])

  useEffect(() => { loadTickets() }, [loadTickets])

  useEffect(() => {
    if (user?.role !== 'customer') {
      api.get('/agents').then(({ data }) => setAgents(data)).catch(() => {})
    }
  }, [user])

  const loadDetail = useCallback(async (id) => {
    setActiveLoading(true)
    try {
      const { data } = await api.get(`/tickets/${id}`)
      setActive(data)
    } catch {
      setActive(null)
      navigate(scope === 'all' ? '/tickets' : `/tickets/${scope}`)
    } finally {
      setActiveLoading(false)
    }
  }, [navigate, scope])

  useEffect(() => {
    if (ticketId) loadDetail(ticketId)
    else setActive(null)
  }, [ticketId, loadDetail])

  function onSearchChange(val) {
    setSearch(val)
  }

  function openTicket(t) {
    const base = scope === 'all' ? '/tickets' : `/tickets/${scope}`
    navigate(`${base}/${t.id}`)
  }

  function closeDetail() {
    navigate(scope === 'all' ? '/tickets' : `/tickets/${scope}`)
  }

  async function changeStatus(val) {
    const { data } = await api.patch(`/tickets/${active.id}`, { status: val })
    setActive((a) => ({ ...a, ...data }))
    loadTickets()
  }

  async function changePriority(val) {
    const { data } = await api.patch(`/tickets/${active.id}`, { priority: val })
    setActive((a) => ({ ...a, ...data }))
    loadTickets()
  }

  async function changeAssignee(val) {
    const { data } = await api.post(`/tickets/${active.id}/assign`, { assignee_id: val || null })
    setActive((a) => ({ ...a, ...data }))
    loadTickets()
  }

  async function claimTicket() {
    const { data } = await api.post(`/tickets/${active.id}/claim`)
    setActive((a) => ({ ...a, ...data }))
    loadTickets()
  }

  async function sendReply() {
    if (!replyText.trim()) return
    setSending(true)
    try {
      await api.post(`/tickets/${active.id}/comments`, {
        body: replyText.trim(),
        is_internal: replyType === 'internal',
      })
      setReplyText('')
      await loadDetail(active.id)
    } finally {
      setSending(false)
    }
  }

  const titles = { all: 'All tickets', mine: 'My tickets', unassigned: 'Unassigned' }
  const isStaff = user?.role === 'admin' || user?.role === 'agent'

  return (
    <Layout title={titles[scope]} onSearch={onSearchChange} searchValue={search}
      headerExtra={<NewTicketButton onCreated={loadTickets} />}>
      <div className="split-view">
        <div className="ticket-col">
          <div className="ticket-col-header">
            {scope === 'unassigned' && total > 0 && (
              <div className="banner warning">
                <i className="ti ti-alert-triangle" aria-hidden="true" />
                {total} ticket{total === 1 ? '' : 's'} need assignment \u2014 claim one to start working on it.
              </div>
            )}
            <div className="filters">
              <span className="filter-label">Status:</span>
              {['all', ...STATUS_OPTIONS].map((s) => (
                <button key={s} className={`filter-chip ${statusFilter === s ? 'active' : ''}`} onClick={() => setStatusFilter(s)}>
                  {s === 'all' ? 'All' : s[0].toUpperCase() + s.slice(1)}
                </button>
              ))}
              <span className="filter-label" style={{ marginLeft: 8 }}>Priority:</span>
              {['all', ...PRIORITY_OPTIONS].map((p) => (
                <button key={p} className={`filter-chip ${priorityFilter === p ? 'active' : ''}`} onClick={() => setPriorityFilter(p)}>
                  {p === 'all' ? 'All' : p[0].toUpperCase() + p.slice(1)}
                </button>
              ))}
            </div>
          </div>

          <div className="ticket-col-scroll">
            <div className="ticket-header-row">
              <div>#</div><div>Subject</div><div>Status</div><div>Priority</div><div>Assignee</div><div>Updated</div>
            </div>
            {loading ? (
              <div style={{ padding: 40, display: 'flex', justifyContent: 'center' }}><Spinner /></div>
            ) : tickets.length === 0 ? (
              <EmptyState icon="ti-ticket-off" title="No tickets match your filters" hint="Try clearing a filter or search term." />
            ) : (
              <div className="ticket-list">
                {tickets.map((t) => (
                  <button key={t.id} className={`ticket-row ${active?.id === t.id ? 'selected' : ''}`} onClick={() => openTicket(t)}>
                    <div className="ticket-id">#{t.id}</div>
                    <div>
                      <div className="ticket-subject">{t.subject}</div>
                      <div className="ticket-sub">{t.description}</div>
                      {t.sla_response && (
                        <div className="sla-bar"><div className={`sla-fill ${slaClass(t.sla_response)}`} style={{ width: `${slaWidth(t.sla_response)}%` }} /></div>
                      )}
                    </div>
                    <div><StatusBadge status={t.status} /></div>
                    <div><PriorityBadge priority={t.priority} /></div>
                    <div>
                      {t.assignee ? (
                        <div className="agent-chip"><Avatar name={t.assignee.name} size={22} tone="agent" />{t.assignee.name.split(' ')[0]}</div>
                      ) : (
                        <span className="unassigned-label">Unassigned</span>
                      )}
                    </div>
                    <div className="updated-cell">{relativeTime(t.updated_at)}</div>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        <div className="detail-panel">
          {!ticketId ? (
            <div className="detail-panel-empty">
              <i className="ti ti-ticket" aria-hidden="true" />
              <div style={{ fontSize: 13 }}>Select a ticket to view</div>
            </div>
          ) : activeLoading || !active ? (
            <div className="detail-panel-empty"><Spinner /></div>
          ) : (
            <>
              <div className="detail-header">
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8 }}>
                  <div>
                    <div className="detail-id">#{active.id}</div>
                    <div className="detail-subject">{active.subject}</div>
                  </div>
                  <button className="btn sm ghost" onClick={closeDetail}><i className="ti ti-x" aria-hidden="true" /></button>
                </div>
                <div className="detail-meta">
                  <StatusBadge status={active.status} />
                  <PriorityBadge priority={active.priority} />
                  {active.assignee ? (
                    <div className="agent-chip"><Avatar name={active.assignee.name} size={20} tone="agent" />{active.assignee.name}</div>
                  ) : (
                    <span style={{ fontSize: 12, color: 'var(--text-danger)' }}>Unassigned</span>
                  )}
                  {active.tags?.map((tag) => <TagPill key={tag.id} name={tag.name} color={tag.color} />)}
                </div>

                {active.sla_response && (
                  <div className="detail-sla-row">
                    <div className="detail-sla-label">
                      <span>First response SLA</span>
                      <span style={{ color: active.sla_response.breached ? 'var(--text-danger)' : 'var(--text-muted)' }}>
                        {active.sla_response.met ? 'Met' : active.sla_response.breached ? 'Breached' : `${active.sla_response.minutes_remaining}m left`}
                      </span>
                    </div>
                    <div className="sla-bar" style={{ height: 4 }}><div className={`sla-fill ${slaClass(active.sla_response)}`} style={{ width: `${slaWidth(active.sla_response)}%` }} /></div>
                  </div>
                )}

                {isStaff && (
                  <div className="detail-controls">
                    <select className="select" value={active.status} onChange={(e) => changeStatus(e.target.value)}>
                      {STATUS_OPTIONS.map((s) => <option key={s} value={s}>{s[0].toUpperCase() + s.slice(1)}</option>)}
                    </select>
                    <select className="select" value={active.priority} onChange={(e) => changePriority(e.target.value)}>
                      {PRIORITY_OPTIONS.map((p) => <option key={p} value={p}>{p[0].toUpperCase() + p.slice(1)}</option>)}
                    </select>
                  </div>
                )}
                {isStaff && (
                  <div className="detail-controls">
                    <select className="select" value={active.assignee?.id || ''} onChange={(e) => changeAssignee(e.target.value)}>
                      <option value="">Unassigned</option>
                      {agents.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                    {!active.assignee && <button className="btn sm" onClick={claimTicket}>Claim</button>}
                  </div>
                )}
              </div>

              <div className="detail-body">
                <div className="conv-msg public">
                  <div className="conv-meta">
                    <strong>{active.requester?.name}</strong>
                    <span>{relativeTime(active.created_at)}</span>
                    <RoleBadge role={active.requester?.role || 'customer'} />
                  </div>
                  <div className="conv-msg-text">{active.description}</div>
                </div>

                {active.comments?.map((c) => (
                  <div key={c.id} className={`conv-msg ${c.is_internal ? 'internal' : 'public'}`}>
                    <div className="conv-meta">
                      <strong>{c.user?.name}</strong>
                      <span>{relativeTime(c.created_at)}</span>
                      <span className={`tag ${c.is_internal ? 'internal' : ''}`}>{c.is_internal ? 'internal note' : 'public reply'}</span>
                    </div>
                    <div className="conv-msg-text">{c.body}</div>
                  </div>
                ))}

                {active.activity_logs?.length > 0 && (
                  <div style={{ borderTop: '0.5px solid var(--border)', paddingTop: 10, marginTop: 4 }}>
                    {active.activity_logs.slice(0, 6).map((log) => (
                      <div key={log.id} className="activity-line">
                        <i className="ti ti-point-filled" aria-hidden="true" style={{ fontSize: 8 }} />
                        {describeActivity(log)}
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div className="reply-box">
                <div className="reply-tabs">
                  <button className={`reply-tab ${replyType === 'public' ? 'active' : ''}`} onClick={() => setReplyType('public')}>Public reply</button>
                  {isStaff && (
                    <button className={`reply-tab ${replyType === 'internal' ? 'active' : ''}`} onClick={() => setReplyType('internal')}>Internal note</button>
                  )}
                </div>
                <textarea
                  className="textarea"
                  rows={3}
                  placeholder={replyType === 'internal' ? 'Add an internal note (only agents can see this)\u2026' : 'Write a reply\u2026'}
                  value={replyText}
                  onChange={(e) => setReplyText(e.target.value)}
                />
                <div className="reply-actions">
                  <span style={{ fontSize: 11, color: 'var(--text-muted)' }}>Replying as {user?.name}</span>
                  <button className="btn primary sm" onClick={sendReply} disabled={sending || !replyText.trim()}>
                    {sending ? 'Sending\u2026' : 'Send reply'}
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </Layout>
  )
}

function describeActivity(log) {
  switch (log.action) {
    case 'created': return `${log.user?.name || 'Someone'} opened this ticket`
    case 'status_changed': return `Status changed from ${log.meta?.from} to ${log.meta?.to}`
    case 'priority_changed': return `Priority changed from ${log.meta?.from} to ${log.meta?.to}`
    case 'assigned': return log.meta?.claimed ? `${log.user?.name} claimed this ticket` : `Assigned by ${log.user?.name || 'system'}`
    case 'replied': return `${log.user?.name || 'Someone'} replied`
    case 'internal_note': return `${log.user?.name || 'Someone'} added an internal note`
    default: return log.action
  }
}

function NewTicketButton({ onCreated }) {
  const [open, setOpen] = useState(false)
  const [subject, setSubject] = useState('')
  const [description, setDescription] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    if (!subject.trim() || !description.trim()) return
    setBusy(true)
    try {
      await api.post('/tickets', { subject: subject.trim(), description: description.trim() })
      setSubject('')
      setDescription('')
      setOpen(false)
      onCreated?.()
    } finally {
      setBusy(false)
    }
  }

  if (!open) {
    return (
      <button className="btn primary" onClick={() => setOpen(true)}>
        <i className="ti ti-plus" aria-hidden="true" /> New ticket
      </button>
    )
  }

  return (
    <div style={{ position: 'relative' }}>
      <div style={{
        position: 'absolute', right: 0, top: 40, width: 320, background: 'var(--surface-1)',
        border: '0.5px solid var(--border)', borderRadius: 'var(--radius-lg)', padding: 16,
        boxShadow: '0 8px 24px rgba(0,0,0,0.08)', zIndex: 20,
      }}>
        <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div className="field">
            <label>Subject</label>
            <input className="input" autoFocus value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Short summary" required />
          </div>
          <div className="field">
            <label>Description</label>
            <textarea className="textarea" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} placeholder="What's going on?" required />
          </div>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
            <button type="button" className="btn sm ghost" onClick={() => setOpen(false)}>Cancel</button>
            <button type="submit" className="btn sm primary" disabled={busy}>{busy ? 'Creating\u2026' : 'Create ticket'}</button>
          </div>
        </form>
      </div>
    </div>
  )
}
