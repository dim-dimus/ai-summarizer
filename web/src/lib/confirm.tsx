"use client";

import {
  ReactNode,
  createContext,
  useCallback,
  useContext,
  useState,
} from "react";

type ConfirmOptions = {
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
};

type ConfirmContextType = {
  show: (options: ConfirmOptions) => Promise<boolean>;
};

const ConfirmContext = createContext<ConfirmContextType | null>(null);

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<{
    options: ConfirmOptions;
    resolve?: (value: boolean) => void;
  } | null>(null);

  const show = useCallback(
    (options: ConfirmOptions): Promise<boolean> => {
      return new Promise((resolve) => {
        setState({ options, resolve });
      });
    },
    [],
  );

  const handleConfirm = () => {
    state?.resolve?.(true);
    setState(null);
  };

  const handleCancel = () => {
    state?.resolve?.(false);
    setState(null);
  };

  return (
    <ConfirmContext.Provider value={{ show }}>
      {children}
      {state && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="rounded-lg bg-white p-6 shadow-lg dark:bg-black dark:shadow-2xl">
            <h2 className="text-lg font-semibold">{state.options.title}</h2>
            <p className="mt-2 text-sm text-black/60 dark:text-white/60">
              {state.options.message}
            </p>
            <div className="mt-6 flex gap-3 justify-end">
              <button
                onClick={handleCancel}
                className="rounded-md border border-black/15 px-4 py-2 text-sm font-medium hover:bg-black/5 dark:border-white/20 dark:hover:bg-white/5"
              >
                {state.options.cancelText ?? "Cancel"}
              </button>
              <button
                onClick={handleConfirm}
                className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
              >
                {state.options.confirmText ?? "Confirm"}
              </button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  );
}

export function useConfirm() {
  const ctx = useContext(ConfirmContext);
  if (!ctx)
    throw new Error("useConfirm must be used inside ConfirmProvider");
  return ctx;
}
