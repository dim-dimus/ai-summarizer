"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { Summary } from "@/lib/types";
import { StatusBadge } from "@/components/StatusBadge";

const POLL_MS = 2000;

export default function SummaryDetailPage() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const { user, loading } = useAuth();
  const router = useRouter();
  const [summary, setSummary] = useState<Summary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [user, loading, router]);

  useEffect(() => {
    if (!user) return;
    let active = true;

    async function poll() {
      try {
        const res = await api.getSummary(id);
        if (!active) return;
        setSummary(res.data);
        // Keep polling only while the worker is still processing.
        if (res.data.status === "pending" || res.data.status === "processing") {
          timer.current = setTimeout(poll, POLL_MS);
        }
      } catch {
        if (active) setError("Could not load this summary.");
      }
    }

    poll();
    return () => {
      active = false;
      if (timer.current) clearTimeout(timer.current);
    };
  }, [id, user]);

  if (loading || !user) return null;
  if (error) return <p className="text-red-600">{error}</p>;
  if (!summary)
    return <p className="text-black/60 dark:text-white/60">Loading…</p>;

  const pending =
    summary.status === "pending" || summary.status === "processing";

  return (
    <div className="space-y-6">
      <Link href="/summaries" className="text-sm underline">
        ← Back to history
      </Link>

      <div className="flex items-start justify-between gap-4">
        <h1 className="text-2xl font-semibold">
          {summary.title ?? summary.source_url ?? `Summary #${summary.id}`}
        </h1>
        <StatusBadge status={summary.status} />
      </div>

      {summary.source_url && (
        <a
          href={summary.source_url}
          target="_blank"
          rel="noreferrer"
          className="block break-all text-sm text-blue-600 underline"
        >
          {summary.source_url}
        </a>
      )}

      {pending && (
        <p className="text-sm text-black/60 dark:text-white/60">
          Generating summary… this page updates automatically.
        </p>
      )}

      {summary.status === "failed" && (
        <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          <strong>Failed:</strong> {summary.error_message ?? "Unknown error."}
        </div>
      )}

      {summary.status === "completed" && summary.result_text && (
        <article className="whitespace-pre-wrap rounded-lg border border-black/10 p-5 leading-relaxed dark:border-white/15">
          {summary.result_text}
        </article>
      )}

      {summary.status === "completed" && (
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <Stat label="Model" value={summary.model ?? "—"} />
          <Stat label="Input tokens" value={summary.input_tokens ?? "—"} />
          <Stat label="Output tokens" value={summary.output_tokens ?? "—"} />
          <Stat
            label="Cost (USD)"
            value={
              summary.cost_usd != null ? `$${summary.cost_usd.toFixed(6)}` : "—"
            }
          />
        </dl>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-md border border-black/10 p-3 dark:border-white/15">
      <dt className="text-xs text-black/50 dark:text-white/50">{label}</dt>
      <dd className="mt-0.5 break-all font-medium">{value}</dd>
    </div>
  );
}
