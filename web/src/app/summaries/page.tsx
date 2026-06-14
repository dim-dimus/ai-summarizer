"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useToast } from "@/lib/toast";
import { useConfirm } from "@/lib/confirm";
import type { SourceType, Summary, SummaryStyle } from "@/lib/types";
import { StatusBadge } from "@/components/StatusBadge";

const STYLES: { value: SummaryStyle; label: string }[] = [
  { value: "tldr", label: "TL;DR (one paragraph)" },
  { value: "bullets", label: "Bullets (key points)" },
  { value: "short", label: "Short (3–4 sentences)" },
];

export default function SummariesPage() {
  const { user, loading } = useAuth();
  const router = useRouter();
  const { show } = useToast();
  const [summaries, setSummaries] = useState<Summary[]>([]);
  const [listLoading, setListLoading] = useState(true);
  const pollTimer = useRef<ReturnType<typeof setInterval> | null>(null);
  const prevSummaries = useRef<Summary[]>([]);

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [user, loading, router]);

  // Detect status changes and show notifications
  useEffect(() => {
    summaries.forEach((current) => {
      const prev = prevSummaries.current.find((s) => s.id === current.id);
      if (
        prev &&
        prev.status !== "completed" &&
        current.status === "completed"
      ) {
        show(`Summary #${current.id} completed.`, "success");
      }
    });
    prevSummaries.current = summaries;
  }, [summaries, show]);

  const refresh = useCallback(async () => {
    try {
      const res = await api.listSummaries();
      setSummaries(res.data);
    } finally {
      setListLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!user) return;
    refresh();
    pollTimer.current = setInterval(refresh, 3000);
    return () => {
      if (pollTimer.current) clearInterval(pollTimer.current);
    };
  }, [user, refresh]);

  if (loading || !user) return null;

  return (
    <div className="space-y-10">
      <SubmitForm onCreated={refresh} />
      <section>
        <h2 className="mb-4 text-lg font-semibold">Your summaries</h2>
        {listLoading ? (
          <p className="text-sm text-black/60 dark:text-white/60">Loading…</p>
        ) : summaries.length === 0 ? (
          <p className="text-sm text-black/60 dark:text-white/60">
            No summaries yet — submit one above.
          </p>
        ) : (
          <ul className="divide-y divide-black/10 dark:divide-white/15">
            {summaries.map((s) => (
              <SummaryRow key={s.id} summary={s} onDeleted={refresh} />
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

function SubmitForm({ onCreated }: { onCreated: () => void }) {
  const [sourceType, setSourceType] = useState<SourceType>("url");
  const [url, setUrl] = useState("");
  const [text, setText] = useState("");
  const [style, setStyle] = useState<SummaryStyle>("tldr");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await api.createSummary({
        source_type: sourceType,
        style,
        ...(sourceType === "url" ? { url } : { text }),
      });
      setUrl("");
      setText("");
      onCreated();
    } catch (err) {
      setError(
        err instanceof ApiRequestError
          ? err.status === 429
            ? "Rate limit reached. Try again later."
            : err.message
          : "Failed to submit.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section>
      <h1 className="mb-4 text-2xl font-semibold">New summary</h1>
      <form
        onSubmit={handleSubmit}
        className="space-y-4 rounded-lg border border-black/10 p-5 dark:border-white/15"
      >
        <div className="flex gap-2">
          {(["url", "text"] as SourceType[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setSourceType(t)}
              className={`rounded-md px-3 py-1.5 text-sm ${
                sourceType === t
                  ? "bg-black text-white dark:bg-white dark:text-black"
                  : "border border-black/15 dark:border-white/20"
              }`}
            >
              {t === "url" ? "From URL" : "Paste text"}
            </button>
          ))}
        </div>

        {sourceType === "url" ? (
          <input
            type="url"
            value={url}
            required
            placeholder="https://example.com/article"
            onChange={(e) => setUrl(e.target.value)}
            className="w-full rounded-md border border-black/15 bg-transparent px-3 py-2 outline-none focus:border-black dark:border-white/20 dark:focus:border-white"
          />
        ) : (
          <textarea
            value={text}
            required
            rows={6}
            maxLength={50000}
            placeholder="Paste the text to summarize…"
            onChange={(e) => setText(e.target.value)}
            className="w-full rounded-md border border-black/15 bg-transparent px-3 py-2 outline-none focus:border-black dark:border-white/20 dark:focus:border-white"
          />
        )}

        <label className="block">
          <span className="mb-1 block text-sm font-medium">Style</span>
          <select
            value={style}
            onChange={(e) => setStyle(e.target.value as SummaryStyle)}
            className="w-full rounded-md border border-black/15 bg-transparent px-3 py-2 outline-none focus:border-black dark:border-white/20 dark:focus:border-white"
          >
            {STYLES.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
        </label>

        {error && <p className="text-sm text-red-600">{error}</p>}

        <button
          type="submit"
          disabled={submitting}
          className="rounded-md bg-black px-4 py-2 text-white disabled:opacity-50 dark:bg-white dark:text-black"
        >
          {submitting ? "Submitting…" : "Summarize"}
        </button>
      </form>
    </section>
  );
}

function SummaryRow({
  summary,
  onDeleted,
}: {
  summary: Summary;
  onDeleted: () => void;
}) {
  const [deleting, setDeleting] = useState(false);
  const { show } = useConfirm();

  async function handleDelete() {
    const confirmed = await show({
      title: "Delete summary?",
      message: "This action cannot be undone.",
      confirmText: "Delete",
      cancelText: "Cancel",
    });
    if (!confirmed) return;
    setDeleting(true);
    try {
      await api.deleteSummary(summary.id);
      onDeleted();
    } catch {
      setDeleting(false);
    }
  }

  return (
    <li className="flex items-center justify-between gap-4 py-3">
      <Link href={`/summaries/view?id=${summary.id}`} className="min-w-0 flex-1">
        <span className="block truncate font-medium">
          {summary.title ?? summary.source_url ?? `Summary #${summary.id}`}
        </span>
        <span className="text-xs text-black/50 dark:text-white/50">
          {summary.model ?? "—"} · {summary.style} ·{" "}
          {new Date(summary.created_at).toLocaleString()}
        </span>
      </Link>
      <StatusBadge status={summary.status} />
      <button
        onClick={handleDelete}
        disabled={deleting}
        className="text-sm text-red-600 hover:underline disabled:opacity-50"
      >
        Delete
      </button>
    </li>
  );
}
