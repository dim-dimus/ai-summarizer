"use client";

import { useToast } from "@/lib/toast";

export function ToastContainer() {
  const { toasts, remove } = useToast();

  return (
    <div className="fixed right-4 top-4 z-50 flex flex-col gap-2">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={`rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg animate-in fade-in-0 slide-in-from-right-4 duration-200 ${
            toast.variant === "error"
              ? "bg-red-600"
              : "bg-green-600"
          }`}
        >
          <div className="flex items-center justify-between gap-3">
            <span>{toast.message}</span>
            <button
              onClick={() => remove(toast.id)}
              className="hover:opacity-70"
            >
              ✕
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}
