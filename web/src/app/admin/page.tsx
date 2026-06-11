"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { AdminStats, Summary, User } from "@/lib/types";
import { StatusBadge } from "@/components/StatusBadge";

export default function AdminPage() {
  const { user, loading } = useAuth();
  const router = useRouter();
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [summaries, setSummaries] = useState<Summary[]>([]);
  const [dataLoading, setDataLoading] = useState(true);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
    } else if (user.role !== "admin") {
      router.replace("/summaries");
    }
  }, [user, loading, router]);

  useEffect(() => {
    if (user?.role !== "admin") return;
    Promise.all([api.adminStats(), api.adminUsers(), api.adminSummaries()])
      .then(([s, u, sum]) => {
        setStats(s);
        setUsers(u);
        setSummaries(sum.data);
      })
      .finally(() => setDataLoading(false));
  }, [user]);

  if (loading || user?.role !== "admin") return null;
  if (dataLoading)
    return <p className="text-black/60 dark:text-white/60">Loading…</p>;

  return (
    <div className="space-y-10">
      <section>
        <h1 className="mb-4 text-2xl font-semibold">Admin</h1>
        {stats && (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Stat label="Summaries" value={stats.total_summaries} />
            <Stat label="Input tokens" value={stats.total_input_tokens.toLocaleString()} />
            <Stat label="Output tokens" value={stats.total_output_tokens.toLocaleString()} />
            <Stat label="Total cost" value={`$${stats.total_cost_usd.toFixed(4)}`} />
          </div>
        )}
        {stats && (
          <div className="mt-3 flex flex-wrap gap-2">
            {Object.entries(stats.by_status).map(([status, count]) => (
              <span
                key={status}
                className="rounded-full border border-black/15 px-3 py-1 text-xs dark:border-white/20"
              >
                {status}: {count}
              </span>
            ))}
          </div>
        )}
      </section>

      <section>
        <h2 className="mb-4 text-lg font-semibold">Users ({users.length})</h2>
        <table className="w-full text-left text-sm">
          <thead className="text-black/50 dark:text-white/50">
            <tr>
              <th className="py-2">Name</th>
              <th className="py-2">Email</th>
              <th className="py-2">Role</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-black/10 dark:divide-white/15">
            {users.map((u) => (
              <tr key={u.id}>
                <td className="py-2">{u.name}</td>
                <td className="py-2">{u.email}</td>
                <td className="py-2">{u.role}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <section>
        <h2 className="mb-4 text-lg font-semibold">All summaries</h2>
        <table className="w-full text-left text-sm">
          <thead className="text-black/50 dark:text-white/50">
            <tr>
              <th className="py-2">Title</th>
              <th className="py-2">Style</th>
              <th className="py-2">Status</th>
              <th className="py-2">Cost</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-black/10 dark:divide-white/15">
            {summaries.map((s) => (
              <tr key={s.id}>
                <td className="max-w-xs truncate py-2">
                  {s.title ?? s.source_url ?? `#${s.id}`}
                </td>
                <td className="py-2">{s.style}</td>
                <td className="py-2">
                  <StatusBadge status={s.status} />
                </td>
                <td className="py-2">
                  {s.cost_usd != null ? `$${s.cost_usd.toFixed(6)}` : "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-md border border-black/10 p-3 dark:border-white/15">
      <div className="text-xs text-black/50 dark:text-white/50">{label}</div>
      <div className="mt-0.5 text-lg font-semibold">{value}</div>
    </div>
  );
}
