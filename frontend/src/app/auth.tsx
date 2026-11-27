import { createContext, useContext, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiError } from '../lib/api'
import type { User } from '../lib/types'

type Me = { data: User; permissions: string[] }
type AuthState = {
  user: User | null
  permissions: string[]
  loading: boolean
  can: (permission: string) => boolean
  signIn: (email: string, password: string) => Promise<void>
  signOut: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const client = useQueryClient()
  const me = useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        return await api<Me>('/auth/me')
      } catch (e) {
        if (e instanceof ApiError && e.problem.status === 401) return null
        throw e
      }
    },
    staleTime: 60_000,
  })

  const permissions = me.data?.permissions ?? []
  const value: AuthState = {
    user: me.data?.data ?? null,
    permissions,
    loading: me.isLoading,
    // Navigation hint only: every action is authorised again by the API.
    can: p => permissions.includes(p),
    signIn: async (email, password) => {
      const result = await api<Me>('/auth/login', { method: 'POST', body: { email, password } })
      client.setQueryData(['me'], result)
    },
    signOut: async () => {
      await api('/auth/logout', { method: 'POST' })
      // A full reload drops every cached record from memory, not just the session.
      client.clear()
      window.location.assign('/login')
    },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
