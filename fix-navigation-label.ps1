$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    # Fix $navigationLabel back to ?string
    if ($content -match 'protected static BackedEnum\|string\|null \$navigationLabel') {
        $content = $content -replace 'protected static BackedEnum\|string\|null \$navigationLabel', 'protected static ?string $navigationLabel'
        $changed = $true
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed navigationLabel: $($file.FullName)"
    }
}
