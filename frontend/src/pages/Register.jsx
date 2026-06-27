import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [mode, setMode] = useState('new') // 'new' org (admin) | 'join' existing org (customer)
  const [form, setForm] = useState({
    name: '', email: '', password: '', password_confirmation: '',
    organization_name: '', organization_slug: '',
  })
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  function update(key, val) {
    setForm((f) => ({ ...f, [key]: val }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setBusy(true)
    try {
      const payload = {
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      }
      if (mode === 'new') payload.organization_name = form.organization_name
      else payload.organization_slug = form.organization_slug

      await register(payload)
      navigate('/tickets')
    } catch (err) {
      const msg = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : err.response?.data?.message || 'Could not create your account.'
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth-shell">
      <div className="auth-card">
        <div className="auth-logo">
          <div className="logo-mark"><i className="ti ti-headset" aria-hidden="true" /></div>
          <div className="logo-text">PulseDesk</div>
        </div>
        <div className="auth-title">Create your account</div>
        <div className="auth-sub">Start a new support desk, or join one as a customer.</div>

        <div className="auth-radio-group" style={{ marginBottom: 16 }}>
          <div className={`auth-radio ${mode === 'new' ? 'active' : ''}`} onClick={() => setMode('new')}>
            New organization
          </div>
          <div className={`auth-radio ${mode === 'join' ? 'active' : ''}`} onClick={() => setMode('join')}>
            Join existing
          </div>
        </div>

        {error && <div className="auth-error" style={{ marginBottom: 14 }}>{error}</div>}

        <form className="auth-form" onSubmit={handleSubmit}>
          <div className="field">
            <label htmlFor="name">Your name</label>
            <input id="name" className="input" required value={form.name} onChange={(e) => update('name', e.target.value)} placeholder="Jordan Lee" />
          </div>
          <div className="field">
            <label htmlFor="email">Email</label>
            <input id="email" className="input" type="email" required value={form.email} onChange={(e) => update('email', e.target.value)} placeholder="you@company.com" />
          </div>

          {mode === 'new' ? (
            <div className="field">
              <label htmlFor="org_name">Organization name</label>
              <input id="org_name" className="input" required value={form.organization_name} onChange={(e) => update('organization_name', e.target.value)} placeholder="Acme Support" />
            </div>
          ) : (
            <div className="field">
              <label htmlFor="org_slug">Organization slug</label>
              <input id="org_slug" className="input" required value={form.organization_slug} onChange={(e) => update('organization_slug', e.target.value)} placeholder="acme" />
            </div>
          )}

          <div className="field">
            <label htmlFor="password">Password</label>
            <input id="password" className="input" type="password" required minLength={8} value={form.password} onChange={(e) => update('password', e.target.value)} placeholder="At least 8 characters" />
          </div>
          <div className="field">
            <label htmlFor="password_confirmation">Confirm password</label>
            <input id="password_confirmation" className="input" type="password" required value={form.password_confirmation} onChange={(e) => update('password_confirmation', e.target.value)} placeholder="Repeat your password" />
          </div>

          <button className="btn primary full" type="submit" disabled={busy}>
            {busy ? 'Creating account\u2026' : 'Create account'}
          </button>
        </form>

        <div className="auth-foot">
          Already have an account? <Link to="/login" style={{ color: 'var(--text-accent)', fontWeight: 500 }}>Sign in</Link>
        </div>
      </div>
    </div>
  )
}
