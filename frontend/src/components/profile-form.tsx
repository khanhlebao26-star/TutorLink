"use client";

import { useState } from "react";
import type { ProfileResponse } from "@/lib/data/types";
import { Button, Field } from "./ui";

export function ProfileForm({ data }: { data: ProfileResponse }) {
  const profile = data.profile;
  const [bio, setBio] = useState(profile.bio ?? "");
  const [saved, setSaved] = useState(false);
  return <form className="card form-stack" onSubmit={(event) => { event.preventDefault(); setSaved(true); }}><Field label="Họ và tên" htmlFor="profile-name"><input id="profile-name" value={data.user.full_name} readOnly /></Field><Field label="Email" htmlFor="profile-email"><input id="profile-email" value={data.user.email} readOnly /></Field><Field label="Giới thiệu" htmlFor="profile-bio"><textarea id="profile-bio" rows={5} value={bio} onChange={(event) => { setSaved(false); setBio(event.target.value); }} /></Field>{"headline" in profile ? <p className="muted">Chuyên môn: {profile.headline} · {profile.experience_years} năm kinh nghiệm</p> : null}{saved ? <p className="inline-success" role="status">Đã lưu trạng thái form mẫu.</p> : null}<Button type="submit">Lưu hồ sơ</Button></form>;
}
