"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";

export default function Home() {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    router.replace(user ? "/summaries" : "/login");
  }, [user, loading, router]);

  return <p className="text-black/60 dark:text-white/60">Loading…</p>;
}
