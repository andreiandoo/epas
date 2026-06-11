<?php

namespace App\Logging\Classifiers;

class StorageClassifier extends AbstractClassifier
{
    public function classify(array $record): ?ClassificationResult
    {
        if ($this->messageContains($record, ['No space left on device', 'disk full'])) {
            return new ClassificationResult('storage', 'disk_full');
        }
        if ($this->messageContains($record, ['Permission denied', 'permission denied'])
            && $this->messageContains($record, ['file', 'directory', 'storage'])) {
            return new ClassificationResult('storage', 'permission_denied');
        }
        if ($this->messageContains($record, ['CDN upload failed', 'upload to S3 failed', 'BunnyCDN upload'])) {
            return new ClassificationResult('storage', 'cdn_upload_failed');
        }
        if ($this->exceptionIsA($record, [
            'League\Flysystem\UnableToWriteFile',
            'League\Flysystem\UnableToReadFile',
            'League\Flysystem\UnableToCheckFileExistence',
        ])) {
            return new ClassificationResult('storage', 'flysystem_error');
        }
        if ($this->exceptionIsA($record, [
            'Symfony\Component\HttpFoundation\File\Exception\FileException',
            'Symfony\Component\HttpFoundation\File\Exception\UploadException',
        ])) {
            return new ClassificationResult('storage', 'upload_failed');
        }
        return null;
    }
}
