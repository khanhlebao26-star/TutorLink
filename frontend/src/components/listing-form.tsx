"use client";

import { ApiClientError, describeApiError } from "@/lib/api/client";
import { createOwnerListing, deliveryLabels, getMarketplaceCatalog, getOwnerListing, getOwnerListings, priceUnitLabels, updateOwnerListing } from "@/lib/data/marketplace";
import type { CatalogCategory, DeliveryMode, ListingInput, OwnerListing, PriceUnit } from "@/lib/data/marketplace-types";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { OwnerStatus } from "./owner-listings";
import { Button, ErrorActions, Field, Input, Select, StatePanel } from "./ui";

const emptyInput: ListingInput = { title: "", description: "", specialization_id: 0, delivery_mode: "online", city: null, district: null, price_from_minor: 0, currency: "VND", price_unit: "session" };

export function ListingForm({ id }: { id?: number }) {
  const router = useRouter();
  const [catalog, setCatalog] = useState<CatalogCategory[]>([]);
  const [listing, setListing] = useState<OwnerListing | null>(null);
  const [input, setInput] = useState<ListingInput>(emptyInput);
  const [fee, setFee] = useState("");
  const [categoryId, setCategoryId] = useState(0);
  const [subcategoryId, setSubcategoryId] = useState(0);
  const [allowed, setAllowed] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [retry, setRetry] = useState(0);
  const [initialized, setInitialized] = useState(false);
  const didInitialize = useRef(false);

  useEffect(() => {
    let current = true;
    Promise.all([getMarketplaceCatalog(), id ? getOwnerListing(id) : getOwnerListings()]).then(([categories, resource]) => {
      if (!current) return;
      setCatalog(categories);
      if ("id" in resource) {
        setListing(resource); setAllowed(resource.status !== "cancelled");
        if (!didInitialize.current) {
          setInput({ title: resource.title, description: resource.description, specialization_id: resource.specialization_id, delivery_mode: resource.delivery_mode, city: resource.city ?? null, district: resource.district ?? null, price_from_minor: resource.price_from_minor, currency: "VND", price_unit: resource.price_unit as PriceUnit });
          setFee(String(resource.price_from_minor));
          const category = categories.find((item) => item.subcategories.some((sub) => sub.specializations.some((specialization) => specialization.id === resource.specialization_id)));
          setCategoryId(category?.id ?? 0);
          setSubcategoryId(category?.subcategories.find((sub) => sub.specializations.some((specialization) => specialization.id === resource.specialization_id))?.id ?? 0);
        }
      } else setAllowed(true);
      didInitialize.current = true; setInitialized(true); setError(null);
    }).catch((reason) => { if (current) { setError(reason); setAllowed(false); } }).finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [id, retry]);

  const category = catalog.find((item) => item.id === categoryId);
  const subcategory = category?.subcategories.find((item) => item.id === subcategoryId);
  const needsCity = input.delivery_mode !== "online";
  const fieldError = (name: string) => fieldErrors[name]?.join(" ");
  function change<K extends keyof ListingInput>(name: K, value: ListingInput[K]) { setInput((current) => ({ ...current, [name]: value })); }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || !allowed) return;
    const errors: Record<string, string[]> = {};
    if (!input.title.trim()) errors.title = ["Nhập tiêu đề dịch vụ."];
    if (!input.description.trim()) errors.description = ["Nhập mô tả dịch vụ."];
    if (!subcategory?.specializations.some((item) => item.id === input.specialization_id)) errors.specialization_id = ["Chọn chuyên ngành thuộc danh mục đã chọn."];
    if (needsCity && !input.city?.trim()) errors.city = ["Nhập thành phố cho dịch vụ trực tiếp hoặc kết hợp."];
    if (!/^\d+$/.test(fee) || !Number.isSafeInteger(Number(fee))) errors.price_from_minor = ["Nhập số nguyên VND không âm."];
    if (!["hour", "session", "course"].includes(input.price_unit)) errors.price_unit = ["Chọn đơn vị phí."];
    setFieldErrors(errors);
    if (Object.keys(errors).length) return;
    setBusy(true); setError(null);
    const payload: ListingInput = { ...input, title: input.title.trim(), description: input.description.trim(), price_from_minor: Number(fee), city: needsCity ? input.city!.trim() : null, district: needsCity ? input.district?.trim() || null : null };
    try {
      const saved = id ? await updateOwnerListing(id, payload) : await createOwnerListing(payload);
      router.push(`/me/listings/${saved.id}`);
    } catch (reason) {
      setError(reason);
      if (reason instanceof ApiClientError) {
        setFieldErrors(reason.fieldErrors ?? {});
        if ([403, 404, 409].includes(reason.status ?? 0)) {
          setAllowed(false);
          try {
            if (id) { const fresh = await getOwnerListing(id); setListing(fresh); setAllowed(fresh.status !== "cancelled"); }
            else { await getOwnerListings(); setAllowed(true); }
          } catch (refreshError) { setError(refreshError); }
        }
      }
    } finally { setBusy(false); }
  }

  if (loading && !initialized) return <StatePanel kind="loading" message="Đang tải thông tin và quyền thao tác…" />;
  if (!initialized) return <StatePanel kind="error" message={error instanceof ApiClientError && error.status === 404 ? "Không tìm thấy dịch vụ này." : describeApiError(error)} action={<ErrorActions login={error instanceof ApiClientError && [401, 419].includes(error.status ?? 0)} onRetry={() => { setLoading(true); setRetry((value) => value + 1); }} />} />;
  return <section className="owner-form-page"><Link href="/me/listings">← Dịch vụ của tôi</Link><form className="card owner-form" onSubmit={submit}>
    <header className="owner-heading"><div><h1>{id ? "Chỉnh sửa dịch vụ" : "Tạo dịch vụ mới"}</h1><p className="muted">{id ? "Cập nhật nội dung dịch vụ của bạn." : "Dịch vụ mới được lưu dưới dạng bản nháp. Công khai dịch vụ sau khi lưu khi tài khoản được cấp quyền."}</p></div>{listing ? <OwnerStatus status={listing.status} /> : null}</header>
    {!allowed ? <p className="owner-notice" role="alert">Bạn chưa được cấp quyền {id ? "chỉnh sửa dịch vụ này" : "tạo dịch vụ"}. Nội dung đã nhập vẫn được giữ lại.</p> : null}
    {!catalog.length ? <StatePanel kind="empty" message="Chưa có danh mục đang hoạt động. Hãy thử lại sau." /> : null}
    {error ? <div role="alert" className="owner-notice"><p>{describeApiError(error)}</p>{error instanceof ApiClientError && [401, 419].includes(error.status ?? 0) ? <div className="button-row"><a className="button button-secondary" href="/auth/login" target="_blank" rel="noopener noreferrer">Đăng nhập trong tab mới</a><Button variant="secondary" disabled={busy || loading} onClick={() => { setLoading(true); setRetry((value) => value + 1); }}>Kiểm tra lại phiên</Button></div> : null}</div> : null}
    <fieldset disabled={busy || loading} className="owner-form-fields">
      <Field label="Tiêu đề dịch vụ *" htmlFor="listing-title" error={fieldError("title")}><Input required maxLength={120} value={input.title} placeholder="Ví dụ: Luyện giao tiếp tiếng Anh" onChange={(event) => change("title", event.target.value)} /></Field>
      <Field label="Mô tả dịch vụ *" htmlFor="listing-description" error={fieldError("description")}><textarea required maxLength={1000} rows={4} value={input.description} placeholder="Nội dung, phương pháp giảng dạy và đối tượng phù hợp…" onChange={(event) => change("description", event.target.value)} /></Field>
      <div className="owner-form-grid">
        <Field label="Danh mục *" htmlFor="listing-category" error={fieldError("category_id")}><Select required value={categoryId || ""} onChange={(event) => { setCategoryId(Number(event.target.value)); setSubcategoryId(0); change("specialization_id", 0); }}><option value="">Chọn danh mục</option>{catalog.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
        <Field label="Nhóm chuyên môn *" htmlFor="listing-subcategory" error={fieldError("subcategory_id")}><Select required disabled={!category} value={subcategoryId || ""} onChange={(event) => { setSubcategoryId(Number(event.target.value)); change("specialization_id", 0); }}><option value="">Chọn nhóm chuyên môn</option>{category?.subcategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
        <Field label="Chuyên ngành *" htmlFor="listing-specialization" error={fieldError("specialization_id")}><Select required disabled={!subcategory} value={input.specialization_id || ""} onChange={(event) => change("specialization_id", Number(event.target.value))}><option value="">Chọn chuyên ngành</option>{subcategory?.specializations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field>
      </div>
      <fieldset className="owner-delivery"><legend>Hình thức giảng dạy *</legend><div>{(["online", "offline", "hybrid"] as DeliveryMode[]).map((mode) => <label key={mode}><input type="radio" name="delivery_mode" value={mode} checked={input.delivery_mode === mode} onChange={() => change("delivery_mode", mode)} />{deliveryLabels[mode]}</label>)}</div>{fieldError("delivery_mode") ? <span className="field-error" role="alert">{fieldError("delivery_mode")}</span> : null}</fieldset>
      <div className="owner-form-grid owner-form-grid-two"><Field label={`Thành phố${needsCity ? " *" : ""}`} htmlFor="listing-city" error={fieldError("city")} hint="Bắt buộc khi dạy trực tiếp hoặc kết hợp."><Input required={needsCity} disabled={!needsCity} value={input.city ?? ""} onChange={(event) => change("city", event.target.value)} /></Field><Field label="Quận / huyện" htmlFor="listing-district" error={fieldError("district")}><Input disabled={!needsCity} value={input.district ?? ""} onChange={(event) => change("district", event.target.value)} /></Field></div>
      <div className="owner-form-grid owner-form-grid-two"><Field label="Giá từ (VND) *" htmlFor="listing-fee" error={fieldError("price_from_minor")}><Input required type="number" min={0} max={Number.MAX_SAFE_INTEGER} step={1} inputMode="numeric" value={fee} onChange={(event) => setFee(event.target.value)} /></Field><Field label="Đơn vị phí *" htmlFor="listing-unit" error={fieldError("price_unit")}><Select required value={input.price_unit} onChange={(event) => change("price_unit", event.target.value as PriceUnit)}>{["hour", "session", "course"].map((unit) => <option key={unit} value={unit}>mỗi {priceUnitLabels[unit]}</option>)}</Select></Field></div>
    </fieldset>
    <footer className="owner-form-footer">{!busy ? <Link className="button button-outline" href="/me/listings">Hủy</Link> : null}<Button type="submit" disabled={busy || loading || !allowed}>{busy ? "Đang lưu…" : id ? "Lưu thay đổi" : "Lưu bản nháp"}</Button></footer>
  </form></section>;
}
