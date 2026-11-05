"use client";
import Link from "next/link";
import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { openConversation } from "@/lib/data/messaging";
import { Button } from "./ui";
export function OpenChatButton({
  peerId,
  listingId,
}: {
  peerId: number;
  listingId?: number;
}) {
  const router = useRouter();
  const lock = useRef(false);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState<unknown>(null);
  async function open() {
    if (lock.current) return;
    lock.current = true;
    setBusy(true);
    setError(null);
    try {
      const c = await openConversation(peerId, listingId);
      router.push(`/messages/${c.id}`);
    } catch (e) {
      setError(e);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }
  return (
    <>
      <Button disabled={busy} onClick={open}>
        {busy ? "Đang mở…" : "Nhắn tin với gia sư"}
      </Button>
      {!!error && (
        <p role="alert">
          {describeApiError(error)}{" "}
          {error instanceof ApiClientError && error.status === 401 && (
            <Link href="/auth/login">Đăng nhập</Link>
          )}
        </p>
      )}
    </>
  );
}
