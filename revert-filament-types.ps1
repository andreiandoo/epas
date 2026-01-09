$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    if ($content -match 'protected static \\BackedEnum\|string\|null \$navigationIcon') {
        $content = $content -replace 'protected static \\BackedEnum\|string\|null \$navigationIcon', 'protected static ?string $navigationIcon'
        $changed = $true
    }
    if ($content -match 'protected static string\|BackedEnum\|null \$navigationIcon') {
        $content = $content -replace 'protected static string\|BackedEnum\|null \$navigationIcon', 'protected static ?string $navigationIcon'
        $changed = $true
    }
    if ($content -match 'protected static \\BackedEnum\|string\|null \$navigationLabel') {
        $content = $content -replace 'protected static \\BackedEnum\|string\|null \$navigationLabel', 'protected static ?string $navigationLabel'
        $changed = $true
    }
    if ($content -match 'protected static \\UnitEnum\|string\|null \$navigationGroup') {
        $content = $content -replace 'protected static \\UnitEnum\|string\|null \$navigationGroup', 'protected static ?string $navigationGroup'
        $changed = $true
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Reverted: $($file.FullName)"
    }
}
