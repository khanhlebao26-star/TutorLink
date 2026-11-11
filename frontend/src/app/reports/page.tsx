import {ReportsPage} from "@/components/reports-page";
export default async function Page({searchParams}:{searchParams:Promise<{program_id?:string}>}){const {program_id}=await searchParams;return <ReportsPage programId={program_id&&/^\d+$/.test(program_id)?Number(program_id):undefined}/>;}
