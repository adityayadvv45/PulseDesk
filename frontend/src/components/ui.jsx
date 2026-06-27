import { formatMinutes, initials, hueFromString } from '../lib/format'

export function StatusBadge({ status }) {
  return <span className={`badge ${status}`}>{status}</span>
}

export function PriorityBadge({ priority }) {
  return <span className={`badge ${priority}`}>{priority}</span>
}

export function RoleBadge({ role }) {
  return <span className={`role-badge role-${role}`}>{role}</span>
}

export function TagPill({ name, color }) {
  return (
    <span className="tag-pill" style={{ backgroundColor: color || '#64748b' }}>
      {name}
    </span>
  )
}

export function Avatar({ name, size = 28, tone = 'accent' }) {
  const hue = hueFromString(name)
  const style =
    tone === 'agent'
      ? { background: 'var(--bg-success)', color: 'var(--text-success)' }
      : { background: `hsl(${hue} 60% 94%)`, color: `hsl(${hue} 45% 38%)` }
  return (
    <span
      className="avatar"
      style={{ width: size, height: size, fontSize: size * 0.38, ...style }}
    >
      {initials(name)}
    </span>
  )
}

// Live "time remaining / breached" indicator for SLA targets.
export function SlaPill({ sla, label }) {
  if (!sla) return null
  let text, cls
  if (sla.met) {
    text = `${label} met`
    cls = 'ok'
  } else if (sla.breached) {
    text = `${label} breached`
    cls = 'breach'
  } else {
    text = `${label} \u00b7 ${formatMinutes(sla.minutes_remaining)} left`
    cls = sla.minutes_remaining < 60 ? 'warn' : 'ok'
  }
  return (
    <span className={`sla-pill ${cls}`}>
      <ClockIcon />
      {text}
    </span>
  )
}

export function ClockIcon() {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v5l3 2" strokeLinecap="round" />
    </svg>
  )
}

export function Spinner({ size = 20 }) {
  return (
    <svg className="spinner" width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" opacity="0.18" />
      <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
    </svg>
  )
}

export function EmptyState({ icon = 'ti-ticket-off', title, hint }) {
  return (
    <div className="empty-state">
      <i className={`ti ${icon}`} aria-hidden="true" />
      <div className="empty-title">{title}</div>
      {hint && <div className="empty-hint">{hint}</div>}
    </div>
  )
}
