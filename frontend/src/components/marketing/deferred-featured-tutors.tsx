'use client';
import dynamic from 'next/dynamic';
import { useEffect, useRef, useState } from 'react';
const FeaturedTutors = dynamic(() => import('./featured-tutors').then(module => module.FeaturedTutors));

export function DeferredFeaturedTutors() {
  const container = useRef<HTMLDivElement>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    if (typeof IntersectionObserver === 'undefined') {
      const timer = window.setTimeout(() => setVisible(true), 0);
      return () => window.clearTimeout(timer);
    }
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) { setVisible(true); observer.disconnect(); }
    }, {rootMargin: '500px'});
    if (container.current) observer.observe(container.current);
    return () => observer.disconnect();
  }, []);
  return <div ref={container} style={{minHeight: 420}}>{visible ? <FeaturedTutors/> : <section className="landing-inner" aria-label="Gia sư nổi bật"><h2>Gia sư nổi bật</h2></section>}</div>;
}
