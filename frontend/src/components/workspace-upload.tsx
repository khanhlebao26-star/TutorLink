"use client";
import {useRef,useState} from "react";
import {uploadFile,completeFile} from "@/lib/data/services";
import {describeApiError} from "@/lib/api/client";
import type {FileRecord} from "@/lib/data/types";
import {Button} from "./ui";
export function WorkspaceUpload({onComplete,onBusy}:{onComplete:(id:number)=>void;onBusy:(busy:boolean)=>void}){const [busy,setBusy]=useState(false),[percent,setPercent]=useState(0),[error,setError]=useState("");const uploaded=useRef<FileRecord|null>(null),selected=useRef<File|null>(null),lock=useRef(false);
 async function run(){if(!selected.current||lock.current)return;lock.current=true;setBusy(true);onBusy(true);setError("");try{uploaded.current??=await uploadFile(selected.current,"task_attachment",setPercent);const completed=await completeFile(uploaded.current.id);onComplete(completed.id);uploaded.current=null;selected.current=null;setPercent(100);}catch(e){setError(describeApiError(e));}finally{lock.current=false;setBusy(false);onBusy(false);}}
 return <div><label>Đính kèm bài nộp (tối đa 5.000.000 byte)<input type="file" disabled={busy} onChange={e=>{const file=e.target.files?.[0];e.target.value="";if(file){selected.current=file;uploaded.current=null;void run();}}}/></label>{busy&&<progress aria-label="Tiến độ tải file" value={percent} max={100}/>}<p role="status">{percent}%</p>{error&&<div role="alert"><p>{error}</p><Button variant="outline" disabled={busy} onClick={run}>Thử tải lại</Button></div>}</div>;
}
