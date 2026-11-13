"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

const SessionHintContext = createContext(false);

export function SessionHintProvider({
  authenticated,
  children,
}: {
  authenticated: boolean;
  children: ReactNode;
}) {
  const [hint, setHint] = useState(authenticated);

  useEffect(() => {
    const update = (event: Event) => {
      setHint((event as CustomEvent<boolean>).detail);
    };
    window.addEventListener("tutorlink-session-hint", update);
    return () => window.removeEventListener("tutorlink-session-hint", update);
  }, []);

  return (
    <SessionHintContext.Provider value={hint}>
      {children}
    </SessionHintContext.Provider>
  );
}

export function useSessionHint() {
  return useContext(SessionHintContext);
}
