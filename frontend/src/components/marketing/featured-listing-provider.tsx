'use client';
import { getFeaturedListingCandidates } from '@/lib/data/marketplace';
import { createContext, useCallback, useContext, useRef, type ReactNode } from 'react';
const FeaturedListingsContext = createContext(getFeaturedListingCandidates);

/** Keep one public snapshot per homepage mount, including sections mounted later. */
export function FeaturedListingProvider({children}: {children: ReactNode}) {
  const pending = useRef<ReturnType<typeof getFeaturedListingCandidates> | null>(null);
  const load = useCallback(() => {
    if (!pending.current) {
      pending.current = getFeaturedListingCandidates().catch(error => { pending.current = null; throw error; });
    }
    return pending.current;
  }, []);
  return <FeaturedListingsContext.Provider value={load}>{children}</FeaturedListingsContext.Provider>;
}
export function useFeaturedListings() { return useContext(FeaturedListingsContext); }
