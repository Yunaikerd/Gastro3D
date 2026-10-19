export interface ContactInfo {
  whatsapp?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  country?: string | null;
}

export interface ThemeConfig {
  primary_color?: string;
  accent_color?: string;
  background_color?: string;
  card_color?: string;
  font_family?: string;
  [key: string]: any;
}

export interface MenuSummary {
  total_categories: number;
  total_products: number;
  total_3d_products: number;
}

export interface MenuProductData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  price: number;
  photo_url: string | null;
  is_featured: boolean;
  is_available: boolean;
  has_3d: boolean;
  ar_enabled: boolean;
  model_glb_url: string | null;
  dietary_tags: string[];
  allergens: string[];
  prep_time_minutes: number | null;
  sort_order: number;
}

export interface MenuCategoryData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  sort_order: number;
  products: MenuProductData[];
}

export interface EstablishmentMenuData {
  id: string;
  name: string;
  slug: string;
  business_type: string;
  tagline: string | null;
  description: string | null;
  logo_url: string | null;
  cover_url: string | null;
  contact: ContactInfo;
  currency: string;
  theme_config: ThemeConfig;
  summary: MenuSummary;
  categories: MenuCategoryData[];
}

export interface MenuApiResponse {
  success: boolean;
  data: EstablishmentMenuData;
  message?: string;
}
