import { ProgramDetail } from "@/components/programs/program-detail";
import { notFound } from "next/navigation";
export default async function Page({params}:{params:Promise<{id:string}>}){const {id}=await params;if(!/^\d+$/.test(id)||Number(id)<1)notFound();return <ProgramDetail id={Number(id)}/>;}
