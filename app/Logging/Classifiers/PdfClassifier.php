<?php

namespace App\Logging\Classifiers;

class PdfClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->fileContains($record, ['Dompdf', 'DomPDF', 'barryvdh'])) {
            return new ClassificationResult('pdf', 'dompdf_error');
        }
        if ($this->messageContains($record, ['DomPDF', 'Dompdf', 'PDF generation', 'Pdf::loadHTML', 'Pdf::loadView'])) {
            return new ClassificationResult('pdf', 'dompdf_error');
        }
        if ($this->messageContains($record, ['Snappy', 'wkhtmltopdf'])) {
            return new ClassificationResult('pdf', 'snappy_error');
        }
        if ($this->messageContains($record, ['template render', 'failed to render template'])
            && $this->messageContains($record, 'pdf')) {
            return new ClassificationResult('pdf', 'template_render_failed');
        }
        if ($this->messageContains($record, ['font missing', 'unable to load font'])) {
            return new ClassificationResult('pdf', 'font_missing');
        }
        return null;
    }
}
