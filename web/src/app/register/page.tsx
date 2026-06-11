"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { ApiRequestError } from "@/lib/api";

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setErrors({});
    setSubmitting(true);
    try {
      await register(name, email, password);
      router.push("/summaries");
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setErrors(err.fieldErrors ?? {});
      } else {
        setError("Registration failed.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="mx-auto max-w-sm">
      <h1 className="mb-6 text-2xl font-semibold">Create account</h1>
      <form onSubmit={handleSubmit} className="space-y-4">
        <Field label="Name" value={name} onChange={setName} errors={errors.name} />
        <Field
          label="Email"
          type="email"
          value={email}
          onChange={setEmail}
          errors={errors.email}
        />
        <Field
          label="Password"
          type="password"
          value={password}
          onChange={setPassword}
          errors={errors.password}
        />
        {error && <p className="text-sm text-red-600">{error}</p>}
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-black px-4 py-2 text-white disabled:opacity-50 dark:bg-white dark:text-black"
        >
          {submitting ? "Creating…" : "Create account"}
        </button>
      </form>
      <p className="mt-4 text-sm text-black/60 dark:text-white/60">
        Have an account?{" "}
        <Link href="/login" className="underline">
          Log in
        </Link>
      </p>
    </div>
  );
}

function Field({
  label,
  type = "text",
  value,
  onChange,
  errors,
}: {
  label: string;
  type?: string;
  value: string;
  onChange: (v: string) => void;
  errors?: string[];
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium">{label}</span>
      <input
        type={type}
        value={value}
        required
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-black/15 bg-transparent px-3 py-2 outline-none focus:border-black dark:border-white/20 dark:focus:border-white"
      />
      {errors?.map((m) => (
        <span key={m} className="mt-1 block text-xs text-red-600">
          {m}
        </span>
      ))}
    </label>
  );
}
