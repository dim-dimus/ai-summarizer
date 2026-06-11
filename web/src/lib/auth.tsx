"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";
import { api, clearToken, getToken, setToken } from "./api";
import type { User } from "./types";

interface AuthState {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

const USER_KEY = "ai_summarizer_user";

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Restore session from localStorage on first mount. This must run in an
  // effect (browser-only API; reading during render would break hydration).
  useEffect(() => {
    const token = getToken();
    const raw = window.localStorage.getItem(USER_KEY);
    let restored: User | null = null;
    if (token && raw) {
      try {
        restored = JSON.parse(raw) as User;
      } catch {
        clearToken();
      }
    }
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time hydration-safe restore
    setUser(restored);
    setLoading(false);
  }, []);

  const persist = useCallback((u: User, token: string) => {
    setToken(token);
    window.localStorage.setItem(USER_KEY, JSON.stringify(u));
    setUser(u);
  }, []);

  const login = useCallback(
    async (email: string, password: string) => {
      const res = await api.login(email, password);
      persist(res.user, res.token);
    },
    [persist],
  );

  const register = useCallback(
    async (name: string, email: string, password: string) => {
      const res = await api.register(name, email, password);
      persist(res.user, res.token);
    },
    [persist],
  );

  const logout = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // ignore — clear locally regardless
    }
    clearToken();
    window.localStorage.removeItem(USER_KEY);
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
