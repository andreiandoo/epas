$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    # Fix order: string|BackedEnum|null -> BackedEnum|string|null
    if ($content -match 'protected static string\|BackedEnum\|null \$navigationIcon') {
        $content = $content -replace 'protected static string\|BackedEnum\|null \$navigationIcon', 'protected static BackedEnum|string|null $navigationIcon'
        $changed = $true
    }
    if ($content -match 'protected static string\|BackedEnum\|null \$navigationLabel') {
        $content = $content -replace 'protected static string\|BackedEnum\|null \$navigationLabel', 'protected static BackedEnum|string|null $navigationLabel'
        $changed = $true
    }

    # Fix order: string|UnitEnum|null -> UnitEnum|string|null
    if ($content -match 'protected static string\|UnitEnum\|null \$navigationGroup') {
        $content = $content -replace 'protected static string\|UnitEnum\|null \$navigationGroup', 'protected static UnitEnum|string|null $navigationGroup'
        $changed = $true
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.FullName)"
    }
}
