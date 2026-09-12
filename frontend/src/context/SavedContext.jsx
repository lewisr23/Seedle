import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from './AuthContext';

const SavedContext = createContext(null);

const EMPTY = { product: new Set(), plant: new Set() };

/**
 * Holds which products and plants the signed-in user has saved, fetched once as
 * a pair of id lists. A heart toggle anywhere can then render from memory
 * rather than asking the API per card.
 */
export function SavedProvider({ children }) {
  const { user } = useAuth();
  const [ids, setIds] = useState(EMPTY);

  useEffect(() => {
    if (!user) {
      setIds(EMPTY);
      return;
    }

    let cancelled = false;
    api('/saved/ids')
      .then((res) => {
        if (cancelled) return;
        setIds({
          product: new Set(res.product_ids),
          plant: new Set(res.plant_ids),
        });
      })
      .catch(() => {
        // Failing to load saves shouldn't stop the rest of the page working.
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

  const isSaved = useCallback((type, id) => ids[type]?.has(id) ?? false, [ids]);

  const toggle = useCallback(
    async (type, id) => {
      const currentlySaved = ids[type]?.has(id) ?? false;
      const path = type === 'product' ? `/products/${id}/save` : `/plants/${id}/save`;

      // Flip first so the heart responds immediately, then put it back if the
      // request turns out to fail.
      setIds((prev) => {
        const next = new Set(prev[type]);
        if (currentlySaved) {
          next.delete(id);
        } else {
          next.add(id);
        }
        return { ...prev, [type]: next };
      });

      try {
        await api(path, { method: currentlySaved ? 'DELETE' : 'POST' });
      } catch {
        setIds((prev) => {
          const next = new Set(prev[type]);
          if (currentlySaved) {
            next.add(id);
          } else {
            next.delete(id);
          }
          return { ...prev, [type]: next };
        });
      }
    },
    [ids]
  );

  const value = useMemo(
    () => ({ isSaved, toggle, savedCount: ids.product.size + ids.plant.size }),
    [isSaved, toggle, ids]
  );

  return <SavedContext.Provider value={value}>{children}</SavedContext.Provider>;
}

export function useSaved() {
  const ctx = useContext(SavedContext);
  if (!ctx) throw new Error('useSaved must be used within SavedProvider');
  return ctx;
}
