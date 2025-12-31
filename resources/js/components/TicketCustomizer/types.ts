/**
 * Type definitions for Ticket Customizer Component
 *
 * These types match the JSON schema expected by the backend API
 */

export type LayerType = 'text' | 'image' | 'qr' | 'barcode' | 'shape';
export type BarcodeFormat = 'code128' | 'ean13' | 'pdf417';
export type ShapeKind = 'rect' | 'circle' | 'line';
export type TextAlign = 'left' | 'center' | 'right';
export type TemplateStatus = 'draft' | 'active' | 'archived';

export interface TemplateMeta {
  dpi: number;
  size_mm: {
    w: number;
    h: number;
  };
  orientation: 'portrait' | 'landscape';
  bleed_mm?: number;
  safe_area_mm?: number;
}

export interface LayerFrame {
  x: number;  // mm
  y: number;  // mm
  w: number;  // mm
  h: number;  // mm
}

export interface BaseLayer {
  id: string;
  name: string;
  type: LayerType;
  frame: LayerFrame;
  z: number;
  opacity?: number;
  rotation?: number;
  locked?: boolean;
  visible?: boolean;
}

export interface TextLayerProps {
  content: string;
  size_pt: number;
  color: string;
  align?: TextAlign;
  weight?: 'normal' | 'bold' | 'light';
  font_family?: string;
}

export interface TextLayer extends BaseLayer {
  type: 'text';
  props: TextLayerProps;
}

export interface ImageLayerProps {
  asset_id: string;
  fit?: 'cover' | 'contain' | 'fill';
}

export interface ImageLayer extends BaseLayer {
  type: 'image';
  props: ImageLayerProps;
}

export interface QRLayerProps {
  data: string;
  error_correction?: 'L' | 'M' | 'Q' | 'H';
}

export interface QRLayer extends BaseLayer {
  type: 'qr';
  props: QRLayerProps;
}

export interface BarcodeLayerProps {
  data: string;
  format: BarcodeFormat;
}

export interface BarcodeLayer extends BaseLayer {
  type: 'barcode';
  props: BarcodeLayerProps;
}

export interface ShapeLayerProps {
  kind: ShapeKind;
  fill?: string;
  stroke?: string;
  stroke_width?: number;
}

export interface ShapeLayer extends BaseLayer {
  type: 'shape';
  props: ShapeLayerProps;
}

export type Layer = TextLayer | ImageLayer | QRLayer | BarcodeLayer | ShapeLayer;

export interface Asset {
  id: string;
  filename: string;
  mime_type: string;
  size_bytes: number;
  url: string;
}

export interface TemplateData {
  meta: TemplateMeta;
  assets?: Asset[];
  layers: Layer[];
}

export interface TicketTemplate {
  id: string;
  tenant_id: string;
  name: string;
  description?: string;
  status: TemplateStatus;
  template_data: TemplateData;
  preview_image?: string;
  version: number;
  parent_id?: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface Variable {
  key: string;
  label: string;
  placeholder: string;
  description?: string;
}

export interface VariableCategory {
  category: string;
  variables: Variable[];
}

export interface ValidationResult {
  ok: boolean;
  errors: string[];
  warnings: string[];
}

export interface PreviewResult {
  success: boolean;
  preview?: {
    path: string;
    url: string;
    width: number;
    height: number;
    format: string;
  };
  error?: string;
  message?: string;
}

export interface TemplatePreset {
  id: string;
  name: string;
  size_mm: {
    w: number;
    h: number;
  };
  orientation: 'portrait' | 'landscape';
  dpi: number;
}
