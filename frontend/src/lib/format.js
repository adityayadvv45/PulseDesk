// Shared, presentation-agnostic helpers.

export function formatMinutes(m) {
  if (m == null) return '—'
  const abs = Math.abs(m)
  const sign = m < 0 ? '-' : ''
  if (abs < 60) return `${sign}${Math.round(abs)}m`
  if (abs < 1440) return `${sign}${(abs / 60).toFixed(1)}h`
  return `${sign}${(abs / 1440).toFixed(1)}d`
}

export function relativeTime(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  const diffMs = Date.now() - date.getTime()
  const diffMin = Math.round(diffMs / 60000)
  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHr = Math.round(diffMin / 60)
  if (diffHr < 24) return `${diffHr}h ago`
  const diffDay = Math.round(diffHr / 24)
  if (diffDay < 7) return `${diffDay}d ago`
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

export function initials(name) {
  return (name || '?')
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

export function hueFromString(str) {
  return [...(str || 'x')].reduce((a, c) => a + c.charCodeAt(0), 0) % 360
}

export const STATUS_LABEL = { open: 'Open', pending: 'Pending', resolved: 'Resolved', closed: 'Closed' }
export const PRIORITY_LABEL = { low: 'Low', medium: 'Medium', high: 'High', urgent: 'Urgent' }
