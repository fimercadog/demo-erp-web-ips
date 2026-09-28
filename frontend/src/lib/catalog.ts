import { api, PaginatedResponse } from "@/lib/api";

/** Vista publica de un producto (sin costo ni existencia). */
export type PublicProduct = {
  id: number;
  sku: string;
  name: string;
  description: string | null;
  image_url: string | null;
  unit_price: number;
  compare_price?: number | null;
  category: string | null;
  brand: string | null;
  unit: string | null;
  vertical?: string | null;
};

export type PublicService = {
  id: number;
  name: string;
  description: string | null;
  type: string;
  estimated_duration_minutes: number | null;
  price: number;
  compare_price?: number | null;
  category: string | null;
  image_url: string | null;
  vertical: string | null;
  display_order: number;
};

export type CatalogCategory = { id: number; name: string; vertical?: string | null };

export type QuoteRequestItem = { product_id: number; quantity: number };

export type QuoteRequestPayload = {
  name: string;
  email: string;
  phone: string | null;
  company_name: string | null;
  message: string | null;
  consent: boolean;
  items: QuoteRequestItem[];
};

export async function fetchCatalog(params: { vertical?: string; category_id?: number; q?: string; page?: number }) {
  const { data } = await api.get<PaginatedResponse<PublicProduct>>("/public/catalog/products", {
    params: { ...params, per_page: 12 },
  });
  return data;
}

export async function fetchPublicServices(params?: { vertical?: string; category_id?: number; q?: string }) {
  const { data } = await api.get<{ data: PublicService[] }>("/public/catalog/services", {
    params,
  });
  return data.data;
}

export async function fetchCatalogProduct(id: string | number) {
  const { data } = await api.get<{ data: PublicProduct }>(`/public/catalog/products/${id}`);
  return data.data;
}

export async function fetchCatalogCategories(params?: { vertical?: string }) {
  const { data } = await api.get<CatalogCategory[]>("/public/catalog/categories", { params });
  return data;
}

export function submitQuoteRequest(payload: QuoteRequestPayload) {
  return api.post("/public/catalog/quote-requests", payload);
}

export const formatCOP = (value: number) => `$${Number(value).toLocaleString("es-CO")}`;
