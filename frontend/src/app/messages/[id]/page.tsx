import {notFound} from "next/navigation";
export default async function Page({params}:{params:Promise<{id:string}>}){const {id}=await params;if(!/^\d+$/.test(id)||!Number.isSafeInteger(Number(id))||Number(id)<1)notFound();return null;}
