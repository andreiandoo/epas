/**
 * Ticket Customizer Component
 *
 * WYSIWYG editor for designing ticket templates with drag-and-drop,
 * real-time preview, and variable placeholders.
 *
 * This is a scaffold showing the structure. Full implementation would
 * require additional libraries:
 * - React DnD or react-draggable for drag-and-drop
 * - Fabric.js or Konva for canvas manipulation
 * - react-color for color picker
 * - Additional UI components
 */

import React, { useState, useEffect, useCallback } from 'react';
import type {
  TemplateData,
  Layer,
  TemplateMeta,
  VariableCategory,
  TemplatePreset,
} from './types';
import { ticketCustomizerAPI } from './api';

interface TicketCustomizerProps {
  tenantId: string;
  templateId?: string;
  onSave?: (templateData: TemplateData) => void;
  onCancel?: () => void;
}

export const TicketCustomizer: React.FC<TicketCustomizerProps> = ({
  tenantId,
  templateId,
  onSave,
  onCancel,
}) => {
  // State
  const [templateData, setTemplateData] = useState<TemplateData | null>(null);
  const [selectedLayerId, setSelectedLayerId] = useState<string | null>(null);
  const [variables, setVariables] = useState<VariableCategory[]>([]);
  const [presets, setPresets] = useState<TemplatePreset[]>([]);
  const [zoom, setZoom] = useState<number>(100);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<string[]>([]);
  const [validationWarnings, setValidationWarnings] = useState<string[]>([]);

  // Load template or create new
  useEffect(() => {
    const loadData = async () => {
      try {
        // Load variables
        const varsData = await ticketCustomizerAPI.getVariables(tenantId);
        setVariables(varsData.variables);

        // Load presets
        const presetsData = await ticketCustomizerAPI.getPresets();
        setPresets(presetsData.presets);

        // Load existing template or create new
        if (templateId) {
          const { template } = await ticketCustomizerAPI.get(templateId);
          setTemplateData(template.template_data);
        } else {
          // Initialize with default preset
          initializeTemplate(presetsData.presets[0]);
        }
      } catch (error) {
        console.error('Failed to load template data:', error);
      }
    };

    loadData();
  }, [tenantId, templateId]);

  const initializeTemplate = (preset: TemplatePreset) => {
    const meta: TemplateMeta = {
      dpi: preset.dpi,
      size_mm: preset.size_mm,
      orientation: preset.orientation,
      bleed_mm: 3,
      safe_area_mm: 5,
    };

    setTemplateData({
      meta,
      assets: [],
      layers: [],
    });
  };

  // Validate template
  const validateTemplate = useCallback(async () => {
    if (!templateData) return;

    try {
      const result = await ticketCustomizerAPI.validate(templateData);
      setValidationErrors(result.errors);
      setValidationWarnings(result.warnings);
      return result.ok;
    } catch (error) {
      console.error('Validation failed:', error);
      return false;
    }
  }, [templateData]);

  // Generate preview
  const generatePreview = useCallback(async () => {
    if (!templateData) return;

    try {
      const result = await ticketCustomizerAPI.preview(templateData, undefined, 2);
      if (result.success && result.preview) {
        setPreviewUrl(result.preview.url);
      }
    } catch (error) {
      console.error('Preview generation failed:', error);
    }
  }, [templateData]);

  // Layer operations
  const addLayer = useCallback((layer: Layer) => {
    if (!templateData) return;

    setTemplateData({
      ...templateData,
      layers: [...templateData.layers, layer],
    });
  }, [templateData]);

  const updateLayer = useCallback((layerId: string, updates: Partial<Layer>) => {
    if (!templateData) return;

    setTemplateData({
      ...templateData,
      layers: templateData.layers.map(layer =>
        layer.id === layerId ? { ...layer, ...updates } : layer
      ),
    });
  }, [templateData]);

  const deleteLayer = useCallback((layerId: string) => {
    if (!templateData) return;

    setTemplateData({
      ...templateData,
      layers: templateData.layers.filter(layer => layer.id !== layerId),
    });

    if (selectedLayerId === layerId) {
      setSelectedLayerId(null);
    }
  }, [templateData, selectedLayerId]);

  const moveLayer = useCallback((layerId: string, direction: 'up' | 'down') => {
    if (!templateData) return;

    const currentLayer = templateData.layers.find(l => l.id === layerId);
    if (!currentLayer) return;

    const newZ = direction === 'up' ? currentLayer.z + 1 : currentLayer.z - 1;

    setTemplateData({
      ...templateData,
      layers: templateData.layers.map(layer =>
        layer.id === layerId ? { ...layer, z: newZ } : layer
      ),
    });
  }, [templateData]);

  const handleSave = async () => {
    if (!templateData) return;

    const isValid = await validateTemplate();
    if (!isValid) {
      alert('Template has validation errors. Please fix them before saving.');
      return;
    }

    if (onSave) {
      onSave(templateData);
    }
  };

  if (!templateData) {
    return <div>Loading...</div>;
  }

  const selectedLayer = templateData.layers.find(l => l.id === selectedLayerId);

  return (
    <div className="ticket-customizer flex h-screen">
      {/* Left Sidebar - Tools & Layers */}
      <aside className="w-64 bg-gray-100 border-r border-gray-300 p-4 overflow-y-auto">
        <h2 className="text-lg font-bold mb-4">Tools</h2>

        {/* Add Layer Buttons */}
        <div className="space-y-2 mb-6">
          <button className="w-full px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Add Text
          </button>
          <button className="w-full px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Add Image
          </button>
          <button className="w-full px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Add QR Code
          </button>
          <button className="w-full px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Add Barcode
          </button>
          <button className="w-full px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Add Shape
          </button>
        </div>

        {/* Layers List */}
        <h3 className="text-md font-semibold mb-2">Layers</h3>
        <div className="space-y-1">
          {templateData.layers
            .sort((a, b) => b.z - a.z)
            .map(layer => (
              <div
                key={layer.id}
                className={`p-2 rounded cursor-pointer ${
                  selectedLayerId === layer.id ? 'bg-blue-200' : 'bg-white'
                }`}
                onClick={() => setSelectedLayerId(layer.id)}
              >
                <div className="flex items-center justify-between">
                  <span className="text-sm">{layer.name}</span>
                  <span className="text-xs text-gray-500">{layer.type}</span>
                </div>
              </div>
            ))}
        </div>
      </aside>

      {/* Center - Canvas */}
      <main className="flex-1 bg-gray-200 p-8 overflow-auto">
        <div className="mb-4 flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <span className="text-sm">Zoom:</span>
            <input
              type="range"
              min="25"
              max="800"
              step="25"
              value={zoom}
              onChange={e => setZoom(Number(e.target.value))}
              className="w-32"
            />
            <span className="text-sm">{zoom}%</span>
          </div>

          <div className="flex space-x-2">
            <button
              onClick={generatePreview}
              className="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
            >
              Generate Preview
            </button>
            <button
              onClick={validateTemplate}
              className="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600"
            >
              Validate
            </button>
          </div>
        </div>

        {/* Canvas Area */}
        <div className="bg-white shadow-lg mx-auto" style={{ width: 'fit-content' }}>
          <div
            className="relative border border-gray-400"
            style={{
              width: `${(templateData.meta.size_mm.w * zoom) / 100}px`,
              height: `${(templateData.meta.size_mm.h * zoom) / 100}px`,
            }}
          >
            {/* Render layers here (placeholder) */}
            {templateData.layers.map(layer => (
              <div
                key={layer.id}
                className="absolute border border-dashed border-blue-400"
                style={{
                  left: `${(layer.frame.x * zoom) / 100}px`,
                  top: `${(layer.frame.y * zoom) / 100}px`,
                  width: `${(layer.frame.w * zoom) / 100}px`,
                  height: `${(layer.frame.h * zoom) / 100}px`,
                  opacity: layer.opacity ?? 1,
                  transform: `rotate(${layer.rotation ?? 0}deg)`,
                }}
              >
                <span className="text-xs">{layer.name}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Validation Messages */}
        {validationErrors.length > 0 && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 rounded">
            <h4 className="font-bold text-red-800">Errors:</h4>
            <ul className="list-disc list-inside">
              {validationErrors.map((error, i) => (
                <li key={i} className="text-red-700">{error}</li>
              ))}
            </ul>
          </div>
        )}

        {validationWarnings.length > 0 && (
          <div className="mt-4 p-4 bg-yellow-100 border border-yellow-400 rounded">
            <h4 className="font-bold text-yellow-800">Warnings:</h4>
            <ul className="list-disc list-inside">
              {validationWarnings.map((warning, i) => (
                <li key={i} className="text-yellow-700">{warning}</li>
              ))}
            </ul>
          </div>
        )}
      </main>

      {/* Right Sidebar - Properties */}
      <aside className="w-80 bg-gray-100 border-l border-gray-300 p-4 overflow-y-auto">
        <h2 className="text-lg font-bold mb-4">Properties</h2>

        {selectedLayer ? (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Name</label>
              <input
                type="text"
                value={selectedLayer.name}
                onChange={e => updateLayer(selectedLayer.id, { name: e.target.value })}
                className="w-full px-3 py-2 border rounded"
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">Position (mm)</label>
              <div className="grid grid-cols-2 gap-2">
                <input
                  type="number"
                  placeholder="X"
                  value={selectedLayer.frame.x}
                  onChange={e =>
                    updateLayer(selectedLayer.id, {
                      frame: { ...selectedLayer.frame, x: Number(e.target.value) },
                    })
                  }
                  className="px-3 py-2 border rounded"
                />
                <input
                  type="number"
                  placeholder="Y"
                  value={selectedLayer.frame.y}
                  onChange={e =>
                    updateLayer(selectedLayer.id, {
                      frame: { ...selectedLayer.frame, y: Number(e.target.value) },
                    })
                  }
                  className="px-3 py-2 border rounded"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">Size (mm)</label>
              <div className="grid grid-cols-2 gap-2">
                <input
                  type="number"
                  placeholder="Width"
                  value={selectedLayer.frame.w}
                  onChange={e =>
                    updateLayer(selectedLayer.id, {
                      frame: { ...selectedLayer.frame, w: Number(e.target.value) },
                    })
                  }
                  className="px-3 py-2 border rounded"
                />
                <input
                  type="number"
                  placeholder="Height"
                  value={selectedLayer.frame.h}
                  onChange={e =>
                    updateLayer(selectedLayer.id, {
                      frame: { ...selectedLayer.frame, h: Number(e.target.value) },
                    })
                  }
                  className="px-3 py-2 border rounded"
                />
              </div>
            </div>

            {/* Type-specific properties would go here */}

            <div className="pt-4 border-t">
              <button
                onClick={() => deleteLayer(selectedLayer.id)}
                className="w-full px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600"
              >
                Delete Layer
              </button>
            </div>
          </div>
        ) : (
          <p className="text-gray-500">Select a layer to edit properties</p>
        )}

        {/* Variables Panel */}
        <div className="mt-8">
          <h3 className="text-md font-semibold mb-2">Available Variables</h3>
          <div className="space-y-4">
            {variables.map(category => (
              <div key={category.category}>
                <h4 className="text-sm font-medium text-gray-700 mb-1">
                  {category.category}
                </h4>
                <div className="space-y-1">
                  {category.variables.map(variable => (
                    <div
                      key={variable.key}
                      className="text-xs p-2 bg-white rounded cursor-pointer hover:bg-blue-50"
                      title={variable.description}
                    >
                      <code className="text-blue-600">{variable.placeholder}</code>
                      <span className="ml-2 text-gray-600">{variable.label}</span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </aside>

      {/* Bottom Toolbar */}
      <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-300 p-4 flex justify-between items-center">
        <div className="text-sm text-gray-600">
          Template: {templateData.meta.size_mm.w} × {templateData.meta.size_mm.h} mm @ {templateData.meta.dpi} DPI
        </div>

        <div className="flex space-x-2">
          {onCancel && (
            <button
              onClick={onCancel}
              className="px-6 py-2 border border-gray-300 rounded hover:bg-gray-100"
            >
              Cancel
            </button>
          )}
          <button
            onClick={handleSave}
            className="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Save Template
          </button>
        </div>
      </div>
    </div>
  );
};

export default TicketCustomizer;
