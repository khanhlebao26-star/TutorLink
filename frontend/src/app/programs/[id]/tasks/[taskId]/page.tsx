import {TaskWorkspace} from "@/components/task-workspace";
import {notFound} from "next/navigation";
export default async function Page({params}:{params:Promise<{id:string;taskId:string}>}){const {id,taskId}=await params;if(!/^\d+$/.test(id)||!/^\d+$/.test(taskId)||Number(id)<1||Number(taskId)<1)notFound();return <TaskWorkspace programId={Number(id)} id={Number(taskId)}/>;}
