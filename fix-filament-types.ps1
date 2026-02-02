$files = Get-ChildItem -Path "app/Filament/Resources" -Recurse -Filter "*.php"
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $changed = $false

    if ($content -match 'protected static \?string \$navigationIcon') {
        $content = $content -replace 'protected static \?string \$navigationIcon', 'protected static \BackedEnum|string|null $navigationIcon'
        $changed = $true
    }
    if ($content -match 'protected static \?string \$navigationLabel') {
        $content = $content -replace 'protected static \?string \$navigationLabel', 'protected static \BackedEnum|string|null $navigationLabel'
        $changed = $true
    }
    if ($content -match 'protected static \?string \$navigationGroup') {
        $content = $content -replace 'protected static \?string \$navigationGroup', 'protected static \UnitEnum|string|null $navigationGroup'
        $changed = $true
    }

    if ($changed) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($file.FullName)"
    }
}
