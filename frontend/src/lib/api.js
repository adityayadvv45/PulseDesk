import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  headers: { Accept: 'application/json' },
})

// Attach the bearer token on every request.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('pd_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Bounce to login on 401 (session expired / not authenticated).
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('pd_token')
      if (!location.pathname.startsWith('/login') && !location.pathname.startsWith('/register')) {
        location.href = '/login'
      }
    }
    return Promise.reject(err)
  }
)

export default api
