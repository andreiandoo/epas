<?php

$directory = 'app/Filament/Marketplace/Resources';

function fixFilamentActions($filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;

    // Check if file uses Tables\Actions
    if (strpos($content, 'Tables\\Actions\\') !== false || strpos($content, '->actions([') !== false) {

        // Add imports if not present
        $importsToAdd = [];

        if (strpos($content, 'Tables\\Actions\\EditAction') !== false && strpos($content, 'use Filament\\Actions\\EditAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\EditAction;';
        }
        if (strpos($content, 'Tables\\Actions\\ViewAction') !== false && strpos($content, 'use Filament\\Actions\\ViewAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\ViewAction;';
        }
        if (strpos($content, 'Tables\\Actions\\DeleteAction') !== false && strpos($content, 'use Filament\\Actions\\DeleteAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\DeleteAction;';
        }
        if (strpos($content, 'Tables\\Actions\\Action::make') !== false && strpos($content, 'use Filament\\Actions\\Action;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\Action;';
        }
        if (strpos($content, 'Tables\\Actions\\ActionGroup') !== false && strpos($content, 'use Filament\\Actions\\ActionGroup;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\ActionGroup;';
        }
        if (strpos($content, 'Tables\\Actions\\BulkAction::') !== false && strpos($content, 'use Filament\\Actions\\BulkAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\BulkAction;';
        }
        if (strpos($content, 'Tables\\Actions\\BulkActionGroup') !== false && strpos($content, 'use Filament\\Actions\\BulkActionGroup;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\BulkActionGroup;';
        }
        if (strpos($content, 'Tables\\Actions\\DeleteBulkAction') !== false && strpos($content, 'use Filament\\Actions\\DeleteBulkAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\DeleteBulkAction;';
        }
        if (strpos($content, 'Tables\\Actions\\RestoreBulkAction') !== false && strpos($content, 'use Filament\\Actions\\RestoreBulkAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\RestoreBulkAction;';
        }
        if (strpos($content, 'Tables\\Actions\\ForceDeleteBulkAction') !== false && strpos($content, 'use Filament\\Actions\\ForceDeleteBulkAction;') === false) {
            $importsToAdd[] = 'use Filament\\Actions\\ForceDeleteBulkAction;';
        }

        // Add imports after the last existing use statement
        if (!empty($importsToAdd)) {
            $importStr = implode("\n", $importsToAdd);
            // Find all use statements and get the last one
            if (preg_match_all('/^use [^;]+;$/m', $content, $allMatches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($allMatches[0]);
                $insertPos = $lastMatch[1] + strlen($lastMatch[0]);
                $content = substr($content, 0, $insertPos) . "\n" . $importStr . substr($content, $insertPos);
            }
        }

        // Replace Tables\Actions\* with direct class names
        $content = str_replace('Tables\\Actions\\EditAction', 'EditAction', $content);
        $content = str_replace('Tables\\Actions\\ViewAction', 'ViewAction', $content);
        $content = str_replace('Tables\\Actions\\DeleteAction', 'DeleteAction', $content);
        $content = str_replace('Tables\\Actions\\ActionGroup', 'ActionGroup', $content);
        $content = str_replace('Tables\\Actions\\BulkActionGroup', 'BulkActionGroup', $content);
        $content = str_replace('Tables\\Actions\\DeleteBulkAction', 'DeleteBulkAction', $content);
        $content = str_replace('Tables\\Actions\\RestoreBulkAction', 'RestoreBulkAction', $content);
        $content = str_replace('Tables\\Actions\\ForceDeleteBulkAction', 'ForceDeleteBulkAction', $content);
        $content = str_replace('Tables\\Actions\\BulkAction', 'BulkAction', $content);
        $content = str_replace('Tables\\Actions\\Action', 'Action', $content);

        // Replace ->actions([ with ->recordActions([
        $content = str_replace('->actions([', '->recordActions([', $content);

        // Replace ->bulkActions([ with ->toolbarActions([
        $content = str_replace('->bulkActions([', '->toolbarActions([', $content);

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            return true;
        }
    }

    return false;
}

// Find all PHP files in the directory recursively
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory)
);

$fixedFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (fixFilamentActions($path)) {
            $fixedFiles[] = $path;
        }
    }
}

echo "Fixed " . count($fixedFiles) . " files:\n";
foreach ($fixedFiles as $f) {
    echo "  - " . $f . "\n";
}
