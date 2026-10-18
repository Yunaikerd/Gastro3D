export type GenerationMode = "standard" | "multiview";
export type TextureQuality = "standard" | "detailed";
export type GenerationStatus =
  | "pending"
  | "uploading"
  | "queued"
  | "running"
  | "success"
  | "failed"
  | "cancelled";

export type ViewAngle = "front" | "left" | "right" | "back";

export interface DishViewData {
  angle: ViewAngle;
  image_url: string;
  file_token?: string;
  image_path?: string;
  original_filename?: string;
}

export interface DishGenerationData {
  id: string;
  dish_model_id: string;
  version_number: number;
  mode: GenerationMode;
  model_version: string;
  texture_quality: TextureQuality;
  texture_alignment?: string;
  pbr: boolean;
  views: DishViewData[];
  tripo_task_id: string | null;
  status: GenerationStatus;
  progress: number;
  glb_path: string | null;
  glb_url: string | null;
  thumbnail_url: string | null;
  file_size_bytes: number | null;
  credits_consumed: number | null;
  error_message: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface DishModelData {
  id: string;
  title: string | null;
  prompt: string | null;
  original_filename: string | null;
  image_path: string;
  image_url: string;
  mime_type: string | null;
  file_size_bytes: number | null;
  tripo_task_id: string | null;
  model_version: string;
  status: GenerationStatus;
  progress: number;
  glb_path: string | null;
  glb_url: string | null;
  thumbnail_url: string | null;
  error_message: string | null;
  created_at: string;
  updated_at?: string;
  active_generation?: DishGenerationData | null;
  generations?: DishGenerationData[];
}

export interface CostEstimateResponse {
  success: boolean;
  mode: GenerationMode;
  model_version: string;
  texture_quality: TextureQuality;
  required_credits: number;
  available_credits: number;
  frozen_credits: number;
  can_afford: boolean;
}

export interface TripoModelInfo {
  id: string;
  name: string;
  description: string;
  credits: number;
  recommended: boolean;
}

export interface HealthData {
  status: string;
  service: string;
  timestamp: string;
  database: {
    status: string;
    driver: string;
    error: string | null;
  };
}
