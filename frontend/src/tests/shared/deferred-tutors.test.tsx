import { DeferredFeaturedTutors } from '@/components/marketing/deferred-featured-tutors';
import { act,render,screen } from '@testing-library/react';
import { afterEach,expect,it,vi } from 'vitest';
vi.mock('next/dynamic',()=>({default:()=>function Deferred(){return <p>Loaded tutor cards</p>;}}));
afterEach(()=>vi.unstubAllGlobals());
it('defers the noncritical tutor section until it approaches the viewport',async()=>{
 let callback!: IntersectionObserverCallback;
 const disconnect=vi.fn();const observe=vi.fn();
 vi.stubGlobal('IntersectionObserver',class {constructor(cb:IntersectionObserverCallback){callback=cb;}observe=observe;disconnect=disconnect;});
 const view=render(<DeferredFeaturedTutors/>);expect(screen.queryByText('Loaded tutor cards')).not.toBeInTheDocument();expect(observe).toHaveBeenCalled();await act(async()=>callback([{isIntersecting:true}] as IntersectionObserverEntry[],{} as IntersectionObserver));expect(screen.getByText('Loaded tutor cards')).toBeVisible();expect(disconnect).toHaveBeenCalled();view.unmount();
});
it('loads the section when the browser does not support intersection observers',async()=>{
 vi.stubGlobal('IntersectionObserver',undefined);
 render(<DeferredFeaturedTutors/>);
 expect(await screen.findByText('Loaded tutor cards')).toBeVisible();
});
