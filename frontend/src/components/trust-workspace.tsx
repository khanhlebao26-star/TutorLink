"use client";
import {useCallback} from "react";
import Link from "next/link";
import {AppShell} from "./app-shell";
import {ApiState} from "./api-state";
import {Card,Button} from "./ui";
import {WorkspaceForm} from "./workspace-form";
import {useAsyncData} from "@/lib/hooks/use-async-data";
import {workspaceRead} from "@/lib/data/workspace";
interface Review {id:number;rating:number;comment:string|null;visibility:string;version:number}
export function ProgramTrust({id}:{id:number}){const load=useCallback(()=>workspaceRead<{data:{review:Review|null;can_review:boolean}}>(`/programs/${id}/review`),[id]);const result=useAsyncData(load);return <Card><h2>Đánh giá chương trình</h2><ApiState {...result}/>{result.data?.data.review&&<><p>{result.data.data.review.rating}/5 · {result.data.data.review.visibility}</p><p>{result.data.data.review.comment}</p></>}{result.data?.data.can_review&&<WorkspaceForm url={`/programs/${id}/review`} fields={[{name:"rating",label:"Điểm đánh giá (1–5)",type:"number",min:1,max:5,required:true},{name:"comment",label:"Nhận xét",type:"textarea",maxLength:2000}]} label="Gửi đánh giá" onSaved={result.reload}/>}<Link className="button button-outline" href={`/reports?program_id=${id}`}>Báo cáo vấn đề</Link></Card>;}
