"use client";
import { conversations, type Conversation } from "@/lib/data/messaging";
import { usePolling } from "@/lib/hooks/use-polling";
import { useCallback, useRef, useState } from "react";

// This state lives in the shared messages layout, so switching threads keeps the inbox.
export function useInboxData() {
  const [items, setItems] = useState<Conversation[]>([]),
    [listCursor, setListCursor] = useState<string | null>(null),
    [listBusy, setListBusy] = useState(false);
  const [listLoaded, setListLoaded] = useState(false);
  const initialized = useRef(false);
  const poll = useCallback(async (signal: AbortSignal) => {
    const list = await conversations(undefined, signal);
    if (signal.aborted) return;
    setItems((previous) => [...list.data, ...previous.filter((item) => !list.data.some((fresh) => fresh.id === item.id))]);
    if (!initialized.current) setListCursor(list.meta.next_cursor);
    initialized.current = true;
    setListLoaded(true);
  }, []);
  const listError = usePolling(poll);
  return { items, setItems, listCursor, setListCursor, listBusy, setListBusy, listLoaded, listError };
}

