import { ProgramEditor } from "@/components/programs/program-editor";
export default async function Page({searchParams}:{searchParams:Promise<{customer_id?:string;source_listing_id?:string}>}){const p=await searchParams;return <ProgramEditor customerId={p.customer_id?Number(p.customer_id):undefined} sourceListingId={p.source_listing_id?Number(p.source_listing_id):undefined}/>;}
