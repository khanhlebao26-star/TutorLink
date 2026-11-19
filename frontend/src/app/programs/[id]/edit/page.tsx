import { ProgramEditor } from "@/components/programs/program-editor";
import { notFound } from "next/navigation";
export default async function Page({params}:{params:Promise<{id:string}>}){const {id}=await params;if(!/^\d+$/.test(id)||Number(id)<1)notFound();return <ProgramEditor id={Number(id)}/>;}
